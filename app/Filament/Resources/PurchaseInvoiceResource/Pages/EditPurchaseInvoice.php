<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load related data for form
        if ($this->record->from_model_type === 'App\Models\PurchaseOrder') {
            $data['selected_supplier'] = $this->record->fromModel->supplier_id ?? null;
            $data['selected_purchase_order'] = $this->record->from_model_id ?? null;
            $data['selected_purchase_receipts'] = $this->record->purchase_receipts ?? [];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove temporary fields
        unset($data['selected_supplier']);
        unset($data['selected_purchase_order']);
        unset($data['selected_purchase_receipts']);
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Sync invoice items
        if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
            // Delete existing items
            $this->record->invoiceItem()->delete();
            
            // Create new items
            foreach ($this->data['invoiceItem'] as $item) {
                $this->record->invoiceItem()->create($item);
            }
        }
    }
}
