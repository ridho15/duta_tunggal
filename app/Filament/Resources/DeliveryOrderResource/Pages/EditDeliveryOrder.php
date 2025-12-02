<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Services\DeliveryOrderItemService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeliveryOrder extends EditRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    public function resolveRecord($key): \Illuminate\Database\Eloquent\Model
    {
        return DeliveryOrder::with([
            'salesOrders',
            'deliveryOrderItem.saleOrderItem.product',
            'deliveryOrderItem.product'
        ])->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure salesOrders field is populated with related sales order IDs
        if (!isset($data['salesOrders']) || empty($data['salesOrders'])) {
            $data['salesOrders'] = $this->record->salesOrders->pluck('id')->toArray();
        }
        
        // Populate selected_items based on existing delivery order items
        $selectedItems = [];
        $deliveryOrderItems = $this->record->deliveryOrderItem ?? [];
        
        foreach ($deliveryOrderItems as $item) {
            if ($item->sale_order_item_id) {
                $saleOrderItem = $item->saleOrderItem;
                if ($saleOrderItem) {
                    $selectedItems[] = [
                        'selected' => true,
                        'product_name' => "({$saleOrderItem->product->sku}) {$saleOrderItem->product->name}",
                        'remaining_qty' => $saleOrderItem->remaining_quantity + $item->quantity, // Add back the delivered quantity
                        'quantity' => $item->quantity,
                        'sale_order_item_id' => $item->sale_order_item_id,
                        'product_id' => $item->product_id,
                    ];
                }
            }
        }
        
        $data['selected_items'] = $selectedItems;
        
        return $data;
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Additional validation before updating
        app(DeliveryOrderItemService::class)->validateItemsForSalesOrder(
            (int) ($data['salesOrders'] ?? 0),
            $data['deliveryOrderItem'] ?? [],
            $this->record->id
        );

        return $data;
    }
}
