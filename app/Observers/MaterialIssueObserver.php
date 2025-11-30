<?php

namespace App\Observers;

use App\Models\MaterialIssue;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssueItem;
use App\Models\ManufacturingOrderMaterial;
use App\Services\ManufacturingJournalService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaterialIssueObserver
{
    /**
     * Handle the MaterialIssue "updated" event.
     */
    public function updated(MaterialIssue $materialIssue): void
    {
        Log::info('MaterialIssueObserver: updated called', [
            'material_issue_id' => $materialIssue->id,
            'status' => $materialIssue->status,
            'original_status' => $materialIssue->getOriginal('status'),
        ]);

        // Always keep total_cost in sync
        // $materialIssue->updateTotalCost(); // Temporarily disabled for testing

        // If status transitioned to pending approval, set all items to pending approval
        $originalStatus = $materialIssue->getOriginal('status');
        if ($originalStatus !== MaterialIssue::STATUS_PENDING_APPROVAL && $materialIssue->isPendingApproval()) {
            $this->setAllItemsToPendingApproval($materialIssue);
        }

        // If status transitioned to approved, reserve stock
        if ($originalStatus !== MaterialIssue::STATUS_APPROVED && $materialIssue->isApproved()) {
            $this->reserveStock($materialIssue);
        }

        // If status transitioned to completed AND approved, generate journal and update MO usages
        if ($originalStatus !== MaterialIssue::STATUS_COMPLETED && $materialIssue->isCompleted()) {
            // Only process if material issue has been approved
            $this->setAllApprovedItemsToCompleted($materialIssue);
            if ($materialIssue->approved_by && $materialIssue->approved_at) {
                $this->consumeReservedStock($materialIssue);
                $this->createStockMovements($materialIssue); // Create stock movements for record keeping
                $this->generateJournal($materialIssue);
                $this->createManufacturingOrder($materialIssue); // Create Manufacturing Order automatically
                // Release reserved stock is now handled by StockReservationService.consumeReservedStockForMaterialIssue
                // $this->releaseReservedStock($materialIssue);
            }
        }
    }

    /**
     * Handle the MaterialIssue "created" event.
     */
    public function created(MaterialIssue $materialIssue): void
    {
        // Initialize total cost on create
        // $materialIssue->updateTotalCost(); // Temporarily disabled for testing

        // If created directly as completed, mirror update behavior
        if ($materialIssue->isCompleted()) {
            $this->generateJournal($materialIssue);
            $this->createStockMovements($materialIssue);
        }
    }

    protected function generateJournal(MaterialIssue $materialIssue): void
    {
        try {
            $journalService = app(ManufacturingJournalService::class);
            if ($materialIssue->type === 'issue') {
                $journalService->generateJournalForMaterialIssue($materialIssue);
            } else {
                $journalService->generateJournalForMaterialReturn($materialIssue);
            }
        } catch (\Throwable $e) {
            // Log the error for debugging
            \Illuminate\Support\Facades\Log::error('Failed to generate journal in observer', [
                'material_issue_id' => $materialIssue->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Re-throw to see the error
            throw $e;
        }
    }

    /**
     * Create stock movements based on completed material issues/returns
     */
    protected function createStockMovements(MaterialIssue $materialIssue): void
    {
        try {
            $materialIssue->loadMissing('items');
            
            // Process items in chunks to prevent memory issues
            $materialIssue->items->chunk(50)->each(function ($chunk) use ($materialIssue) {
                foreach ($chunk as $item) {
                    $value = $item->total_cost ?? ($item->cost_per_unit ? $item->cost_per_unit * $item->quantity : null);

                    \App\Models\StockMovement::create([
                        'product_id' => $item->product_id,
                        'warehouse_id' => $item->warehouse_id ?? $materialIssue->warehouse_id,
                        'rak_id' => $item->rak_id,
                        'quantity' => $item->quantity,
                        'value' => $value,
                        'type' => $materialIssue->type === 'issue' ? 'manufacture_out' : 'manufacture_in',
                        'date' => Carbon::now(),
                        'reference_id' => $materialIssue->id,
                        'notes' => $materialIssue->issue_number ?? 'Material Issue',
                        'from_model_type' => MaterialIssue::class,
                        'from_model_id' => $materialIssue->id,
                        'meta' => array_filter([
                            'source' => 'material_issue',
                            'material_issue_id' => $materialIssue->id,
                            'material_issue_type' => $materialIssue->type,
                            'material_issue_number' => $materialIssue->issue_number ?? null,
                            'production_plan_id' => $materialIssue->production_plan_id,
                            'manufacturing_order_id' => $materialIssue->manufacturing_order_id,
                            'skip_stock_update' => true, // Flag to prevent double deduction
                        ]),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            // Swallow to keep flow; optionally log
        }
    }

    /**
     * Set all items in the material issue to pending approval
     */
    protected function setAllItemsToPendingApproval(MaterialIssue $materialIssue): void
    {
        MaterialIssueItem::where('material_issue_id', $materialIssue->id)
            ->where('status', MaterialIssueItem::STATUS_DRAFT)
            ->update(['status' => MaterialIssueItem::STATUS_PENDING_APPROVAL]);
    }

    /**
     * Set all approved items to completed
     */
    protected function setAllApprovedItemsToCompleted(MaterialIssue $materialIssue): void
    {
        MaterialIssueItem::where('material_issue_id', $materialIssue->id)
            ->where('status', MaterialIssueItem::STATUS_APPROVED)
            ->update(['status' => MaterialIssueItem::STATUS_COMPLETED]);
    }

    /**
     * Set all pending items to approved
     */
    protected function setAllPendingItemsToApproved(MaterialIssue $materialIssue): void
    {
        MaterialIssueItem::where('material_issue_id', $materialIssue->id)
            ->where('status', MaterialIssueItem::STATUS_PENDING_APPROVAL)
            ->update([
                'status' => MaterialIssueItem::STATUS_APPROVED,
                'approved_by' => $materialIssue->approved_by,
                'approved_at' => $materialIssue->approved_at,
            ]);
    }

    /**
     * Set all items back to draft
     */
    protected function setAllItemsToDraft(MaterialIssue $materialIssue): void
    {
        MaterialIssueItem::where('material_issue_id', $materialIssue->id)
            ->update(['status' => MaterialIssueItem::STATUS_DRAFT]);
    }

    /**
     * Reserve stock when material issue is approved
     */
    protected function reserveStock(MaterialIssue $materialIssue): void
    {
        try {
            \Illuminate\Support\Facades\Log::info('MaterialIssueObserver: reserveStock called', [
                'material_issue_id' => $materialIssue->id,
                'status' => $materialIssue->status,
            ]);

            $service = app(\App\Services\StockReservationService::class);
            $service->reserveStockForMaterialIssue($materialIssue);

            $materialIssue->loadMissing('items');

            foreach ($materialIssue->items as $item) {
                $warehouseId = $item->warehouse_id ?? $materialIssue->warehouse_id;

                $inventoryStock = \App\Models\InventoryStock::where('product_id', $item->product_id)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if ($inventoryStock) {
                    \Illuminate\Support\Facades\Log::info('MaterialIssueObserver: stock reservation completed', [
                        'inventory_stock_id' => $inventoryStock->id,
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                        'quantity' => $item->quantity,
                        'qty_available_current' => $inventoryStock->qty_available,
                        'qty_reserved_current' => $inventoryStock->qty_reserved,
                    ]);
                } else {
                    \Illuminate\Support\Facades\Log::warning('MaterialIssueObserver: inventory stock not found', [
                        'product_id' => $item->product_id,
                        'warehouse_id' => $warehouseId,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to reserve stock in MaterialIssueObserver', [
                'material_issue_id' => $materialIssue->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Consume reserved stock when material issue is completed
     */
    protected function consumeReservedStock(MaterialIssue $materialIssue): void
    {
        try {
            $service = app(\App\Services\StockReservationService::class);
            $service->consumeReservedStockForMaterialIssue($materialIssue);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to consume reserved stock in MaterialIssueObserver', [
                'material_issue_id' => $materialIssue->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create Manufacturing Order automatically when Material Issue is completed
     */
    protected function createManufacturingOrder(MaterialIssue $materialIssue): void
    {
        try {
            // Only create MO for material issues that have a production plan
            if (!$materialIssue->production_plan_id) {
                Log::info('MaterialIssueObserver: No production plan associated, skipping MO creation', [
                    'material_issue_id' => $materialIssue->id,
                ]);
                return;
            }

            // Check if Manufacturing Order already exists for this Production Plan
            $existingMO = ManufacturingOrder::where('production_plan_id', $materialIssue->production_plan_id)->first();
            if ($existingMO) {
                Log::info('MaterialIssueObserver: Manufacturing Order already exists for Production Plan', [
                    'material_issue_id' => $materialIssue->id,
                    'production_plan_id' => $materialIssue->production_plan_id,
                    'existing_mo_id' => $existingMO->id,
                    'existing_mo_number' => $existingMO->mo_number,
                ]);
                return;
            }

            // Load production plan with necessary relationships
            $productionPlan = $materialIssue->productionPlan;
            if (!$productionPlan) {
                Log::warning('MaterialIssueObserver: Production Plan not found, skipping MO creation', [
                    'material_issue_id' => $materialIssue->id,
                    'production_plan_id' => $materialIssue->production_plan_id,
                ]);
                return;
            }

            // Generate MO number
            $manufacturingService = app(\App\Services\ManufacturingService::class);
            $moNumber = $manufacturingService->generateMoNumber();

            // Prepare items from Material Issue
            $items = [];
            foreach ($materialIssue->items as $issueItem) {
                $items[] = [
                    'product_id' => $issueItem->product_id,
                    'uom_id' => $issueItem->uom_id,
                    'quantity' => $issueItem->quantity,
                    'notes' => null,
                ];
            }

            // Create Manufacturing Order
            $manufacturingOrder = ManufacturingOrder::create([
                'mo_number' => $moNumber,
                'production_plan_id' => $materialIssue->production_plan_id,
                'start_date' => $productionPlan->start_date,
                'end_date' => $productionPlan->end_date,
                'status' => 'draft',
                'items' => $items,
            ]);

            // Create warehouse confirmation
            $manufacturingService->createWarehouseConfirmation($manufacturingOrder);

            Log::info('MaterialIssueObserver: Manufacturing Order created successfully', [
                'material_issue_id' => $materialIssue->id,
                'production_plan_id' => $materialIssue->production_plan_id,
                'manufacturing_order_id' => $manufacturingOrder->id,
                'mo_number' => $manufacturingOrder->mo_number,
            ]);

        } catch (\Throwable $e) {
            Log::error('MaterialIssueObserver: Failed to create Manufacturing Order', [
                'material_issue_id' => $materialIssue->id,
                'production_plan_id' => $materialIssue->production_plan_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw exception to prevent breaking the Material Issue completion flow
        }
    }
}
