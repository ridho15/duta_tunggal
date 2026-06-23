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
    public function postInvoice(Invoice $invoice, bool $allowRepostAfterReversal = false): array
    {
        // Atomic duplicate-posting guard: lock a sentinel row (or gap lock) so that two
        // concurrent requests cannot both pass the exists() check before either commits.
        $alreadyPosted = DB::transaction(function () use ($invoice, $allowRepostAfterReversal) {
            $query = JournalEntry::withoutGlobalScopes()
                ->where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->lockForUpdate();

            if ($allowRepostAfterReversal) {
                $query->where('is_reversal', false)
                    ->whereNull('reversal_of_transaction_id');
            }

            return $query->exists();
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
        
        $currencyContext = $this->resolveInvoiceCurrencyAndRate($invoice);
        $currencyId = $currencyContext['currency_id'];
        $exchangeRate = $currencyContext['exchange_rate'];

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

        // Totals are stored on the invoice in the invoice's source currency.
        // Convert once here so journal entries and financial reports stay in IDR.
        $subtotalOriginal = (float) $invoice->subtotal;
        $taxOriginal = (float) $invoice->tax;
        $totalOriginal = (float) $invoice->total;

        $isForeignCurrencyInvoice = $currencyId && $exchangeRate > 1.0;
        $subtotal = $isForeignCurrencyInvoice
            ? round($subtotalOriginal * $exchangeRate, 2)
            : $subtotalOriginal;
        $tax = $isForeignCurrencyInvoice
            ? round($taxOriginal * $exchangeRate, 2)
            : $taxOriginal;
        $total = $isForeignCurrencyInvoice
            ? round($totalOriginal * $exchangeRate, 2)
            : $totalOriginal;

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
                $debitEntry = $this->createJournalEntry([
                    'coa_id' => $debitCoa->id,
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => 'Purchase invoice - ' . ($hasReceipts ? 'unbilled purchase' : 'inventory') . ' for ' . $invoice->invoice_number,
                    'debit' => $subtotal,
                    'credit' => 0,
                    'amounts_are_idr' => true,
                    'journal_type' => 'purchase',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                ], $currencyId, $exchangeRate);
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
            $subtotalAmount = $subtotal;
            $invoice->loadMissing('invoiceItem');
            $ppnOriginalFromItems = (float) $invoice->invoiceItem->sum('tax_amount');
            $ppnRateVal = (float) ($invoice->ppn_rate ?? 0);
            if ($ppnOriginalFromItems > 0) {
                // Purchase invoices can store an effective rate derived from rounded
                // line-level tax amounts. Post the stored nominal tax to avoid
                // reintroducing rounding drift in IDR journals.
                $ppnAmount = $isForeignCurrencyInvoice
                    ? round($ppnOriginalFromItems * $exchangeRate, 2)
                    : round($ppnOriginalFromItems, 2);
            } elseif ($ppnRateVal > 0) {
                // Primary: ppn_rate stores the percentage (e.g. 11 for 11%)
                $ppnAmount = round($subtotalAmount * ($ppnRateVal / 100), 2);
            } elseif ((float) ($invoice->tax ?? 0) > 0) {
                // Legacy fallback: tax field may store absolute IDR amount or legacy percentage rate
                $taxValue = (float) $invoice->tax;
                $expectedByRate = round($subtotalAmount * ($taxValue / 100), 2);
                $otherFeeAmount = (float) ($invoice->other_fee_total ?? 0);
                if ($isForeignCurrencyInvoice) {
                    $otherFeeAmount = round($otherFeeAmount * $exchangeRate, 2);
                }
                $looksLikeLegacyRate = $taxValue <= 100
                    && abs($total - ($subtotalAmount + $otherFeeAmount + $expectedByRate)) < 1;
                $ppnAmount = $looksLikeLegacyRate ? $expectedByRate : $taxValue;
            }

            // Import purchase invoices should not post PPN Masukan at invoice stage.
            // PPN impor is posted at payment stage (VendorPayment) when applicable.
            if (! $isImportPurchase && $ppnAmount > 0 && $ppnMasukanCoa) {
                $entries[] = $this->createJournalEntry([
                    'coa_id' => $ppnMasukanCoa->id,
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => 'PPN Masukan for ' . $invoice->invoice_number,
                    'debit' => $ppnAmount,
                    'credit' => 0,
                    'amounts_are_idr' => true,
                    'journal_type' => 'purchase',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                ], $currencyId, $exchangeRate);
                $actualPpnAmount = $ppnAmount;
            }

            // Prefer the normalized other_fee JSON as the source of truth.
            // This prevents Rp 0 shipping/additional fee rows from being journalized
            // while still allowing legacy invoices without structured fees to fall
            // back to the stored grand total difference.
            $normalizedOtherFeeTotal = (float) $invoice->getOtherFeeTotalAttribute();
            if ($isForeignCurrencyInvoice) {
                $normalizedOtherFeeTotal = round($normalizedOtherFeeTotal * $exchangeRate, 2);
            }
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
                $entries[] = $this->createJournalEntry([
                    'coa_id' => $expenseCoa ? $expenseCoa->id : 1, // fallback to first COA if not found
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => 'Biaya lainnya (termasuk dari purchase receipt) untuk ' . $invoice->invoice_number,
                    'debit' => $totalOtherFees,
                    'credit' => 0,
                    'amounts_are_idr' => true,
                    'journal_type' => 'purchase',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                ], $currencyId, $exchangeRate);
            }

            // Credit Accounts Payable for total amount (subtotal + actual PPN + other fees)
            $totalAmount = $subtotal + $actualPpnAmount + $totalOtherFees;
            if ($totalAmount > 0 && $utangCoa) {
                $creditEntry = $this->createJournalEntry([
                    'coa_id' => $utangCoa->id,
                    'date' => $date,
                    'reference' => $invoice->invoice_number,
                    'description' => 'Accounts payable for ' . $invoice->invoice_number,
                    'debit' => 0,
                    'credit' => $totalAmount,
                    'amounts_are_idr' => true,
                    'journal_type' => 'purchase',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => Invoice::class,
                    'source_id' => $invoice->id,
                ], $currencyId, $exchangeRate);
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

        $currencyContext = $this->resolveDepositCurrencyAndRate($deposit);
        $currencyId = $currencyContext['currency_id'];
        $exchangeRate = $currencyContext['exchange_rate'];

        $entries = [];
        $date = now()->toDateString();

        // Supplier deposit (uang muka pembelian)
        if ($deposit->from_model_type === 'App\\Models\\Supplier') {
            // Debit: Uang Muka (deposit account)
            if ($deposit->coa_id) {
                $entries[] = $this->createJournalEntry([
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
                ], $currencyId, $exchangeRate);
            }

            // Credit: Kas/Bank (try to find default)
            $bankCoa = \App\Models\ChartOfAccount::where('code', 'LIKE', config('coa.cash_and_bank') . '%')->first();
            if ($bankCoa) {
                $entries[] = $this->createJournalEntry([
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
                ], $currencyId, $exchangeRate);
            }
        } elseif ($deposit->from_model_type === 'App\\Models\\Customer') {
            // Customer deposit (receipt from customer)
            // Debit: Kas/Bank (coa_id in deposit)
            if ($deposit->coa_id) {
                $entries[] = $this->createJournalEntry([
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
                ], $currencyId, $exchangeRate);
            }

            // Credit: Customer deposit liability
            $liabilityCoa = \App\Models\ChartOfAccount::where('code', config('coa.customer_deposit'))->first();
            if ($liabilityCoa) {
                $entries[] = $this->createJournalEntry([
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
                ], $currencyId, $exchangeRate);
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
            $details = $payment->vendorPaymentDetail()->with('invoice')->get();
            $detailSnapshots = $this->syncVendorPaymentCurrencySnapshots($payment, $details);

            $total = (float) ($detailSnapshots->sum('amount_idr') ?: $payment->total_payment_idr ?: $payment->total_payment);

            if ($total <= 0) {
                return ['status' => 'skipped', 'message' => 'VendorPayment has no amount to post'];
            }

            $currencyContext = $this->resolveVendorPaymentCurrencyAndRate($payment);
            $currencyId = $currencyContext['currency_id'];
            $exchangeRate = $currencyContext['exchange_rate'];

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
                $entries[] = $this->createJournalEntry([
                    'coa_id' => $utangCoa->id,
                    'date' => $date,
                    'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                    'description' => 'Payment to supplier for payment id ' . $payment->id,
                    'debit' => $total,
                    'credit' => 0,
                    'amounts_are_idr' => true,
                    'journal_type' => 'payment',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => VendorPayment::class,
                    'source_id' => $payment->id,
                ], $currencyId, $exchangeRate);
            }

            $depositDetailsAmount = $detailSnapshots->filter(function ($detail) {
                return strtolower($detail->method ?? '') === 'deposit';
            })->sum('amount_idr');

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

                $entries[] = $this->createJournalEntry([
                    'coa_id' => $depositCoa->id,
                    'date' => $date,
                    'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                    'description' => 'Deposit / Uang Muka usage for payment id ' . $payment->id,
                    'debit' => 0,
                    'credit' => $depositAmount,
                    'amounts_are_idr' => true,
                    'journal_type' => 'payment',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => VendorPayment::class,
                    'source_id' => $payment->id,
                ], $currencyId, $exchangeRate);
            }

            $nonDepositDetails = $detailSnapshots->filter(function ($detail) {
                return strtolower($detail->method ?? '') !== 'deposit';
            });

            if ($nonDepositDetails->isNotEmpty()) {
                $grouped = $nonDepositDetails->groupBy(function ($detail) {
                    return $detail->coa_id ?? 'default';
                });

                foreach ($grouped as $coaKey => $group) {
                    $amount = (float) $group->sum('amount_idr');
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

                    $entries[] = $this->createJournalEntry([
                        'coa_id' => $coa->id,
                        'date' => $date,
                        'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                        'description' => 'Bank/Cash for payment id ' . $payment->id . ' via ' . ($group->first()->method ?? 'Cash/Bank'),
                        'debit' => 0,
                        'credit' => $amount,
                        'amounts_are_idr' => true,
                        'journal_type' => 'payment',
                        'cabang_id' => $branchId,
                        'department_id' => $departmentId,
                        'project_id' => $projectId,
                        'source_type' => VendorPayment::class,
                        'source_id' => $payment->id,
                    ], $currencyId, $exchangeRate);
                }
            } elseif ($cashBankAmount > 0) {
                // If no details or all details are deposit, use payment's coa_id or default bank coa
                $coa = $defaultBankCoa ?: ($payment->coa_id ? ChartOfAccount::find($payment->coa_id) : null);
                if ($coa) {
                    $entries[] = $this->createJournalEntry([
                        'coa_id' => $coa->id,
                        'date' => $date,
                        'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                        'description' => 'Bank/Cash for payment id ' . $payment->id,
                        'debit' => 0,
                        'credit' => $cashBankAmount,
                        'amounts_are_idr' => true,
                        'journal_type' => 'payment',
                        'cabang_id' => $branchId,
                        'department_id' => $departmentId,
                        'project_id' => $projectId,
                        'source_type' => VendorPayment::class,
                        'source_id' => $payment->id,
                    ], $currencyId, $exchangeRate);
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

                    $entries[] = $this->createJournalEntry([
                        'coa_id' => $debitCoa->id,
                        'date' => $date,
                        'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                        'description' => $definition['description'] . ' payment id ' . $payment->id,
                        'debit' => $amount,
                        'credit' => 0,
                        'amounts_are_idr' => true,
                        'journal_type' => 'payment',
                        'cabang_id' => $branchId,
                        'department_id' => $departmentId,
                        'project_id' => $projectId,
                        'source_type' => VendorPayment::class,
                        'source_id' => $payment->id,
                    ], $currencyId, $exchangeRate);

                    $entries[] = $this->createJournalEntry([
                        'coa_id' => $defaultBankCoa->id,
                        'date' => $date,
                        'reference' => 'PAY-' . ($payment->id ?? 'N/A'),
                        'description' => 'Kas/Bank ' . strtolower($definition['description']) . ' payment id ' . $payment->id,
                        'debit' => 0,
                        'credit' => $amount,
                        'amounts_are_idr' => true,
                        'journal_type' => 'payment',
                        'cabang_id' => $branchId,
                        'department_id' => $departmentId,
                        'project_id' => $projectId,
                        'source_type' => VendorPayment::class,
                        'source_id' => $payment->id,
                    ], $currencyId, $exchangeRate);
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

    protected function syncVendorPaymentCurrencySnapshots(VendorPayment $payment, \Illuminate\Support\Collection $details): \Illuminate\Support\Collection
    {
        if ($details->isEmpty()) {
            $context = $this->resolveVendorPaymentCurrencyAndRate($payment);
            $rate = (float) ($context['exchange_rate'] ?? 1);
            $rate = $rate > 0 ? $rate : 1.0;
            $amountOriginal = (float) ($payment->total_payment ?? 0);
            $amountIdr = round($amountOriginal * $rate, 2);

            $payment->forceFill([
                'currency_id' => $this->validCurrencyId($context['currency_id'] ?? null),
                'exchange_rate' => $rate,
                'total_payment_idr' => $amountIdr,
            ])->saveQuietly();

            return collect([(object) [
                'method' => $payment->payment_method,
                'coa_id' => $payment->coa_id,
                'amount' => $amountOriginal,
                'amount_idr' => $amountIdr,
                'currency_id' => $this->validCurrencyId($context['currency_id'] ?? null),
                'exchange_rate' => $rate,
            ]]);
        }

        $snapshots = $details->map(function ($detail) {
            $context = $detail->invoice?->exists
                ? $this->resolveInvoiceCurrencyAndRate($detail->invoice)
                : [
                    'currency_id' => is_numeric($detail->currency_id ?? null) ? (int) $detail->currency_id : null,
                    'exchange_rate' => (float) ($detail->exchange_rate ?? 1),
                ];

            $rate = (float) ($context['exchange_rate'] ?? 1);
            $rate = $rate > 0 ? $rate : 1.0;
            $amountOriginal = (float) ($detail->amount ?? 0);
            $amountIdr = round($amountOriginal * $rate, 2);

            $detail->forceFill([
                'currency_id' => $this->validCurrencyId($context['currency_id'] ?? null),
                'exchange_rate' => $rate,
                'amount_idr' => $amountIdr,
            ])->saveQuietly();

            return (object) [
                'method' => $detail->method,
                'coa_id' => $detail->coa_id,
                'amount' => $amountOriginal,
                'amount_idr' => $amountIdr,
                'currency_id' => $this->validCurrencyId($context['currency_id'] ?? null),
                'exchange_rate' => $rate,
            ];
        });

        $uniqueCurrencies = $snapshots
            ->unique(fn ($snapshot) => ($snapshot->currency_id ?? 'null') . ':' . number_format((float) $snapshot->exchange_rate, 8, '.', ''));

        if ($uniqueCurrencies->count() > 1) {
            throw new \RuntimeException('Vendor payment hanya boleh membayar invoice dengan satu mata uang dan satu rate. Pisahkan pembayaran multi-currency.');
        }

        $first = $snapshots->first();
        $payment->forceFill([
            'currency_id' => $first?->currency_id,
            'exchange_rate' => (float) ($first?->exchange_rate ?? 1),
            'total_payment_idr' => round((float) $snapshots->sum('amount_idr'), 2),
        ])->saveQuietly();

        return $snapshots;
    }

    protected function validCurrencyId(mixed $currencyId): ?int
    {
        if (! is_numeric($currencyId)) {
            return null;
        }

        $currencyId = (int) $currencyId;

        return \App\Models\Currency::whereKey($currencyId)->exists() ? $currencyId : null;
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
            $details = $receipt->customerReceiptItem()->with('invoice')->get();
            $detailSnapshots = $this->syncCustomerReceiptCurrencySnapshots($receipt, $details);

            $total = (float) ($detailSnapshots->sum('amount_idr') ?: $receipt->total_payment_idr ?: $receipt->total_payment);

            if ($total <= 0) {
                return ['status' => 'skipped', 'message' => 'CustomerReceipt has no amount to post'];
            }

            $currencyContext = $this->resolveCustomerReceiptCurrencyAndRate($receipt);
            $currencyId = $currencyContext['currency_id'];
            $exchangeRate = $currencyContext['exchange_rate'];

            // For customer receipt: Debit Cash/Bank, Credit Account Receivable (Piutang Dagang)
            $piutangCoa = ChartOfAccount::where('code', config('coa.accounts_receivable'))->first();
            $defaultBankCoa = $receipt->coa_id ? $receipt->coa : ChartOfAccount::where('code', config('coa.cash_and_bank'))->first();

            $entries = [];

            if ($piutangCoa) {
                $entries[] = $this->createJournalEntry([
                    'coa_id' => $piutangCoa->id,
                    'date' => $date,
                    'reference' => 'REC-' . ($receipt->id ?? 'N/A'),
                    'description' => 'Customer receipt for receipt id ' . $receipt->id,
                    'debit' => 0,
                    'credit' => $total,
                    'amounts_are_idr' => true,
                    'journal_type' => 'receipt',
                    'source_type' => \App\Models\CustomerReceipt::class,
                    'source_id' => $receipt->id,
                ], $currencyId, $exchangeRate);
            }

            $depositDetailsAmount = $detailSnapshots->filter(function ($detail) {
                return strtolower($detail->method ?? '') === 'deposit';
            })->sum('amount_idr');

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

                $entries[] = $this->createJournalEntry([
                    'coa_id' => $depositCoa->id,
                    'date' => $date,
                    'reference' => 'REC-' . ($receipt->id ?? 'N/A'),
                    'description' => 'Deposit / Uang Muka usage for receipt id ' . $receipt->id,
                    'debit' => $depositAmount,
                    'credit' => 0,
                    'amounts_are_idr' => true,
                    'journal_type' => 'receipt',
                    'source_type' => \App\Models\CustomerReceipt::class,
                    'source_id' => $receipt->id,
                ], $currencyId, $exchangeRate);
            }

            $nonDepositDetails = $detailSnapshots->filter(function ($detail) {
                return strtolower($detail->method ?? '') !== 'deposit';
            });

            if ($nonDepositDetails->isNotEmpty()) {
                $grouped = $nonDepositDetails->groupBy(function ($detail) {
                    return $detail->coa_id ?? 'default';
                });

                foreach ($grouped as $coaKey => $group) {
                    $amount = (float) $group->sum('amount_idr');
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

                    $entries[] = $this->createJournalEntry([
                        'coa_id' => $coa->id,
                        'date' => $date,
                        'reference' => 'REC-' . ($receipt->id ?? 'N/A'),
                        'description' => 'Bank/Cash for receipt id ' . $receipt->id . ' via ' . ($group->first()->method ?? 'Cash/Bank'),
                        'debit' => $amount,
                        'credit' => 0,
                        'amounts_are_idr' => true,
                        'journal_type' => 'receipt',
                        'source_type' => \App\Models\CustomerReceipt::class,
                        'source_id' => $receipt->id,
                    ], $currencyId, $exchangeRate);
                }
            } elseif ($cashBankAmount > 0) {
                // If no details or all details are deposit, use receipt's coa_id or default bank coa
                $coa = $defaultBankCoa ?: ($receipt->coa_id ? ChartOfAccount::find($receipt->coa_id) : null);
                if ($coa) {
                    $entries[] = $this->createJournalEntry([
                        'coa_id' => $coa->id,
                        'date' => $date,
                        'reference' => 'REC-' . ($receipt->id ?? 'N/A'),
                        'description' => 'Bank/Cash for receipt id ' . $receipt->id,
                        'debit' => $cashBankAmount,
                        'credit' => 0,
                        'amounts_are_idr' => true,
                        'journal_type' => 'receipt',
                        'source_type' => \App\Models\CustomerReceipt::class,
                        'source_id' => $receipt->id,
                    ], $currencyId, $exchangeRate);
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

    protected function syncCustomerReceiptCurrencySnapshots(\App\Models\CustomerReceipt $receipt, \Illuminate\Support\Collection $details): \Illuminate\Support\Collection
    {
        if ($details->isEmpty()) {
            $context = $this->resolveCustomerReceiptCurrencyAndRate($receipt);
            $rate = (float) ($context['exchange_rate'] ?? 1);
            $rate = $rate > 0 ? $rate : 1.0;
            $amountIdr = (float) ($receipt->total_payment_idr ?: ($receipt->total_payment ?? 0));

            $receipt->forceFill([
                'currency_id' => $this->validCurrencyId($context['currency_id'] ?? null),
                'exchange_rate' => $rate,
                'total_payment_idr' => round($amountIdr, 2),
            ])->saveQuietly();

            return collect([(object) [
                'method' => $receipt->payment_method,
                'coa_id' => $receipt->coa_id,
                'amount' => $amountIdr,
                'amount_idr' => round($amountIdr, 2),
                'currency_id' => $this->validCurrencyId($context['currency_id'] ?? null),
                'exchange_rate' => $rate,
            ]]);
        }

        $snapshots = $details->map(function ($detail) {
            $context = $detail->invoice?->exists
                ? $this->resolveInvoiceCurrencyAndRate($detail->invoice)
                : [
                    'currency_id' => is_numeric($detail->currency_id ?? null) ? (int) $detail->currency_id : null,
                    'exchange_rate' => (float) ($detail->exchange_rate ?? 1),
                ];

            $rate = (float) ($context['exchange_rate'] ?? 1);
            $rate = $rate > 0 ? $rate : 1.0;
            $amountIdr = (float) ($detail->amount_idr ?: ($detail->amount ?? 0));

            $detail->forceFill([
                'currency_id' => $this->validCurrencyId($context['currency_id'] ?? null),
                'exchange_rate' => $rate,
                'amount_idr' => round($amountIdr, 2),
            ])->saveQuietly();

            return (object) [
                'method' => $detail->method,
                'coa_id' => $detail->coa_id,
                'amount' => (float) ($detail->amount ?? 0),
                'amount_idr' => round($amountIdr, 2),
                'currency_id' => $this->validCurrencyId($context['currency_id'] ?? null),
                'exchange_rate' => $rate,
            ];
        });

        $uniqueCurrencies = $snapshots
            ->unique(fn ($snapshot) => ($snapshot->currency_id ?? 'null') . ':' . number_format((float) $snapshot->exchange_rate, 8, '.', ''));

        if ($uniqueCurrencies->count() > 1) {
            throw new \RuntimeException('Customer receipt hanya boleh membayar invoice dengan satu mata uang dan satu rate. Pisahkan penerimaan multi-currency.');
        }

        $first = $snapshots->first();
        $receipt->forceFill([
            'currency_id' => $first?->currency_id,
            'exchange_rate' => (float) ($first?->exchange_rate ?? 1),
            'total_payment_idr' => round((float) $snapshots->sum('amount_idr'), 2),
        ])->saveQuietly();

        return $snapshots;
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
                throw new \RuntimeException("Tidak ada jurnal ditemukan untuk transaction_id: {$transactionId}.");
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

            $originals = JournalEntry::withoutGlobalScopes()
                ->where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->where('is_reversal', false)
                ->whereNull('reversal_of_transaction_id')
                ->get();

            if ($originals->isEmpty()) {
                throw new \RuntimeException("Tidak ada jurnal ditemukan untuk invoice ID: {$invoice->id}.");
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

            JournalEntry::withoutGlobalScopes()
                ->where('source_type', Invoice::class)
                ->where('source_id', $invoice->id)
                ->where('is_reversal', false)
                ->whereNull('reversal_of_transaction_id')
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

    /**
     * Helper to create a JournalEntry, automatically handling foreign currency conversion to IDR.
     */
    private function createJournalEntry(array $data, ?int $currencyId, float $exchangeRate): JournalEntry
    {
        $debitOrig = (float) ($data['debit'] ?? 0);
        $creditOrig = (float) ($data['credit'] ?? 0);
        $amountsAreIdr = (bool) ($data['amounts_are_idr'] ?? false);

        unset($data['amounts_are_idr']);

        // Convert amounts to IDR for the ledger
        if (! $amountsAreIdr && $currencyId && $exchangeRate > 1.0) {
            $debitIdr = round($debitOrig * $exchangeRate, 2);
            $creditIdr = round($creditOrig * $exchangeRate, 2);
            $originalAmount = max($debitOrig, $creditOrig);
        } else {
            $debitIdr = $debitOrig;
            $creditIdr = $creditOrig;
            $originalAmount = $amountsAreIdr && $currencyId && $exchangeRate > 1.0
                ? round(max($debitOrig, $creditOrig) / $exchangeRate, 4)
                : max($debitOrig, $creditOrig);

            $currencyId = $currencyId ?: \App\Support\CurrencyConversionResolver::resolveCurrencyIdByCode('IDR');
            $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;
        }

        $entryData = array_merge($data, [
            'debit' => $debitIdr,
            'credit' => $creditIdr,
            'currency_id' => $currencyId,
            'exchange_rate' => $exchangeRate,
            'amount_original_currency' => $originalAmount,
        ]);

        return JournalEntry::create($entryData);
    }

    /**
     * Resolve the currency ID and exchange rate for a given Invoice.
     */
    private function resolveInvoiceCurrencyAndRate(Invoice $invoice): array
    {
        $currencyId = null;
        $exchangeRate = 1.0;

        if (is_numeric($invoice->currency_id ?? null)) {
            $currencyId = (int) $invoice->currency_id;
            $exchangeRate = (float) ($invoice->exchange_rate ?? 1.0);
        } elseif ($invoice->from_model_type === 'App\\Models\\PurchaseOrder' || $invoice->from_model_type === 'App\Models\PurchaseOrder') {
            $po = $invoice->fromModel;
            if ($po) {
                $context = app(PurchaseInvoiceAccountingService::class)->currencyContextFromPurchaseOrderIds([$po->id]);
                if ($context) {
                    $currencyId = $context['currency_id'];
                    $exchangeRate = (float) ($context['exchange_rate'] ?? 1.0);
                }
            }
        } elseif ($invoice->from_model_type === 'App\\Models\\PurchaseReceipt' || $invoice->from_model_type === 'App\Models\PurchaseReceipt') {
            $receipt = $invoice->fromModel;
            if ($receipt) {
                $currencyId = $receipt->currency_id;
                if ($receipt->purchaseOrder) {
                    $poCurrency = $receipt->purchaseOrder->purchaseOrderCurrency()->firstWhere('currency_id', $currencyId);
                    if ($poCurrency) {
                        $exchangeRate = (float) ($poCurrency->nominal ?? 1.0);
                    }
                }
            }
        } elseif ($invoice->from_model_type === 'App\\Models\\SaleOrder' || $invoice->from_model_type === 'App\Models\SaleOrder') {
            $so = $invoice->fromModel;
            if ($so) {
                $currencyId = $so->currency_id;
                $exchangeRate = (float) ($so->exchange_rate ?? 1.0);
            }
        }

        // Fallback to default rate if not resolved
        if ($currencyId && $exchangeRate <= 1.0) {
            $exchangeRate = \App\Support\CurrencyConversionResolver::resolveRate($currencyId);
        }

        return [
            'currency_id' => $currencyId,
            'exchange_rate' => $exchangeRate > 0 ? $exchangeRate : 1.0,
        ];
    }

    /**
     * Resolve the currency ID and exchange rate for a given VendorPayment.
     */
    private function resolveVendorPaymentCurrencyAndRate(VendorPayment $payment): array
    {
        $currencyId = null;
        $exchangeRate = 1.0;

        $invoiceIds = collect($payment->selected_invoices ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['invoice_id'] ?? null) : $item)
            ->filter()->values();

        if ($invoiceIds->isEmpty()) {
            $invoiceIds = $payment->vendorPaymentDetail()
                ->whereNotNull('invoice_id')
                ->pluck('invoice_id')
                ->filter()
                ->values();
        }

        if ($invoiceIds->isNotEmpty()) {
            $invoice = \App\Models\Invoice::find($invoiceIds->first());
            if ($invoice) {
                return $this->resolveInvoiceCurrencyAndRate($invoice);
            }
        }

        if (is_numeric($payment->currency_id ?? null)) {
            return [
                'currency_id' => (int) $payment->currency_id,
                'exchange_rate' => (float) ($payment->exchange_rate ?? 1),
            ];
        }

        return [
            'currency_id' => $currencyId,
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * Resolve the currency ID and exchange rate for a given CustomerReceipt.
     */
    private function resolveCustomerReceiptCurrencyAndRate(\App\Models\CustomerReceipt $receipt): array
    {
        $currencyId = null;
        $exchangeRate = 1.0;

        $invoiceIds = collect($receipt->selected_invoices ?? [])
            ->map(fn ($item) => is_array($item) ? ($item['invoice_id'] ?? null) : $item)
            ->filter()->values();

        if ($invoiceIds->isEmpty()) {
            $invoiceIds = $receipt->customerReceiptItem()
                ->whereNotNull('invoice_id')
                ->pluck('invoice_id')
                ->filter()
                ->values();
        }

        if ($invoiceIds->isNotEmpty()) {
            $invoice = \App\Models\Invoice::find($invoiceIds->first());
            if ($invoice) {
                return $this->resolveInvoiceCurrencyAndRate($invoice);
            }
        }

        if ($receipt->invoice_id) {
            $invoice = \App\Models\Invoice::find($receipt->invoice_id);
            if ($invoice) {
                return $this->resolveInvoiceCurrencyAndRate($invoice);
            }
        }

        if (is_numeric($receipt->currency_id ?? null)) {
            return [
                'currency_id' => (int) $receipt->currency_id,
                'exchange_rate' => (float) ($receipt->exchange_rate ?? 1),
            ];
        }

        return [
            'currency_id' => $currencyId,
            'exchange_rate' => $exchangeRate,
        ];
    }

    /**
     * Resolve the currency ID and exchange rate for a given Deposit.
     */
    private function resolveDepositCurrencyAndRate(\App\Models\Deposit $deposit): array
    {
        $currencyId = null;
        $exchangeRate = 1.0;

        if (isset($deposit->currency_id) && $deposit->currency_id) {
            $currencyId = $deposit->currency_id;
            $exchangeRate = \App\Support\CurrencyConversionResolver::resolveRate($currencyId);
        }

        return [
            'currency_id' => $currencyId,
            'exchange_rate' => $exchangeRate,
        ];
    }
}
