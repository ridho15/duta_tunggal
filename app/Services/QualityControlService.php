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

        // Hitung berapa PO pada hari ini
        $last = QualityControl::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->qc_number, -4));
            $number = $lastNumber + 1;
        }

        return 'QC-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public function generateQcManufactureNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa QC Manufacture pada hari ini
        $last = QualityControl::where('qc_number', 'like', 'QC-M-' . $date . '-%')
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->qc_number, -4));
            $number = $lastNumber + 1;
        }

        return 'QC-M-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
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
        $purchaseReceiptItem->update(['is_sent' => 1]);

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
            $returnProductService = app(ReturnProductService::class);
            $returnData = array_merge($data, [
                'return_number' => $returnProductService->generateReturnNumber(),
                'from_model_id' => $qualityControl->id,
                'from_model_type' => QualityControl::class,
                'warehouse_id' => $qualityControl->warehouse_id,
                'status' => 'draft',
            ]);
            $returnProduct = $qualityControl->returnProduct()->create($returnData);
            $qualityControl->returnProductItem()->create([
                'return_product_id' => $returnProduct->id,
                'product_id' => $qualityControl->product_id,
                'quantity' => $qualityControl->rejected_quantity,
                'condition' => $data['item_condition'] ?? 'damage',
            ]);
        }

        if ($qualityControl->from_model_type == 'App\Models\Production' && $qualityControl->passed_quantity >= $qualityControl->fromModel->manufacturingOrder->quantity) {
            $qualityControl->fromModel->manufacturingOrder->update([
                'status' => 'completed'
            ]);
            HelperController::sendNotification(isSuccess: true, title: "Information", message: "Manufacturing Completed");
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
        if (($qualityControl->from_model_type === 'App\Models\PurchaseOrderItem' || $qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') && $qualityControl->passed_quantity > 0) {
            $this->createJournalEntriesAndInventoryForQC($qualityControl);
        }

        // Handle Purchase Receipt and Purchase Order completion based on QC results
        if ($qualityControl->from_model_type === 'App\Models\PurchaseReceiptItem') {
            $this->handlePurchaseReceiptCompletion($qualityControl);
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
        $purchaseOrder->load(['purchaseOrderItem.purchaseReceiptItem.qualityControl']);

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

        Log::info("QC Completion Check - PO {$purchaseOrder->id}: Ordered={$totalOrdered}, Received={$totalReceived}, AllComplete={$allItemsComplete}");

        if ($allItemsComplete && $totalOrdered == $totalReceived) {
            // Complete the purchase receipt
            $purchaseReceipt->update([
                'status' => 'completed',
                'completed_by' => Auth::id(),
                'completed_at' => Carbon::now()
            ]);

            // Complete the purchase order
            $purchaseOrder->update([
                'status' => 'completed',
                'completed_by' => Auth::id(),
                'completed_at' => Carbon::now()
            ]);

            Log::info("Completed Purchase Receipt {$purchaseReceipt->id} and Purchase Order {$purchaseOrder->id} due to QC completion");

            HelperController::sendNotification(
                isSuccess: true,
                title: "Purchase Order Completed",
                message: "Purchase Order {$purchaseOrder->po_number} has been completed. All items have been received and quality controlled."
            );
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
    }
}
