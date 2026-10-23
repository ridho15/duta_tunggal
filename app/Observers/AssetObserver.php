<?php

namespace App\Observers;

use App\Models\Asset;
use App\Services\AssetService;
use Illuminate\Support\Facades\Log;

class AssetObserver
{
    /**
     * Handle the Asset "creating" event.
     */
    public function creating(Asset $asset): void
    {
        $this->calculateDepreciation($asset);
    }

    /**
     * Handle the Asset "created" event.
     */
    public function created(Asset $asset): void
    {
        try {
            // If asset was generated from a PurchaseOrder that already has acquisition journals at PO level, skip duplicate per-unit posting
            if ($asset->purchase_order_id) {
                $hasPoJournals = \App\Models\JournalEntry::where(function ($q) {
                    $q->where('source_type', \App\Models\PurchaseOrder::class)
                      ->orWhere('source_type', 'App\\Models\\PurchaseOrder');
                })
                ->where('source_id', $asset->purchase_order_id)
                ->where('journal_type', 'asset_acquisition')
                ->exists();

                if ($hasPoJournals) {
                    return;
                }
            }

            $assetService = app(AssetService::class);

            if (!$assetService->hasPostedJournals($asset)) {
                $creditCoaId = $asset->credit_coa_id ?? null;
                $assetService->postAssetAcquisitionJournal($asset, $creditCoaId ? (int) $creditCoaId : null);
            }
        } catch (\Throwable $e) {
            Log::warning('AssetObserver: automatic asset journal posting skipped', [
                'asset_id' => $asset->id,
                'asset_name' => $asset->name,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Asset "deleting" event.
     */
    public function deleting(Asset $asset): void
    {
        // Soft delete related journal entries when asset is deleted
        $asset->journalEntries()->delete();
    }

    /**
     * Handle the Asset "updating" event.
     */
    public function updating(Asset $asset): void
    {
        // Only recalculate if relevant fields changed
        if ($asset->isDirty(['purchase_cost', 'salvage_value', 'useful_life_years'])) {
            $this->calculateDepreciation($asset);
            
            // Update related journal entries if asset cost changed
            if ($asset->isDirty('purchase_cost')) {
                $this->updateAcquisitionJournals($asset);
            }
        }
    }

    /**
     * Calculate depreciation values
     */
    protected function calculateDepreciation(Asset $asset): void
    {
        $amounts = $asset->depreciationAmounts();
        $asset->annual_depreciation = $amounts['annual_depreciation'];
        $asset->monthly_depreciation = $amounts['monthly_depreciation'];
        $asset->book_value = $amounts['book_value'];
    }

    /**
     * Update acquisition journal entries when asset cost changes
     */
    protected function updateAcquisitionJournals(Asset $asset): void
    {
        $acquisitionEntries = $asset->journalEntries()
            ->where('description', 'like', '%Asset acquisition%')
            ->get();

        foreach ($acquisitionEntries as $entry) {
            if ($entry->debit > 0) {
                // Update debit entry (asset account)
                $entry->update(['debit' => $asset->purchase_cost]);
            } elseif ($entry->credit > 0) {
                // Update credit entry (accounts payable/cash)
                $entry->update(['credit' => $asset->purchase_cost]);
            }
        }
    }
}
