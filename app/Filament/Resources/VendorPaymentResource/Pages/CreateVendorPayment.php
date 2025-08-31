<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use App\Models\Invoice;
use App\Models\AccountPayable;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorPayment extends CreateRecord
{
    protected static string $resource = VendorPaymentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = 'Draft';
        
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
        
        // Update account payables for selected invoices
        if (!empty($record->selected_invoices)) {
            $invoices = Invoice::whereIn('id', $record->selected_invoices)->get();
            
            foreach ($invoices as $invoice) {
                $accountPayable = AccountPayable::where('invoice_id', $invoice->id)->first();
                
                if ($accountPayable) {
                    // Calculate payment amount per invoice based on proportion
                    $totalInvoiceAmount = $invoices->sum(function ($inv) {
                        return $inv->accountPayable->remaining ?? $inv->total;
                    });
                    
                    $invoiceRemaining = $accountPayable->remaining ?? $invoice->total;
                    $proportion = $totalInvoiceAmount > 0 ? ($invoiceRemaining / $totalInvoiceAmount) : 0;
                    $paymentForThisInvoice = ($record->total_payment - $record->payment_adjustment) * $proportion;
                    
                    // Update account payable
                    $newPaid = $accountPayable->paid + $paymentForThisInvoice;
                    $newRemaining = $accountPayable->total - $newPaid;
                    
                    $accountPayable->update([
                        'paid' => $newPaid,
                        'remaining' => max(0, $newRemaining),
                        'status' => $newRemaining <= 0 ? 'Lunas' : 'Belum Lunas'
                    ]);
                }
            }
        } else if ($record->invoice_id) {
            // Handle single invoice (backward compatibility)
            $accountPayable = AccountPayable::where('invoice_id', $record->invoice_id)->first();
            
            if ($accountPayable) {
                $paymentAmount = $record->total_payment - $record->payment_adjustment;
                $newPaid = $accountPayable->paid + $paymentAmount;
                $newRemaining = $accountPayable->total - $newPaid;
                
                $accountPayable->update([
                    'paid' => $newPaid,
                    'remaining' => max(0, $newRemaining),
                    'status' => $newRemaining <= 0 ? 'Lunas' : 'Belum Lunas'
                ]);
            }
        }
    }
}
