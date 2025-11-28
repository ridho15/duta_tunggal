<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Services\PurchaseOrderService;

class PurchaseOrderObserver
{
    protected $purchaseOrderService;

    public function __construct(PurchaseOrderService $purchaseOrderService)
    {
        $this->purchaseOrderService = $purchaseOrderService;
    }

    /**
     * Handle the PurchaseOrder "saved" event.
     * Run the total calculation after the model is persisted to avoid
     * interfering with form validation and relation syncing (Filament nested
     * relationship data may not be available during the "saving" event).
     */
    public function saved(PurchaseOrder $purchaseOrder): void
    {
        // Skip if this is already being called from updateTotalAmount to prevent infinite loop
        if (\App\Services\PurchaseOrderService::isUpdatingTotalAmount()) {
            return;
        }

        // Update total amount after the purchase order has been saved
        $this->purchaseOrderService->updateTotalAmount($purchaseOrder);
    }

    /**
     * Handle the PurchaseOrder "created" event.
     */
    public function created(PurchaseOrder $purchaseOrder): void
    {
        // Update total amount when purchase order is first created
        $this->purchaseOrderService->updateTotalAmount($purchaseOrder);
    }

    /**
     * Handle the PurchaseOrder "updated" event.
     */
    public function updated(PurchaseOrder $purchaseOrder): void
    {
        // Update total amount when purchase order is updated
        $this->purchaseOrderService->updateTotalAmount($purchaseOrder);
    }
}