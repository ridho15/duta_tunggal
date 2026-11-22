<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Services\AccountingSettings;
use App\Services\DepositNumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerReturnService
{
    /**
     * Process the completion of a customer return.
     *
     * Flow:
     *  - Validates that returned quantity does not exceed returnable quantity of the invoice item.
     *  - For items with decision = 'replace' or 'credit': goods are physically back at DT, so inventory (qty_available) is
     *    restored at the HPP recognised on the sale (invoice snapshot; fallback master cost_price) with a stock-in movement.
     *  - For items with decision = 'repair': customer-owned goods are serviced and handed back — the sale stands, so no
     *    revenue/PPN/AR/HPP reversal, no saleable stock and no stock card entry.
     *  - For items with decision = 'reject': the claim was rejected, goods stay with the
     *    customer (or are scrapped), so no stock is added.
     *  - Journal entries are created:
     *      1. Inventory Journal: Dr Persediaan Produk @ HPP, Cr HPP (5100.10) @ the same HPP.
     *      2. Financial Journal: Dr Retur Penjualan (4120.10) @ DPP, Dr PPN Keluaran (2120.06) @ PPN, Cr Piutang Dagang (1120) @ Total Retur.
     *  - AccountReceivable is reduced by the returned financial total.
     *  - stock_restored_at is set to prevent double-processing.
     *
     * @throws \Exception if the return is already processed or has no warehouse set
     */
    /**
     * @return array{restored_qty: float, repair_qty: float, rejected_qty: float, warehouse: ?string} ringkasan untuk pesan ke user
     */
    public function processCompletion(CustomerReturn $customerReturn): array
    {
        if ($customerReturn->stock_restored_at) {
            throw new \Exception("Customer return #{$customerReturn->return_number} has already been processed.");
        }

        $warehouseId = $customerReturn->warehouse_id;

        // Barang yang kembali (bukan klaim ditolak) wajib punya gudang penerima. Dulu tanpa gudang stok dilewati diam-diam,
        // padahal pesan sukses menyatakan stok sudah dipulihkan (temuan UAT 11 Nov 2026).
        $customerReturn->loadMissing('customerReturnItems');
        $needsWarehouse = $customerReturn->customerReturnItems
            ->contains(fn ($i) => ! in_array($i->decision, [CustomerReturnItem::DECISION_REJECT, CustomerReturnItem::DECISION_REPAIR], true) && (float) $i->quantity > 0);
        if (! $warehouseId && $needsWarehouse) {
            throw new \Exception('Gudang penerima retur belum diisi. Isi "Gudang Penerima" pada form Inspeksi QC, simpan, lalu selesaikan retur lagi.');
        }

        $summary = ['restored_qty' => 0.0, 'repair_qty' => 0.0, 'rejected_qty' => 0.0,
            'warehouse' => $warehouseId ? \App\Models\Warehouse::withoutGlobalScopes()->whereKey($warehouseId)->value('name') : null];

        DB::transaction(function () use ($customerReturn, $warehouseId, &$summary) {
            /** @var CustomerReturn $lockedReturn */
            $lockedReturn = CustomerReturn::query()
                ->whereKey($customerReturn->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedReturn->stock_restored_at !== null || $lockedReturn->status === CustomerReturn::STATUS_COMPLETED) {
                throw new \Exception("Customer return #{$lockedReturn->return_number} has already been processed.");
            }

            $totalRestoredCost = 0.0;
            $restoredLines = [];

            $totalDpp = 0.0;
            $totalPpn = 0.0;

            // Produk dimuat tanpa scope cabang: barang pada invoice tetap harus terbaca HPP & akunnya walau user beda cabang.
            $lockedReturn->loadMissing([
                'customerReturnItems.invoiceItem',
                'customerReturnItems.product' => fn ($q) => $q->withoutGlobalScopes(),
                'invoice',
            ]);

            foreach ($lockedReturn->customerReturnItems as $item) {
                // ── 0. Validate returnable quantity ───────────────────────────────
                if ($item->invoiceItem) {
                    $alreadyReturned = CustomerReturnItem::where('invoice_item_id', $item->invoice_item_id)
                        ->where('id', '!=', $item->id)
                        ->whereHas('customerReturn', fn ($q) => $q->whereIn('status', [CustomerReturn::STATUS_COMPLETED, CustomerReturn::STATUS_APPROVED]))
                        ->sum('quantity');
                    $maxReturnable = max(0, (float) $item->invoiceItem->quantity - (float) $alreadyReturned);
                    if ((float) $item->quantity > $maxReturnable + 0.001) {
                        throw new \Exception("Kuantitas retur produk {$item->product?->name} ({$item->quantity} pcs) melebihi batas maksimal yang dapat diretur ({$maxReturnable} pcs).");
                    }
                }

                if (empty($item->qc_result)) {
                    $productName = $item->product?->name ?? 'Produk';
                    throw new \Exception("Item {$productName} belum memiliki hasil pemeriksaan QC. Harap lengkapi hasil QC sebelum menyelesaikan retur.");
                }

                if ($item->decision === CustomerReturnItem::DECISION_REJECT) {
                    $summary['rejected_qty'] += (float) $item->quantity;
                    continue;
                }

                $qty = (float) $item->quantity;
                if ($qty <= 0) {
                    continue;
                }

                // Perbaikan (repair): barang milik customer diservis lalu dikembalikan — penjualan tetap sah. Tidak membalik
                // pendapatan/PPN/piutang/HPP dan tidak masuk stok jual; kartu barang juga tidak dicatat agar tetap = stok fisik.
                if ($item->decision === CustomerReturnItem::DECISION_REPAIR) {
                    $summary['repair_qty'] += $qty;
                    continue;
                }

                // ── HPP yang diakui saat penjualan (snapshot invoice), sumber yang sama untuk debit persediaan & kredit HPP ──
                $unitCost = $this->recognizedUnitCost($item, $lockedReturn);
                $itemCostTotal = round($qty * $unitCost, 2);

                // ── Selling price & VAT for financial reversal ────────────────────
                // Jika CreditNoteService aktif dan keputusan adalah credit, Nota Kredit yang akan menjurnal sisi uang.
                $shouldReverseFinancial = ! CreditNoteService::enabled() || $item->decision !== CustomerReturnItem::DECISION_CREDIT;

                if ($shouldReverseFinancial) {
                    $invItem = $item->invoiceItem;
                    if ($invItem) {
                        $invQty = max(0.0001, (float) $invItem->quantity);
                        $dppPerUnit = (float) $invItem->subtotal / $invQty;
                        $ppnPerUnit = (float) $invItem->tax_amount / $invQty;
                        $totalDpp += round($qty * $dppPerUnit, 2);
                        $totalPpn += round($qty * $ppnPerUnit, 2);
                    }
                }

                // ── 1. Restore inventory stock (replace / standard return) ─────────
                if ($warehouseId) {
                    $summary['restored_qty'] += $qty;
                    $stock = InventoryStock::firstOrNew([
                        'product_id'   => $item->product_id,
                        'warehouse_id' => $warehouseId,
                    ]);

                    if ($stock->exists) {
                        $stock->increment('qty_available', $qty);
                    } else {
                        $stock->qty_available = $qty;
                        $stock->qty_reserved  = 0;
                        $stock->qty_min       = 0;
                        $stock->save();
                    }

                    $existingMovement = StockMovement::where('from_model_type', CustomerReturn::class)
                        ->where('from_model_id', $customerReturn->id)
                        ->where('product_id', $item->product_id)
                        ->where('meta->customer_return_item_id', $item->id)
                        ->first();

                    if (! $existingMovement) {
                        StockMovement::create([
                            'product_id'      => $item->product_id,
                            'warehouse_id'    => $warehouseId,
                            'quantity'        => $qty,
                            'value'           => $itemCostTotal,
                            'type'            => 'customer_return',
                            'reference_id'    => $customerReturn->id,
                            'date'            => $customerReturn->return_date ?? now()->toDateString(),
                            'notes'           => "Retur dari customer (penggantian): {$customerReturn->return_number}",
                            'from_model_type' => CustomerReturn::class,
                            'from_model_id'   => $customerReturn->id,
                            'meta'            => [
                                'customer_return_item_id' => $item->id,
                                'skip_stock_update' => true,
                            ],
                        ]);
                    }
                } else {
                    Log::warning('CustomerReturnService: warehouse_id null, replace stock movement skipped', [
                        'return_id'  => $customerReturn->id,
                        'product_id' => $item->product_id,
                    ]);
                }

                $restoredLines[] = ['product' => $item->product, 'amount' => $itemCostTotal];
                $totalRestoredCost += $itemCostTotal;
            }

            // ── 2. Create inventory journal entries ───────────────────────────────
            if ($totalRestoredCost > 0) {
                $this->createInventoryJournalEntries($lockedReturn, $restoredLines);
            }

            // ── 3. Create financial journal entries & adjust AR / Customer Deposit ──
            $totalReturnFin = round($totalDpp + $totalPpn, 2);
            if ($totalReturnFin > 0) {
                $this->createFinancialJournalEntriesAndAdjustAr($lockedReturn, $totalDpp, $totalPpn, $totalReturnFin);
            }

            // ── 4. Mark as processed ──────────────────────────────────────────────
            $lockedReturn->update([
                'status'             => CustomerReturn::STATUS_COMPLETED,
                'stock_restored_at'  => now(),
                'completed_at'       => now(),
            ]);

            Log::info('CustomerReturn processed successfully', [
                'return_id'          => $lockedReturn->id,
                'return_number'      => $lockedReturn->return_number,
                'warehouse_id'       => $warehouseId,
                'total_restored_cost'=> $totalRestoredCost,
                'total_financial'    => $totalReturnFin,
            ]);
        });

        return $summary;
    }

    /**
     * Create inventory journal entries for a completed customer return (barang kembali ke stok: replace / credit).
     *   Debit  Persediaan (COA produk, fallback akun persediaan retur) @ HPP yang diakui saat penjualan
     *   Credit HPP (5100.10)                                           @ nilai yang sama → jurnal selalu seimbang
     *
     * @param  array<int, array{product: ?\App\Models\Product, amount: float}>  $lines
     */
    private function createInventoryJournalEntries(CustomerReturn $customerReturn, array $lines): void
    {
        // Prevent duplicate posting
        if (JournalEntry::where('source_type', CustomerReturn::class)
            ->where('source_id', $customerReturn->id)
            ->where('description', 'like', '%COGS reversal%')
            ->exists()) {
            return;
        }

        $date      = ($customerReturn->completed_at ?? now())->toDateString();
        $reference = $customerReturn->return_number;
        $desc      = "Customer Return: {$reference}";

        // COA: Default Inventory account (utamakan 1140.01 Barang Dagangan daripada 1140.10)
        $settings = app(AccountingSettings::class);
        $defaultInventoryCoa = $this->firstExistingCoa([
            ...$settings->codes('return_inventory'),
            config('coa.inventory'),
            ...$settings->codes('inventory'),
        ]);

        // COA: COGS reversal
        $cogsCoa = $this->firstExistingCoa([
            ...$settings->codes('cogs'),
            ...$settings->codes('return_cogs'),
        ]);

        $totalsByCoa = [];
        $total = 0.0;
        foreach ($lines as $line) {
            $invCoa = $line['product']?->resolveInventoryCoaOrDefault() ?? $defaultInventoryCoa;
            if (! $invCoa || ! $cogsCoa) {
                throw new \Exception(sprintf(
                    'Akun %s untuk jurnal persediaan retur customer tidak ditemukan. Tambahkan akun tersebut di Bagan Akun (COA) atau atur di Pengaturan Akuntansi, lalu selesaikan retur lagi.',
                    ! $invCoa ? 'Persediaan (kode '.implode('/', array_filter([...$settings->codes('return_inventory'), config('coa.inventory'), ...$settings->codes('inventory')])).')' : 'HPP (kode '.implode('/', array_filter([...$settings->codes('cogs'), ...$settings->codes('return_cogs')])).')'
                ));
            }
            $totalsByCoa[$invCoa->id] = ['coa' => $invCoa, 'amount' => ($totalsByCoa[$invCoa->id]['amount'] ?? 0.0) + $line['amount']];
            $total += $line['amount'];
        }

        // Debit Inventory per COA – barang kembali ke stok fisik
        foreach ($totalsByCoa as $data) {
            JournalEntry::create([
                'coa_id'       => $data['coa']->id,
                'date'         => $date,
                'reference'    => $reference,
                'description'  => $desc . ' - Restore inventory value (penggantian)',
                'debit'        => round($data['amount'], 2),
                'credit'       => 0,
                'journal_type' => 'customer_return',
                'source_type'  => CustomerReturn::class,
                'source_id'    => $customerReturn->id,
                'cabang_id'    => $customerReturn->cabang_id,
            ]);
        }

        // Credit COGS reversal – beban pokok penjualan dibalik karena barang kembali
        JournalEntry::create([
            'coa_id'       => $cogsCoa->id,
            'date'         => $date,
            'reference'    => $reference,
            'description'  => $desc . ' - COGS reversal',
            'debit'        => 0,
            'credit'       => round($total, 2),
            'journal_type' => 'customer_return',
            'source_type'  => CustomerReturn::class,
            'source_id'    => $customerReturn->id,
            'cabang_id'    => $customerReturn->cabang_id,
        ]);
    }

    /**
     * HPP per unit yang diakui saat penjualan: snapshot invoice (cost_price, atau cogs_amount ÷ qty); bila tidak tersedia,
     * HPP rata-rata master produk. Tanpa HPP sama sekali retur ditolak — persediaan tidak boleh dinilai dengan harga jual.
     */
    private function recognizedUnitCost(CustomerReturnItem $item, CustomerReturn $customerReturn): float
    {
        $invoiceItem = $item->invoiceItem;
        $unitCost = (float) ($invoiceItem?->cost_price ?? 0);
        if ($unitCost <= 0 && $invoiceItem && (float) $invoiceItem->quantity > 0) {
            $unitCost = (float) ($invoiceItem->cogs_amount ?? 0) / (float) $invoiceItem->quantity;
        }
        if ($unitCost <= 0) {
            $unitCost = (float) ($item->product?->cost_price ?? 0);
        }

        if ($unitCost <= 0) {
            $productName = $item->product?->name ?? ('Produk #'.$item->product_id);
            throw new \Exception("HPP produk {$productName} tidak tersedia (HPP invoice dan HPP rata-rata master produk = 0), sehingga nilai persediaan retur {$customerReturn->return_number} tidak dapat dihitung. Isi HPP produk di Master Produk (atau lengkapi HPP pada invoice asal), lalu selesaikan retur lagi.");
        }

        return round($unitCost, 4);
    }

    /**
     * Create financial journal entries and adjust AccountReceivable / Customer Deposit.
     *
     * Rules:
     * - Debit Retur Penjualan (4120.10) @ DPP
     * - Debit PPN Keluaran (2120.06) @ PPN
     * - If invoice is unpaid / has remaining AR:
     *     Credit Piutang Dagang (1120) up to remaining AR
     * - If invoice is paid or return exceeds remaining AR:
     *     Credit Deposit Pelanggan (2160.04) for excess / full amount
     *     Record Deposit & DepositLog for the Customer
     * - Adjust AR total, paid, remaining so total - paid = remaining invariant holds and remaining never < 0.
     */
    private function createFinancialJournalEntriesAndAdjustAr(CustomerReturn $customerReturn, float $dpp, float $ppn, float $totalReturnFin): void
    {
        $hasFinancialJournal = JournalEntry::where('source_type', CustomerReturn::class)
            ->where('source_id', $customerReturn->id)
            ->where('description', 'like', '%Sales Return%')
            ->exists();

        $date      = ($customerReturn->completed_at ?? now())->toDateString();
        $reference = $customerReturn->return_number;
        $desc      = "Customer Return: {$reference}";

        $settings = app(AccountingSettings::class);
        $salesReturnCoa = $this->firstExistingCoa([
            ...$settings->codes('sales_returns'),
            ...$settings->codes('return_sales_returns'),
        ], 'Retur Penjualan');

        $vatCoa = $this->firstExistingCoa([
            ...$settings->codes('sales_output_vat'),
            ...$settings->codes('return_output_vat'),
        ], 'PPN Keluaran');

        $arCoa = $this->firstExistingCoa([
            ...$settings->codes('accounts_receivable'),
        ], 'Piutang');

        $depositCoa = $this->firstExistingCoa([
            ...$settings->codes('customer_deposit'),
            ...$settings->codes('return_deposit'),
        ], 'Deposit Konsumen');


        // Determine AR remaining vs to deposit
        $invoice = $customerReturn->invoice;
        $ar = $invoice ? AccountReceivable::where('invoice_id', $invoice->id)->lockForUpdate()->first() : null;

        $remainingAr = $ar ? max(0.0, (float) $ar->remaining) : 0.0;
        $isInvoicePaid = ($invoice && $invoice->status === Invoice::STATUS_PAID) || ($remainingAr <= 0.05);

        if ($isInvoicePaid) {
            $applyToAr = 0.0;
            $toDeposit = $totalReturnFin;
        } else {
            $applyToAr = min($totalReturnFin, $remainingAr);
            $toDeposit = round($totalReturnFin - $applyToAr, 2);
        }

        // Setiap sisi jurnal wajib punya akun; tanpa itu jurnal timpang atau piutang/deposit tidak sinkron dengan GL.
        $missing = [];
        if ($dpp > 0 && ! $salesReturnCoa) {
            $missing[] = 'Retur Penjualan (kode '.$this->codeList([...$settings->codes('sales_returns'), ...$settings->codes('return_sales_returns')]).')';
        }
        if ($ppn > 0 && ! $vatCoa) {
            $missing[] = 'PPN Keluaran (kode '.$this->codeList([...$settings->codes('sales_output_vat'), ...$settings->codes('return_output_vat')]).')';
        }
        if ($applyToAr > 0 && ! $arCoa) {
            $missing[] = 'Piutang Dagang (kode '.$this->codeList($settings->codes('accounts_receivable')).')';
        }
        if ($toDeposit > 0 && ! $depositCoa) {
            $missing[] = 'Deposit Konsumen (kode '.$this->codeList([...$settings->codes('customer_deposit'), ...$settings->codes('return_deposit')]).')';
        }
        if ($missing !== []) {
            throw new \Exception('Retur '.$customerReturn->return_number.' tidak dapat dijurnal karena akun berikut tidak ditemukan: '.implode(', ', $missing)
                .'. Tambahkan akun tersebut di Bagan Akun (COA) atau atur di Pengaturan Akuntansi, lalu selesaikan retur lagi.');
        }

        if (! $hasFinancialJournal) {
            // Debit Retur Penjualan (DPP)
            if ($dpp > 0) {
                JournalEntry::create([
                    'coa_id'       => $salesReturnCoa->id,
                    'date'         => $date,
                    'reference'    => $reference,
                    'description'  => $desc . ' - Sales Return (DPP)',
                    'debit'        => $dpp,
                    'credit'       => 0,
                    'journal_type' => 'customer_return',
                    'source_type'  => CustomerReturn::class,
                    'source_id'    => $customerReturn->id,
                    'cabang_id'    => $customerReturn->cabang_id,
                ]);
            }

            // Debit PPN Keluaran
            if ($ppn > 0) {
                JournalEntry::create([
                    'coa_id'       => $vatCoa->id,
                    'date'         => $date,
                    'reference'    => $reference,
                    'description'  => $desc . ' - Reversal Output VAT (PPN)',
                    'debit'        => $ppn,
                    'credit'       => 0,
                    'journal_type' => 'customer_return',
                    'source_type'  => CustomerReturn::class,
                    'source_id'    => $customerReturn->id,
                    'cabang_id'    => $customerReturn->cabang_id,
                ]);
            }

            // Credit Piutang Dagang (jika invoice belum lunas dan masih ada sisa piutang)
            if ($applyToAr > 0) {
                JournalEntry::create([
                    'coa_id'       => $arCoa->id,
                    'date'         => $date,
                    'reference'    => $reference,
                    'description'  => $desc . ' - Reduce Accounts Receivable',
                    'debit'        => 0,
                    'credit'       => $applyToAr,
                    'journal_type' => 'customer_return',
                    'source_type'  => CustomerReturn::class,
                    'source_id'    => $customerReturn->id,
                    'cabang_id'    => $customerReturn->cabang_id,
                ]);
            }

            // Credit Deposit Pelanggan (jika invoice sudah lunas atau retur melebihi sisa piutang)
            if ($toDeposit > 0) {
                JournalEntry::create([
                    'coa_id'       => $depositCoa->id,
                    'date'         => $date,
                    'reference'    => $reference,
                    'description'  => $desc . ' - Customer Deposit / Store Credit',
                    'debit'        => 0,
                    'credit'       => $toDeposit,
                    'journal_type' => 'customer_return',
                    'source_type'  => CustomerReturn::class,
                    'source_id'    => $customerReturn->id,
                    'cabang_id'    => $customerReturn->cabang_id,
                ]);
            }
        }

        // Adjust AccountReceivable record
        if ($ar) {
            $rate = (float) ($ar->exchange_rate ?? 1);
            $rate = $rate > 0 ? $rate : 1.0;

            $ar->total = max(0.0, (float) $ar->total - $totalReturnFin);
            if ($applyToAr > 0) {
                $ar->remaining = max(0.0, (float) $ar->remaining - $applyToAr);
            }
            if ($toDeposit > 0) {
                $ar->paid = max(0.0, (float) $ar->paid - $toDeposit);
            }

            $ar->total_original = round((float) $ar->total / $rate, 4);
            $ar->paid_original = round((float) $ar->paid / $rate, 4);
            $ar->remaining_original = round((float) $ar->remaining / $rate, 4);
            $ar->status = $ar->remaining > 0.05 ? 'Belum Lunas' : 'Lunas';
            $ar->save();

            if ($ar->remaining <= 0.05 && $ar->ageingSchedule()->exists()) {
                $ar->ageingSchedule->delete();
            }
        }

        // Jika ada nilai yang masuk ke deposit, catat di tabel Deposit & DepositLog
        if ($toDeposit > 0) {
            $customerId = $customerReturn->customer_id ?? $invoice?->fromModel?->customer_id;
            if ($customerId) {
                $deposit = Deposit::where('from_model_type', Customer::class)
                    ->where('from_model_id', $customerId)
                    ->lockForUpdate()
                    ->first();

                if (! $deposit) {
                    $deposit = Deposit::create([
                        'from_model_type'  => Customer::class,
                        'from_model_id'    => $customerId,
                        'amount'           => $toDeposit,
                        'used_amount'      => 0,
                        'remaining_amount' => $toDeposit,
                        'coa_id'           => $depositCoa?->id,
                        'status'           => 'active',
                        'created_by'       => auth()->id() ?? $customerReturn->created_by,
                        'deposit_number'   => app(DepositNumberGenerator::class)->generate(),
                        'note'             => "Dari Retur Penjualan {$customerReturn->return_number}",
                    ]);
                } else {
                    $deposit->forceFill([
                        'amount'           => (float) $deposit->amount + $toDeposit,
                        'remaining_amount' => (float) $deposit->remaining_amount + $toDeposit,
                        'status'           => 'active',
                    ])->saveQuietly();
                }

                DepositLog::create([
                    'deposit_id'     => $deposit->id,
                    'type'           => 'add',
                    'reference_type' => CustomerReturn::class,
                    'reference_id'   => $customerReturn->id,
                    'amount'         => $toDeposit,
                    'note'           => "Kelebihan retur penjualan {$customerReturn->return_number} (Invoice {$invoice?->invoice_number})",
                    'created_by'     => auth()->id() ?? $customerReturn->created_by,
                ]);
            }
        }

        // Finalize invoice status
        if ($invoice) {
            if ($ar && $ar->total <= 0.05 && $ar->remaining <= 0.05) {
                $invoice->status = Invoice::STATUS_CANCELLED;
            } elseif ($ar && $ar->remaining <= 0.05) {
                $invoice->status = Invoice::STATUS_PAID;
            } elseif ($ar && $ar->paid > 0) {
                $invoice->status = Invoice::STATUS_PARTIALLY_PAID;
            }
            $invoice->saveQuietly();
        }
    }

    /** @param  array<int, ?string>  $codes */
    private function codeList(array $codes): string
    {
        return implode('/', array_values(array_unique(array_filter($codes)))) ?: '-';
    }

    protected function firstExistingCoa(array $codes, ?string $fallbackNameLike = null, ?string $fallbackType = null): ?ChartOfAccount
    {
        foreach ($codes as $code) {
            if (! $code) {
                continue;
            }

            $coa = ChartOfAccount::where('code', $code)->first();
            if ($coa?->id) {
                return $coa;
            }
        }

        if ($fallbackNameLike) {
            $query = ChartOfAccount::where('name', 'LIKE', '%' . $fallbackNameLike . '%');
            if ($fallbackType) {
                $query->where('type', $fallbackType);
            }
            $coa = $query->first();
            if ($coa?->id) {
                return $coa;
            }
        }

        return null;
    }
}
