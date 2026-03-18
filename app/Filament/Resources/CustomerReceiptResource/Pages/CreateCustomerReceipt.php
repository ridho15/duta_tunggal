<?php

namespace App\Filament\Resources\CustomerReceiptResource\Pages;

use App\Filament\Resources\CustomerReceiptResource;
use App\Models\Invoice;
use App\Models\AccountReceivable;
use App\Models\CustomerReceiptItem;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class CreateCustomerReceipt extends CreateRecord
{
    protected static string $resource = CustomerReceiptResource::class;

    #[On('updateInvoiceData')]
    public function updateInvoiceData($data)
    {
        
        if (isset($data['selected_invoices'])) {
            $this->form->fill(['selected_invoices' => $data['selected_invoices']]);
        }
        
        if (isset($data['invoice_receipts'])) {
            $this->form->fill(['invoice_receipts' => $data['invoice_receipts']]);
        }
    }

    #[On('updateHiddenField')]
    public function updateHiddenField($field, $value)
    {
        
        // Handle the update based on field name
        if ($field === 'selected_invoices' || $field === 'invoice_receipts') {
            // Parse JSON string if needed
            $parsedValue = is_string($value) ? json_decode($value, true) : $value;
            
            // Update the form data
            $this->data[$field] = $parsedValue;
            
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
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

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        
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
                    // Create CustomerReceiptItem
                    CustomerReceiptItem::create([
                        'customer_receipt_id' => $record->id,
                        'invoice_id' => $invoiceId,
                        'method' => $record->payment_method ?? 'cash', // Use payment method from receipt
                        'amount' => $paymentAmount, // Use 'amount' instead of 'payment_amount'
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

                        $accountReceivable->update([
                            'paid'      => $newPaid,
                            'remaining' => max(0, $newRemaining),
                        ]);

                        // Sync invoice and AR status
                        if ($newRemaining <= 0) {
                            $accountReceivable->invoice?->update(['status' => 'paid']);
                            $accountReceivable->update(['status' => 'Lunas']);
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

        // Mark that AR was already updated here so the Observer does not double-count
        // when CustomerReceiptItemObserver triggers a receipt status change.
        \App\Observers\CustomerReceiptObserver::markArUpdatedInCreate($record->id);

        // Show success notification
        Notification::make()
            ->success()
            ->title('Customer Receipt created successfully')
            ->body("Payment of Rp " . number_format($finalTotal, 0, ',', '.') . " processed for {$itemsCreated} invoice(s). {$arUpdated} Account Receivable record(s) updated.")
            ->send();
    }
}
