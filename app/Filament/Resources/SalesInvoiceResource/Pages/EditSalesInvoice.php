<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

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
        if ($this->record->from_model_type === 'App\Models\SaleOrder') {
            $data['selected_customer'] = $this->record->fromModel->customer_id ?? null;
            $data['selected_sale_order'] = $this->record->from_model_id ?? null;
            $data['selected_delivery_orders'] = $this->record->delivery_orders ?? [];
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove temporary fields
        unset($data['selected_customer']);
        unset($data['selected_sale_order']);
        unset($data['selected_delivery_orders']);
        
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
