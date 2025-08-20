<?php

namespace App\Services;

use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\QualityControl;
use App\Models\InventoryStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseReturnAutomationService
{
    protected PurchaseReturnService $purchaseReturnService;

    public function __construct(PurchaseReturnService $purchaseReturnService)
    {
        $this->purchaseReturnService = $purchaseReturnService;
    }

    /**
     * Automatically create purchase returns based on quality control results
     */
    public function automatePurchaseReturns(bool $isDryRun = false): array
    {
        $processed = 0;
        $created = 0;
        $errors = [];

        try {
            // Get quality control items that have rejected quantities and haven't been processed for returns
            $failedQualityControls = QualityControl::where('rejected_quantity', '>', 0)
                ->whereNull('purchase_return_processed')
                ->with(['product', 'fromModel'])
                ->get();

            foreach ($failedQualityControls as $qc) {
                $processed++;

                try {
                    if (!$isDryRun) {
                        $this->processPurchaseReturn($qc);
                        $created++;
                        
                        // Mark as processed
                        $qc->update(['purchase_return_processed' => now()]);
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to process QC ID {$qc->id}: " . $e->getMessage();
                    Log::error("Purchase return automation error for QC {$qc->id}", [
                        'error' => $e->getMessage(),
                        'qc' => $qc->toArray()
                    ]);
                }
            }

        } catch (\Exception $e) {
            $errors[] = "General automation error: " . $e->getMessage();
            Log::error("Purchase return automation general error", ['error' => $e->getMessage()]);
        }

        return [
            'processed' => $processed,
            'created' => $created,
            'errors' => $errors
        ];
    }

    /**
     * Process a single purchase return based on quality control
     */
    protected function processPurchaseReturn(QualityControl $qc): void
    {
        DB::transaction(function () use ($qc) {
            // Quality control should be linked to purchase receipt item through fromModel
            if (!$qc->fromModel || $qc->from_model_type !== 'App\Models\PurchaseReceiptItem') {
                throw new \Exception("Quality control {$qc->id} is not linked to a purchase receipt item");
            }

            $purchaseReceiptItem = $qc->fromModel;
            
            // Check if purchase return already exists for this receipt
            $existingReturn = PurchaseReturn::where('purchase_receipt_id', $purchaseReceiptItem->purchase_receipt_id)
                ->whereHas('purchaseReturnItem', function ($query) use ($purchaseReceiptItem) {
                    $query->where('purchase_receipt_item_id', $purchaseReceiptItem->id);
                })
                ->first();

            if ($existingReturn) {
                // Add item to existing return
                $this->addItemToExistingReturn($existingReturn, $qc, $purchaseReceiptItem);
            } else {
                // Create new purchase return
                $this->createNewPurchaseReturn($qc, $purchaseReceiptItem);
            }

            // Update inventory stock
            $this->updateInventoryStock($qc);
        });
    }

    /**
     * Create a new purchase return
     */
    protected function createNewPurchaseReturn(QualityControl $qc, $purchaseReceiptItem): PurchaseReturn
    {
        $purchaseReturn = PurchaseReturn::create([
            'purchase_receipt_id' => $purchaseReceiptItem->purchase_receipt_id,
            'return_date' => now(),
            'nota_retur' => $this->purchaseReturnService->generateNotaRetur(),
            'created_by' => 1, // System user
            'notes' => 'Auto-generated return based on quality control rejection: ' . ($qc->qc_number ?? 'QC-' . $qc->id)
        ]);

        $this->createReturnItem($purchaseReturn, $qc, $purchaseReceiptItem);

        return $purchaseReturn;
    }

    /**
     * Add item to existing purchase return
     */
    protected function addItemToExistingReturn(PurchaseReturn $purchaseReturn, QualityControl $qc, $purchaseReceiptItem): void
    {
        // Check if item already exists in this return
        $existingItem = $purchaseReturn->purchaseReturnItem()
            ->where('purchase_receipt_item_id', $purchaseReceiptItem->id)
            ->first();

        if ($existingItem) {
            // Update quantity
            $existingItem->increment('qty_returned', $qc->rejected_quantity);
            $existingItem->update([
                'reason' => $existingItem->reason . '; Additional QC rejection: ' . ($qc->reason_reject ?? 'Quality control failed')
            ]);
        } else {
            $this->createReturnItem($purchaseReturn, $qc, $purchaseReceiptItem);
        }
    }

    /**
     * Create return item
     */
    protected function createReturnItem(PurchaseReturn $purchaseReturn, QualityControl $qc, $purchaseReceiptItem): PurchaseReturnItem
    {
        return PurchaseReturnItem::create([
            'purchase_return_id' => $purchaseReturn->id,
            'purchase_receipt_item_id' => $purchaseReceiptItem->id,
            'product_id' => $qc->product_id,
            'qty_returned' => $qc->rejected_quantity,
            'unit_price' => $purchaseReceiptItem->purchaseOrderItem->unit_price ?? 0,
            'reason' => 'Quality Control Rejection: ' . ($qc->reason_reject ?? 'Quality control failed') . ' (QC: ' . ($qc->qc_number ?? 'QC-' . $qc->id) . ')'
        ]);
    }

    /**
     * Update inventory stock after return
     */
    protected function updateInventoryStock(QualityControl $qc): void
    {
        // Find inventory stock for this product
        $inventoryStock = InventoryStock::where('product_id', $qc->product_id)
            ->where('warehouse_id', $qc->warehouse_id)
            ->first();

        if ($inventoryStock) {
            // Decrease available quantity
            $inventoryStock->decrement('qty_available', $qc->rejected_quantity);
            
            // Increase reserved quantity for return processing
            $inventoryStock->increment('qty_reserved', $qc->rejected_quantity);
        }
    }

    /**
     * Manual trigger for specific quality control
     */
    public function triggerReturnForQualityControl(int $qualityControlId): array
    {
        try {
            $qc = QualityControl::with(['product', 'fromModel'])
                ->findOrFail($qualityControlId);

            if ($qc->rejected_quantity <= 0) {
                throw new \Exception('Quality control must have rejected quantity to trigger return');
            }

            if ($qc->purchase_return_processed) {
                throw new \Exception('This quality control has already been processed for return');
            }

            $this->processPurchaseReturn($qc);
            $qc->update(['purchase_return_processed' => now()]);

            return ['success' => true, 'message' => 'Purchase return created successfully'];

        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get automation statistics
     */
    public function getAutomationStats(): array
    {
        return [
            'pending_qc_rejects' => QualityControl::where('rejected_quantity', '>', 0)
                ->whereNull('purchase_return_processed')
                ->count(),
            'processed_this_month' => QualityControl::where('rejected_quantity', '>', 0)
                ->whereNotNull('purchase_return_processed')
                ->whereMonth('purchase_return_processed', now()->month)
                ->count(),
            'total_automated_returns' => PurchaseReturn::where('created_by', 1)
                ->whereMonth('created_at', now()->month)
                ->count()
        ];
    }
}
