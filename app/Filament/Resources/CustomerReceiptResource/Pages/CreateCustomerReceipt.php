<?php

namespace App\Filament\Resources\CustomerReceiptResource\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\CustomerReceiptResource;
use App\Helpers\MoneyHelper;
use App\Models\Invoice;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\CustomerReceiptItem;
use App\Models\Deposit;
use App\Services\CustomerReceiptAllocator;
use App\Services\DepositNumberGenerator;
use App\Services\LedgerPostingService;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Throwable;

class CreateCustomerReceipt extends CreateRecord
{
    protected static string $resource = CustomerReceiptResource::class;

    #[On('updateInvoiceData')]
    public function updateInvoiceData($data)
    {
        if (isset($data['selected_invoices'])) {
            $this->form->fill([
                'selected_invoices' => $data['selected_invoices'],
            ]);
        }
        
        if (isset($data['invoice_receipts'])) {
            $this->form->fill([
                'invoice_receipts' => $data['invoice_receipts'],
            ]);
        }
    }

    #[On('updateHiddenField')]
    public function updateHiddenField($field, $value)
    {
        // Handle the update based on field name
        if ($field === 'selected_invoices' || $field === 'invoice_receipts') {
            // Parse JSON string if needed
            $parsedValue = is_string($value) ? json_decode($value, true) : $value;

            $this->form->fill([
                $field => $parsedValue,
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['total_payment'] = MoneyHelper::safeParse($data['total_payment'] ?? 0);
        $data['payment_method'] = $data['payment_method'] ?? 'Cash';

        // Opsi kelebihan bayar -> Deposit Customer (bukan kolom tabel; hanya memengaruhi validasi).
        $allowDepositOverpayment = (bool) ($data['overpayment_as_deposit'] ?? ($this->data['overpayment_as_deposit'] ?? false));
        unset($data['overpayment_as_deposit']);

        // Extract data from Livewire component data if not in form data
        if (empty($data['selected_invoices']) || empty($data['invoice_receipts'])) {
            // Try to get data from current component state
            if (!empty($this->data['selected_invoices'])) {
                $data['selected_invoices'] = $this->data['selected_invoices'];
            }
            
            if (!empty($this->data['invoice_receipts'])) {
                $data['invoice_receipts'] = $this->data['invoice_receipts'];
            }
            
            // Alternative: extract from request data directly
            $requestData = request()->all();
            if (isset($requestData['components'][0]['snapshot'])) {
                $snapshot = json_decode($requestData['components'][0]['snapshot'], true);
                if (isset($snapshot['data']['data'][0])) {
                    $componentData = $snapshot['data']['data'][0];
                    
                    if (empty($data['selected_invoices']) && !empty($componentData['selected_invoices'])) {
                        $data['selected_invoices'] = $componentData['selected_invoices'];
                    }
                    
                    if (empty($data['invoice_receipts']) && !empty($componentData['invoice_receipts'])) {
                        $data['invoice_receipts'] = $componentData['invoice_receipts'];
                    }
                }
            }
        }
        
        // Handle JSON strings from form (hidden fields send JSON strings)
        if (isset($data['selected_invoices']) && is_string($data['selected_invoices'])) {
            $data['selected_invoices'] = json_decode($data['selected_invoices'], true) ?? [];
        }
        
        if (isset($data['invoice_receipts']) && is_string($data['invoice_receipts'])) {
            $data['invoice_receipts'] = json_decode($data['invoice_receipts'], true) ?? [];
        }
        
        // Tidak ada lagi "auto-pilih invoice pertama" saat invoice tidak dipilih: uang tidak boleh
        // dialokasikan diam-diam ke invoice yang tidak dipilih user. Invoice wajib dipilih (lihat allocator).

        // Handle backward compatibility for single invoice
        if (!empty($data['selected_invoices']) && empty($data['invoice_id'])) {
            // Parse selected_invoices if it's still a string
            $selectedInvoices = $data['selected_invoices'];
            if (is_string($selectedInvoices)) {
                $selectedInvoices = json_decode($selectedInvoices, true) ?? [];
            }
            
            // Set invoice_id to first selected invoice for compatibility
            if (!empty($selectedInvoices)) {
                $data['invoice_id'] = $selectedInvoices[0];
            }
        }

        // Validasi & alokasi (cabang, kelebihan bayar, akun penerima) — satu aturan, tanpa pemotongan senyap
        $this->validateAndFixDataConsistency($data, $allowDepositOverpayment);
        $currencyInvoiceIds = ! empty($data['invoice_receipts'])
            ? array_keys($data['invoice_receipts'])
            : ($data['selected_invoices'] ?? []);
        $currencyContext = $this->resolveReceiptCurrencyContext($currencyInvoiceIds);
        if ($currencyContext !== null) {
            $data['currency_id'] = $currencyContext['currency_id'];
            $data['exchange_rate'] = $currencyContext['exchange_rate'];
        }
        $data['total_payment_idr'] = (float) ($data['total_payment'] ?? 0);

        return $data;
    }

    protected function validateAndFixDataConsistency(array &$data, bool $allowDepositOverpayment = false): void
    {
        // Parse JSON strings if needed
        if (isset($data['selected_invoices']) && is_string($data['selected_invoices'])) {
            $data['selected_invoices'] = json_decode($data['selected_invoices'], true) ?? [];
        }

        if (isset($data['invoice_receipts']) && is_string($data['invoice_receipts'])) {
            $data['invoice_receipts'] = json_decode($data['invoice_receipts'], true) ?? [];
        }

        // Ensure selected_invoices is array
        if (!isset($data['selected_invoices']) || !is_array($data['selected_invoices'])) {
            $data['selected_invoices'] = [];
        }

        // Ensure invoice_receipts is array
        if (!isset($data['invoice_receipts']) || !is_array($data['invoice_receipts'])) {
            $data['invoice_receipts'] = [];
        }

        $allocator = app(CustomerReceiptAllocator::class);

        // JS tidak mengirim nominal per invoice: turunkan dari total_payment. Satu invoice = seluruh total.
        // Beberapa invoice: berurutan (terlama dahulu) sampai sisa tagihan; sisanya dilaporkan sebagai
        // KELEBIHAN oleh allocator (ditolak / dicatat sebagai deposit) — tidak hilang.
        if (empty($data['invoice_receipts']) && !empty($data['selected_invoices']) && $data['total_payment'] > 0) {
            $data['invoice_receipts'] = $this->distributeTotalAcrossInvoices($data['selected_invoices'], (float) $data['total_payment'], $allocator);
        }

        try {
            $allocator->assertAccountAllowed(isset($data['coa_id']) ? (int) $data['coa_id'] : null, $data['payment_method'] ?? 'Cash');
            $accountError = null;
        } catch (ValidationException $e) {
            $accountError = $e;
        }

        try {
            $plan = $allocator->plan(
                (int) ($data['customer_id'] ?? 0),
                $data['invoice_receipts'],
                $data['payment_method'] ?? 'Cash',
                $allowDepositOverpayment,
            );
            $planError = null;
        } catch (ValidationException $e) {
            $plan = null;
            $planError = $e;
        }

        if ($accountError || $planError) {
            $messages = array_merge($accountError?->errors() ?? [], $planError?->errors() ?? []);
            $this->failWithMessages($messages);
        }

        if ($plan['overpayment'] > 0
            && ! \App\Models\ChartOfAccount::where('code', config('coa.customer_deposit'))->exists()) {
            $this->failWithMessages(['total_payment' => ['Akun Deposit Pelanggan (' . config('coa.customer_deposit') . ') belum ada di Chart of Account, sehingga kelebihan bayar belum dapat dicatat sebagai deposit.']]);
        }

        // Nominal yang benar-benar dialokasikan ke invoice (sudah sama dengan / di bawah sisa tagihan).
        $data['invoice_receipts'] = $plan['applied'];
        $data['selected_invoices'] = array_map('intval', array_keys($plan['applied']));
        $data['invoice_id'] = $data['invoice_id'] ?? ($data['selected_invoices'][0] ?? null);
        $data['total_payment'] = round(array_sum($plan['applied']), 2);
        $data['overpayment_amount'] = $plan['overpayment'];

        // Cabang penerimaan = cabang invoice (bukan cabang customer).
        $data['cabang_id'] = $plan['cabang_id'] ?? ($data['cabang_id'] ?? Auth::user()?->cabang_id);

        $this->resolveReceiptCurrencyContext(array_keys($data['invoice_receipts'] ?? []));
    }

    /**
     * @param  array<int|string>  $invoiceIds
     * @return array<int, float>
     */
    private function distributeTotalAcrossInvoices(array $invoiceIds, float $total, CustomerReceiptAllocator $allocator): array
    {
        $invoiceIds = collect($invoiceIds)->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();

        if ($invoiceIds->count() === 1) {
            return [$invoiceIds->first() => $total];
        }

        $left = $total;
        $receipts = [];
        foreach ($invoiceIds as $invoiceId) {
            $invoice = Invoice::withoutGlobalScopes()->find($invoiceId);
            $remaining = $invoice ? $allocator->remainingFor($invoice) : 0.0;
            $portion = min($left, $remaining);
            if ($portion > 0) {
                $receipts[$invoiceId] = $portion;
                $left -= $portion;
            }
        }

        // Sisa yang tidak tertampung dilekatkan pada invoice terakhir agar terdeteksi sebagai kelebihan.
        if ($left > 0.009 && $receipts !== []) {
            $lastId = array_key_last($receipts);
            $receipts[$lastId] += $left;
        } elseif ($receipts === []) {
            $receipts[$invoiceIds->last()] = $total;
        }

        return $receipts;
    }

    /**
     * Tampilkan galat pada field terkait (awalan "data.") dan sebagai notifikasi, lalu hentikan penyimpanan.
     *
     * @param  array<string, array<int, string>|string>  $messages
     */
    private function failWithMessages(array $messages): never
    {
        $flat = collect($messages)->flatten()->implode(' ');

        Notification::make()
            ->danger()
            ->title('Penerimaan tidak dapat disimpan')
            ->body($flat)
            ->persistent()
            ->send();

        throw ValidationException::withMessages(
            collect($messages)->mapWithKeys(fn ($message, $key) => ['data.' . $key => $message])->all()
        );
    }

    private function resolveReceiptCurrencyContext(array $invoiceIds): ?array
    {
        $invoiceIds = collect($invoiceIds)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : null)
            ->filter()
            ->values();

        if ($invoiceIds->isEmpty()) {
            return null;
        }

        $invoices = Invoice::whereIn('id', $invoiceIds)->get();
        $snapshots = $invoices
            ->map(function (Invoice $invoice) {
                $currencyId = is_numeric($invoice->currency_id ?? null) ? (int) $invoice->currency_id : null;
                $rate = (float) ($invoice->exchange_rate ?? 1);

                return [
                    'currency_id' => $currencyId,
                    'exchange_rate' => $rate > 0 ? $rate : 1.0,
                ];
            })
            ->unique(fn (array $snapshot) => ($snapshot['currency_id'] ?? 'null') . ':' . number_format((float) $snapshot['exchange_rate'], 8, '.', ''))
            ->values();

        if ($snapshots->count() > 1) {
            throw ValidationException::withMessages([
                'selected_invoices' => 'Customer receipt hanya boleh mencakup invoice dengan satu mata uang dan satu rate.',
            ]);
        }

        return $snapshots->first();
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        // Mark early so CustomerReceiptObserver does not double-count AR while
        // CustomerReceiptItemObserver triggers receipt status updates during item creation.
        \App\Observers\CustomerReceiptObserver::markArUpdatedInCreate($record->id);
        
        
        // Create customer receipt items based on invoice_receipts data
        $invoiceReceipts = [];
        
        // Try to get invoice receipts data
        if (!empty($record->invoice_receipts)) {
            $invoiceReceipts = is_array($record->invoice_receipts) 
                ? $record->invoice_receipts 
                : json_decode($record->invoice_receipts, true) ?? [];
            
        } else {
        }
        
        // If no invoice_receipts data but we have selected_invoices and total_payment,
        // create a receipt item for the first selected invoice
        if (empty($invoiceReceipts) && !empty($record->selected_invoices) && $record->total_payment > 0) {
            
            $selectedInvoices = is_array($record->selected_invoices) 
                ? $record->selected_invoices 
                : json_decode($record->selected_invoices, true) ?? [];
                
                
            if (!empty($selectedInvoices)) {
                // Create receipt item for first selected invoice with full payment amount
                $firstInvoiceId = $selectedInvoices[0];
                $invoiceReceipts = [$firstInvoiceId => $record->total_payment];
                
            }
        }
        
        // Create CustomerReceiptItems and update Account Receivable
        $itemsCreated = 0;
        $arUpdated = 0;
        $totalActualPayment = 0;
        
        if (!empty($invoiceReceipts)) {
            foreach ($invoiceReceipts as $invoiceId => $paymentAmount) {
                if ($paymentAmount > 0) {
                    $invoice = Invoice::find($invoiceId);
                    $currencyId = is_numeric($invoice?->currency_id ?? null) ? (int) $invoice->currency_id : null;
                    $exchangeRate = (float) ($invoice?->exchange_rate ?? 1);
                    $exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1.0;

                    // Create CustomerReceiptItem
                    CustomerReceiptItem::create([
                        'customer_receipt_id' => $record->id,
                        'invoice_id' => $invoiceId,
                        'currency_id' => $currencyId,
                        'exchange_rate' => $exchangeRate,
                        'method' => $record->payment_method ?? 'Cash',
                        'amount' => $paymentAmount, // Use 'amount' instead of 'payment_amount'
                        'amount_idr' => $paymentAmount,
                        'coa_id' => $record->coa_id, // Use coa_id from receipt
                        'payment_date' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $itemsCreated++;
                    $totalActualPayment += $paymentAmount;
                    
                    
                    // Update Account Receivable — both paid and remaining
                    $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();
                    if ($accountReceivable) {
                        $newPaid      = $accountReceivable->paid + $paymentAmount;
                        $newRemaining = $accountReceivable->remaining - $paymentAmount;
                        $arExchangeRate = (float) ($accountReceivable->exchange_rate ?? $exchangeRate);
                        $arExchangeRate = $arExchangeRate > 0 ? $arExchangeRate : 1.0;

                        $accountReceivable->update([
                            'paid'      => $newPaid,
                            'remaining' => max(0, $newRemaining),
                            'paid_original' => round($newPaid / $arExchangeRate, 4),
                            'remaining_original' => round(max(0, $newRemaining) / $arExchangeRate, 4),
                        ]);

                        // Sync invoice and AR status
                        if ($newRemaining <= 0) {
                            $accountReceivable->invoice?->update(['status' => 'paid']);
                            $accountReceivable->update(['status' => PaymentStatus::PAID->value]);
                            if ($accountReceivable->ageingSchedule) {
                                $accountReceivable->ageingSchedule->delete();
                            }
                        } elseif ($newPaid > 0) {
                            $accountReceivable->invoice?->update(['status' => 'partially_paid']);
                        }

                        $arUpdated++;
                    }
                }
            }
        }
        
        // Recalculate total_payment from actual CustomerReceiptItems using model method
        $finalTotal = $record->recalculateTotalPayment();

        // Ensure the receipt status moves out of Draft after the create flow has
        // finished updating Account Receivable balances.
        $this->syncReceiptStatusFromReceivables($record);

        // Kelebihan bayar (bila user memilih mencatatnya) menjadi Deposit Customer dalam transaksi yang sama.
        $deposit = $this->recordOverpaymentAsDeposit($record);

        // Show success notification
        $depositNote = $deposit
            ? ' Kelebihan ' . \App\Helpers\MoneyHelper::rupiah($deposit->amount) . " dicatat sebagai Deposit Customer {$deposit->deposit_number}."
            : '';

        Notification::make()
            ->success()
            ->title('Customer Receipt created successfully')
            ->body("Payment of " . \App\Helpers\MoneyHelper::rupiah($finalTotal) . " processed for {$itemsCreated} invoice(s). {$arUpdated} Account Receivable record(s) updated." . $depositNote)
            ->send();
    }

    /**
     * Catat kelebihan bayar sebagai Deposit Customer: Dr Kas/Bank (akun penerimaan), Cr Deposit Pelanggan (2160.04),
     * di cabang penerimaan. Bila jurnal gagal, seluruh penerimaan dibatalkan (transaksi Filament) —
     * uang tidak boleh diterima tanpa tercatat.
     */
    private function recordOverpaymentAsDeposit($record): ?Deposit
    {
        $overpayment = round((float) ($record->overpayment_amount ?? 0), 2);

        if ($overpayment <= 0 || $record->deposit_id) {
            return null;
        }

        try {
            $deposit = Deposit::create([
                'deposit_number' => app(DepositNumberGenerator::class)->generate(),
                'from_model_type' => Customer::class,
                'from_model_id' => $record->customer_id,
                'amount' => $overpayment,
                'used_amount' => 0,
                'remaining_amount' => $overpayment,
                'coa_id' => $record->coa_id,
                'payment_coa_id' => $record->coa_id,
                'note' => 'Kelebihan bayar dari Penerimaan Customer #' . $record->id,
                'status' => 'active',
                'created_by' => Auth::id(),
            ]);

            app(LedgerPostingService::class)->postDeposit($deposit, $record->cabang_id ? (int) $record->cabang_id : null);

            $record->update(['deposit_id' => $deposit->id]);

            return $deposit;
        } catch (Throwable $exception) {
            ProcurementFailureNotifier::danger(
                'Gagal Mencatat Deposit dari Kelebihan Bayar',
                $exception,
                'Penerimaan dibatalkan karena kelebihan bayar tidak dapat dicatat sebagai Deposit Customer.'
            );

            $this->halt(true);

            return null;
        }
    }

    private function syncReceiptStatusFromReceivables($record): void
    {
        $selectedInvoices = $record->selected_invoices;

        if (is_string($selectedInvoices)) {
            $selectedInvoices = json_decode($selectedInvoices, true) ?? [];
        }

        if (! is_array($selectedInvoices) || empty($selectedInvoices)) {
            return;
        }

        $allPaid = true;
        $anyPartial = false;

        foreach ($selectedInvoices as $invoiceId) {
            $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();

            if (! $accountReceivable) {
                continue;
            }

            if ($accountReceivable->remaining > 0) {
                $allPaid = false;

                if ($accountReceivable->paid > 0) {
                    $anyPartial = true;
                }
            }
        }

        if ($allPaid) {
            $record->update(['status' => 'Paid']);
            try {
                app(LedgerPostingService::class)->postCustomerReceipt($record->fresh());
            } catch (Throwable $exception) {
                ProcurementFailureNotifier::danger(
                    'Gagal Posting Jurnal Penerimaan',
                    $exception,
                    'Customer receipt berhasil diproses, tetapi jurnal penerimaan belum dapat dibuat.'
                );
            }
        } elseif ($anyPartial) {
            $record->update(['status' => 'Partial']);
            try {
                app(LedgerPostingService::class)->postCustomerReceipt($record->fresh());
            } catch (Throwable $exception) {
                ProcurementFailureNotifier::danger(
                    'Gagal Posting Jurnal Penerimaan',
                    $exception,
                    'Customer receipt berhasil diproses, tetapi jurnal penerimaan belum dapat dibuat.'
                );
            }
        }
    }
}
