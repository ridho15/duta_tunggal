<?php

namespace App\Filament\Resources\CustomerReceiptResource\Pages;

use App\Filament\Resources\CustomerReceiptResource;
use App\Models\Invoice;
use App\Models\AccountReceivable;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerReceipt extends CreateRecord
{
    protected static string $resource = CustomerReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Handle backward compatibility for single invoice
        if (!empty($data['selected_invoices']) && empty($data['invoice_id'])) {
            // If multiple invoices selected, set invoice_id to first one for compatibility
            $data['invoice_id'] = $data['selected_invoices'][0] ?? null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        
        // Update account receivables for selected invoices
        if (!empty($record->selected_invoices)) {
            $invoices = Invoice::whereIn('id', $record->selected_invoices)->get();
            
            foreach ($invoices as $invoice) {
                $accountReceivable = AccountReceivable::where('invoice_id', $invoice->id)->first();
                
                if ($accountReceivable) {
                    // Calculate payment amount per invoice based on proportion
                    $totalInvoiceAmount = $invoices->sum(function ($inv) {
                        return $inv->accountReceivable->remaining ?? $inv->total;
                    });
                    
                    $invoiceRemaining = $accountReceivable->remaining ?? $invoice->total;
                    $proportion = $totalInvoiceAmount > 0 ? ($invoiceRemaining / $totalInvoiceAmount) : 0;
                    $paymentForThisInvoice = ($record->total_payment - $record->payment_adjustment) * $proportion;
                    
                    // Update account receivable
                    $newPaid = $accountReceivable->paid + $paymentForThisInvoice;
                    $newRemaining = $accountReceivable->total - $newPaid;
                    
                    $accountReceivable->update([
                        'paid' => $newPaid,
                        'remaining' => max(0, $newRemaining),
                        'status' => $newRemaining <= 0 ? 'Lunas' : 'Belum Lunas'
                    ]);
                }
            }
        } else if ($record->invoice_id) {
            // Handle single invoice (backward compatibility)
            $accountReceivable = AccountReceivable::where('invoice_id', $record->invoice_id)->first();
            
            if ($accountReceivable) {
                $paymentAmount = $record->total_payment - $record->payment_adjustment;
                $newPaid = $accountReceivable->paid + $paymentAmount;
                $newRemaining = $accountReceivable->total - $newPaid;
                
                $accountReceivable->update([
                    'paid' => $newPaid,
                    'remaining' => max(0, $newRemaining),
                    'status' => $newRemaining <= 0 ? 'Lunas' : 'Belum Lunas'
                ]);
            }
        }
    }
}
