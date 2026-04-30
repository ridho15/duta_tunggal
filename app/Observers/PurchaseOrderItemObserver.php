<?php

namespace App\Observers;

use App\Models\OrderRequest;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\OrderRequestItem;

class PurchaseOrderItemObserver
{
    public function creating(PurchaseOrderItem $purchaseOrderItem): void
    {
        if (! empty($purchaseOrderItem->refer_item_model_type) && ! empty($purchaseOrderItem->refer_item_model_id)) {
            return;
        }

        $purchaseOrder = PurchaseOrder::find($purchaseOrderItem->purchase_order_id);
        if (! $purchaseOrder || $purchaseOrder->refer_model_type !== OrderRequest::class || ! $purchaseOrder->refer_model_id) {
            return;
        }

        $orderRequest = \App\Models\OrderRequest::find($purchaseOrder->refer_model_id);
        if (! $orderRequest || ! $orderRequest->exists) {
            return;
        }

        $matchedItem = $orderRequest->orderRequestItem()
            ->where('product_id', $purchaseOrderItem->product_id)
            ->whereRaw('quantity > COALESCE(fulfilled_quantity, 0)')
            ->orderBy('id')
            ->first();

        if (! $matchedItem) {
            return;
        }

        $purchaseOrderItem->refer_item_model_type = OrderRequestItem::class;
        $purchaseOrderItem->refer_item_model_id = $matchedItem->id;
    }

    /**
     * Handle the PurchaseOrderItem "created" event.
     */
    public function created(PurchaseOrderItem $purchaseOrderItem): void
    {
        // Track fulfilled quantity if this PO item refers to an OrderRequestItem
        $referType = $purchaseOrderItem->refer_item_model_type;
        
        if (($referType === 'App\\Models\\OrderRequestItem' || $referType === OrderRequestItem::class) 
            && $purchaseOrderItem->refer_item_model_id) {
            return;
        }
    }

    /**
     * Handle the PurchaseOrderItem "saved" event.
     */
    public function saved(PurchaseOrderItem $purchaseOrderItem): void
    {
        $purchaseOrder = $purchaseOrderItem->purchaseOrder;

        // When a PO item is saved, sync the parent PO's journal entries
        if ($purchaseOrder) {
            $observer = new PurchaseOrderObserver(app(\App\Services\PurchaseOrderService::class));
            $observer->syncJournalEntriesPublic($purchaseOrder);
        }
    }

    /**
     * Handle the PurchaseOrderItem "deleted" event.
     */
    public function deleted(PurchaseOrderItem $purchaseOrderItem): void
    {
        // When a PO item is deleted, sync the parent PO's journal entries
        $purchaseOrder = $purchaseOrderItem->purchaseOrder;
        if ($purchaseOrder) {
            $observer = new PurchaseOrderObserver(app(\App\Services\PurchaseOrderService::class));
            $observer->syncJournalEntriesPublic($purchaseOrder);
        }
    }
}