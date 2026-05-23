<?php

namespace App\Observers;

use App\Models\OrderRequest;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrder;
use App\Models\OrderRequestItem;
use App\Support\OrderRequestQuantityLock;

class PurchaseOrderItemObserver
{
    public function creating(PurchaseOrderItem $purchaseOrderItem): void
    {
        if (! empty($purchaseOrderItem->refer_item_model_type) && ! empty($purchaseOrderItem->refer_item_model_id)) {
            OrderRequestQuantityLock::validatePurchaseOrderItem($purchaseOrderItem);
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
            ->when($purchaseOrder->supplier_id, function ($query) use ($purchaseOrder) {
                $query->where(function ($supplierQuery) use ($purchaseOrder) {
                    $supplierQuery->where('supplier_id', $purchaseOrder->supplier_id)
                        ->orWhereNull('supplier_id');
                });
            })
            ->when($purchaseOrder->cabang_id, function ($query) use ($purchaseOrder) {
                $query->where(function ($branchQuery) use ($purchaseOrder) {
                    $branchQuery->where('cabang_id', $purchaseOrder->cabang_id)
                        ->orWhereNull('cabang_id');
                });
            })
            ->orderBy('id')
            ->get()
            ->first(fn (OrderRequestItem $item) => OrderRequestQuantityLock::orderRequestItemLimit((int) $item->id)['remaining_for_po'] > 0);

        if (! $matchedItem) {
            throw new \InvalidArgumentException('Item Purchase Order tidak memiliki sisa quantity Order Request yang tersedia.');
        }

        $purchaseOrderItem->refer_item_model_type = OrderRequestItem::class;
        $purchaseOrderItem->refer_item_model_id = $matchedItem->id;

        OrderRequestQuantityLock::validatePurchaseOrderItem($purchaseOrderItem);
    }

    public function saving(PurchaseOrderItem $purchaseOrderItem): void
    {
        OrderRequestQuantityLock::validatePurchaseOrderItem($purchaseOrderItem);
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
