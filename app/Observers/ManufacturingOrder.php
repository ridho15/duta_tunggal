<?php

namespace App\Observers;

use App\Models\ManufacturingOrder as ModelsManufacturingOrder;
use App\Models\StockMovement;
use Carbon\Carbon;

class ManufacturingOrder
{
    public function updated(ModelsManufacturingOrder $manufacturingOrder): void
    {
        // Stock movements are handled by MaterialIssue (for raw material out/return)
        // and FinishedGoodsCompletion (for finished goods in). Avoid creating
        // movements here to prevent double counting.

        // Update ProductionPlan status based on ManufacturingOrder status changes
        $this->updateProductionPlanStatus($manufacturingOrder);
    }

    /**
     * Update ProductionPlan status based on ManufacturingOrder status changes
     */
    protected function updateProductionPlanStatus(ModelsManufacturingOrder $manufacturingOrder): void
    {
        $productionPlan = $manufacturingOrder->productionPlan;
        if (!$productionPlan) {
            return;
        }

        $originalStatus = $manufacturingOrder->getOriginal('status');

        // If ManufacturingOrder status changed to 'in_progress', update ProductionPlan to 'in_progress'
        if ($originalStatus !== 'in_progress' && $manufacturingOrder->status === 'in_progress') {
            if ($productionPlan->status === 'scheduled') {
                $productionPlan->update(['status' => 'in_progress']);
            }
        }

        // If ManufacturingOrder status changed to 'completed', check if all MO are completed
        if ($originalStatus !== 'completed' && $manufacturingOrder->status === 'completed') {
            $this->checkAndUpdateProductionPlanCompletion($productionPlan);
        }
    }

    /**
     * Check if all ManufacturingOrders in ProductionPlan are completed and update status accordingly
     */
    protected function checkAndUpdateProductionPlanCompletion($productionPlan): void
    {
        // Get all manufacturing orders for this production plan
        $manufacturingOrders = $productionPlan->manufacturingOrders;

        // Check if all manufacturing orders are completed
        $allCompleted = $manufacturingOrders->every(function ($mo) {
            return $mo->status === 'completed';
        });

        // If all MO are completed and ProductionPlan is in progress, mark as completed
        if ($allCompleted && $productionPlan->status === 'in_progress') {
            $productionPlan->update(['status' => 'completed']);
        }
    }
}
