<?php

namespace App\Filament\Resources\SalesInvoiceResource\Pages;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\DeliveryOrder;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
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

        // Load invoice items
        $this->record->load('invoiceItem.product');
        $data['invoiceItem'] = $this->record->invoiceItem->toArray();

        // Set delivery_order_items from invoice items and delivery orders
        if (isset($data['delivery_orders']) && is_array($data['delivery_orders']) && isset($data['invoiceItem'])) {
            $deliveryOrders = DeliveryOrder::with('deliveryOrderItem.product', 'deliveryOrderItem.saleOrderItem')
                ->whereIn('id', $data['delivery_orders'])
                ->get();
            
            $deliveryOrderItems = [];
            foreach ($deliveryOrders as $do) {
                foreach ($do->deliveryOrderItem as $item) {
                    if ($item->product && $item->saleOrderItem) {
                        $originalPrice = $item->saleOrderItem->unit_price - $item->saleOrderItem->discount + $item->saleOrderItem->tax;
                        
                        // Find matching invoice item
                        $invoiceItem = collect($data['invoiceItem'])->first(function ($invItem) use ($item) {
                            return $invItem['product_id'] == $item->product_id;
                        });
                        
                        $deliveryOrderItems[] = [
                            'do_number' => $do->do_number,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name . ' (' . $item->product->sku . ')',
                            'original_quantity' => $item->quantity,
                            'invoice_quantity' => $invoiceItem['quantity'] ?? $item->quantity,
                            'original_price' => $originalPrice,
                            'unit_price' => $invoiceItem['price'] ?? $originalPrice,
                            'total_price' => ((float) ($invoiceItem['quantity'] ?? $item->quantity)) * ((float) ($invoiceItem['price'] ?? $originalPrice)),
                            'coa_id' => $invoiceItem['coa_id'] ?? $item->product->sales_coa_id,
                        ];
                    }
                }
            }
            
            if (!empty($deliveryOrderItems)) {
                $data['delivery_order_items'] = $deliveryOrderItems;
            }
        }
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove temporary fields
        unset($data['selected_customer']);
        unset($data['selected_sale_order']);
        unset($data['selected_delivery_orders']);
        unset($data['delivery_order_items']);
        
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
