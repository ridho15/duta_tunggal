<?php

namespace App\Observers;

use App\Models\Production;
use App\Services\QualityControlService;
use Illuminate\Support\Facades\Log;

class ProductionObserver
{
    /**
     * Handle the Production "updated" event.
     */
    public function updated(Production $production): void
    {
        // Check if status changed to 'finished'
        $originalStatus = $production->getOriginal('status');
        if ($originalStatus !== 'finished' && $production->status === 'finished') {
            Log::info("ProductionObserver: Production {$production->id} status changed to finished, creating QC...");
            // Create Quality Control automatically when production is finished
            $this->createQualityControlForProduction($production);
            Log::info("ProductionObserver: QC creation completed for production {$production->id}");

            // Journal and stock movement will be created during Quality Control completion
            // $this->generateJournalForProductionCompletion($production);
            $this->checkAndUpdateProductionPlanCompletion($production);
        }
    }

    /**
     * Handle the Production "created" event.
     */
    public function created(Production $production): void
    {
        // If created directly as finished, generate journal
        if ($production->status === 'finished') {
            // Journal and stock movement will be created during Quality Control completion
            // $this->generateJournalForProductionCompletion($production);
            $this->checkAndUpdateProductionPlanCompletion($production);
        }
    }

    /**
     * Create Quality Control automatically when production is finished
     */
    protected function createQualityControlForProduction(Production $production): void
    {
        try {
            // Check if QC already exists for this production
            $existingQC = $production->qualityControl()->exists();
            if ($existingQC) {
                Log::info("QC already exists for production ID: {$production->id}");
                return;
            }

            $qcService = app(QualityControlService::class);
            $qc = $qcService->createQCFromProduction($production);

            Log::info("QC created automatically for production ID: {$production->id}, QC ID: {$qc->id}");

        } catch (\Exception $e) {
            Log::error("Failed to create QC for production ID: {$production->id}. Error: " . $e->getMessage());
        }
    }

    /**
     * Check if all Productions in ManufacturingOrder are finished and update ProductionPlan accordingly
     */
    protected function checkAndUpdateProductionPlanCompletion(Production $production): void
    {
        $manufacturingOrder = $production->manufacturingOrder;
        if (!$manufacturingOrder) {
            return;
        }

        $productionPlan = $manufacturingOrder->productionPlan;
        if (!$productionPlan) {
            return;
        }

        // Get all productions for this manufacturing order
        $productions = $manufacturingOrder->productions;

        // Check if all productions are finished
        $allFinished = $productions->every(function ($prod) {
            return $prod->status === 'finished';
        });

        // If all productions are finished, mark ManufacturingOrder as completed
        if ($allFinished && $manufacturingOrder->status !== 'completed') {
            $manufacturingOrder->update(['status' => 'completed']);
            // The ManufacturingOrder observer will handle updating ProductionPlan status
        }
    }
}