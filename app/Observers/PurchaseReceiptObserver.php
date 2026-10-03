<?php

namespace App\Observers;

use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\PurchaseReceiptService;
use App\Services\PurchaseReturnService;
use App\Support\ProcurementFailureNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PurchaseReceiptObserver
{
    protected $purchaseReceiptService;
    protected $purchaseReturnService;

    public function __construct(PurchaseReceiptService $purchaseReceiptService, PurchaseReturnService $purchaseReturnService)
    {
        $this->purchaseReceiptService = $purchaseReceiptService;
        $this->purchaseReturnService = $purchaseReturnService;
    }

    public function created(PurchaseReceipt $purchaseReceipt)
    {
        Log::info("PurchaseReceiptObserver: created() called for receipt ID {$purchaseReceipt->id}");
        // Auto-create PurchaseReturn for rejected items during receiving
        $this->createPurchaseReturnForRejectedItems($purchaseReceipt);
    }

    public function updated(PurchaseReceipt $purchaseReceipt)
    {
        // Check if all receipts for this purchase order are completed
        $this->checkAndUpdatePurchaseOrderStatus($purchaseReceipt);
    }

    public function deleted(PurchaseReceipt $purchaseReceipt)
    {
        // Sync PO status when a receipt is cancelled/deleted
        $this->checkAndUpdatePurchaseOrderStatus($purchaseReceipt);
    }

    public function restored(PurchaseReceipt $purchaseReceipt)
    {
        // Sync PO status when a receipt is restored
        $this->checkAndUpdatePurchaseOrderStatus($purchaseReceipt);
    }

    protected function createPurchaseReturnForRejectedItems(PurchaseReceipt $purchaseReceipt)
    {
        Log::info("PurchaseReceiptObserver: Checking for rejected items in receipt ID {$purchaseReceipt->id}");

        // Load receipt items with rejected quantities
        $purchaseReceipt->load('purchaseReceiptItem');

        Log::info("PurchaseReceiptObserver: Loaded " . $purchaseReceipt->purchaseReceiptItem->count() . " receipt items");

        $rejectedItems = $purchaseReceipt->purchaseReceiptItem->filter(function ($item) {
            $isRejected = $item->qty_rejected > 0;
            Log::info("PurchaseReceiptObserver: Item ID {$item->id} - qty_rejected: {$item->qty_rejected}, isRejected: " . ($isRejected ? 'true' : 'false'));
            return $isRejected;
        });

        Log::info("PurchaseReceiptObserver: Found " . $rejectedItems->count() . " rejected items");

        if ($rejectedItems->isEmpty()) {
            Log::info("PurchaseReceiptObserver: No rejected items found, skipping PurchaseReturn creation");
            return; // No rejected items, no need to create PurchaseReturn
        }

        try {
            // Create PurchaseReturn header
            $purchaseReturn = PurchaseReturn::create([
                'purchase_receipt_id' => $purchaseReceipt->id,
                'return_date' => now(),
                'nota_retur' => $this->purchaseReturnService->generateNotaRetur(),
                'created_by' => Auth::id() ?? 1, // System user if no auth
                'notes' => 'Auto-generated return for items rejected during receiving',
                'status' => 'draft'
            ]);

            // Create PurchaseReturnItem for each rejected item
            foreach ($rejectedItems as $receiptItem) {
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'purchase_receipt_item_id' => $receiptItem->id,
                    'product_id' => $receiptItem->product_id,
                    'qty_returned' => $receiptItem->qty_rejected,
                    'unit_price' => $receiptItem->purchaseOrderItem->unit_price ?? 0,
                    'reason' => 'Rejected during receiving: ' . ($receiptItem->reason_rejected ?? 'Quality issues')
                ]);
            }

            Log::info("Auto-created PurchaseReturn {$purchaseReturn->nota_retur} for PurchaseReceipt {$purchaseReceipt->receipt_number} with {$rejectedItems->count()} rejected items");

        } catch (\Exception $e) {
            Log::error("Failed to auto-create PurchaseReturn for PurchaseReceipt {$purchaseReceipt->id}: " . $e->getMessage());

            ProcurementFailureNotifier::warning(
                'Retur Otomatis Belum Berhasil Dibuat',
                $e,
                'Penerimaan barang sudah tersimpan, tetapi retur otomatis untuk item yang ditolak belum berhasil dibuat. Silakan periksa data retur pembelian.'
            );
        }
    }

    protected function checkAndUpdatePurchaseOrderStatus(PurchaseReceipt $purchaseReceipt)
    {
        $purchaseOrder = $purchaseReceipt->purchaseOrder;

        if (! $purchaseOrder || ! $purchaseOrder->exists) {
            if ($purchaseReceipt->purchase_order_id) {
                $purchaseOrder = \App\Models\PurchaseOrder::find($purchaseReceipt->purchase_order_id);
            }
        }

        if (! $purchaseOrder) {
            return;
        }

        $purchaseOrder->syncReceiptFulfillmentStatus(Auth::id());
        Log::info("PurchaseReceiptObserver: Synced PO {$purchaseOrder->id} status to {$purchaseOrder->status}");
    }
}