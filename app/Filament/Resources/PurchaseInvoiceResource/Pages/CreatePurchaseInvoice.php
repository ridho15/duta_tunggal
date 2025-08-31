<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove temporary fields
        unset($data['selected_supplier']);
        unset($data['selected_purchase_order']);
        unset($data['selected_purchase_receipts']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Create invoice items
        if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
            foreach ($this->data['invoiceItem'] as $item) {
                $this->record->invoiceItem()->create($item);
            }
        }
    }
}
