<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Remove temporary fields
        unset($data['selected_customer']);
        unset($data['selected_sale_order']);
        unset($data['selected_delivery_orders']);
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Create invoice items
        if (isset($this->data['invoiceItem']) && is_array($this->data['invoiceItem'])) {
            foreach ($this->data['invoiceItem'] as $item) {
                // Calculate subtotal if not provided
                $quantity = (float) ($item['quantity'] ?? 0);
                $price = (float) ($item['price'] ?? 0);
                $subtotal = $quantity * $price;
                
                $itemData = array_merge($item, [
                    'subtotal' => $subtotal,
                    'discount' => 0,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                ]);
                
                $this->record->invoiceItem()->create($itemData);
            }
        }

        // Post journal entries for sales invoice
        if ($this->record->from_model_type === 'App\Models\SaleOrder') {
            $invoiceObserver = new \App\Observers\InvoiceObserver();
            $invoiceObserver->postSalesInvoice($this->record);
        }
    }
}
