<?php

namespace App\Observers;

use App\Models\DeliveryOrderItem;
use Illuminate\Support\Facades\Log;

class DeliveryOrderItemObserver
{
    /**
     * Handle the DeliveryOrderItem "updated" event.
     */
    public function updated(DeliveryOrderItem $deliveryOrderItem): void
    {
        // Check if quantity has changed and delivery order status is 'sent'
        $originalQuantity = $deliveryOrderItem->getOriginal('quantity');
        $currentQuantity = $deliveryOrderItem->quantity;

        \Illuminate\Support\Facades\Log::info('DeliveryOrderItemObserver: updated called', [
            'item_id' => $deliveryOrderItem->id,
            'original_quantity' => $originalQuantity,
            'current_quantity' => $currentQuantity,
            'delivery_order_status' => $deliveryOrderItem->deliveryOrder?->status,
        ]);

        if ($originalQuantity != $currentQuantity && $deliveryOrderItem->deliveryOrder?->status === 'sent') {
            \Illuminate\Support\Facades\Log::info('DeliveryOrderItemObserver: Quantity changed for sent delivery order', [
                'delivery_order_item_id' => $deliveryOrderItem->id,
                'delivery_order_id' => $deliveryOrderItem->delivery_order_id,
                'original_quantity' => $originalQuantity,
                'current_quantity' => $currentQuantity,
            ]);

            // Use DeliveryOrderObserver to handle journal entry updates
            $deliveryOrderObserver = app(\App\Observers\DeliveryOrderObserver::class);
            $deliveryOrderObserver->handleQuantityUpdateAfterSent($deliveryOrderItem->deliveryOrder);
        }
    }
}