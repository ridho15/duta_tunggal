<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\SaleOrderItem;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryOrder extends CreateRecord
{
    protected static string $resource = DeliveryOrderResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Additional validation before creating
        $this->validateDeliveryOrderItems($data);
        return $data;
    }
    
    protected function validateDeliveryOrderItems(array $data): void
    {
        if (empty($data['sales_order_id']) || empty($data['deliveryOrderItem'])) {
            return;
        }

        $deliveryItems = $data['deliveryOrderItem'];
        
        // Validasi setiap delivery item
        foreach ($deliveryItems as $deliveryItem) {
            if (!empty($deliveryItem['sale_order_item_id']) && !empty($deliveryItem['quantity'])) {
                $saleOrderItem = SaleOrderItem::find($deliveryItem['sale_order_item_id']);
                
                if ($saleOrderItem) {
                    // Validasi 1: Quantity delivery item tidak boleh lebih besar dari quantity sale order item asli
                    if ($deliveryItem['quantity'] > $saleOrderItem->quantity) {
                        $productName = $saleOrderItem->product->name ?? "Unknown Product";
                        throw new \Exception("Quantity untuk item '$productName' ({$deliveryItem['quantity']}) tidak boleh lebih besar dari quantity sale order item ({$saleOrderItem->quantity}).");
                    }
                    
                    // Validasi 2: Quantity delivery item tidak boleh melebihi remaining quantity
                    if ($deliveryItem['quantity'] > $saleOrderItem->remaining_quantity) {
                        $productName = $saleOrderItem->product->name ?? "Unknown Product";
                        throw new \Exception("Quantity untuk item '$productName' ({$deliveryItem['quantity']}) melebihi sisa quantity yang tersedia ({$saleOrderItem->remaining_quantity}).");
                    }
                }
            }
        }
        
        // Validasi tidak ada duplicate sale order item
        $saleOrderItemIds = collect($deliveryItems)->pluck('sale_order_item_id')->filter();
        $duplicates = $saleOrderItemIds->duplicates();
        
        if ($duplicates->isNotEmpty()) {
            throw new \Exception("Tidak boleh ada duplicate sale order item dalam satu delivery order.");
        }
    }
}
