<?php

namespace App\Observers;

use App\Models\Production;
use App\Services\ManufacturingJournalService;
use App\Services\QualityControlService;
use Filament\Notifications\Notification;
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
        }
    }

    /**
     * Handle the Production "created" event.
     *
     * Posts the "Produksi In Progress" WIP journal:
     *   Dr. 1-201  Persediaan Barang Dalam Proses - WIP INVENTORY
     *       Cr. 1400.04 Pos Sementara Produksi  (material cost)
     *       Cr. 5230   Biaya Tenaga Kerja Proses Produksi  (labor + overhead)
     */
    public function created(Production $production): void
    {
        // Post WIP journal whenever production work begins
        try {
            app(ManufacturingJournalService::class)->generateJournalForProductionInProgress($production);
            Log::info("ProductionObserver: WIP journal posted for production {$production->id}");
        } catch (\Exception $e) {
            Log::warning("ProductionObserver: Could not post WIP journal for production {$production->id}: " . $e->getMessage());

            Notification::make()
                ->title('Gagal Membuat Jurnal Produksi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        if ($production->status === 'finished') {
            $this->createQualityControlForProduction($production);
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

            Notification::make()
                ->title('Gagal Membuat QC Produksi')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}