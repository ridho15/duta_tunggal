<?php

namespace App\Observers;

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\OrderRequestItem;

class PurchaseOrderItemObserver
{
    /**
     * Handle the PurchaseOrderItem "created" event.
     */
    public function created(PurchaseOrderItem $purchaseOrderItem): void
    {
        // Track fulfilled quantity if this PO item refers to an OrderRequestItem
        $referType = $purchaseOrderItem->refer_item_model_type;
        
        if (($referType === 'App\\Models\\OrderRequestItem' || $referType === OrderRequestItem::class) 
            && $purchaseOrderItem->refer_item_model_id) {
            $orderRequestItem = OrderRequestItem::find($purchaseOrderItem->refer_item_model_id);
            if ($orderRequestItem) {
                $orderRequestItem->addFulfilledQuantity($purchaseOrderItem->quantity);
            }
        }
    }

    /**
     * Handle the PurchaseOrderItem "saved" event.
     */
    public function saved(PurchaseOrderItem $purchaseOrderItem): void
    {
        // When a PO item is saved, sync the parent PO's journal entries
        $purchaseOrder = $purchaseOrderItem->purchaseOrder;
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
        // Reduce fulfilled quantity if this PO item refers to an OrderRequestItem
        $referType = $purchaseOrderItem->refer_item_model_type;
        if (($referType === 'App\\Models\\OrderRequestItem' || $referType === OrderRequestItem::class) 
            && $purchaseOrderItem->refer_item_model_id) {
            $orderRequestItem = OrderRequestItem::find($purchaseOrderItem->refer_item_model_id);
            if ($orderRequestItem) {
                $orderRequestItem->reduceFulfilledQuantity($purchaseOrderItem->quantity);
            }
        }
        
        // When a PO item is deleted, sync the parent PO's journal entries
        $purchaseOrder = $purchaseOrderItem->purchaseOrder;
        if ($purchaseOrder) {
            $observer = new PurchaseOrderObserver(app(\App\Services\PurchaseOrderService::class));
            $observer->syncJournalEntriesPublic($purchaseOrder);
        }
    }
}