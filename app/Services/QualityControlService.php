<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\PurchaseOrder;
use App\Models\QualityControl;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\ChartOfAccount;
use App\Services\ReturnProductService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class QualityControlService
{
    protected static $coaCache = [];
    public function generateQcNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'QC-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = QualityControl::where('qc_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    public function generateQcManufactureNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'QC-M-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = QualityControl::where('qc_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    /**
     * Create a Quality Control record from a PurchaseReceiptItem.
     * This does not create receipts immediately; when QC is completed, a receipt
     * will be generated from the QC result.
     *
     * @param \App\Models\PurchaseReceiptItem $purchaseReceiptItem
     * @param array $data
     * @return \App\Models\QualityControl
     */
    public function createQCFromPurchaseReceiptItem($purchaseReceiptItem, $data)
    {
        // Validate passed_quantity doesn't exceed qty_accepted (or qty_received if qty_accepted is 0)
        $passedQuantity = $data['passed_quantity'] ?? $purchaseReceiptItem->qty_accepted;
        $maxAllowed = $purchaseReceiptItem->qty_accepted > 0 ? $purchaseReceiptItem->qty_accepted : $purchaseReceiptItem->qty_received;
        if ($passedQuantity > $maxAllowed) {
            throw new \Exception("QC passed quantity ({$passedQuantity}) cannot exceed accepted quantity ({$maxAllowed}) in purchase receipt.");
        }

        $qualityControl = QualityControl::create([
            'qc_number' => $this->generateQcNumber(),
            'passed_quantity' => $passedQuantity,
            'rejected_quantity' => $data['rejected_quantity'] ?? 0,
            'status' => 0,
            'inspected_by' => $data['inspected_by'] ?? null,
            'warehouse_id' => $purchaseReceiptItem->warehouse_id,
            'product_id' => $purchaseReceiptItem->product_id,
            'rak_id' => $purchaseReceiptItem->rak_id ?? null,
            'from_model_type' => \App\Models\PurchaseReceiptItem::class,
            'from_model_id' => $purchaseReceiptItem->id,
        ]);

        // Update the purchase receipt item to mark it as sent to QC
        $purchaseReceiptItem->update(['status' => 'completed']);

        return $qualityControl;
    }

    /**
     * Create a Quality Control record from a PurchaseOrderItem.
     * This creates QC directly from PO item without requiring a receipt first.
     *
     * @param \App\Models\PurchaseOrderItem $purchaseOrderItem
     * @param array $data
     * @return \App\Models\QualityControl
     */
    public function createQCFromPurchaseOrderItem($purchaseOrderItem, $data)
    {
        // Validate passed_quantity doesn't exceed ordered quantity
        $passedQuantity = $data['passed_quantity'] ?? $purchaseOrderItem->quantity;
        if ($passedQuantity > $purchaseOrderItem->quantity) {
            throw new \Exception("QC passed quantity ({$passedQuantity}) cannot exceed ordered quantity ({$purchaseOrderItem->quantity}) in purchase order.");
        }

        $qualityControl = QualityControl::create([
            'qc_number' => $this->generateQcNumber(),
            'passed_quantity' => $passedQuantity,
            'rejected_quantity' => $data['rejected_quantity'] ?? 0,
            'status' => 0,
            'inspected_by' => $data['inspected_by'] ?? null,
            'warehouse_id' => $data['warehouse_id'] ?? $purchaseOrderItem->purchaseOrder->warehouse_id,
            'product_id' => $purchaseOrderItem->product_id,
            'rak_id' => $data['rak_id'] ?? null,
            'from_model_type' => \App\Models\PurchaseOrderItem::class,
            'from_model_id' => $purchaseOrderItem->id,
        ]);

        return $qualityControl;
    }

    /**
     * Create a Quality Control record from a Production.
     * This creates QC automatically when production is finished.
     *
     * @param \App\Models\Production $production
     * @return \App\Models\QualityControl
     */
    public function createQCFromProduction($production)
    {
        $manufacturingOrder = $production->manufacturingOrder;
        $product = $manufacturingOrder->productionPlan->product;

        $qualityControl = QualityControl::create([
            'qc_number' => $this->generateQcManufactureNumber(),
            'passed_quantity' => $production->quantity_produced ?? $manufacturingOrder->productionPlan->quantity,
            'rejected_quantity' => 0,
            'status' => 0, // Not processed yet
            'inspected_by' => Auth::id() ?? 1, // Default to admin user if no auth
            'warehouse_id' => $production->warehouse_id ?? $manufacturingOrder->productionPlan->warehouse_id,
            'rak_id' => $production->rak_id ?? null,
            'product_id' => $product->id,
            'from_model_type' => \App\Models\Production::class,
            'from_model_id' => $production->id,
            'date_send_stock' => Carbon::now(),
        ]);

        return $qualityControl;
    }

    public function completeQualityControl($qualityControl, $data)
    {
        $productService = app(ProductService::class);

        // Validate QC passed quantity against receipt quantity for PurchaseReceiptItem
        if ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $purchaseReceiptItem = $qualityControl->fromModel;
            if ($purchaseReceiptItem && $qualityControl->passed_quantity > $purchaseReceiptItem->qty_received) {
                throw new \Exception("QC passed quantity ({$qualityControl->passed_quantity}) cannot exceed received quantity ({$purchaseReceiptItem->qty_received}) in purchase receipt.");
            }
        }

        if ($qualityControl->rejected_quantity > 0) {
            // Only create a sales-side ReturnProduct for non-purchase QC types.
            // Purchase QC (from PurchaseOrderItem) uses PurchaseReturn instead,
            // which is created by PurchaseReturnService::createFromQualityControl()
            // BEFORE completeQualityControl() is called (from the process_qc action form).
            if ($qualityControl->from_model_type !== 'App\Models\PurchaseOrderItem') {
                $returnProductService = app(ReturnProductService::class);
                $returnData = array_merge($data, [
                    'return_number' => $returnProductService->generateReturnNumber(),
                    'from_model_id' => $qualityControl->id,
                    'from_model_type' => QualityControl::class,
                    'warehouse_id' => $data['warehouse_id'] ?? $qualityControl->warehouse_id,
                    'status' => 'draft',
                ]);
                $returnProduct = $qualityControl->returnProduct()->create($returnData);
                $qualityControl->returnProductItem()->create([
                    'return_product_id' => $returnProduct->id,
                    'product_id' => $qualityControl->product_id,
                    'quantity' => $qualityControl->rejected_quantity,
                    'condition' => $data['item_condition'] ?? 'damage',
                    'rak_id' => $data['rak_id'] ?? null,
                ]);
            }
        }

        // Load manufacturing order relationship only for Production model
        if ($qualityControl->from_model_type == 'App\Models\Production') {
            $qualityControl->fromModel->load('manufacturingOrder.productionPlan');

            if ($qualityControl->passed_quantity >= $qualityControl->fromModel->manufacturingOrder->productionPlan->quantity) {
                echo "passed_quantity: {$qualityControl->passed_quantity}, plan_quantity: {$qualityControl->fromModel->manufacturingOrder->productionPlan->quantity}\n";
                echo "Completing MO: passed_quantity {$qualityControl->passed_quantity} >= plan_quantity {$qualityControl->fromModel->manufacturingOrder->productionPlan->quantity}\n";
                $qualityControl->fromModel->manufacturingOrder->update([
                    'status' => 'completed'
                ]);
                HelperController::sendNotification(isSuccess: true, title: "Information", message: "Manufacturing Completed");
            }
        }

        $qualityControl->update([
            'status' => 1,
            'date_send_stock' => Carbon::now()
        ]);

        // Note: qty_accepted on PurchaseReceiptItem should NOT be updated here
        // QC only provides inspection results, acceptance decision is separate process
        // Update qty_accepted on PurchaseReceiptItem if QC is from PurchaseReceiptItem
        // if ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
        //     $purchaseReceiptItem = $qualityControl->fromModel;
        //     if ($purchaseReceiptItem) {
        //         $purchaseReceiptItem->update([
        //             'qty_accepted' => $qualityControl->passed_quantity
        //         ]);
        //     }
        // }

        // Create journal entries and inventory stock for passed QC items from PurchaseOrderItem or PurchaseReceiptItem
        // For PurchaseReceiptItem QC (legacy flow), journal entries are created when the receipt is posted
        // For PurchaseOrderItem QC (new flow), journal entries are created here since receipt posting happens later
        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem' && $qualityControl->passed_quantity > 0) {
            $this->createJournalEntriesAndInventoryForQC($qualityControl);
        }

        // Handle Purchase Receipt and Purchase Order completion based on QC results
        if ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $this->handlePurchaseReceiptCompletion($qualityControl);
        }

        // NEW FLOW: Auto-create Purchase Receipt after QC for PurchaseOrderItem
        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem' && $qualityControl->passed_quantity > 0) {
            $purchaseReceipt = $this->autoCreatePurchaseReceiptFromQC($qualityControl, $data);
            if ($purchaseReceipt) {
                try {
                    // Post the receipt so journals and stock movements are created via PurchaseReceiptService
                    // Add retry-with-backoff to handle transient ordering/race conditions where
                    // the receipt items may not be fully available immediately after creation.
                    $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
                    $maxAttempts = config('procurement.auto_post_retries', 3);
                    $delayMs = [200, 500, 1000];
                    $result = null;

                    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                        // Refresh receipt to pick up any related items that may have been attached
                        $purchaseReceipt = $purchaseReceipt->fresh();
                        $result = $purchaseReceiptService->postPurchaseReceipt($purchaseReceipt);

                        Log::info('Auto postPurchaseReceipt returned (attempt ' . $attempt . ')', [
                            'qc' => $qualityControl->id,
                            'receipt_id' => $purchaseReceipt->id,
                            'attempt' => $attempt,
                            'result' => $result,
                        ]);

                        // Stop retrying when posted or an explicit error occurred
                        if (($result['status'] ?? null) === 'posted' || ($result['status'] ?? null) === 'error') {
                            break;
                        }

                        // Backoff before next attempt
                        if ($attempt < $maxAttempts) {
                            $sleepMs = $delayMs[$attempt - 1] ?? 500;
                            usleep($sleepMs * 1000);
                        }
                    }

                    if (($result['status'] ?? null) !== 'posted') {
                        Log::warning('Auto postPurchaseReceipt did not post after retries', [
                            'qc' => $qualityControl->id,
                            'receipt_id' => $purchaseReceipt->id,
                            'final_result' => $result,
                            'attempts' => $maxAttempts,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Auto postPurchaseReceipt failed for QC ' . $qualityControl->id . ': ' . $e->getMessage(), ['qc' => $qualityControl->id]);
                }
            }
        }

        // NEW: Check and auto-complete Purchase Order if all items are received
        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            $this->checkAndCompletePurchaseOrder($qualityControl);
        }

        // Create journal entries and stock movement for passed QC items from Production
        if ($qualityControl->from_model_type === 'App\Models\Production' && $qualityControl->passed_quantity > 0) {
            $this->createJournalEntriesAndStockMovementForProductionQC($qualityControl);
        }
    }

    /**
     * Create journal entries and inventory stock for QC passed items
     */
    public function createJournalEntriesAndInventoryForQC(QualityControl $qualityControl): void
    {
        // Load necessary relationships based on model type
        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            $qualityControl->loadMissing([
                'fromModel.purchaseOrder.purchaseOrderCurrency',
                'product.inventoryCoa',
                'product.temporaryProcurementCoa',
                'product.unbilledPurchaseCoa'
            ]);
        } elseif ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $qualityControl->loadMissing([
                'fromModel.purchaseOrderItem.purchaseOrder.purchaseOrderCurrency',
                'product.inventoryCoa',
                'product.temporaryProcurementCoa',
                'product.unbilledPurchaseCoa'
            ]);
        }

        $fromModel = $qualityControl->fromModel;
        $product = $qualityControl->product;
        $passedQuantity = $qualityControl->passed_quantity;

        // Get unit price based on model type
        if ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            $unitPrice = $fromModel?->unit_price ?? 0;
        } elseif ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $unitPrice = $fromModel?->purchaseOrderItem?->unit_price ?? 0;
        } else {
            $unitPrice = 0;
        }

        if ($passedQuantity <= 0 || $unitPrice <= 0) {
            return;
        }

        $amount = round($passedQuantity * $unitPrice, 2);

        // Get COA accounts
        $inventoryCoa = $product->inventoryCoa ?? $this->resolveCoaByCodes(['1140.10', '1140.01']);
        $temporaryProcurementCoa = $product->temporaryProcurementCoa ?? $this->resolveCoaByCodes(['1180.01', '1400.01']);
        $unbilledPurchaseCoa = $product->unbilledPurchaseCoa ?? $this->resolveCoaByCodes(['2100.10', '2190.10', '1180.01']);

        if (!$inventoryCoa || !$temporaryProcurementCoa || !$unbilledPurchaseCoa) {
            // Skip posting if required COA accounts are not available (e.g., in test environment)
            return;
        }

        $date = now()->toDateString();
        $reference = $qualityControl->qc_number;

        // Prevent duplicate posting
        if (JournalEntry::where('source_type', QualityControl::class)
            ->where('source_id', $qualityControl->id)
            ->where('description', 'like', '%QC Inventory%')
            ->exists()) {
            return;
        }

        $entries = [];

        // Debit inventory account
        $entries[] = JournalEntry::create([
            'coa_id' => $inventoryCoa->id,
            'date' => $date,
            'reference' => $reference,
            'description' => 'QC Inventory - Debit inventory for QC passed items: ' . $qualityControl->qc_number,
            'debit' => $amount,
            'credit' => 0,
            'journal_type' => 'inventory',
            'source_type' => QualityControl::class,
            'source_id' => $qualityControl->id,
        ]);

        // Credit temporary procurement position
        $entries[] = JournalEntry::create([
            'coa_id' => $temporaryProcurementCoa->id,
            'date' => $date,
            'reference' => $reference,
            'description' => 'QC Inventory - Credit temporary procurement for QC passed items: ' . $qualityControl->qc_number,
            'debit' => 0,
            'credit' => $amount,
            'journal_type' => 'inventory',
            'source_type' => QualityControl::class,
            'source_id' => $qualityControl->id,
        ]);

        // Create stock movement to update inventory
        $existingMovement = StockMovement::where('from_model_type', QualityControl::class)
            ->where('from_model_id', $qualityControl->id)
            ->first();

        if (!$existingMovement) {
            $meta = [
                'source' => 'quality_control',
                'qc_id' => $qualityControl->id,
                'qc_number' => $qualityControl->qc_number,
                'unit_cost' => $unitPrice,
                'currency' => $qualityControl->from_model_type === 'App\Models\PurchaseOrderItem'
                    ? optional($fromModel->purchaseOrder->purchaseOrderCurrency->first())->currency_code
                    : optional($fromModel->purchaseReceipt->currency)->code,
                'purchase_order_item_id' => $qualityControl->from_model_type === 'App\Models\PurchaseOrderItem'
                    ? $fromModel?->id
                    : $fromModel?->purchase_order_item_id,
                'passed_quantity' => $passedQuantity,
                'rejected_quantity' => $qualityControl->rejected_quantity,
            ];

            StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $qualityControl->warehouse_id,
                'quantity' => $passedQuantity,
                'value' => $amount,
                'type' => 'purchase_in',
                'date' => $date,
                'notes' => 'Stock inbound from QC completion: ' . $qualityControl->qc_number,
                'meta' => $meta,
                'rak_id' => $qualityControl->rak_id,
                'from_model_type' => QualityControl::class,
                'from_model_id' => $qualityControl->id,
            ]);
        }
    }

    protected function resolveCoaByCodes(array $codes): ?\App\Models\ChartOfAccount
    {
        foreach ($codes as $code) {
            if (!$code) {
                continue;
            }

            if (!array_key_exists($code, self::$coaCache)) {
                self::$coaCache[$code] = ChartOfAccount::where('code', $code)->first();
            }

            if (self::$coaCache[$code]) {
                return self::$coaCache[$code];
            }
        }

        return null;
    }

    public function checkPenerimaanBarang($qualityControl)
    {
        Log::info('checkPenerimaanBarang: CALLED for QC ' . $qualityControl->id . ' from_model_type=' . $qualityControl->from_model_type);
        
        // Find the purchase order that this quality control belongs to
        $purchaseOrder = null;

        if ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $purchaseReceiptItem = $qualityControl->fromModel;
            if ($purchaseReceiptItem && $purchaseReceiptItem->purchaseOrderItem) {
                $purchaseOrder = $purchaseReceiptItem->purchaseOrderItem->purchaseOrder;
            }
        } elseif ($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem') {
            // QC from PO item should NOT complete the PO - it only validates quality before receipt creation
            // PO completion happens when receipts are posted, not when QC from PO item is completed
            Log::info('checkPenerimaanBarang: SKIPPING for QC from PurchaseOrderItem - PO completion happens via receipt posting');
            return;
        }

        if (!$purchaseOrder) {
            Log::info('checkPenerimaanBarang: No purchase order found for QC ' . $qualityControl->id);
            return;
        }

        // Load relationships
        $purchaseOrder->load(['purchaseOrderItem.purchaseReceiptItem']);

        Log::info('checkPenerimaanBarang: Loaded PO relationships for PO ' . $purchaseOrder->id);
        Log::info('checkPenerimaanBarang: PO has ' . $purchaseOrder->purchaseOrderItem->count() . ' items');

        $totalQuantityDibutuhkan = 0;
        $totalQuantityYangDiterima = 0;

        foreach ($purchaseOrder->purchaseOrderItem as $purchaseOrderItem) {
            $totalQuantityDibutuhkan += $purchaseOrderItem->quantity;

            // Only count posted receipts as "received" - QC from PO items don't count toward PO completion
            foreach ($purchaseOrderItem->purchaseReceiptItem as $purchaseReceiptItem) {
                if ($purchaseReceiptItem->purchaseReceipt && $purchaseReceiptItem->purchaseReceipt->status === 'completed') {
                    $totalQuantityYangDiterima += $purchaseReceiptItem->qty_accepted;
                }
            }
        }

        Log::info('checkPenerimaanBarang: PO ' . $purchaseOrder->id . ' - Dibutuhkan: ' . $totalQuantityDibutuhkan . ', Diterima: ' . $totalQuantityYangDiterima);

        if ($totalQuantityDibutuhkan == $totalQuantityYangDiterima) {
            Log::info('checkPenerimaanBarang: Completing PO ' . $purchaseOrder->id);
            $purchaseOrder->update([
                'status' => 'completed',
                'completed_by' => Auth::user()->id,
                'completed_at' => Carbon::now()
            ]);

            HelperController::sendNotification(isSuccess: true, message: 'Purchase Order Completed', title: 'Information');
        } else {
            Log::info('checkPenerimaanBarang: PO ' . $purchaseOrder->id . ' not completed yet');
        }
    }

    /**
     * Handle Purchase Receipt and Purchase Order completion based on QC results
     * When QC is completed for a PurchaseReceiptItem, check if all items in the Purchase Order are fully received
     */
    public function handlePurchaseReceiptCompletion($qualityControl)
    {
        $purchaseReceiptItem = $qualityControl->fromModel;
        if (!$purchaseReceiptItem) {
            return;
        }

        // Update qty_accepted on the PurchaseReceiptItem based on QC result
        $purchaseReceiptItem->update([
            'qty_accepted' => $qualityControl->passed_quantity
        ]);

        // Get the purchase receipt and purchase order
        $purchaseReceipt = $purchaseReceiptItem->purchaseReceipt;
        $purchaseOrder = $purchaseReceipt->purchaseOrder;

        if (!$purchaseOrder) {
            return;
        }

        // Load all purchase order items and their receipts
        $purchaseOrder->load(['purchaseOrderItem.purchaseReceiptItem' => function ($query) {
            $query->with(['qualityControl']);
        }]);

        $allItemsComplete = true;
        $totalOrdered = 0;
        $totalReceived = 0;

        foreach ($purchaseOrder->purchaseOrderItem as $poItem) {
            $totalOrdered += $poItem->quantity;

            foreach ($poItem->purchaseReceiptItem as $receiptItem) {
                // Only count items that have completed QC
                if ($receiptItem->qualityControl && $receiptItem->qualityControl->status == 1) {
                    $totalReceived += $receiptItem->qty_accepted;
                } else {
                    // If any item doesn't have completed QC, order is not complete
                    $allItemsComplete = false;
                }
            }
        }

        // Check if all items in THIS receipt have completed QC
        $receiptItemsComplete = true;
        foreach ($purchaseReceipt->purchaseReceiptItem as $receiptItem) {
            if (!$receiptItem->qualityControl || $receiptItem->qualityControl->status != 1) {
                $receiptItemsComplete = false;
                break;
            }
        }

        Log::info("QC Completion Check - Receipt {$purchaseReceipt->id}: ItemsComplete={$receiptItemsComplete}");

        if ($receiptItemsComplete) {
            // Complete the purchase receipt
            $purchaseReceipt->update([
                'status' => 'completed',
                'completed_by' => Auth::id(),
                'completed_at' => Carbon::now()
            ]);

            // Post the receipt so journals and inventory are posted via PurchaseReceiptService
            try {
                app(\App\Services\PurchaseReceiptService::class)->postPurchaseReceipt($purchaseReceipt);
            } catch (\Exception $e) {
                Log::error('postPurchaseReceipt failed during handlePurchaseReceiptCompletion for receipt ' . $purchaseReceipt->id . ': ' . $e->getMessage(), ['receipt' => $purchaseReceipt->id]);
            }

            Log::info("Completed Purchase Receipt {$purchaseReceipt->id} due to QC completion");
        }

        // Check if the entire purchase order is complete
        if ($allItemsComplete && $totalOrdered == $totalReceived) {
            // Complete the purchase order
            $purchaseOrder->update([
                'status' => 'completed',
                'completed_by' => Auth::id(),
                'completed_at' => Carbon::now()
            ]);

            Log::info("Completed Purchase Order {$purchaseOrder->id} due to QC completion");

            HelperController::sendNotification(
                isSuccess: true,
                title: "Purchase Order Completed",
                message: "Purchase Order {$purchaseOrder->po_number} has been completed. All items have been received and quality controlled."
            );
        } elseif ($allItemsComplete) {
            // Complete the purchase receipt if all its items have been QC'd (even if not all ordered qty received)
            $purchaseReceipt->update([
                'status' => 'completed',
                'completed_by' => Auth::id(),
                'completed_at' => Carbon::now()
            ]);

            // Note: Receipt posting is already done in completeQualityControl via createJournalEntriesAndInventoryForQC
        } elseif ($totalReceived > 0) {
            // Set purchase order to partially_received if some items received but not all
            if ($purchaseOrder->status === 'approved') {
                $purchaseOrder->update(['status' => 'partially_received']);
                Log::info("Set Purchase Order {$purchaseOrder->id} to partially_received");
            }
        }
    }

    /**
     * Calculate total BDP cost for a Manufacturing Order
     */
    protected function calculateManufacturingOrderBDPTotal(\App\Models\ManufacturingOrder $mo): float
    {
        // Get BOM from ProductionPlan
        $bom = $mo->productionPlan?->billOfMaterial;

        if (!$bom || !$bom->is_active) {
            return 0; // Return 0 instead of throwing exception
        }

        $bom->loadMissing('items.product');

        // Calculate standard costs from BOM
        $materialCost = $bom->items->sum(function ($item) {
            return (float) $item->quantity * (float) ($item->product->cost_price ?? 0);
        });

        $laborCost = (float) ($bom->labor_cost ?? 0);
        $overheadCost = (float) ($bom->overhead_cost ?? 0);

        // Standard total cost = (BB + TKL + BOP) × quantity
        $standardTotalCost = ($materialCost + $laborCost + $overheadCost) * (float) $mo->productionPlan->quantity;

        // Adjust with actual material issues and returns
        $issuesTotal = \App\Models\MaterialIssue::where('production_plan_id', $mo->production_plan_id)
            ->where('status', 'completed')
            ->where('type', 'issue')
            ->sum('total_cost');

        $returnsTotal = \App\Models\MaterialIssue::where('production_plan_id', $mo->production_plan_id)
            ->where('status', 'completed')
            ->where('type', 'return')
            ->sum('total_cost');

        // Use actual material cost if available, otherwise use standard
        $actualMaterialCost = $issuesTotal - $returnsTotal;
        $materialCostToUse = $actualMaterialCost > 0 ? $actualMaterialCost : $materialCost * (float) $mo->productionPlan->quantity;

        // If using actual material cost, don't add labor/overhead again as they should be allocated separately
        // If using standard cost, include labor and overhead
        $additionalCosts = $actualMaterialCost > 0 ? 0 : ($laborCost + $overheadCost) * (float) $mo->productionPlan->quantity;

        // Sum labor & overhead allocations posted to BDP and linked to this MO via source_type/source_id
        $bdpCoa = $this->resolveCoaByCodes(['1140.02', '1140.03', '1140']);
        $allocationsTotal = 0;
        if ($bdpCoa) {
            $allocationsTotal = \App\Models\JournalEntry::where('coa_id', $bdpCoa->id)
                ->where('journal_type', 'manufacturing_allocation')
                ->where('source_type', \App\Models\ManufacturingOrder::class)
                ->where('source_id', $mo->id)
                ->sum('debit');
        }

        // Final total = Material Cost + Additional Costs + Allocations
        return max(0, $materialCostToUse + $additionalCosts + $allocationsTotal);
    }

    /**
     * Create journal entries and stock movement for production QC completion
     */
    public function createJournalEntriesAndStockMovementForProductionQC(QualityControl $qualityControl): void
    {
        $production = $qualityControl->fromModel;
        $manufacturingOrder = $production->manufacturingOrder;
        $productionPlan = $manufacturingOrder->productionPlan;
        $product = $qualityControl->product;
        $passedQuantity = $qualityControl->passed_quantity;

        // Calculate total cost from BDP (Barang Dalam Proses)
        $totalCost = $this->calculateManufacturingOrderBDPTotal($manufacturingOrder);

        if ($totalCost <= 0 || $passedQuantity <= 0) {
            return;
        }

        // Get COA accounts
        $bom = $productionPlan->billOfMaterial;
        $bdpCoa = $bom->workInProgressCoa ?? $this->resolveCoaByCodes(['1140.02']); // Barang Dalam Proses
        $barangJadiCoa = $bom->finishedGoodsCoa ?? $this->resolveCoaByCodes(['1140.03']); // Persediaan Barang Jadi

        if (!$bdpCoa || !$barangJadiCoa) {
            return;
        }

        $date = now()->toDateString();
        $reference = $qualityControl->qc_number;

        // Prevent duplicate posting
        if (\App\Models\JournalEntry::where('source_type', QualityControl::class)
            ->where('source_id', $qualityControl->id)
            ->where('description', 'like', '%Penyelesaian produksi%')
            ->exists()) {
            return;
        }

        // Calculate cost per unit
        $costPerUnit = $totalCost / $productionPlan->quantity;
        $amount = round($costPerUnit * $passedQuantity, 2);

        // Create journal entries
        \App\Models\JournalEntry::create([
            'coa_id' => $barangJadiCoa->id,
            'date' => $date,
            'reference' => $reference,
            'description' => 'Penyelesaian produksi - ' . $manufacturingOrder->mo_number . ' (' . $product->name . ')',
            'debit' => $amount,
            'credit' => 0,
            'journal_type' => 'finished_goods_completion',
            'source_type' => QualityControl::class,
            'source_id' => $qualityControl->id,
        ]);

        \App\Models\JournalEntry::create([
            'coa_id' => $bdpCoa->id,
            'date' => $date,
            'reference' => $reference,
            'description' => 'Penyelesaian produksi - ' . $manufacturingOrder->mo_number . ' (' . $product->name . ')',
            'debit' => 0,
            'credit' => $amount,
            'journal_type' => 'finished_goods_completion',
            'source_type' => QualityControl::class,
            'source_id' => $qualityControl->id,
        ]);

        // Create stock movement
        $existingMovement = \App\Models\StockMovement::where('from_model_type', QualityControl::class)
            ->where('from_model_id', $qualityControl->id)
            ->first();

        if (!$existingMovement) {
            $meta = [
                'source' => 'quality_control_manufacture',
                'qc_id' => $qualityControl->id,
                'qc_number' => $qualityControl->qc_number,
                'production_id' => $production->id,
                'production_number' => $production->production_number,
                'manufacturing_order_id' => $manufacturingOrder->id,
                'mo_number' => $manufacturingOrder->mo_number,
                'passed_quantity' => $passedQuantity,
                'rejected_quantity' => $qualityControl->rejected_quantity,
                'cost_per_unit' => $costPerUnit,
            ];

            \App\Models\StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $qualityControl->warehouse_id,
                'quantity' => $passedQuantity,
                'value' => $amount,
                'type' => 'manufacture_in',
                'date' => $date,
                'notes' => 'Stock masuk dari penyelesaian produksi: ' . $qualityControl->qc_number,
                'meta' => $meta,
                'rak_id' => $qualityControl->rak_id,
                'from_model_type' => QualityControl::class,
                'from_model_id' => $qualityControl->id,
            ]);
        }

        // Update production status to finished only if all quantity passed QC
        if ($passedQuantity >= $production->quantity_produced) {
            $production->status = 'finished';
            $production->save();
        }
    }

    /**
     * Auto-create Purchase Receipt from Quality Control
     * NEW FLOW: QC creates receipt automatically after QC pass
     */
    protected function autoCreatePurchaseReceiptFromQC($qualityControl, $data)
    {
        $purchaseOrderItem = $qualityControl->fromModel;
        if (!$purchaseOrderItem) {
            return;
        }

        $purchaseOrder = $purchaseOrderItem->purchaseOrder;
        if (!$purchaseOrder) {
            return;
        }

        // Check if receipt already exists for this QC
        $existingReceipt = \App\Models\PurchaseReceiptItem::where('purchase_order_item_id', $purchaseOrderItem->id)
            ->whereHas('purchaseReceipt', function ($query) use ($qualityControl) {
                $query->where('notes', 'like', '%' . $qualityControl->qc_number . '%');
            })
            ->first();

        if ($existingReceipt) {
            Log::info('Purchase Receipt already exists for QC ' . $qualityControl->qc_number);
            return;
        }

        // Generate receipt number
        $receiptNumber = $this->generateReceiptNumber();

        // Create Purchase Receipt
        $purchaseReceipt = \App\Models\PurchaseReceipt::create([
            'receipt_number' => $receiptNumber,
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => Auth::id() ?? $data['received_by'] ?? 1,
            'notes' => 'Auto-created from QC: ' . $qualityControl->qc_number,
            'currency_id' => $purchaseOrder->purchaseOrderCurrency->first()?->currency_id ?? 1,
            'status' => 'completed',
            'cabang_id' => $purchaseOrder->cabang_id,
        ]);

        // Create Purchase Receipt Item
        $receiptItem = \App\Models\PurchaseReceiptItem::create([
            'purchase_receipt_id'    => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id'             => $qualityControl->product_id,
            'qty_received'           => $qualityControl->passed_quantity + $qualityControl->rejected_quantity,
            'qty_accepted'           => $qualityControl->passed_quantity,
            'qty_rejected'           => $qualityControl->rejected_quantity,
            'reason_rejected'        => $qualityControl->rejected_quantity > 0 ? 'Failed QC inspection' : null,
            'warehouse_id'           => $qualityControl->warehouse_id,
            'rak_id'                 => $qualityControl->rak_id,
            'status'                 => 'completed', // QC already done
        ]);

        Log::info('Auto-created Purchase Receipt from QC', [
            'qc_number' => $qualityControl->qc_number,
            'receipt_number' => $receiptNumber,
            'receipt_id' => $purchaseReceipt->id,
            'receipt_item_id' => $receiptItem->id,
        ]);

        return $purchaseReceipt;
    }

    /**
     * Generate receipt number
     */
    protected function generateReceiptNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'PR-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = \App\Models\PurchaseReceipt::where('receipt_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    /**
     * Check and auto-complete Purchase Order if all items are fully received
     * NEW: Auto-complete PO when all items have receipts
     */
    protected function checkAndCompletePurchaseOrder($qualityControl)
    {
        $purchaseOrderItem = $qualityControl->fromModel;
        if (!$purchaseOrderItem) {
            return;
        }

        $purchaseOrder = $purchaseOrderItem->purchaseOrder;
        if (!$purchaseOrder) {
            return;
        }

        // Don't auto-complete if already completed or closed
        if (in_array($purchaseOrder->status, ['completed', 'closed', 'paid'])) {
            return;
        }

        // Refresh PO to get latest data including newly created receipt items
        $purchaseOrder->refresh();

        // Load all items with their receipts
        $purchaseOrder->load(['purchaseOrderItem.purchaseReceiptItem']);

        $allItemsReceived = true;
        
        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            // Sum all received quantities from receipt items for this PO item
            $totalReceived = $item->purchaseReceiptItem->sum('qty_received');
            
            // If any item has not been fully received, don't complete
            if ($totalReceived < $item->quantity) {
                $allItemsReceived = false;
                Log::info('PO item not fully received', [
                    'po_item_id' => $item->id,
                    'ordered_qty' => $item->quantity,
                    'received_qty' => $totalReceived,
                ]);
                break;
            }
        }

        if ($allItemsReceived && !in_array($purchaseOrder->status, ['completed', 'closed', 'paid'])) {
            $purchaseOrder->update([
                'status' => 'completed',
                'completed_by' => Auth::id() ?? 1,
                'completed_at' => now(),
            ]);

            Log::info('Auto-completed Purchase Order', [
                'po_id' => $purchaseOrder->id,
                'po_number' => $purchaseOrder->po_number,
                'trigger' => 'QC completion',
            ]);

            HelperController::sendNotification(
                isSuccess: true,
                title: 'Purchase Order Completed',
                message: 'PO ' . $purchaseOrder->po_number . ' has been automatically completed.'
            );
        }
    }
}
