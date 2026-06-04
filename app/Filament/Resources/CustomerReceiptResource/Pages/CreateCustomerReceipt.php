<?php

namespace App\Filament\Resources\CustomerReceiptResource\Pages;

use App\Enums\PaymentStatus;
use App\Filament\Resources\CustomerReceiptResource;
use App\Helpers\MoneyHelper;
use App\Models\Invoice;
use App\Models\AccountReceivable;
use App\Models\CustomerReceiptItem;
use App\Services\LedgerPostingService;
use App\Support\ProcurementFailureNotifier;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
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
        
        // FALLBACK: If invoice selection is still empty, auto-select based on customer
        if (empty($data['selected_invoices']) && !empty($data['customer_id']) && !empty($data['total_payment'])) {
            
            $customerId = $data['customer_id'];
            $totalPayment = (float) $data['total_payment'];
            
            // Get available invoices for this customer
            $invoices = DB::table('invoices')
                ->join('sale_orders', function($join) use ($customerId) {
                    $join->on('invoices.from_model_id', '=', 'sale_orders.id')
                         ->where('sale_orders.customer_id', $customerId)
                         ->whereIn('sale_orders.status', ['confirmed', 'received', 'completed'])
                         ->whereNull('sale_orders.deleted_at');
                })
                ->where('invoices.from_model_type', 'App\\Models\\SaleOrder')
                ->whereExists(function($query) {
                    $query->select(DB::raw(1))
                          ->from('account_receivables')
                          ->whereRaw('invoices.id = account_receivables.invoice_id')
                          ->where('remaining', '>', 0)
                          ->whereNull('account_receivables.deleted_at');
                })
                ->whereNull('invoices.deleted_at')
                ->select('invoices.*')
                ->distinct()
                ->get();
            
            if ($invoices->count() > 0) {
                // Auto-assign payment to first available invoice
                $firstInvoice = $invoices->first();
                $data['selected_invoices'] = [$firstInvoice->id];
                $data['invoice_receipts']  = [$firstInvoice->id => $totalPayment];
                $data['invoice_id']        = $firstInvoice->id;
            }
        }

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

        // Validate and fix data consistency
        $this->validateAndFixDataConsistency($data);
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

    protected function validateAndFixDataConsistency(array &$data): void
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

        // Fix missing invoice_receipts data
        if (empty($data['invoice_receipts']) && !empty($data['selected_invoices']) && $data['total_payment'] > 0) {
            
            // If only one invoice selected, use full payment amount
            if (count($data['selected_invoices']) === 1) {
                $data['invoice_receipts'] = [
                    $data['selected_invoices'][0] => $data['total_payment']
                ];
            } else {
                // For multiple invoices, distribute payment proportionally
                $totalRemaining = 0;
                $invoiceRemainingAmounts = [];
                
                foreach ($data['selected_invoices'] as $invoiceId) {
                    $ar = \App\Models\AccountReceivable::where('invoice_id', $invoiceId)->first();
                    if ($ar) {
                        $remaining = $ar->remaining;
                        $invoiceRemainingAmounts[$invoiceId] = $remaining;
                        $totalRemaining += $remaining;
                    }
                }
                
                // Distribute payment proportionally
                if ($totalRemaining > 0) {
                    $remainingPayment = $data['total_payment'];
                    foreach ($invoiceRemainingAmounts as $invoiceId => $remaining) {
                        if ($remainingPayment <= 0) break;
                        
                        $proportionalAmount = min($remaining, ($remaining / $totalRemaining) * $data['total_payment']);
                        $data['invoice_receipts'][$invoiceId] = $proportionalAmount;
                        $remainingPayment -= $proportionalAmount;
                    }
                }
            }
        }

        // Validate payment amounts against Account Receivable
        if (!empty($data['invoice_receipts'])) {
            $hasAutoFix = false;
            
            foreach ($data['invoice_receipts'] as $invoiceId => $paymentAmount) {
                if ($paymentAmount > 0) {
                    $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();
                    
                    if ($accountReceivable) {
                        if ($paymentAmount > $accountReceivable->remaining) {
                            
                            // Auto-fix: reduce payment to remaining amount
                            $data['invoice_receipts'][$invoiceId] = $accountReceivable->remaining;
                            $hasAutoFix = true;
                            
                        }
                    } else {
                    }
                }
            }
            
            if ($hasAutoFix) {
                Notification::make()
                    ->warning()
                    ->title('Payment amounts adjusted')
                    ->body('Some payment amounts exceeded remaining invoice balances and were automatically adjusted.')
                    ->send();
            }
        }

        $this->resolveReceiptCurrencyContext(array_keys($data['invoice_receipts'] ?? []));

        // Validate total consistency
        $calculatedTotal = 0;
        if (!empty($data['invoice_receipts'])) {
            foreach ($data['invoice_receipts'] as $amount) {
                $calculatedTotal += $amount;
            }
        }

        // Fix total_payment if inconsistent
        if (abs($calculatedTotal - $data['total_payment']) > 0.01) {
            $data['total_payment'] = $calculatedTotal;
        }
        
        // Final validation log
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

        // Show success notification
        Notification::make()
            ->success()
            ->title('Customer Receipt created successfully')
            ->body("Payment of " . \App\Helpers\MoneyHelper::rupiah($finalTotal) . " processed for {$itemsCreated} invoice(s). {$arUpdated} Account Receivable record(s) updated.")
            ->send();
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
