<?php

namespace App\Observers;

use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrder;

class PurchaseOrderItemObserver
{
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
        // When a PO item is deleted, sync the parent PO's journal entries
        $purchaseOrder = $purchaseOrderItem->purchaseOrder;
        if ($purchaseOrder) {
            $observer = new PurchaseOrderObserver(app(\App\Services\PurchaseOrderService::class));
            $observer->syncJournalEntriesPublic($purchaseOrder);
        }
    }
}