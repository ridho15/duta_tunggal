<?php

namespace App\Observers;

use App\Models\MaterialIssue;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssueItem;
use App\Models\ManufacturingOrderMaterial;
use App\Services\ManufacturingJournalService;
use Illuminate\Support\Facades\DB;

class MaterialIssueObserver
{
    /**
     * Handle the MaterialIssue "updated" event.
     */
    public function updated(MaterialIssue $materialIssue): void
    {
        // Always keep total_cost in sync
        // $materialIssue->updateTotalCost(); // Temporarily disabled for testing

        // If status transitioned to pending approval, set all items to pending approval
        $originalStatus = $materialIssue->getOriginal('status');
        if ($originalStatus !== MaterialIssue::STATUS_PENDING_APPROVAL && $materialIssue->isPendingApproval()) {
            $this->setAllItemsToPendingApproval($materialIssue);
        }

        // If status transitioned to approved, set all pending items to approved
        if ($originalStatus !== MaterialIssue::STATUS_APPROVED && $materialIssue->isApproved()) {
            $this->setAllPendingItemsToApproved($materialIssue);
        }

        // If status transitioned back to draft (rejected), set all items back to draft
        if ($originalStatus !== MaterialIssue::STATUS_DRAFT && $materialIssue->isDraft()) {
            $this->setAllItemsToDraft($materialIssue);
        }

        // If status transitioned to completed, set all approved items to completed
        if ($originalStatus !== MaterialIssue::STATUS_COMPLETED && $materialIssue->isCompleted()) {
            $this->setAllApprovedItemsToCompleted($materialIssue);
        }

        // If status transitioned to completed AND approved, generate journal and update MO usages
        if ($originalStatus !== MaterialIssue::STATUS_COMPLETED && $materialIssue->isCompleted()) {
            // Only process if material issue has been approved
            if ($materialIssue->approved_by && $materialIssue->approved_at) {
                $this->generateJournal($materialIssue);
                $this->createStockMovements($materialIssue);
                $this->updateMoQtyUsed($materialIssue);
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
            $this->updateMoQtyUsed($materialIssue);
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

    protected function updateMoQtyUsed(MaterialIssue $materialIssue): void
    {
        // Skip if no MO relationship to avoid unnecessary processing
        if (!$materialIssue->manufacturing_order_id && !$materialIssue->production_plan_id) {
            return;
        }

        // Resolve target MO: prefer explicit manufacturing_order_id, fallback to production_plan_id
        $mo = null;
        if ($materialIssue->manufacturing_order_id) {
            $mo = ManufacturingOrder::select('id', 'production_plan_id')->find($materialIssue->manufacturing_order_id);
        }
        if (!$mo && $materialIssue->production_plan_id) {
            $mo = ManufacturingOrder::select('id', 'production_plan_id')
                ->where('production_plan_id', $materialIssue->production_plan_id)
                ->latest('id')
                ->first();
        }

        if (!$mo) {
            return;
        }

        // Use direct database update to avoid loading large collections into memory
        // Update qty_used for materials in this MO based on completed issues
        DB::statement("
            UPDATE manufacturing_order_materials mom
            LEFT JOIN (
                SELECT mii.product_id, SUM(mii.quantity) as total_issued
                FROM material_issue_items mii
                INNER JOIN material_issues mi ON mii.material_issue_id = mi.id
                WHERE mi.type = 'issue'
                AND mi.status = 'completed'
                AND (
                    mi.manufacturing_order_id = ?
                    OR (mi.manufacturing_order_id IS NULL AND mi.production_plan_id = ?)
                )
                GROUP BY mii.product_id
            ) issued ON mom.material_id = issued.product_id
            SET mom.qty_used = COALESCE(issued.total_issued, 0)
            WHERE mom.manufacturing_order_id = ?
        ", [$mo->id, $mo->production_plan_id, $mo->id]);
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
                        'date' => $materialIssue->issue_date,
                        'from_model_type' => MaterialIssue::class,
                        'from_model_id' => $materialIssue->id,
                        'meta' => array_filter([
                            'source' => 'material_issue',
                            'material_issue_id' => $materialIssue->id,
                            'material_issue_type' => $materialIssue->type,
                            'material_issue_number' => $materialIssue->issue_number ?? null,
                            'production_plan_id' => $materialIssue->production_plan_id,
                            'manufacturing_order_id' => $materialIssue->manufacturing_order_id,
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
}
