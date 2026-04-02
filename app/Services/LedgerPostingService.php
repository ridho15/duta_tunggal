<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\VendorPayment;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\Deposit;
use App\Models\Supplier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\JournalValidationTrait;

class LedgerPostingService
{
    use JournalValidationTrait;
    /**
     * Post invoice to general ledger. Creates JournalEntry rows linked to the invoice.
     */
    public function postInvoice(Invoice $invoice): array
    {
        // Atomic duplicate-posting guard: lock a sentinel row (or gap lock) so that two
        // concurrent requests cannot both pass the exists() check before either commits.
        $alreadyPosted = DB::transaction(function () use ($invoice) {
            return JournalEntry::where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->lockForUpdate()
                ->exists();
        });
        if ($alreadyPosted) {
            Log::info('Invoice already posted, skipping', ['invoice_id' => $invoice->id]);
            return ['status' => 'skipped', 'message' => 'Invoice already posted to ledger'];
        }

        // Skip sales invoices - they are handled by InvoiceObserver
        if ($invoice->from_model_type === 'App\\Models\\SaleOrder') {
            Log::info('Skipping sales invoice');
            return ['status' => 'skipped', 'message' => 'Sales invoices are posted by InvoiceObserver'];
        }

        // Skip if not a purchase invoice (allow PurchaseOrder or PurchaseReceipt)
        if (! in_array($invoice->from_model_type, [
            'App\\Models\\PurchaseOrder',
            'App\\Models\\PurchaseReceipt'
        ], true)) {
            return ['status' => 'skipped', 'message' => 'Only purchase invoices are handled by this method'];
        }

        $date = $invoice->invoice_date ?? Carbon::now()->toDateString();

        // Determine COAs
        // Check if this is asset purchase
        $isAssetPurchase = false;
        $isImportPurchase = false;
        if ($invoice->from_model_type === PurchaseOrder::class) {
            $purchaseOrder = $invoice->fromModel;
            $isAssetPurchase = $purchaseOrder && $purchaseOrder->is_asset;
            $isImportPurchase = $purchaseOrder && $purchaseOrder->is_import;
        }

        $inventoryCoa = $invoice->inventory_coa_id ? ChartOfAccount::find($invoice->inventory_coa_id) : ChartOfAccount::where('code', config('coa.inventory'))->first();
        $fixedAssetCoa = ChartOfAccount::where('code', config('coa.fixed_asset'))->first() ?? ChartOfAccount::find(11); // HARGA PEROLEHAN ASET TETAP fallback
        $ppnMasukanCoa = $invoice->ppn_masukan_coa_id ? ChartOfAccount::find($invoice->ppn_masukan_coa_id) : ChartOfAccount::where('code', config('coa.ppn_masukan'))->first();
        $utangCoa = $invoice->accounts_payable_coa_id ? ChartOfAccount::find($invoice->accounts_payable_coa_id) : ChartOfAccount::where('code', config('coa.accounts_payable'))->first();
        $unbilledPurchaseCoa = ChartOfAccount::where('code', config('coa.unbilled_purchase'))->first();

        // Use fixed asset COA if it's asset purchase, otherwise inventory
        $debitCoa = $isAssetPurchase ? $fixedAssetCoa : $inventoryCoa;

        // Totals
        $subtotal = (float) $invoice->subtotal;
        $tax = (float) $invoice->tax;
        $total = (float) $invoice->total;

        $entries = [];

        // Resolve branch from source
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($invoice);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($invoice);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($invoice);

        // For purchase invoices, inventory recognition now happens through QC approval
        // So we skip creating inventory debit entries here
        $isPurchaseInvoice = $invoice->from_model_type === PurchaseOrder::class;
        $isGoodsReceiptInvoice = $invoice->from_model_type === PurchaseReceipt::class;

        \Illuminate\Support\Facades\Log::info('DEBUG: Invoice type check', [
            'from_model_type' => $invoice->from_model_type,
            'PurchaseOrder_class' => PurchaseOrder::class,
            'isPurchaseInvoice' => $isPurchaseInvoice,
            'isGoodsReceiptInvoice' => $isGoodsReceiptInvoice
        ]);

        // For purchase invoices and goods receipt invoices: Simplified journal entry format
        if ($isPurchaseInvoice || $isGoodsReceiptInvoice) {
            // Determine whether to debit unbilled purchase or inventory.
            if ($isPurchaseInvoice) {
                // Check if there are any receipts for this PO
                $hasReceipts = \App\Models\PurchaseReceipt::where('purchase_order_id', $invoice->from_model_id)->exists();
            } elseif ($isGoodsReceiptInvoice) {
                // Invoice originates from a receipt, treat as having receipts
                $hasReceipts = true;
            } else {
                $hasReceipts = false;
            }

            if ($hasReceipts) {
                // If there are receipts, debit unbilled purchase (will be credited when QC approved)
                $debitCoa = $unbilledPurchaseCoa;
            } else {
                // If no receipts, debit inventory directly
                $debitCoa = $inventoryCoa;
            }

            // Debit for subtotal
            if ($subtotal > 0 && $debitCoa) {
                $debitEntry = JournalEntry::create([
                    'coa_id' => $debitCoa->id,
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => 'Purchase invoice - ' . ($hasReceipts ? 'unbilled purchase' : 'inventory') . ' for ' . $invoice->invoice_number,
                    'debit' => $subtotal,
                    'credit' => 0,
                    'journal_type' => 'purchase',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                ]);
                $entries[] = $debitEntry;
            } else {
                \Illuminate\Support\Facades\Log::info('DEBUG: Skipping debit entry for unbilled purchase', [
                    'subtotal' => $subtotal,
                    'unbilledPurchaseCoa_exists' => $unbilledPurchaseCoa ? true : false
                ]);
            }

            // Calculate PPN amount — prefer ppn_rate (percentage) as single source of truth.
            // Fall back to legacy `tax` field for older records that stored it as a rate or absolute amount.
            $ppnAmount = 0;
            $actualPpnAmount = 0; // Track actual PPN amount that gets posted
            $subtotalAmount = (float) ($invoice->subtotal ?? 0);
            $ppnRateVal = (float) ($invoice->ppn_rate ?? 0);
            if ($ppnRateVal > 0) {
                // Primary: ppn_rate stores the percentage (e.g. 11 for 11%)
                $ppnAmount = round($subtotalAmount * ($ppnRateVal / 100), 2);
            } elseif ((float) ($invoice->tax ?? 0) > 0) {
                // Legacy fallback: tax field may store absolute IDR amount or legacy percentage rate
                $taxValue = (float) $invoice->tax;
                $expectedByRate = round($subtotalAmount * ($taxValue / 100), 2);
                $otherFeeAmount = (float) ($invoice->other_fee_total ?? 0);
                $looksLikeLegacyRate = $taxValue <= 100
                    && abs(((float) $invoice->total) - ($subtotalAmount + $otherFeeAmount + $expectedByRate)) < 1;
                $ppnAmount = $looksLikeLegacyRate ? $expectedByRate : $taxValue;
            }

            // Import purchase invoices should not post PPN Masukan at invoice stage.
            // PPN impor is posted at payment stage (VendorPayment) when applicable.
            if (! $isImportPurchase && $ppnAmount > 0 && $ppnMasukanCoa) {
                $entries[] = JournalEntry::create([
                    'coa_id' => $ppnMasukanCoa->id,
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => 'PPN Masukan for ' . $invoice->invoice_number,
                    'debit' => $ppnAmount,
                    'credit' => 0,
                    'journal_type' => 'purchase',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                ]);
                $actualPpnAmount = $ppnAmount;
            }

            // Prefer the normalized other_fee JSON as the source of truth.
            // This prevents Rp 0 shipping/additional fee rows from being journalized
            // while still allowing legacy invoices without structured fees to fall
            // back to the stored grand total difference.
            $normalizedOtherFeeTotal = (float) $invoice->getOtherFeeTotalAttribute();
            if ($normalizedOtherFeeTotal > 0) {
                $totalOtherFees = $normalizedOtherFeeTotal;
            } else {
                // Legacy fallback: derive the amount from the stored grand total.
                // Treat sub-rupiah residuals as rounding noise so they do not become
                // false shipping/additional-fee journal lines.
                $legacyOtherFee = max(0.0, $total - $subtotal - $actualPpnAmount);
                $totalOtherFees = $legacyOtherFee >= 1 ? round($legacyOtherFee) : 0.0;
            }

            // Create journal entry for other fees if any
            if ($totalOtherFees > 0) {
                $expenseCoa = $invoice->expense_coa_id ? ChartOfAccount::find($invoice->expense_coa_id) : ChartOfAccount::where('code', config('coa.general_expense'))->first();
                $entries[] = JournalEntry::create([
                    'coa_id' => $expenseCoa ? $expenseCoa->id : 1, // fallback to first COA if not found
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => 'Biaya lainnya (termasuk dari purchase receipt) untuk ' . $invoice->invoice_number,
                    'debit' => $totalOtherFees,
                    'credit' => 0,
                    'journal_type' => 'purchase',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                ]);
            }

            // Credit Accounts Payable for total amount (subtotal + actual PPN + other fees)
            $totalAmount = $subtotal + $actualPpnAmount + $totalOtherFees;
            if ($totalAmount > 0 && $utangCoa) {
                $creditEntry = JournalEntry::create([
                    'coa_id' => $utangCoa->id,
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => 'Accounts payable for ' . $invoice->invoice_number,
                    'debit' => 0,
                    'credit' => $totalAmount,
                    'journal_type' => 'purchase',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                ]);
                $entries[] = $creditEntry;
            } else {
                \Illuminate\Support\Facades\Log::error('Missing accounts payable COA - cannot post invoice to ledger', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'totalAmount' => $totalAmount,
                    'utangCoa_exists' => $utangCoa ? true : false
                ]);

                throw new \Exception('Akun Hutang Dagang (COA 2101.01) tidak ditemukan. Jurnal invoice tidak dapat dibuat. Silakan konfigurasi akun tersebut di Chart of Accounts.');
            }
        }

        // Validate that entries are balanced
        try {
            $this->validateJournalEntries($entries);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Ledger posting validation failed for invoice', [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 'error', 'message' => 'Journal entries are not balanced', 'error' => $e->getMessage()];
        }

        return ['status' => 'posted', 'entries' => $entries];
    }

    /**
     * Post deposit creation to general ledger. Ensures deposit creation always
     * generates corresponding journal entries regardless of UI path.
     */
    public function postDeposit(\App\Models\Deposit $deposit): array
    {
        // Atomic duplicate-posting guard.
        $alreadyPosted = DB::transaction(function () use ($deposit) {
            return \App\Models\JournalEntry::where('source_type', \App\Models\Deposit::class)
                ->where('source_id', $deposit->id)
                ->lockForUpdate()
                ->exists();
        });
        if ($alreadyPosted) {
            return ['status' => 'skipped', 'message' => 'Deposit already posted to ledger'];
        }

        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($deposit);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($deposit);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($deposit);

        $entries = [];
        $date = now()->toDateString();

        // Supplier deposit (uang muka pembelian)
        if ($deposit->from_model_type === 'App\\Models\\Supplier') {
            // Debit: Uang Muka (deposit account)
            if ($deposit->coa_id) {
                $entries[] = \App\Models\JournalEntry::create([
                    'coa_id' => $deposit->coa_id,
                    'date' => $date,
                    'reference' => 'DEP-' . $deposit->id,
                    'description' => 'Deposit to supplier - ' . ($deposit->fromModel->name ?? ''),
                    'debit' => $deposit->amount,
                    'credit' => 0,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $deposit->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }

            // Credit: Kas/Bank (try to find default)
            $bankCoa = \App\Models\ChartOfAccount::where('code', 'LIKE', config('coa.cash_and_bank') . '%')->first();
            if ($bankCoa) {
                $entries[] = \App\Models\JournalEntry::create([
                    'coa_id' => $bankCoa->id,
                    'date' => $date,
                    'reference' => 'DEP-' . $deposit->id,
                    'description' => 'Payment for deposit to supplier - ' . ($deposit->fromModel->name ?? ''),
                    'debit' => 0,
                    'credit' => $deposit->amount,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $deposit->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }
        } elseif ($deposit->from_model_type === 'App\\Models\\Customer') {
            // Customer deposit (receipt from customer)
            // Debit: Kas/Bank (coa_id in deposit)
            if ($deposit->coa_id) {
                $entries[] = \App\Models\JournalEntry::create([
                    'coa_id' => $deposit->coa_id,
                    'date' => $date,
                    'reference' => 'DEP-' . $deposit->id,
                    'description' => 'Deposit from customer - ' . ($deposit->fromModel->name ?? ''),
                    'debit' => $deposit->amount,
                    'credit' => 0,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $deposit->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }

            // Credit: Customer deposit liability
            $liabilityCoa = \App\Models\ChartOfAccount::where('code', config('coa.customer_deposit'))->first();
            if ($liabilityCoa) {
                $entries[] = \App\Models\JournalEntry::create([
                    'coa_id' => $liabilityCoa->id,
                    'date' => $date,
                    'reference' => 'DEP-' . $deposit->id,
                    'description' => 'Deposit from customer - ' . ($deposit->fromModel->name ?? ''),
                    'debit' => 0,
                    'credit' => $deposit->amount,
                    'journal_type' => 'deposit',
                    'source_type' => \App\Models\Deposit::class,
                    'source_id' => $deposit->id,
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                ]);
            }
        }

        // Validate
        $this->validateJournalEntries($entries);

        return ['status' => 'posted', 'entries' => $entries];
    }
    /**
     * Post vendor payment (cash/bank) to ledger. Debits AP and credits bank/cash.
     */
    public function postVendorPayment(VendorPayment $payment): array
    {
        return DB::transaction(function () use ($payment) {
            $alreadyPosted = JournalEntry::where('source_type', VendorPayment::class)
                ->where('source_id', $payment->id)
                ->lockForUpdate()
                ->exists();
            if ($alreadyPosted) {
                return ['status' => 'skipped', 'message' => 'VendorPayment already posted to ledger'];
            }

            $rawPaymentDate = $payment->payment_date; // goes through accessor → Carbon or null
            $date = ($rawPaymentDate instanceof \Carbon\Carbon ? $rawPaymentDate->toDateString() : null)
                ?? Carbon::now()->toDateString();
            $details = $payment->vendorPaymentDetail()->get();

            $total = (float) ($details->sum('amount') ?: $payment->total_payment);

            if ($total <= 0) {
                return ['status' => 'skipped', 'message' => 'VendorPayment has no amount to post'];
            }

            $utangCoa = ChartOfAccount::where('code', config('coa.accounts_payable'))->first();
            $defaultBankCoa = $payment->coa_id ? $payment->coa : ChartOfAccount::where('code', config('coa.cash_and_bank'))->first();
            $ppnMasukanCoa = ChartOfAccount::where('code', config('coa.ppn_masukan'))->first();
            $pph22Coa = ChartOfAccount::where('code', config('coa.pph22'))->first();
            $beaMasukCoa = ChartOfAccount::where('code', config('coa.import_duty'))->first();

            $entries = [];

            // Resolve branch from source
            $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($payment);
            $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($payment);
            $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($payment);

            if ($utangCoa) {
                $entries[] = JournalEntry::create([
                    'coa_id' => $utangCoa->id,
                    'date' => $date,
                    'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                    'description' => 'Payment to supplier for payment id ' . $payment->id,
                    'debit' => $total,
                    'credit' => 0,
                    'journal_type' => 'payment',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => VendorPayment::class,
                    'source_id' => $payment->id,
                ]);
            }

            $depositDetailsAmount = $details->filter(function ($detail) {
                return strtolower($detail->method ?? '') === 'deposit';
            })->sum('amount');

            $paymentMarkedDeposit = strtolower($payment->payment_method ?? '') === 'deposit';
            if ($depositDetailsAmount <= 0 && $paymentMarkedDeposit) {
                $depositDetailsAmount = $total;
            }

            $depositAmount = (float) min($total, $depositDetailsAmount);
            $cashBankAmount = (float) max(0, $total - $depositAmount);

            if ($depositAmount > 0) {
                $depositCoa = $this->resolveDepositCoa($payment);
                if (!$depositCoa) {
                    Log::error('Missing deposit COA for vendor payment', [
                        'payment_id' => $payment->id,
                        'supplier_id' => $payment->supplier_id,
                        'deposit_amount' => $depositAmount,
                    ]);

                    throw new \RuntimeException('Akun deposit / uang muka supplier tidak ditemukan. Jurnal pembayaran tidak dapat dibuat tanpa COA deposit yang valid.');
                }

                $entries[] = JournalEntry::create([
                    'coa_id' => $depositCoa->id,
                    'date' => $date,
                    'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                    'description' => 'Deposit / Uang Muka usage for payment id ' . $payment->id,
                    'debit' => 0,
                    'credit' => $depositAmount,
                    'journal_type' => 'payment',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => VendorPayment::class,
                    'source_id' => $payment->id,
                ]);
            }

            $nonDepositDetails = $details->filter(function ($detail) {
                return strtolower($detail->method ?? '') !== 'deposit';
            });

            if ($nonDepositDetails->isNotEmpty()) {
                $grouped = $nonDepositDetails->groupBy(function ($detail) {
                    return $detail->coa_id ?? 'default';
                });

                foreach ($grouped as $coaKey => $group) {
                    $amount = (float) $group->sum('amount');
                    if ($amount <= 0) {
                        continue;
                    }

                    $coa = $coaKey === 'default'
                        ? $defaultBankCoa
                        : ChartOfAccount::find($group->first()->coa_id);

                    if (!$coa) {
                        $coa = $defaultBankCoa;
                    }

                    if (!$coa) {
                        Log::error('No COA found for payment group', [
                            'coaKey' => $coaKey,
                            'group' => $group->toArray()
                        ]);
                        throw new \Exception('Akun COA untuk metode pembayaran tidak ditemukan (COA ID: ' . $coaKey . '). Jurnal pembayaran tidak dapat dibuat. Silakan periksa konfigurasi akun di Chart of Accounts.');
                    }

                    $entries[] = JournalEntry::create([
                        'coa_id' => $coa->id,
                        'date' => $date,
                        'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                        'description' => 'Bank/Cash for payment id ' . $payment->id . ' via ' . ($group->first()->method ?? 'Cash/Bank'),
                        'debit' => 0,
                        'credit' => $amount,
                        'journal_type' => 'payment',
                        'cabang_id' => $branchId,
                        'department_id' => $departmentId,
                        'project_id' => $projectId,
                        'source_type' => VendorPayment::class,
                        'source_id' => $payment->id,
                    ]);
                }
            } elseif ($cashBankAmount > 0) {
                // If no details or all details are deposit, use payment's coa_id or default bank coa
                $coa = $defaultBankCoa ?: ($payment->coa_id ? ChartOfAccount::find($payment->coa_id) : null);
                if ($coa) {
                    $entries[] = JournalEntry::create([
                        'coa_id' => $coa->id,
                        'date' => $date,
                        'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                        'description' => 'Bank/Cash for payment id ' . $payment->id,
                        'debit' => 0,
                        'credit' => $cashBankAmount,
                        'journal_type' => 'payment',
                        'cabang_id' => $branchId,
                        'department_id' => $departmentId,
                        'project_id' => $projectId,
                        'source_type' => VendorPayment::class,
                        'source_id' => $payment->id,
                    ]);
                } else {
                    Log::error('No COA available for payment credit entry', [
                        'payment_id' => $payment->id,
                        'defaultBankCoa_exists' => $defaultBankCoa ? true : false,
                        'payment_coa_id' => $payment->coa_id
                    ]);
                    throw new \Exception('Akun Bank/Kas tidak dikonfigurasi. Jurnal pembayaran tidak dapat diseimbangkan. Silakan hubungi administrator keuangan.');
                }
            }

            if ($payment->is_import_payment && $defaultBankCoa) {
                $importDefinitions = [
                    [
                        'amount' => (float) $payment->ppn_import_amount,
                        'debit_coa' => $ppnMasukanCoa,
                        'description' => 'PPN Impor'
                    ],
                    [
                        'amount' => (float) $payment->pph22_amount,
                        'debit_coa' => $pph22Coa,
                        'description' => 'PPh 22 Impor'
                    ],
                    [
                        'amount' => (float) $payment->bea_masuk_amount,
                        'debit_coa' => $beaMasukCoa,
                        'description' => 'Bea Masuk'
                    ],
                ];

                foreach ($importDefinitions as $definition) {
                    $amount = $definition['amount'];
                    $debitCoa = $definition['debit_coa'];
                    if ($amount <= 0 || !$debitCoa) {
                        continue;
                    }

                    $entries[] = JournalEntry::create([
                        'coa_id' => $debitCoa->id,
                        'date' => $date,
                        'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                        'description' => $definition['description'] . ' payment id ' . $payment->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'journal_type' => 'payment',
                        'cabang_id' => $branchId,
                        'department_id' => $departmentId,
                        'project_id' => $projectId,
                        'source_type' => VendorPayment::class,
                        'source_id' => $payment->id,
                    ]);

                    $entries[] = JournalEntry::create([
                        'coa_id' => $defaultBankCoa->id,
                        'date' => $date,
                        'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                        'description' => 'Kas/Bank ' . strtolower($definition['description']) . ' payment id ' . $payment->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'journal_type' => 'payment',
                        'cabang_id' => $branchId,
                        'department_id' => $departmentId,
                        'project_id' => $projectId,
                        'source_type' => VendorPayment::class,
                        'source_id' => $payment->id,
                    ]);
                }
            }

            // Validate that entries are balanced
            $this->validateJournalEntries($entries);

            return ['status' => 'posted', 'entries' => $entries];
        });
    }

    protected function resolveDepositCoa(VendorPayment $payment): ?ChartOfAccount
    {
        $deposit = Deposit::where('from_model_type', Supplier::class)
            ->where('from_model_id', $payment->supplier_id)
            ->where('status', 'active')
            ->first();

        if ($deposit && $deposit->coa) {
            return $deposit->coa;
        }

        $preferredCodes = ['1150.01', '1150.02', '1150'];
        foreach ($preferredCodes as $code) {
            $coa = ChartOfAccount::where('code', $code)->first();
            if ($coa) {
                return $coa;
            }
        }

        return ChartOfAccount::where('name', 'LIKE', '%UANG MUKA%')->first();
    }

    public function postCustomerReceipt(\App\Models\CustomerReceipt $receipt): array
    {
        return DB::transaction(function () use ($receipt) {
            $alreadyPosted = JournalEntry::where('source_type', \App\Models\CustomerReceipt::class)
                ->where('source_id', $receipt->id)
                ->lockForUpdate()
                ->exists();
            if ($alreadyPosted) {
                return ['status' => 'skipped', 'message' => 'CustomerReceipt already posted to ledger'];
            }

            $date = $receipt->payment_date ?? Carbon::now()->toDateString();
            $details = $receipt->customerReceiptItem()->get();

            $total = (float) ($details->sum('amount') ?: $receipt->total_payment);

            if ($total <= 0) {
                return ['status' => 'skipped', 'message' => 'CustomerReceipt has no amount to post'];
            }

            // For customer receipt: Debit Cash/Bank, Credit Account Receivable (Piutang Dagang)
            $piutangCoa = ChartOfAccount::where('code', config('coa.accounts_receivable'))->first();
            $defaultBankCoa = $receipt->coa_id ? $receipt->coa : ChartOfAccount::where('code', config('coa.cash_and_bank'))->first();

            $entries = [];

            if ($piutangCoa) {
                $entries[] = JournalEntry::create([
                    'coa_id' => $piutangCoa->id,
                    'date' => $date,
                    'reference' => 'REC-' . ($receipt->id ?? 'N/A'),
                    'description' => 'Customer receipt for receipt id ' . $receipt->id,
                    'debit' => 0,
                    'credit' => $total,
                    'journal_type' => 'receipt',
                    'source_type' => \App\Models\CustomerReceipt::class,
                    'source_id' => $receipt->id,
                ]);
            }

            $depositDetailsAmount = $details->filter(function ($detail) {
                return strtolower($detail->method ?? '') === 'deposit';
            })->sum('amount');

            $paymentMarkedDeposit = strtolower($receipt->payment_method ?? '') === 'deposit';
            if ($depositDetailsAmount <= 0 && $paymentMarkedDeposit) {
                $depositDetailsAmount = $total;
            }

            $depositAmount = (float) min($total, $depositDetailsAmount);
            $cashBankAmount = (float) max(0, $total - $depositAmount);

            if ($depositAmount > 0) {
                $depositCoa = $this->resolveDepositCoaForCustomer($receipt);
                if (!$depositCoa) {
                    Log::error('Missing deposit COA for customer receipt', [
                        'receipt_id' => $receipt->id,
                        'customer_id' => $receipt->customer_id,
                        'deposit_amount' => $depositAmount,
                    ]);

                    throw new \RuntimeException('Akun deposit / uang muka pelanggan tidak ditemukan. Jurnal penerimaan tidak dapat dibuat tanpa COA deposit yang valid.');
                }

                $entries[] = JournalEntry::create([
                    'coa_id' => $depositCoa->id,
                    'date' => $date,
                    'reference' => 'REC-' . ($receipt->id ?? 'N/A'),
                    'description' => 'Deposit / Uang Muka usage for receipt id ' . $receipt->id,
                    'debit' => $depositAmount,
                    'credit' => 0,
                    'journal_type' => 'receipt',
                    'source_type' => \App\Models\CustomerReceipt::class,
                    'source_id' => $receipt->id,
                ]);
            }

            $nonDepositDetails = $details->filter(function ($detail) {
                return strtolower($detail->method ?? '') !== 'deposit';
            });

            if ($nonDepositDetails->isNotEmpty()) {
                $grouped = $nonDepositDetails->groupBy(function ($detail) {
                    return $detail->coa_id ?? 'default';
                });

                foreach ($grouped as $coaKey => $group) {
                    $amount = (float) $group->sum('amount');
                    if ($amount <= 0) {
                        continue;
                    }

                    $coa = $coaKey === 'default'
                        ? $defaultBankCoa
                        : ChartOfAccount::find($group->first()->coa_id);

                    if (!$coa) {
                        $coa = $defaultBankCoa;
                    }

                    if (!$coa) {
                        Log::error('No COA found for receipt group', [
                            'coaKey' => $coaKey,
                            'group' => $group->toArray()
                        ]);
                        throw new \Exception('Akun COA untuk metode penerimaan pembayaran tidak ditemukan (COA ID: ' . $coaKey . '). Jurnal penerimaan tidak dapat dibuat. Silakan periksa konfigurasi akun di Chart of Accounts.');
                    }

                    $entries[] = JournalEntry::create([
                        'coa_id' => $coa->id,
                        'date' => $date,
                        'reference' => 'REC-' . ($receipt->id ?? 'N/A'),
                        'description' => 'Bank/Cash for receipt id ' . $receipt->id . ' via ' . ($group->first()->method ?? 'Cash/Bank'),
                        'debit' => $amount,
                        'credit' => 0,
                        'journal_type' => 'receipt',
                        'source_type' => \App\Models\CustomerReceipt::class,
                        'source_id' => $receipt->id,
                    ]);
                }
            } elseif ($cashBankAmount > 0) {
                // If no details or all details are deposit, use receipt's coa_id or default bank coa
                $coa = $defaultBankCoa ?: ($receipt->coa_id ? ChartOfAccount::find($receipt->coa_id) : null);
                if ($coa) {
                    $entries[] = JournalEntry::create([
                        'coa_id' => $coa->id,
                        'date' => $date,
                        'reference' => 'REC-' . ($receipt->id ?? 'N/A'),
                        'description' => 'Bank/Cash for receipt id ' . $receipt->id,
                        'debit' => $cashBankAmount,
                        'credit' => 0,
                        'journal_type' => 'receipt',
                        'source_type' => \App\Models\CustomerReceipt::class,
                        'source_id' => $receipt->id,
                    ]);
                } else {
                    Log::error('No COA available for receipt debit entry', [
                        'receipt_id' => $receipt->id,
                        'defaultBankCoa_exists' => $defaultBankCoa ? true : false,
                        'receipt_coa_id' => $receipt->coa_id
                    ]);
                    throw new \Exception('Akun Bank/Kas tidak dikonfigurasi untuk penerimaan pembayaran. Jurnal tidak dapat diseimbangkan. Silakan hubungi administrator keuangan.');
                }
            }

            return ['status' => 'success', 'message' => 'CustomerReceipt posted to ledger', 'entries' => $entries];
        });
    }

    private function resolveDepositCoaForCustomer(\App\Models\CustomerReceipt $receipt): ?ChartOfAccount
    {
        // Find deposit for this customer
        $deposit = \App\Models\Deposit::where('from_model_type', \App\Models\Customer::class)
            ->where('from_model_id', $receipt->customer_id)
            ->first();

        if ($deposit && $deposit->coa) {
            return $deposit->coa;
        }

        $preferredCodes = ['1150.01', '1150.02', '1150'];
        foreach ($preferredCodes as $code) {
            $coa = ChartOfAccount::where('code', $code)->first();
            if ($coa) {
                return $coa;
            }
        }

        return ChartOfAccount::where('name', 'LIKE', '%UANG MUKA%')->first();
    }

    /**
     * Reverse a set of journal entries identified by their shared transaction_id.
     *
     * Creates mirror entries (debit ↔ credit swapped) dated on $reversalDate,
     * and marks both the original entries and the new reversal entries with the
     * is_reversal / reversal_of_transaction_id relationship fields.
     *
     * @param  string  $transactionId  The transaction_id shared by the entries to reverse.
     * @param  \Carbon\Carbon|string|null  $reversalDate  Date for the reversal entries (defaults to today).
     * @return \Illuminate\Support\Collection  The newly created reversal JournalEntry models.
     */
    public function reverseJournalEntries(string $transactionId, $reversalDate = null): \Illuminate\Support\Collection
    {
        return DB::transaction(function () use ($transactionId, $reversalDate) {
            $date = $reversalDate
                ? \Carbon\Carbon::parse($reversalDate)->toDateString()
                : now()->toDateString();

            $originals = JournalEntry::where('transaction_id', $transactionId)
                ->where('is_reversal', false)
                ->get();

            if ($originals->isEmpty()) {
                throw new \RuntimeException("Tidak ada jurnal ditemukan untuk transaction_id: {$transactionId}");
            }

            $reversalTransactionId = 'REV-' . $transactionId . '-' . now()->format('YmdHis');
            $reversals = collect();

            foreach ($originals as $entry) {
                $reversal = JournalEntry::create([
                    'coa_id'                      => $entry->coa_id,
                    'date'                        => $date,
                    'reference'                   => 'REVERSAL: ' . ($entry->reference ?? $transactionId),
                    'description'                 => 'Pembalikan Jurnal: ' . ($entry->description ?? ''),
                    'debit'                       => $entry->credit,  // swap
                    'credit'                      => $entry->debit,   // swap
                    'journal_type'                => $entry->journal_type,
                    'cabang_id'                   => $entry->cabang_id,
                    'department_id'               => $entry->department_id,
                    'project_id'                  => $entry->project_id,
                    'source_type'                 => $entry->source_type,
                    'source_id'                   => $entry->source_id,
                    'transaction_id'              => $reversalTransactionId,
                    'is_reversal'                 => true,
                    'reversal_of_transaction_id'  => $transactionId,
                ]);

                $reversals->push($reversal);
            }

            // Mark original entries as reversed
            JournalEntry::where('transaction_id', $transactionId)
                ->where('is_reversal', false)
                ->update(['reversal_of_transaction_id' => $reversalTransactionId]);

            Log::info('LedgerPostingService: journal reversal created', [
                'original_transaction_id' => $transactionId,
                'reversal_transaction_id' => $reversalTransactionId,
                'entries_reversed'        => $reversals->count(),
            ]);

            return $reversals;
        });
    }

    /**
     * Reverse all journal entries for a given invoice source.
     *
     * This is intended for legacy cleanup/backfill when purchase invoice journal
     * entries were created without a transaction_id. It mirrors the entries and
     * links the originals to the generated reversal batch using reversal_of_transaction_id.
     */
    public function reverseInvoiceJournalEntries(Invoice $invoice, $reversalDate = null): \Illuminate\Support\Collection
    {
        return DB::transaction(function () use ($invoice, $reversalDate) {
            $date = $reversalDate
                ? \Carbon\Carbon::parse($reversalDate)->toDateString()
                : now()->toDateString();

            $originals = JournalEntry::where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->where('is_reversal', false)
                ->get();

            if ($originals->isEmpty()) {
                throw new \RuntimeException("Tidak ada jurnal ditemukan untuk invoice ID: {$invoice->id}");
            }

            $reversalTransactionId = 'REV-INVOICE-' . $invoice->id . '-' . now()->format('YmdHis');
            $reversals = collect();

            foreach ($originals as $entry) {
                $reversal = JournalEntry::create([
                    'coa_id'                      => $entry->coa_id,
                    'date'                        => $date,
                    'reference'                   => 'REVERSAL: ' . ($entry->reference ?? $invoice->invoice_number),
                    'description'                 => 'Pembalikan Jurnal Invoice: ' . ($entry->description ?? ''),
                    'debit'                       => $entry->credit,
                    'credit'                      => $entry->debit,
                    'journal_type'                => $entry->journal_type,
                    'cabang_id'                   => $entry->cabang_id,
                    'department_id'               => $entry->department_id,
                    'project_id'                  => $entry->project_id,
                    'source_type'                 => $entry->source_type,
                    'source_id'                   => $entry->source_id,
                    'transaction_id'              => $reversalTransactionId,
                    'is_reversal'                 => true,
                    'reversal_of_transaction_id'  => $invoice->id,
                ]);

                $reversals->push($reversal);
            }

            JournalEntry::where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->where('is_reversal', false)
                ->update(['reversal_of_transaction_id' => $reversalTransactionId]);

            Log::info('LedgerPostingService: invoice journal reversal created', [
                'invoice_id'              => $invoice->id,
                'invoice_number'          => $invoice->invoice_number,
                'reversal_transaction_id' => $reversalTransactionId,
                'entries_reversed'        => $reversals->count(),
            ]);

            return $reversals;
        });
    }
}
