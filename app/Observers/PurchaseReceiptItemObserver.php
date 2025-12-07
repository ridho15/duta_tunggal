<?php

namespace App\Observers;

use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\PurchaseReturnService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PurchaseReceiptItemObserver
{
    protected $purchaseReturnService;

    public function __construct(PurchaseReturnService $purchaseReturnService)
    {
        $this->purchaseReturnService = $purchaseReturnService;
    }

    public function created(PurchaseReceiptItem $receiptItem)
    {
        Log::info("PurchaseReceiptItemObserver: created() called for item ID {$receiptItem->id}, qty_rejected: {$receiptItem->qty_rejected}");

        // Debug: Check qty_rejected type and value
        Log::info("PurchaseReceiptItemObserver: qty_rejected type: " . gettype($receiptItem->qty_rejected) . ", value: '{$receiptItem->qty_rejected}'");

        // Auto-create PurchaseReturn for rejected items during receiving
        // Cast to integer for proper comparison since qty_rejected might be stored as string
        if ((int) $receiptItem->qty_rejected > 0) {
            Log::info("PurchaseReceiptItemObserver: qty_rejected > 0 condition met, calling createPurchaseReturnForRejectedItem");
            $this->createPurchaseReturnForRejectedItem($receiptItem);
        } else {
            Log::info("PurchaseReceiptItemObserver: qty_rejected <= 0 condition NOT met, skipping PurchaseReturn creation");
        }
    }

    protected function createPurchaseReturnForRejectedItem(PurchaseReceiptItem $receiptItem)
    {
        Log::info("PurchaseReceiptItemObserver: createPurchaseReturnForRejectedItem() STARTED for item {$receiptItem->id}");

        try {
            Log::info("PurchaseReceiptItemObserver: About to check existing return for receipt {$receiptItem->purchase_receipt_id}");

            // Check if PurchaseReturn already exists for this receipt
            $existingReturn = PurchaseReturn::where('purchase_receipt_id', $receiptItem->purchase_receipt_id)->first();
            Log::info("PurchaseReceiptItemObserver: Existing return check result: " . ($existingReturn ? "Found ID {$existingReturn->id}" : "Not found"));

            if ($existingReturn) {
                Log::info("PurchaseReceiptItemObserver: PurchaseReturn already exists for receipt {$receiptItem->purchase_receipt_id}, adding item to existing return");

                // Add item to existing return
                PurchaseReturnItem::create([
                    'purchase_return_id' => $existingReturn->id,
                    'purchase_receipt_item_id' => $receiptItem->id,
                    'product_id' => $receiptItem->product_id,
                    'qty_returned' => $receiptItem->qty_rejected,
                    'unit_price' => $receiptItem->purchaseOrderItem->unit_price ?? 0,
                    'reason' => 'Rejected during receiving: ' . ($receiptItem->reason_rejected ?? 'Quality issues')
                ]);

                Log::info("PurchaseReceiptItemObserver: Added rejected item to existing PurchaseReturn {$existingReturn->nota_retur}");
            } else {
                Log::info("PurchaseReceiptItemObserver: Creating new PurchaseReturn for receipt {$receiptItem->purchase_receipt_id}");

                // Create PurchaseReturn header
                $purchaseReturn = PurchaseReturn::create([
                    'purchase_receipt_id' => $receiptItem->purchase_receipt_id,
                    'return_date' => now(),
                    'nota_retur' => $this->purchaseReturnService->generateNotaRetur(),
                    'created_by' => Auth::id() ?? 1, // System user if no auth
                    'notes' => 'Auto-generated return for items rejected during receiving',
                    'status' => 'draft',
                    'cabang_id' => $receiptItem->purchaseReceipt->cabang_id ?? Auth::user()->cabang_id ?? 1
                ]);

                Log::info("PurchaseReceiptItemObserver: PurchaseReturn created with ID {$purchaseReturn->id}, nota_retur {$purchaseReturn->nota_retur}");

                // Create PurchaseReturnItem
                PurchaseReturnItem::create([
                    'purchase_return_id' => $purchaseReturn->id,
                    'purchase_receipt_item_id' => $receiptItem->id,
                    'product_id' => $receiptItem->product_id,
                    'qty_returned' => $receiptItem->qty_rejected,
                    'unit_price' => $receiptItem->purchaseOrderItem->unit_price ?? 0,
                    'reason' => 'Rejected during receiving: ' . ($receiptItem->reason_rejected ?? 'Quality issues')
                ]);

                Log::info("PurchaseReceiptItemObserver: Auto-created PurchaseReturn {$purchaseReturn->nota_retur} for PurchaseReceipt {$receiptItem->purchase_receipt_id}");
            }

        } catch (\Illuminate\Database\QueryException $e) {
            Log::error("PurchaseReceiptItemObserver: Database error for PurchaseReceiptItem {$receiptItem->id}: " . $e->getMessage());
            Log::error("PurchaseReceiptItemObserver: SQL: " . $e->getSql());
            Log::error("PurchaseReceiptItemObserver: Bindings: " . json_encode($e->getBindings()));
        } catch (\Exception $e) {
            Log::error("PurchaseReceiptItemObserver: Failed to auto-create PurchaseReturn for PurchaseReceiptItem {$receiptItem->id}: " . $e->getMessage());
            Log::error("PurchaseReceiptItemObserver: Stack trace: " . $e->getTraceAsString());
        }
    }

    public function updated(PurchaseReceiptItem $receiptItem)
    {
        Log::info("PurchaseReceiptItemObserver: updated() called for item ID {$receiptItem->id}");

        // Check if qty_rejected was changed
        if ($receiptItem->wasChanged('qty_rejected')) {
            $oldQtyRejected = $receiptItem->getOriginal('qty_rejected');
            $newQtyRejected = $receiptItem->qty_rejected;

            Log::info("PurchaseReceiptItemObserver: qty_rejected changed from {$oldQtyRejected} to {$newQtyRejected}");

            $this->syncPurchaseReturnForRejectedItem($receiptItem, $oldQtyRejected, $newQtyRejected);
        }
    }

    public function deleted(PurchaseReceiptItem $receiptItem)
    {
        Log::info("PurchaseReceiptItemObserver: deleted() called for item ID {$receiptItem->id}");

        try {
            // Find and delete related PurchaseReturnItems
            $purchaseReturnItems = \App\Models\PurchaseReturnItem::where('purchase_receipt_item_id', $receiptItem->id)->get();

            foreach ($purchaseReturnItems as $returnItem) {
                Log::info("PurchaseReceiptItemObserver: Deleting PurchaseReturnItem ID {$returnItem->id} for deleted PurchaseReceiptItem {$receiptItem->id}");

                // Check if this is the only item in the PurchaseReturn
                $purchaseReturn = $returnItem->purchaseReturn;
                $itemCount = $purchaseReturn->purchaseReturnItem()->count();

                // Delete the PurchaseReturnItem
                $returnItem->delete();

                // If this was the only item, delete the entire PurchaseReturn
                if ($itemCount <= 1) {
                    Log::info("PurchaseReceiptItemObserver: Deleting PurchaseReturn ID {$purchaseReturn->id} as it has no more items");
                    $purchaseReturn->delete();
                }
            }

        } catch (\Exception $e) {
            Log::error("PurchaseReceiptItemObserver: Failed to delete related PurchaseReturn for deleted PurchaseReceiptItem {$receiptItem->id}: " . $e->getMessage());
        }
    }

    protected function syncPurchaseReturnForRejectedItem(PurchaseReceiptItem $receiptItem, $oldQtyRejected, $newQtyRejected)
    {
        Log::info("PurchaseReceiptItemObserver: syncPurchaseReturnForRejectedItem() STARTED for item {$receiptItem->id}");

        try {
            // Find existing PurchaseReturnItem for this receipt item
            $existingReturnItem = \App\Models\PurchaseReturnItem::where('purchase_receipt_item_id', $receiptItem->id)->first();

            if ($newQtyRejected > 0) {
                // Need to create or update PurchaseReturnItem
                if ($existingReturnItem) {
                    // Update existing item
                    Log::info("PurchaseReceiptItemObserver: Updating existing PurchaseReturnItem ID {$existingReturnItem->id}");
                    $existingReturnItem->update([
                        'qty_returned' => $newQtyRejected,
                        'unit_price' => $receiptItem->purchaseOrderItem->unit_price ?? $existingReturnItem->unit_price,
                        'reason' => 'Rejected during receiving: ' . ($receiptItem->reason_rejected ?? 'Quality issues - Updated')
                    ]);
                } else {
                    // Create new PurchaseReturnItem
                    Log::info("PurchaseReceiptItemObserver: Creating new PurchaseReturnItem for receipt item {$receiptItem->id}");

                    // Check if PurchaseReturn already exists for this receipt
                    $existingReturn = \App\Models\PurchaseReturn::where('purchase_receipt_id', $receiptItem->purchase_receipt_id)->first();

                    if ($existingReturn) {
                        // Add to existing return
                        \App\Models\PurchaseReturnItem::create([
                            'purchase_return_id' => $existingReturn->id,
                            'purchase_receipt_item_id' => $receiptItem->id,
                            'product_id' => $receiptItem->product_id,
                            'qty_returned' => $newQtyRejected,
                            'unit_price' => $receiptItem->purchaseOrderItem->unit_price ?? 0,
                            'reason' => 'Rejected during receiving: ' . ($receiptItem->reason_rejected ?? 'Quality issues - Updated')
                        ]);
                    } else {
                        // Create new PurchaseReturn
                        $purchaseReturn = \App\Models\PurchaseReturn::create([
                            'purchase_receipt_id' => $receiptItem->purchase_receipt_id,
                            'return_date' => now(),
                            'nota_retur' => $this->purchaseReturnService->generateNotaRetur(),
                            'created_by' => \Illuminate\Support\Facades\Auth::id() ?? 1,
                            'notes' => 'Auto-generated return for updated rejected items',
                            'status' => 'draft',
                            'cabang_id' => $receiptItem->purchaseReceipt->cabang_id ?? \Illuminate\Support\Facades\Auth::user()->cabang_id ?? 1
                        ]);

                        \App\Models\PurchaseReturnItem::create([
                            'purchase_return_id' => $purchaseReturn->id,
                            'purchase_receipt_item_id' => $receiptItem->id,
                            'product_id' => $receiptItem->product_id,
                            'qty_returned' => $newQtyRejected,
                            'unit_price' => $receiptItem->purchaseOrderItem->unit_price ?? 0,
                            'reason' => 'Rejected during receiving: ' . ($receiptItem->reason_rejected ?? 'Quality issues - Updated')
                        ]);
                    }
                }
            } elseif ($existingReturnItem && $newQtyRejected == 0) {
                // qty_rejected changed to 0, delete the PurchaseReturnItem
                Log::info("PurchaseReceiptItemObserver: Deleting PurchaseReturnItem ID {$existingReturnItem->id} as qty_rejected is now 0");

                $purchaseReturn = $existingReturnItem->purchaseReturn;
                $itemCount = $purchaseReturn->purchaseReturnItem()->count();

                // Delete the PurchaseReturnItem
                $existingReturnItem->delete();

                // If this was the only item, delete the entire PurchaseReturn
                if ($itemCount <= 1) {
                    Log::info("PurchaseReceiptItemObserver: Deleting PurchaseReturn ID {$purchaseReturn->id} as it has no more items");
                    $purchaseReturn->delete();
                }
            }

        } catch (\Exception $e) {
            Log::error("PurchaseReceiptItemObserver: Failed to sync PurchaseReturn for updated PurchaseReceiptItem {$receiptItem->id}: " . $e->getMessage());
            Log::error("PurchaseReceiptItemObserver: Stack trace: " . $e->getTraceAsString());
        }
    }
}