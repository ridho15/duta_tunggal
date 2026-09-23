<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerReturnService
{
    /**
     * Process the completion of a customer return.
     *
     * Flow:
     *  - Validates that returned quantity does not exceed returnable quantity of the invoice item.
     *  - For items with decision = 'repair' or 'replace': goods are physically back at DT,
     *    so inventory (qty_available) is restored using HPP (cost_price), and a stock-in movement is recorded.
     *  - For items with decision = 'reject': the claim was rejected, goods stay with the
     *    customer (or are scrapped), so no stock is added.
     *  - Journal entries are created:
     *      1. Inventory Journal: Dr Persediaan Produk @ HPP, Cr HPP (5100.10) @ HPP.
     *      2. Financial Journal: Dr Retur Penjualan (4120.10) @ DPP, Dr PPN Keluaran (2120.06) @ PPN, Cr Piutang Dagang (1120) @ Total Retur.
     *  - AccountReceivable is reduced by the returned financial total.
     *  - stock_restored_at is set to prevent double-processing.
     *
     * @throws \Exception if the return is already processed or has no warehouse set
     */
    public function processCompletion(CustomerReturn $customerReturn): void
    {
        if ($customerReturn->stock_restored_at) {
            throw new \Exception("Customer return #{$customerReturn->return_number} has already been processed.");
        }

        $warehouseId = $customerReturn->warehouse_id;

        if (! $warehouseId) {
            Log::warning('CustomerReturnService: warehouse_id is null — stock restoration will be skipped', [
                'return_id'     => $customerReturn->id,
                'return_number' => $customerReturn->return_number,
            ]);
        }

        DB::transaction(function () use ($customerReturn, $warehouseId) {
            $totalRestoredCost = 0.0;
            $repairCost = 0.0;
            $replaceCost = 0.0;

            $totalDpp = 0.0;
            $totalPpn = 0.0;

            $customerReturn->loadMissing([
                'customerReturnItems.invoiceItem.product',
                'customerReturnItems.product',
                'invoice',
            ]);

            foreach ($customerReturn->customerReturnItems as $item) {
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

                if ($item->decision === CustomerReturnItem::DECISION_REJECT) {
                    continue;
                }

                $qty = (float) $item->quantity;
                if ($qty <= 0) {
                    continue;
                }

                // ── Cost price (HPP) for inventory restoration ─────────────────────
                $unitCost = (float) ($item->product?->cost_price ?? $item->invoiceItem?->cost_price ?? 0);
                if ($unitCost <= 0) {
                    $unitCost = (float) ($item->invoiceItem?->net_unit_price ?? 0);
                }
                $itemCostTotal = round($qty * $unitCost, 2);

                // ── Selling price & VAT for financial reversal ────────────────────
                $invItem = $item->invoiceItem;
                if ($invItem) {
                    $invQty = max(0.0001, (float) $invItem->quantity);
                    $dppPerUnit = (float) $invItem->subtotal / $invQty;
                    $ppnPerUnit = (float) $invItem->tax_amount / $invQty;
                    $totalDpp += round($qty * $dppPerUnit, 2);
                    $totalPpn += round($qty * $ppnPerUnit, 2);
                }

                // For 'repair' items, goods come back for fixing but are NOT immediately
                // returned to saleable stock — they go to a WIP/In-Repair holding account.
                if ($item->decision === CustomerReturnItem::DECISION_REPAIR) {
                    $repairCost += $itemCostTotal;
                    $totalRestoredCost += $itemCostTotal;

                    if ($warehouseId) {
                        StockMovement::create([
                            'product_id'      => $item->product_id,
                            'warehouse_id'    => $warehouseId,
                            'quantity'        => $qty,
                            'value'           => $itemCostTotal,
                            'type'            => 'customer_return',
                            'reference_id'    => $customerReturn->id,
                            'date'            => $customerReturn->return_date ?? now()->toDateString(),
                            'notes'           => "Retur dari customer (perbaikan): {$customerReturn->return_number}",
                            'from_model_type' => CustomerReturn::class,
                            'from_model_id'   => $customerReturn->id,
                        ]);
                    } else {
                        Log::warning('CustomerReturnService: warehouse_id null, repair stock movement skipped', [
                            'return_id'  => $customerReturn->id,
                            'product_id' => $item->product_id,
                        ]);
                    }
                    continue;
                }

                // ── 1. Restore inventory stock (replace / standard return) ─────────
                if ($warehouseId) {
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
                    ]);
                } else {
                    Log::warning('CustomerReturnService: warehouse_id null, replace stock movement skipped', [
                        'return_id'  => $customerReturn->id,
                        'product_id' => $item->product_id,
                    ]);
                }

                $replaceCost += $itemCostTotal;
                $totalRestoredCost += $itemCostTotal;
            }

            // ── 2. Create inventory journal entries ───────────────────────────────
            if ($totalRestoredCost > 0) {
                $this->createInventoryJournalEntries($customerReturn, $totalRestoredCost, $replaceCost, $repairCost);
            }

            // ── 3. Create financial journal entries & reduce AR ───────────────────
            $totalReturnFin = round($totalDpp + $totalPpn, 2);
            if ($totalReturnFin > 0) {
                $this->createFinancialJournalEntries($customerReturn, $totalDpp, $totalPpn, $totalReturnFin);
                $this->adjustAccountReceivable($customerReturn, $totalReturnFin);
            }

            // ── 4. Mark as processed ──────────────────────────────────────────────
            $customerReturn->update([
                'status'             => CustomerReturn::STATUS_COMPLETED,
                'stock_restored_at'  => now(),
                'completed_at'       => now(),
            ]);

            Log::info('CustomerReturn processed successfully', [
                'return_id'          => $customerReturn->id,
                'return_number'      => $customerReturn->return_number,
                'warehouse_id'       => $warehouseId,
                'total_restored_cost'=> $totalRestoredCost,
                'total_financial'    => $totalReturnFin,
            ]);
        });
    }

    /**
     * Create inventory journal entries for a completed customer return.
     *
     * For 'replace' items (goods back to stock):
     *   Debit  Inventory            (1140.10 / 1140.01) — goods come back to warehouse @ HPP
     *   Credit COGS reversal        (5100.10)           — cost of goods is no longer "sold" @ HPP
     *
     * For 'repair' items (goods in workshop/WIP, not yet back to saleable stock):
     *   Debit  WIP / In-Repair      (1101.02 or fallback to inventory) — goods held for repair
     *   Credit COGS reversal        (5100.10)           — cost of goods is no longer "sold" @ HPP
     */
    private function createInventoryJournalEntries(CustomerReturn $customerReturn, float $amount, float $replaceAmount, float $repairAmount): void
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

        // COA: Inventory account
        $settings = app(AccountingSettings::class);
        $inventoryCoa = $this->firstExistingCoa([
            ...$settings->codes('return_inventory'),
            config('coa.inventory'),
            '1140.10',
            '1140.01',
        ]);

        // COA: WIP / In-Repair holding account
        $wipCoa = $this->firstExistingCoa($settings->codes('return_wip')) ?? $inventoryCoa;

        // COA: COGS reversal
        $cogsCoa = $this->firstExistingCoa([
            ...$settings->codes('cogs'),
            '5100.10',
            '5100',
        ]);

        if (! $inventoryCoa || ! $cogsCoa) {
            Log::warning('CustomerReturnService: COA account(s) not found — cannot create inventory journal entries', [
                'return_id'    => $customerReturn->id,
                'inventory_ok' => (bool) $inventoryCoa,
                'cogs_ok'      => (bool) $cogsCoa,
            ]);
            throw new \Exception('Akun COA tidak ditemukan untuk jurnal persediaan retur customer. Diperlukan akun persediaan dan COGS yang valid.');
        }

        // Debit Inventory (replace items) – goods physically back in stock
        if ($replaceAmount > 0) {
            JournalEntry::create([
                'coa_id'       => $inventoryCoa->id,
                'date'         => $date,
                'reference'    => $reference,
                'description'  => $desc . ' - Restore inventory value (penggantian)',
                'debit'        => $replaceAmount,
                'credit'       => 0,
                'journal_type' => 'customer_return',
                'source_type'  => CustomerReturn::class,
                'source_id'    => $customerReturn->id,
                'cabang_id'    => $customerReturn->cabang_id,
            ]);
        }

        // Debit WIP (repair items) – goods held for repair, not yet saleable
        if ($repairAmount > 0) {
            JournalEntry::create([
                'coa_id'       => $wipCoa->id,
                'date'         => $date,
                'reference'    => $reference,
                'description'  => $desc . ' - Goods in repair (perbaikan)',
                'debit'        => $repairAmount,
                'credit'       => 0,
                'journal_type' => 'customer_return',
                'source_type'  => CustomerReturn::class,
                'source_id'    => $customerReturn->id,
                'cabang_id'    => $customerReturn->cabang_id,
            ]);
        }

        // Credit COGS reversal – cost of all returned goods is no longer "sold"
        JournalEntry::create([
            'coa_id'       => $cogsCoa->id,
            'date'         => $date,
            'reference'    => $reference,
            'description'  => $desc . ' - COGS reversal',
            'debit'        => 0,
            'credit'       => $amount,
            'journal_type' => 'customer_return',
            'source_type'  => CustomerReturn::class,
            'source_id'    => $customerReturn->id,
            'cabang_id'    => $customerReturn->cabang_id,
        ]);
    }

    /**
     * Create financial journal entries:
     *   Debit  Retur Penjualan (4120.10) @ DPP
     *   Debit  PPN Keluaran    (2120.06) @ PPN (if applicable)
     *   Credit Piutang Dagang  (1120)    @ Total Retur (DPP + PPN)
     */
    private function createFinancialJournalEntries(CustomerReturn $customerReturn, float $dpp, float $ppn, float $total): void
    {
        if (JournalEntry::where('source_type', CustomerReturn::class)
            ->where('source_id', $customerReturn->id)
            ->where('description', 'like', '%Sales Return%')
            ->exists()) {
            return;
        }

        $date      = ($customerReturn->completed_at ?? now())->toDateString();
        $reference = $customerReturn->return_number;
        $desc      = "Customer Return: {$reference}";

        $settings = app(AccountingSettings::class);
        $salesReturnCoa = $this->firstExistingCoa([
            '4120.10',
            '4120',
            '4101',
            '4100.10',
        ]);

        $vatCoa = $this->firstExistingCoa([
            $settings->codes('sales_output_vat')[0] ?? null,
            config('coa.sales_output_vat'),
            '2120.06',
            '2130',
        ]);

        $arCoa = $this->firstExistingCoa([
            $settings->codes('accounts_receivable')[0] ?? null,
            config('coa.accounts_receivable'),
            '1120',
        ]);

        if (! $salesReturnCoa || ! $arCoa) {
            Log::warning('CustomerReturnService: COA account(s) not found for financial journal', [
                'return_id'        => $customerReturn->id,
                'sales_return_ok'  => (bool) $salesReturnCoa,
                'ar_ok'            => (bool) $arCoa,
            ]);
            throw new \Exception('Akun COA retur penjualan atau piutang dagang tidak ditemukan untuk jurnal finansial retur.');
        }

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
        if ($ppn > 0 && $vatCoa) {
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

        // Credit Piutang Dagang (Total)
        JournalEntry::create([
            'coa_id'       => $arCoa->id,
            'date'         => $date,
            'reference'    => $reference,
            'description'  => $desc . ' - Reduce Accounts Receivable',
            'debit'        => 0,
            'credit'       => $total,
            'journal_type' => 'customer_return',
            'source_type'  => CustomerReturn::class,
            'source_id'    => $customerReturn->id,
            'cabang_id'    => $customerReturn->cabang_id,
        ]);
    }

    /**
     * Deduct AccountReceivable balance for the returned invoice.
     */
    private function adjustAccountReceivable(CustomerReturn $customerReturn, float $totalReturnFin): void
    {
        $ar = AccountReceivable::where('invoice_id', $customerReturn->invoice_id)->lockForUpdate()->first();
        if (! $ar) {
            return;
        }

        $rate = (float) ($ar->exchange_rate ?? 1);
        $rate = $rate > 0 ? $rate : 1.0;

        $ar->total = max(0.0, (float) $ar->total - $totalReturnFin);
        $ar->remaining = max(0.0, (float) $ar->remaining - $totalReturnFin);
        $ar->total_original = round((float) $ar->total / $rate, 4);
        $ar->remaining_original = round((float) $ar->remaining / $rate, 4);
        $ar->status = $ar->remaining > 0.05 ? 'Belum Lunas' : 'Lunas';
        $ar->save();

        if ($ar->remaining <= 0.05 && $ar->ageingSchedule()->exists()) {
            $ar->ageingSchedule->delete();
        }

        if ($customerReturn->invoice) {
            $inv = $customerReturn->invoice;
            if ($ar->remaining <= 0.05 && $ar->total <= 0.05) {
                $inv->status = \App\Models\Invoice::STATUS_CANCELLED;
            } elseif ($ar->remaining <= 0.05) {
                $inv->status = \App\Models\Invoice::STATUS_PAID;
            } elseif ($ar->paid > 0) {
                $inv->status = \App\Models\Invoice::STATUS_PARTIALLY_PAID;
            }
            $inv->saveQuietly();
        }
    }

    protected function firstExistingCoa(array $codes): ?ChartOfAccount
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

        return null;
    }
}
