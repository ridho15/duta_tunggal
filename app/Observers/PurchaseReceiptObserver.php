<?php

namespace App\Observers;

use App\Models\PurchaseReceipt;
use App\Services\PurchaseReceiptService;

class PurchaseReceiptObserver
{
    protected $purchaseReceiptService;

    public function __construct(PurchaseReceiptService $purchaseReceiptService)
    {
        $this->purchaseReceiptService = $purchaseReceiptService;
    }

    public function updated(PurchaseReceipt $purchaseReceipt)
    {
        // Check if all receipts for this purchase order are completed
        $this->checkAndUpdatePurchaseOrderStatus($purchaseReceipt);
    }

    protected function checkAndUpdatePurchaseOrderStatus(PurchaseReceipt $purchaseReceipt)
    {
        $purchaseOrder = $purchaseReceipt->purchaseOrder;

        if (!$purchaseOrder) {
            return;
        }

        $receipts = $purchaseOrder->purchaseReceipt;

        // Check statuses
        $hasDraft = $receipts->contains('status', 'draft');
        $hasPartial = $receipts->contains('status', 'partial');
        $allCompleted = $receipts->every(function ($receipt) {
            return $receipt->status === 'completed';
        });

        $newStatus = 'approved'; // default

        if ($allCompleted) {
            $newStatus = 'completed';
        } elseif ($hasPartial) {
            $newStatus = 'partially_received';
        } elseif ($hasDraft) {
            $newStatus = 'approved';
        }

        if ($purchaseOrder->status !== $newStatus) {
            $purchaseOrder->update(['status' => $newStatus]);
        }
    }
}