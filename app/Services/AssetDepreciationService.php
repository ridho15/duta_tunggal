<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\JournalEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AssetDepreciationService
{
    /**
     * Generate monthly depreciation for an asset
     */
    public function generateMonthlyDepreciation(Asset $asset, Carbon $date)
    {
        try {
            DB::beginTransaction();
            
            // Check if depreciation already exists for this month
            $existing = AssetDepreciation::where('asset_id', $asset->id)
                ->where('period_month', $date->month)
                ->where('period_year', $date->year)
                ->where('status', 'recorded')
                ->first();
            
            if ($existing) {
                throw new \Exception('Penyusutan untuk periode ini sudah ada');
            }
            
            // Check if asset is active
            if ($asset->status !== 'active') {
                throw new \Exception('Aset tidak aktif');
            }
            
            // Check if asset usage date is before depreciation date
            if ($asset->usage_date->gt($date)) {
                throw new \Exception('Tanggal penyusutan tidak boleh sebelum tanggal pakai aset');
            }
            
            // Calculate accumulated depreciation
            $previousTotal = $asset->depreciationEntries()
                ->where('status', 'recorded')
                ->sum('amount');
            
            $newTotal = $previousTotal + $asset->monthly_depreciation;
            $newBookValue = $asset->purchase_cost - $newTotal;
            
            // Check if fully depreciated
            if ($newBookValue < $asset->salvage_value) {
                throw new \Exception('Aset sudah disusutkan penuh');
            }
            
            // Create depreciation entry
            $depreciation = AssetDepreciation::create([
                'asset_id' => $asset->id,
                'depreciation_date' => $date,
                'period_month' => $date->month,
                'period_year' => $date->year,
                'amount' => $asset->monthly_depreciation,
                'accumulated_total' => $newTotal,
                'book_value' => $newBookValue,
                'status' => 'recorded',
                'notes' => 'Penyusutan otomatis bulan ' . $date->format('F Y'),
            ]);
            
            // Create journal entries
            $this->createJournalEntries($asset, $depreciation, $date);
            
            // Update asset
            $asset->updateAccumulatedDepreciation();
            
            DB::commit();
            
            return $depreciation;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
    
    /**
     * Generate depreciation for all active assets
     */
    public function generateAllMonthlyDepreciation(Carbon $date)
    {
        $assets = Asset::where('status', 'active')
            ->where('usage_date', '<=', $date)
            ->get();
        
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];
        
        foreach ($assets as $asset) {
            try {
                $this->generateMonthlyDepreciation($asset, $date);
                $results['success']++;
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'asset' => $asset->name,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Create journal entries for depreciation
     */
    protected function createJournalEntries(Asset $asset, AssetDepreciation $depreciation, Carbon $date)
    {
        $reference = 'DEP-' . $date->format('Ym') . '-' . $asset->id;
        $description = 'Penyusutan ' . $asset->name . ' untuk bulan ' . $date->format('F Y');
        
        $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($asset);
        $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($asset);
        $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($asset);
        // Debit: Beban Penyusutan
        $debitEntry = JournalEntry::create([
            'coa_id' => $asset->depreciation_expense_coa_id,
            'date' => $date,
            'reference' => $reference,
            'description' => $description,
            'debit' => $depreciation->amount,
            'credit' => 0,
            'journal_type' => 'depreciation',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => AssetDepreciation::class,
            'source_id' => $depreciation->id,
        ]);
        
        // Credit: Akumulasi Penyusutan
        $creditEntry = JournalEntry::create([
            'coa_id' => $asset->accumulated_depreciation_coa_id,
            'date' => $date,
            'reference' => $reference,
            'description' => $description,
            'debit' => 0,
            'credit' => $depreciation->amount,
            'journal_type' => 'depreciation',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => AssetDepreciation::class,
            'source_id' => $depreciation->id,
        ]);
        
        // Link journal entry to depreciation
        $depreciation->journal_entry_id = $debitEntry->id;
        $depreciation->save();
        
        return [$debitEntry, $creditEntry];
    }
    
    /**
     * Reverse depreciation
     */
    public function reverseDepreciation(AssetDepreciation $depreciation)
    {
        try {
            DB::beginTransaction();
            
            // Mark as reversed
            $depreciation->status = 'reversed';
            $depreciation->save();
            
            // Delete related journal entries
            JournalEntry::where('source_type', AssetDepreciation::class)
                ->where('source_id', $depreciation->id)
                ->delete();
            
            // Update asset
            $depreciation->asset->updateAccumulatedDepreciation();
            
            DB::commit();
            
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}