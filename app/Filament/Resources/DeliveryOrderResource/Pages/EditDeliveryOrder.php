<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\SaleOrderItem;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryOrder extends EditRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $data;
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Additional validation before updating
        $this->validateDeliveryOrderItems($data);
        return $data;
    }
    
    protected function validateDeliveryOrderItems(array $data): void
    {
        if (empty($data['sales_order_id']) || empty($data['deliveryOrderItem'])) {
            return;
        }

        $deliveryItems = $data['deliveryOrderItem'];
        $currentDeliveryOrderId = $this->record->id;
        
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
                    
                    // Hitung quantity yang sudah delivered dari delivery order lain (exclude current DO)
                    $otherDeliveredQty = $saleOrderItem->deliveryOrderItems()
                        ->whereHas('deliveryOrder', function ($query) use ($currentDeliveryOrderId) {
                            $query->where('id', '!=', $currentDeliveryOrderId)
                                  ->whereNotIn('status', ['cancelled', 'rejected']);
                        })
                        ->sum('quantity');
                    
                    $availableQty = $saleOrderItem->quantity - $otherDeliveredQty;
                    
                    // Validasi 2: Quantity delivery item tidak boleh melebihi available quantity
                    if ($deliveryItem['quantity'] > $availableQty) {
                        $productName = $saleOrderItem->product->name ?? "Unknown Product";
                        throw new \Exception("Quantity untuk item '$productName' ({$deliveryItem['quantity']}) melebihi sisa quantity yang tersedia ($availableQty).");
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
