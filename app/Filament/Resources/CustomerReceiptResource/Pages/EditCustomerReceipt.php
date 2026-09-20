<?php

namespace App\Filament\Resources\CustomerReceiptResource\Pages;

use App\Filament\Resources\CustomerReceiptResource;
use App\Helpers\MoneyHelper;
use App\Models\Invoice;
use App\Models\AccountReceivable;
use App\Services\CustomerReceiptAllocator;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditCustomerReceipt extends EditRecord
{
    protected static string $resource = CustomerReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Handle backward compatibility - if no selected_invoices but has invoice_id
        if (empty($data['selected_invoices']) && !empty($data['invoice_id'])) {
            $data['selected_invoices'] = [$data['invoice_id']];
        }

        // Ensure invoice_receipts is a JSON string for the form
        if (isset($data['invoice_receipts']) && is_array($data['invoice_receipts'])) {
            $data['invoice_receipts'] = json_encode($data['invoice_receipts']);
        } elseif (empty($data['invoice_receipts'])) {
            $data['invoice_receipts'] = '{}';
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['total_payment'] = MoneyHelper::safeParse($data['total_payment'] ?? 0);
        $data['payment_method'] = $data['payment_method'] ?? 'Cash';

        // Handle JSON strings from form (hidden fields send JSON strings)
        if (isset($data['selected_invoices']) && is_string($data['selected_invoices'])) {
            $data['selected_invoices'] = json_decode($data['selected_invoices'], true) ?? [];
        }
        
        if (isset($data['invoice_receipts']) && is_string($data['invoice_receipts'])) {
            $data['invoice_receipts'] = json_decode($data['invoice_receipts'], true) ?? [];
        }

        // Handle backward compatibility for single invoice
        if (!empty($data['selected_invoices']) && empty($data['invoice_id'])) {
            // If multiple invoices selected, set invoice_id to first one for compatibility
            $data['invoice_id'] = $data['selected_invoices'][0] ?? null;
        }

        // Validate and fix data consistency (same as create)
        $this->validateAndFixDataConsistency($data);

        // Debug logging to see what's being saved
        Log::info('Customer Receipt Edit Data', [
            'record_id' => $this->record->id ?? null,
            'selected_invoices' => $data['selected_invoices'] ?? null,
            'invoice_receipts' => $data['invoice_receipts'] ?? null,
            'total_payment' => $data['total_payment'] ?? null,
        ]);

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

        // Fix missing invoice_receipts data
        if (empty($data['invoice_receipts']) && !empty($data['selected_invoices']) && $data['total_payment'] > 0) {
            // If only one invoice selected, use full payment amount
            if (count($data['selected_invoices']) === 1) {
                $data['invoice_receipts'] = [
                    $data['selected_invoices'][0] => $data['total_payment']
                ];
            }
        }

        // Validasi & alokasi yang sama dengan halaman Buat: cabang = cabang invoice, akun penerima yang sah,
        // dan nominal tidak boleh melebihi sisa tagihan (tidak ada pemotongan senyap).
        $allocator = app(CustomerReceiptAllocator::class);
        $errors = [];

        try {
            $allocator->assertAccountAllowed(isset($data['coa_id']) ? (int) $data['coa_id'] : null, $data['payment_method'] ?? 'Cash');
        } catch (ValidationException $e) {
            $errors = array_merge($errors, $e->errors());
        }

        try {
            $plan = $allocator->plan(
                (int) ($data['customer_id'] ?? $this->record->customer_id),
                $data['invoice_receipts'] ?? [],
                $data['payment_method'] ?? 'Cash',
                false,
                $this->record,
            );
        } catch (ValidationException $e) {
            $plan = null;
            $errors = array_merge($errors, $e->errors());
        }

        if ($errors !== []) {
            Notification::make()->danger()->title('Penerimaan tidak dapat disimpan')->body(collect($errors)->flatten()->implode(' '))->persistent()->send();

            throw ValidationException::withMessages(collect($errors)->mapWithKeys(fn ($message, $key) => ['data.' . $key => $message])->all());
        }

        $data['invoice_receipts'] = $plan['applied'];
        $data['selected_invoices'] = array_map('intval', array_keys($plan['applied']));
        $data['total_payment'] = round(array_sum($plan['applied']), 2);
        $data['cabang_id'] = $plan['cabang_id'] ?? ($data['cabang_id'] ?? $this->record->cabang_id);
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        app(\App\Services\CustomerReceiptReference::class)->warnIfDuplicate($record);
        
        // Delete existing customer receipt items
        $record->customerReceiptItem()->delete();
        
        // Create customer receipt items based on invoice_receipts data
        $invoiceReceipts = [];
        
        // Try to get invoice receipts data
        if (!empty($record->invoice_receipts)) {
            $invoiceReceipts = is_array($record->invoice_receipts) 
                ? $record->invoice_receipts 
                : json_decode($record->invoice_receipts, true) ?? [];
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
        
        // Create customer receipt items
        foreach ($invoiceReceipts as $invoiceId => $receiptAmount) {
            if ($receiptAmount > 0) {
                $record->customerReceiptItem()->create([
                    'invoice_id' => $invoiceId,
                    'method' => $record->payment_method ?? 'Cash',
                    'amount' => $receiptAmount,
                    'coa_id' => $record->coa_id,
                    'payment_date' => $record->payment_date ?? now(),
                ]);
            }
        }
        
        // The Observer will automatically handle account receivable updates
        // when customer receipt items are created
    }
}
