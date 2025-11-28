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
        Log::info('Livewire event received:', $data);
        
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
        Log::info('Hidden field update event received', [
            'field' => $field,
            'value' => $value,
            'type' => gettype($value)
        ]);
        
        // Handle the update based on field name
        if ($field === 'selected_invoices' || $field === 'invoice_receipts') {
            // Parse JSON string if needed
            $parsedValue = is_string($value) ? json_decode($value, true) : $value;
            
            // Update the form data
            $this->data[$field] = $parsedValue;
            
            Log::info('Form data updated', [
                'field' => $field,
                'new_value' => $this->data[$field],
                'all_data_keys' => array_keys($this->data)
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Debug: Log raw form data first
        Log::info('Raw Form Data Before Processing', $data);
        Log::info('Full Request Data', request()->all());
        
        // Extract data from Livewire component data if not in form data
        if (empty($data['selected_invoices']) || empty($data['invoice_receipts'])) {
            Log::info('Attempting to extract data from Livewire component');
            
            // Try to get data from current component state
            if (!empty($this->data['selected_invoices'])) {
                $data['selected_invoices'] = $this->data['selected_invoices'];
                Log::info('Extracted selected_invoices from component data', [
                    'selected_invoices' => $data['selected_invoices']
                ]);
            }
            
            if (!empty($this->data['invoice_receipts'])) {
                $data['invoice_receipts'] = $this->data['invoice_receipts'];
                Log::info('Extracted invoice_receipts from component data', [
                    'invoice_receipts' => $data['invoice_receipts']
                ]);
            }
            
            // Alternative: extract from request data directly
            $requestData = request()->all();
            if (isset($requestData['components'][0]['snapshot'])) {
                $snapshot = json_decode($requestData['components'][0]['snapshot'], true);
                if (isset($snapshot['data']['data'][0])) {
                    $componentData = $snapshot['data']['data'][0];
                    
                    if (empty($data['selected_invoices']) && !empty($componentData['selected_invoices'])) {
                        $data['selected_invoices'] = $componentData['selected_invoices'];
                        Log::info('Extracted selected_invoices from request snapshot', [
                            'selected_invoices' => $data['selected_invoices']
                        ]);
                    }
                    
                    if (empty($data['invoice_receipts']) && !empty($componentData['invoice_receipts'])) {
                        $data['invoice_receipts'] = $componentData['invoice_receipts'];
                        Log::info('Extracted invoice_receipts from request snapshot', [
                            'invoice_receipts' => $data['invoice_receipts']
                        ]);
                    }
                }
            }
        }
        
        // Handle JSON strings from form (hidden fields send JSON strings)
        if (isset($data['selected_invoices']) && is_string($data['selected_invoices'])) {
            $originalValue = $data['selected_invoices'];
            $data['selected_invoices'] = json_decode($data['selected_invoices'], true) ?? [];
            Log::info('Parsed selected_invoices', [
                'original' => $originalValue,
                'parsed' => $data['selected_invoices']
            ]);
        }
        
        if (isset($data['invoice_receipts']) && is_string($data['invoice_receipts'])) {
            $originalValue = $data['invoice_receipts'];
            $data['invoice_receipts'] = json_decode($data['invoice_receipts'], true) ?? [];
            Log::info('Parsed invoice_receipts', [
                'original' => $originalValue,
                'parsed' => $data['invoice_receipts']
            ]);
        }
        
        // FALLBACK: If invoice selection is still empty, auto-select based on customer
        if (empty($data['selected_invoices']) && !empty($data['customer_id']) && !empty($data['total_payment'])) {
            Log::info('Applying fallback invoice auto-selection');
            
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
                $selectedInvoices = [];
                $invoiceReceipts = [];
                
                // Auto-assign payment to first available invoice
                $firstInvoice = $invoices->first();
                $selectedInvoices[] = $firstInvoice->id;
                $invoiceReceipts[$firstInvoice->id] = $totalPayment;
                
                $data['selected_invoices'] = $selectedInvoices;
                $data['invoice_receipts'] = $invoiceReceipts;
                $data['invoice_id'] = $firstInvoice->id;
                
                Log::info('Auto-assigned invoice selection', [
                    'selected_invoices' => $selectedInvoices,
                    'invoice_receipts' => $invoiceReceipts,
                    'invoice_id' => $firstInvoice->id
                ]);
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
                Log::info('Set invoice_id for backward compatibility', [
                    'selected_invoices' => $selectedInvoices,
                    'invoice_id' => $data['invoice_id']
                ]);
            }
        }

        // Validate and fix data consistency
        $this->validateAndFixDataConsistency($data);

        // Debug logging to see what's being saved
        Log::info('Customer Receipt Create Data Final', [
            'customer_id' => $data['customer_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'selected_invoices' => $data['selected_invoices'] ?? null,
            'invoice_receipts' => $data['invoice_receipts'] ?? null,
            'total_payment' => $data['total_payment'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'ntpn' => $data['ntpn'] ?? null,
        ]);

        return $data;
    }

    protected function validateAndFixDataConsistency(array &$data): void
    {
        // Parse JSON strings if needed
        if (isset($data['selected_invoices']) && is_string($data['selected_invoices'])) {
            $originalValue = $data['selected_invoices'];
            $data['selected_invoices'] = json_decode($data['selected_invoices'], true) ?? [];
            Log::info('Parsed selected_invoices in validation', [
                'original' => $originalValue,
                'parsed' => $data['selected_invoices']
            ]);
        }
        
        if (isset($data['invoice_receipts']) && is_string($data['invoice_receipts'])) {
            $originalValue = $data['invoice_receipts'];
            $data['invoice_receipts'] = json_decode($data['invoice_receipts'], true) ?? [];
            Log::info('Parsed invoice_receipts in validation', [
                'original' => $originalValue,
                'parsed' => $data['invoice_receipts']
            ]);
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
            Log::info('Fixing missing invoice_receipts data', [
                'selected_invoices' => $data['selected_invoices'],
                'total_payment' => $data['total_payment']
            ]);
            
            // If only one invoice selected, use full payment amount
            if (count($data['selected_invoices']) === 1) {
                $data['invoice_receipts'] = [
                    $data['selected_invoices'][0] => $data['total_payment']
                ];
                Log::info('Applied single invoice receipt', ['invoice_receipts' => $data['invoice_receipts']]);
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
                    Log::info('Applied proportional distribution', ['invoice_receipts' => $data['invoice_receipts']]);
                }
            }
        }

        // Validate payment amounts against Account Receivable
        if (!empty($data['invoice_receipts'])) {
            Log::info('Validating payment amounts against Account Receivable');
            $hasAutoFix = false;
            
            foreach ($data['invoice_receipts'] as $invoiceId => $paymentAmount) {
                if ($paymentAmount > 0) {
                    $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();
                    
                    if ($accountReceivable) {
                        if ($paymentAmount > $accountReceivable->remaining) {
                            Log::warning('Payment amount exceeds remaining balance', [
                                'invoice_id' => $invoiceId,
                                'payment_amount' => $paymentAmount,
                                'remaining_balance' => $accountReceivable->remaining
                            ]);
                            
                            // Auto-fix: reduce payment to remaining amount
                            $data['invoice_receipts'][$invoiceId] = $accountReceivable->remaining;
                            $hasAutoFix = true;
                            
                            Log::info('Auto-fixed payment amount', [
                                'invoice_id' => $invoiceId,
                                'original_amount' => $paymentAmount,
                                'fixed_amount' => $accountReceivable->remaining
                            ]);
                        }
                    } else {
                        Log::warning('Account Receivable not found for invoice', [
                            'invoice_id' => $invoiceId
                        ]);
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
            Log::warning('Customer Receipt: Fixing inconsistent total payment', [
                'original_total' => $data['total_payment'],
                'calculated_total' => $calculatedTotal,
                'invoice_receipts' => $data['invoice_receipts']
            ]);
            $data['total_payment'] = $calculatedTotal;
        }
        
        // Final validation log
        Log::info('Data validation completed', [
            'selected_invoices' => $data['selected_invoices'],
            'invoice_receipts' => $data['invoice_receipts'],
            'invoice_id' => $data['invoice_id'] ?? 'not_set',
            'total_payment' => $data['total_payment']
        ]);
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        Log::info('Customer Receipt afterCreate started', [
            'record_id' => $record->id,
            'customer_id' => $record->customer_id,
            'invoice_id' => $record->invoice_id,
            'selected_invoices' => $record->selected_invoices,
            'invoice_receipts' => $record->invoice_receipts,
            'total_payment' => $record->total_payment,
        ]);
        
        // Create customer receipt items based on invoice_receipts data
        $invoiceReceipts = [];
        
        // Try to get invoice receipts data
        if (!empty($record->invoice_receipts)) {
            $invoiceReceipts = is_array($record->invoice_receipts) 
                ? $record->invoice_receipts 
                : json_decode($record->invoice_receipts, true) ?? [];
            
            Log::info('Found invoice_receipts data', ['invoice_receipts' => $invoiceReceipts]);
        } else {
            Log::warning('No invoice_receipts data found');
        }
        
        // If no invoice_receipts data but we have selected_invoices and total_payment,
        // create a receipt item for the first selected invoice
        if (empty($invoiceReceipts) && !empty($record->selected_invoices) && $record->total_payment > 0) {
            Log::info('Using fallback logic for invoice receipts');
            
            $selectedInvoices = is_array($record->selected_invoices) 
                ? $record->selected_invoices 
                : json_decode($record->selected_invoices, true) ?? [];
                
            Log::info('Selected invoices for fallback', ['selected_invoices' => $selectedInvoices]);
                
            if (!empty($selectedInvoices)) {
                // Create receipt item for first selected invoice with full payment amount
                $firstInvoiceId = $selectedInvoices[0];
                $invoiceReceipts = [$firstInvoiceId => $record->total_payment];
                
                Log::info('Created fallback invoice receipts', ['invoice_receipts' => $invoiceReceipts]);
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
                    
                    Log::info('CustomerReceiptItem created', [
                        'customer_receipt_id' => $record->id,
                        'invoice_id' => $invoiceId,
                        'amount' => $paymentAmount
                    ]);
                    
                    // Update Account Receivable
                    $accountReceivable = AccountReceivable::where('invoice_id', $invoiceId)->first();
                    if ($accountReceivable) {
                        $oldRemaining = $accountReceivable->remaining;
                        $newRemaining = $oldRemaining - $paymentAmount;
                        
                        $accountReceivable->update([
                            'remaining' => $newRemaining
                        ]);
                        
                        $arUpdated++;
                        
                        Log::info('Account Receivable updated', [
                            'ar_id' => $accountReceivable->id,
                            'invoice_id' => $invoiceId,
                            'amount' => $paymentAmount,
                            'old_remaining' => $oldRemaining,
                            'new_remaining' => $newRemaining
                        ]);
                    } else {
                        Log::warning('Account Receivable not found for update', [
                            'invoice_id' => $invoiceId,
                            'amount' => $paymentAmount
                        ]);
                    }
                }
            }
        }
        
        // Recalculate total_payment from actual CustomerReceiptItems using model method
        $finalTotal = $record->recalculateTotalPayment();
            
        Log::info('Recalculated total using model method', [
            'calculated_total' => $totalActualPayment,
            'final_total_from_items' => $finalTotal,
            'original_form_total' => $record->getOriginal('total_payment')
        ]);
        
        Log::info('Customer Receipt created successfully', [
            'customer_receipt_id' => $record->id,
            'total_payment' => $finalTotal,
            'invoices_count' => $itemsCreated,
            'account_receivables_updated' => $arUpdated
        ]);
        
        // Show success notification
        Notification::make()
            ->success()
            ->title('Customer Receipt created successfully')
            ->body("Payment of Rp " . number_format($finalTotal, 0, ',', '.') . " processed for {$itemsCreated} invoice(s). {$arUpdated} Account Receivable record(s) updated.")
            ->send();
    }
}
