<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AssetService
{
    /**
     * Post journal entries for asset acquisition
     */
    public function postAssetAcquisitionJournal(Asset $asset, ?int $creditCoaId = null): void
    {
        DB::transaction(function () use ($asset, $creditCoaId) {
            // Check if journal entries already exist for this asset
            $existingEntries = JournalEntry::where('source_type', 'App\Models\Asset')
                ->where('source_id', $asset->id)
                ->where('description', 'like', '%Asset acquisition%')
                ->exists();

            if ($existingEntries) {
                throw new \Exception('Journal entries already exist for this asset acquisition');
            }

            // Get COA accounts
            $assetCoa = $asset->assetCoa;
            if (!$assetCoa) {
                throw new \Exception('Asset COA not found');
            }

            // For asset acquisition, we need to determine the credit account
            // This could be Accounts Payable if it's from a purchase order, or Cash/Bank if paid directly
            $creditCoa = null;
            $description = 'Asset acquisition: ' . $asset->name;

            // If credit COA is provided directly, use it
            if ($creditCoaId) {
                $creditCoa = ChartOfAccount::find($creditCoaId);
                if ($creditCoa) {
                    $description .= ' (Manual)';
                }
            }

            // If asset is from purchase order, credit Accounts Payable
            if (!$creditCoa && $asset->purchaseOrder) {
                $accountsPayableCoa = ChartOfAccount::where('code', '2100')->first(); // Hutang Usaha
                if ($accountsPayableCoa) {
                    $creditCoa = $accountsPayableCoa;
                    $description .= ' (PO: ' . $asset->purchaseOrder->po_number . ')';
                }
            }

            // If no purchase order or accounts payable not found, we need manual intervention
            if (!$creditCoa) {
                throw new \Exception('Cannot determine credit account for asset acquisition. Please specify the funding source.');
            }

            // Create journal entries
            // Resolve branch from source
            $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($asset);
            $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($asset);
            $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($asset);

            // Debit: Fixed Asset
            JournalEntry::create([
                'date' => $asset->purchase_date,
                'coa_id' => $assetCoa->id,
                'debit' => $asset->purchase_cost,
                'credit' => 0,
                'description' => $description,
                'journal_type' => 'asset_acquisition',
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'source_type' => 'App\Models\Asset',
                'source_id' => $asset->id,
                'created_by' => Auth::id(),
            ]);

            // Credit: Accounts Payable (or Cash/Bank)
            JournalEntry::create([
                'date' => $asset->purchase_date,
                'coa_id' => $creditCoa->id,
                'debit' => 0,
                'credit' => $asset->purchase_cost,
                'description' => $description,
                'journal_type' => 'asset_acquisition',
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'source_type' => 'App\Models\Asset',
                'source_id' => $asset->id,
                'created_by' => Auth::id(),
            ]);

            // Update asset status
            $asset->update(['status' => 'posted']);
        });
    }

    /**
     * Post journal entries for asset depreciation
     */
    public function postAssetDepreciationJournal(Asset $asset, float $depreciationAmount, string $period): void
    {
        DB::transaction(function () use ($asset, $depreciationAmount, $period) {
            $depreciationExpenseCoa = $asset->depreciationExpenseCoa;
            $accumulatedDepreciationCoa = $asset->accumulatedDepreciationCoa;

            if (!$depreciationExpenseCoa || !$accumulatedDepreciationCoa) {
                throw new \Exception('Depreciation COA accounts not configured for this asset');
            }

            $description = 'Depreciation expense for ' . $asset->name . ' - ' . $period;

            // Resolve branch from source
            $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($asset);
            $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($asset);
            $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($asset);

            // Debit: Depreciation Expense
            JournalEntry::create([
                'date' => now(),
                'coa_id' => $depreciationExpenseCoa->id,
                'debit' => $depreciationAmount,
                'credit' => 0,
                'description' => $description,
                'journal_type' => 'asset_depreciation',
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'source_type' => 'App\Models\Asset',
                'source_id' => $asset->id,
                'created_by' => Auth::id(),
            ]);

            // Credit: Accumulated Depreciation
            JournalEntry::create([
                'date' => now(),
                'coa_id' => $accumulatedDepreciationCoa->id,
                'debit' => 0,
                'credit' => $depreciationAmount,
                'description' => $description,
                'journal_type' => 'asset_depreciation',
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'source_type' => 'App\Models\Asset',
                'source_id' => $asset->id,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Check if asset has posted journal entries
     */
    public function hasPostedJournals(Asset $asset): bool
    {
        return JournalEntry::where('source_type', 'App\Models\Asset')
            ->where('source_id', $asset->id)
            ->exists();
    }

    /**
     * Get journal entries for asset
     */
    public function getAssetJournals(Asset $asset)
    {
        return JournalEntry::where('source_type', 'App\Models\Asset')
            ->where('source_id', $asset->id)
            ->orderBy('date')->get();
    }
}