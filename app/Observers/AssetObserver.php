<?php

namespace App\Observers;

use App\Models\Asset;

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
        $depreciableAmount = $asset->purchase_cost - $asset->salvage_value;
        
        if ($asset->useful_life_years > 0) {
            $asset->annual_depreciation = $depreciableAmount / $asset->useful_life_years;
            $asset->monthly_depreciation = $asset->annual_depreciation / 12;
        } else {
            $asset->annual_depreciation = 0;
            $asset->monthly_depreciation = 0;
        }
        
        // Calculate initial book value
        $asset->book_value = $asset->purchase_cost - ($asset->accumulated_depreciation ?? 0);
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
