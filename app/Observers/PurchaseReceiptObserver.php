<?php

namespace App\Observers;

use App\Models\PurchaseReceipt;
use App\Services\PurchaseReceiptService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        // Load items with receipts
        $purchaseOrder->load(['purchaseOrderItem.purchaseReceiptItem']);

        $totalOrdered = $purchaseOrder->purchaseOrderItem->sum('quantity');
        $totalAccepted = 0;

        foreach ($purchaseOrder->purchaseOrderItem as $poItem) {
            $totalAccepted += $poItem->purchaseReceiptItem->sum('qty_accepted');
        }

        Log::info("Observer Check PO {$purchaseOrder->id}: Ordered={$totalOrdered}, Accepted={$totalAccepted}");

        $newStatus = 'approved'; // default

        if ($totalAccepted >= $totalOrdered) {
            $newStatus = 'completed';
        } elseif ($totalAccepted > 0) {
            $newStatus = 'partially_received';
        }

        Log::info("New Status: {$newStatus}, Current: {$purchaseOrder->status}");

        if ($purchaseOrder->status !== $newStatus) {
            $purchaseOrder->update([
                'status' => $newStatus,
                'completed_by' => $newStatus === 'completed' ? Auth::id() : null,
                'completed_at' => $newStatus === 'completed' ? now() : null,
            ]);
            Log::info("Updated PO {$purchaseOrder->id} to {$newStatus}");
        }
    }
}