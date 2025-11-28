<?php

namespace App\Observers;

use App\Models\Production;
use App\Services\ManufacturingJournalService;
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
            $this->generateJournalForProductionCompletion($production);
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
            $this->generateJournalForProductionCompletion($production);
            $this->checkAndUpdateProductionPlanCompletion($production);
        }
    }

    /**
     * Generate journal entries for production completion
     */
    protected function generateJournalForProductionCompletion(Production $production): void
    {
        try {
            $journalService = app(ManufacturingJournalService::class);
            $journalService->generateJournalForProductionCompletion($production);
        } catch (\Throwable $e) {
            // Log error but don't break the update flow
            Log::error('Failed to generate journal for production completion: ' . $e->getMessage(), [
                'production_id' => $production->id,
                'production_number' => $production->production_number
            ]);
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