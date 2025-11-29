<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\MaterialIssue;
use App\Models\Production;
use App\Models\ProductionPlan;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ManufacturingJournalService
{
    /**
     * Generate journal entries for material issue (Pengambilan Bahan Baku)
     * Dr. 1140.02 Barang Dalam Proses
     *     Cr. [inventory_coa_id] Persediaan Bahan Baku (based on actual Material Issue Items)
     */
    public function generateJournalForMaterialIssue(MaterialIssue $materialIssue): void
    {
        if ($materialIssue->type !== 'issue' || !$materialIssue->isCompleted()) {
            throw new \Exception('Material issue must be of type "issue" and status "completed"');
        }

        // Load material issue items with their relationships
        $materialIssue->loadMissing('items.product', 'items.inventoryCoa');

        // Calculate total cost from actual Material Issue Items (not BOM)
        $totalCost = $materialIssue->items->sum('total_cost');

        \Illuminate\Support\Facades\Log::info('Material Issue Journal Generation', [
            'material_issue_id' => $materialIssue->id,
            'production_plan_id' => $materialIssue->production_plan_id,
            'total_items' => $materialIssue->items->count(),
            'total_cost_from_items' => $totalCost,
            'original_total_cost' => $materialIssue->total_cost,
        ]);

        // Get work in progress COA - try BOM first, then fallback
        $bdpCoa = null;
        if ($materialIssue->productionPlan && $materialIssue->productionPlan->billOfMaterial) {
            $bom = $materialIssue->productionPlan->billOfMaterial;
            if ($bom->workInProgressCoa) {
                $bdpCoa = $bom->workInProgressCoa;
            }
        }

        if (!$bdpCoa) {
            // Use specific WIP COA code for consistency
            $bdpCoa = ChartOfAccount::where('code', '1140.02')->first();
            if (!$bdpCoa) {
                $bdpCoa = $this->resolveCoaByCodes(['1140.02', '1140.03', '1140']);
            }
        }

        if (!$bdpCoa) {
            \Illuminate\Support\Facades\Log::error('WIP COA not found for material issue', [
                'material_issue_id' => $materialIssue->id,
                'production_plan_id' => $materialIssue->production_plan_id,
                'searched_codes' => ['1140.02', '1140.03', '1140']
            ]);
            throw new \Exception('Work in progress COA not found. Please set WIP COA in BOM or ensure COA with code 1140.02, 1140.03, or 1140 exists.');
        }

        DB::transaction(function () use ($materialIssue, $bdpCoa, $totalCost) {
            $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($materialIssue);
            $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($materialIssue);
            $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($materialIssue);

            // Delete existing journal entries for this material issue
            JournalEntry::where('source_type', MaterialIssue::class)
                ->where('source_id', $materialIssue->id)
                ->delete();

            // Debit: Barang Dalam Proses (single entry for total)
            $debitEntry = JournalEntry::create([
                'coa_id' => $bdpCoa->id,
                'date' => $materialIssue->issue_date,
                'reference' => $materialIssue->issue_number,
                'description' => 'Pengambilan bahan baku untuk produksi - ' . ($materialIssue->productionPlan->plan_number ?? $materialIssue->manufacturingOrder->mo_number ?? 'N/A'),
                'debit' => $totalCost,
                'credit' => 0,
                'journal_type' => 'manufacturing_issue',
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'source_type' => MaterialIssue::class,
                'source_id' => $materialIssue->id,
            ]);

            // Credit entries: One for each Material Issue Item using actual item costs
            $creditEntries = [];
            foreach ($materialIssue->items as $item) {
                // Use actual cost from Material Issue Item
                $itemCost = $item->total_cost;

                // COA hierarchy: Item-specific → Product-specific → Fallback
                $productInventoryCoa = $item->inventory_coa_id && $item->inventoryCoa ? $item->inventoryCoa :
                                     ($item->product->inventory_coa_id && $item->product->inventoryCoa ? $item->product->inventoryCoa :
                                     $this->resolveCoaByCodes(['1140.10', '1140.01', '1140']));

                if (!$productInventoryCoa) {
                    throw new \Exception('Inventory COA not found for product: ' . $item->product->name . '. Please set COA in Material Issue Item, Product, or ensure COA with code 1140, 1140.01, or 1140.10 exists.');
                }

                $creditEntry = JournalEntry::create([
                    'coa_id' => $productInventoryCoa->id,
                    'date' => $materialIssue->issue_date,
                    'reference' => $materialIssue->issue_number,
                    'description' => 'Pengambilan bahan baku: ' . $item->product->name . ' untuk produksi - ' . ($materialIssue->productionPlan->plan_number ?? $materialIssue->manufacturingOrder->mo_number ?? 'N/A'),
                    'debit' => 0,
                    'credit' => $itemCost,
                    'journal_type' => 'manufacturing_issue',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => MaterialIssue::class,
                    'source_id' => $materialIssue->id,
                ]);

                $creditEntries[] = $creditEntry;
            }

            \Illuminate\Support\Facades\Log::info('Material Issue Journal Entries Created', [
                'material_issue_id' => $materialIssue->id,
                'total_cost_from_items' => $totalCost,
                'debit_entry_id' => $debitEntry->id,
                'debit_amount' => $debitEntry->debit,
                'credit_entries_count' => count($creditEntries),
                'total_credit_amount' => collect($creditEntries)->sum('credit'),
                'cost_breakdown' => $materialIssue->items->map(function ($item) {
                    return [
                        'product' => $item->product->name,
                        'quantity' => $item->quantity,
                        'cost_per_unit' => $item->cost_per_unit,
                        'total_cost' => $item->total_cost,
                    ];
                })->toArray(),
            ]);
        });
    }

    /**
     * Generate journal entries for material return (Retur Bahan Baku)
     * Dr. [inventory_coa_id] Persediaan Bahan Baku (based on actual Material Issue Items)
     *     Cr. 1140.02 Barang Dalam Proses
     */
    public function generateJournalForMaterialReturn(MaterialIssue $materialIssue): void
    {
        if ($materialIssue->type !== 'return' || !$materialIssue->isCompleted()) {
            throw new \Exception('Material issue must be of type "return" and status "completed"');
        }

        // Load material issue items with their relationships
        $materialIssue->loadMissing('items.product', 'items.inventoryCoa');

        // Calculate total cost from actual Material Issue Items
        $totalCost = $materialIssue->items->sum('total_cost');

        \Illuminate\Support\Facades\Log::info('Material Return Journal Generation', [
            'material_issue_id' => $materialIssue->id,
            'production_plan_id' => $materialIssue->production_plan_id,
            'total_items' => $materialIssue->items->count(),
            'total_cost_from_items' => $totalCost,
            'original_total_cost' => $materialIssue->total_cost,
        ]);

        // Get work in progress COA - try BOM first, then fallback
        $bdpCoa = null;
        if ($materialIssue->productionPlan && $materialIssue->productionPlan->billOfMaterial) {
            $bom = $materialIssue->productionPlan->billOfMaterial;
            if ($bom->workInProgressCoa) {
                $bdpCoa = $bom->workInProgressCoa;
            }
        }

        if (!$bdpCoa) {
            // Use specific WIP COA code for consistency
            $bdpCoa = ChartOfAccount::where('code', '1140.02')->first();
            if (!$bdpCoa) {
                $bdpCoa = $this->resolveCoaByCodes(['1140.02', '1140.03', '1140']);
            }
        }

        if (!$bdpCoa) {
            \Illuminate\Support\Facades\Log::error('WIP COA not found for material return', [
                'material_issue_id' => $materialIssue->id,
                'production_plan_id' => $materialIssue->production_plan_id,
                'searched_codes' => ['1140.02', '1140.03', '1140']
            ]);
            throw new \Exception('Work in progress COA not found. Please set WIP COA in BOM or ensure COA with code 1140.02, 1140.03, or 1140 exists.');
        }

        DB::transaction(function () use ($materialIssue, $bdpCoa, $totalCost) {
            $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($materialIssue);
            $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($materialIssue);
            $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($materialIssue);

            // Delete existing journal entries for this material issue
            JournalEntry::where('source_type', MaterialIssue::class)
                ->where('source_id', $materialIssue->id)
                ->delete();

            // Debit entries: One for each Material Issue Item using actual item costs
            $debitEntries = [];
            foreach ($materialIssue->items as $item) {
                // Use actual cost from Material Issue Item
                $itemCost = $item->total_cost;

                // COA hierarchy: Item-specific → Product-specific → Fallback
                $productInventoryCoa = $item->inventory_coa_id && $item->inventoryCoa ? $item->inventoryCoa :
                                     ($item->product->inventory_coa_id && $item->product->inventoryCoa ? $item->product->inventoryCoa :
                                     $this->resolveCoaByCodes(['1140.10', '1140.01', '1140']));

                if (!$productInventoryCoa) {
                    throw new \Exception('Inventory COA not found for product: ' . $item->product->name . '. Please set COA in Material Issue Item, Product, or ensure COA with code 1140, 1140.01, or 1140.10 exists.');
                }

                $debitEntry = JournalEntry::create([
                    'coa_id' => $productInventoryCoa->id,
                    'date' => $materialIssue->issue_date,
                    'reference' => $materialIssue->issue_number,
                    'description' => 'Retur bahan baku: ' . $item->product->name . ' dari produksi - ' . ($materialIssue->productionPlan->plan_number ?? $materialIssue->manufacturingOrder->mo_number ?? 'N/A'),
                    'debit' => $itemCost,
                    'credit' => 0,
                    'journal_type' => 'manufacturing_return',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => MaterialIssue::class,
                    'source_id' => $materialIssue->id,
                ]);

                $debitEntries[] = $debitEntry;
            }

            // Credit: Barang Dalam Proses (single entry for total)
            $creditEntry = JournalEntry::create([
                'coa_id' => $bdpCoa->id,
                'date' => $materialIssue->issue_date,
                'reference' => $materialIssue->issue_number,
                'description' => 'Retur bahan baku dari produksi - ' . ($materialIssue->productionPlan->plan_number ?? $materialIssue->manufacturingOrder->mo_number ?? 'N/A'),
                'debit' => 0,
                'credit' => $totalCost,
                'journal_type' => 'manufacturing_return',
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'source_type' => MaterialIssue::class,
                'source_id' => $materialIssue->id,
            ]);

            \Illuminate\Support\Facades\Log::info('Material Return Journal Entries Created', [
                'material_issue_id' => $materialIssue->id,
                'total_cost_from_items' => $totalCost,
                'debit_entries_count' => count($debitEntries),
                'total_debit_amount' => collect($debitEntries)->sum('debit'),
                'credit_entry_id' => $creditEntry->id,
                'credit_amount' => $creditEntry->credit,
                'cost_breakdown' => $materialIssue->items->map(function ($item) {
                    return [
                        'product' => $item->product->name,
                        'quantity' => $item->quantity,
                        'cost_per_unit' => $item->cost_per_unit,
                        'total_cost' => $item->total_cost,
                    ];
                })->toArray(),
            ]);
        });
    }

    /**
     * Generate journal entries for production completion (Penyelesaian Barang Jadi)
     * Dr. [BOM.finished_goods_coa_id or 1140.03] Persediaan Barang Jadi
     *     Cr. [BOM.work_in_progress_coa_id or 1140.02] Persediaan Barang dalam Proses
     */
    public function generateJournalForProductionCompletion(Production $production): void
    {
        if ($production->status !== 'finished') {
            throw new \Exception('Production must be in "finished" status');
        }

        $manufacturingOrder = $production->manufacturingOrder;
        if (!$manufacturingOrder) {
            throw new \Exception('Production does not have a related Manufacturing Order');
        }

        // Load required relationships
        $manufacturingOrder->load(['productionPlan.product.billOfMaterial', 'productionPlan.billOfMaterial']);

        // Total cost to move from BDP to Finished Goods must include:
        // - Raw material issues (type=issue, completed) for this MO
        // - Minus raw material returns (type=return, completed) for this MO
        // - Plus any labor & overhead allocations posted to BDP and linked to this MO
        $totalCost = $this->calculateManufacturingOrderBDPTotal($manufacturingOrder);
        if ($totalCost <= 0) {
            throw new \Exception('Total BDP cost for MO is zero or negative; cannot post completion journal');
        }

        // Get BOM with COA relationships loaded
        $bom = $manufacturingOrder->productionPlan->product->billOfMaterial->firstWhere('is_active', true)
            ?? $manufacturingOrder->productionPlan?->billOfMaterial;

        if (!$bom) {
            throw new \Exception('No active BOM found for Manufacturing Order: ' . $manufacturingOrder->mo_number);
        }

        $bom->loadMissing(['finishedGoodsCoa', 'workInProgressCoa']);

        // Get COA with dynamic hierarchy from BOM, fallback to hardcoded codes
        $bdpCoa = $bom->workInProgressCoa ?? $this->resolveCoaByCodes(['1140.02']); // Barang Dalam Proses
        if (!$bdpCoa) {
            throw new \Exception('Work in progress COA not found. Please set WIP COA in BOM or ensure COA with code 1140.02 exists.');
        }

        $barangJadiCoa = $bom->finishedGoodsCoa ?? $this->resolveCoaByCodes(['1140.03']); // Persediaan Barang Jadi
        if (!$barangJadiCoa) {
            throw new \Exception('Finished goods COA not found. Please set finished goods COA in BOM or ensure COA with code 1140.03 exists.');
        }

        DB::transaction(function () use ($production, $bdpCoa, $barangJadiCoa, $totalCost, $manufacturingOrder) {
            $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($production);
            $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($production);
            $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($production);
            // Delete existing journal entries for this production
            JournalEntry::where('source_type', Production::class)
                ->where('source_id', $production->id)
                ->delete();

            // Debit: Persediaan Barang Jadi
            JournalEntry::create([
                'coa_id' => $barangJadiCoa->id,
                'date' => $production->production_date,
                'reference' => $production->production_number,
                'description' => 'Penyelesaian produksi - ' . $manufacturingOrder->mo_number . ' (' . $manufacturingOrder->productionPlan->product->name . ')',
                'debit' => $totalCost,
                'credit' => 0,
                'journal_type' => 'manufacturing_completion',
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'source_type' => Production::class,
                'source_id' => $production->id,
            ]);

            // Credit: Barang Dalam Proses
            JournalEntry::create([
                'coa_id' => $bdpCoa->id,
                'date' => $production->production_date,
                'reference' => $production->production_number,
                'description' => 'Penyelesaian produksi - ' . $manufacturingOrder->mo_number . ' (' . $manufacturingOrder->productionPlan->product->name . ')',
                'debit' => 0,
                'credit' => $totalCost,
                'journal_type' => 'manufacturing_completion',
                'cabang_id' => $branchId,
                'department_id' => $departmentId,
                'project_id' => $projectId,
                'source_type' => Production::class,
                'source_id' => $production->id,
            ]);
        });
    }

    /**
     * Calculate total BDP cost for a Manufacturing Order, including:
     * - Raw material costs from BOM (BB)
     * - Labor costs from BOM (TKL)
     * - Overhead costs from BOM (BOP)
     * - Adjusted by actual material issues and returns
     */
    protected function calculateManufacturingOrderBDPTotal(\App\Models\ManufacturingOrder $mo): float
    {
        // Get BOM from ProductionPlan
        $bom = $mo->productionPlan?->billOfMaterial;

        if (!$bom || !$bom->is_active) {
            throw new \Exception('No active BOM found for Manufacturing Order: ' . $mo->mo_number);
        }

        $bom->loadMissing('items.product');

        // Calculate standard costs from BOM
        $materialCost = $bom->items->sum(function ($item) {
            return (float) $item->quantity * (float) ($item->product->cost_price ?? 0);
        });

        $laborCost = (float) ($bom->labor_cost ?? 0);
        $overheadCost = (float) ($bom->overhead_cost ?? 0);

        // Standard total cost = (BB + TKL + BOP) × quantity
        $standardTotalCost = ($materialCost + $laborCost + $overheadCost) * (float) $mo->productionPlan->quantity;

        // Adjust with actual material issues and returns
        $issuesTotal = \App\Models\MaterialIssue::where('manufacturing_order_id', $mo->id)
            ->where('status', 'completed')
            ->where('type', 'issue')
            ->sum('total_cost');

        $returnsTotal = \App\Models\MaterialIssue::where('manufacturing_order_id', $mo->id)
            ->where('status', 'completed')
            ->where('type', 'return')
            ->sum('total_cost');

        // Use actual material cost if available, otherwise use standard
        $actualMaterialCost = $issuesTotal - $returnsTotal;
        $materialCostToUse = $actualMaterialCost > 0 ? $actualMaterialCost : $materialCost * (float) $mo->productionPlan->quantity;

        // Sum labor & overhead allocations posted to BDP and linked to this MO via source_type/source_id
        $bdpCoa = $bom->workInProgressCoa ?? $this->resolveCoaByCodes(['1140.02', '1140.03', '1140']);
        $allocationsTotal = 0;
        if ($bdpCoa) {
            $allocationsTotal = JournalEntry::where('coa_id', $bdpCoa->id)
                ->where('journal_type', 'manufacturing_allocation')
                ->where('source_type', \App\Models\ManufacturingOrder::class)
                ->where('source_id', $mo->id)
                ->sum('debit');
        }

        // Final total = Actual Material Cost + Labor Cost + Overhead Cost + Allocations
        return max(0, $materialCostToUse + ($laborCost * (float) $mo->productionPlan->quantity) + ($overheadCost * (float) $mo->productionPlan->quantity) + $allocationsTotal);
    }

    /**
     * Allocate labor and overhead costs to WIP (Alokasi TKL & BOP ke BDP)
     * Dr. 1140.02 BDP - Tenaga Kerja & Overhead
     *     Cr. Kas/Beban
     * 
     * This is typically done manually or periodically
     */
    public function allocateLaborAndOverhead(
        float $laborCost,
        float $overheadCost,
        string $reference,
        Carbon $date,
        ?int $expenseCoa = null,
        string $description = 'Alokasi biaya TKL & BOP ke produksi',
        ?\App\Models\ManufacturingOrder $manufacturingOrder = null
    ): void {
        $totalCost = $laborCost + $overheadCost;

        if ($totalCost <= 0) {
            throw new \Exception('Total labor and overhead cost must be greater than 0');
        }

        // Get COA with fallback options
        $bdpCoa = $this->resolveCoaByCodes(['1140.02', '1140.03', '1140']);
        if (!$bdpCoa) {
            throw new \Exception('Work in progress COA not found. Please ensure COA with code 1140.02, 1140.03, or 1140 exists.');
        }
        
        // Default expense COA if not provided (bisa pakai akun Kas atau Beban)
        $expenseCoa = $expenseCoa ?? ChartOfAccount::where('code', 'LIKE', '6%')->first()?->id;

        if (!$expenseCoa) {
            throw new \Exception('Expense COA not found. Please provide a valid expense COA ID.');
        }

        DB::transaction(function () use ($bdpCoa, $expenseCoa, $totalCost, $reference, $date, $description, $manufacturingOrder) {
            // Debit: Barang Dalam Proses
            JournalEntry::create([
                'coa_id' => $bdpCoa->id,
                'date' => $date,
                'reference' => $reference,
                'description' => $description,
                'debit' => $totalCost,
                'credit' => 0,
                'journal_type' => 'manufacturing_allocation',
                'cabang_id' => null,
                'source_type' => $manufacturingOrder ? \App\Models\ManufacturingOrder::class : null,
                'source_id' => $manufacturingOrder ? $manufacturingOrder->id : null,
            ]);

            // Credit: Beban/Kas
            JournalEntry::create([
                'coa_id' => $expenseCoa,
                'date' => $date,
                'reference' => $reference,
                'description' => $description,
                'debit' => 0,
                'credit' => $totalCost,
                'journal_type' => 'manufacturing_allocation',
                'cabang_id' => null,
                'source_type' => $manufacturingOrder ? \App\Models\ManufacturingOrder::class : null,
                'source_id' => $manufacturingOrder ? $manufacturingOrder->id : null,
            ]);
        });
    }

    /**
     * Get BDP (Barang Dalam Proses) balance
     */
    public function getBDPBalance(): float
    {
        $bdpCoa = $this->resolveCoaByCodes(['1140.02', '1140.03', '1140']);
        
        if (!$bdpCoa) {
            return 0;
        }

        $totalDebit = JournalEntry::where('coa_id', $bdpCoa->id)->sum('debit');
        $totalCredit = JournalEntry::where('coa_id', $bdpCoa->id)->sum('credit');

        return $totalDebit - $totalCredit;
    }

    /**
     * Get detailed BDP transactions
     */
    public function getBDPTransactions()
    {
        $bdpCoa = $this->resolveCoaByCodes(['1140.02', '1140.03', '1140']);
        
        if (!$bdpCoa) {
            return collect();
        }

        return JournalEntry::where('coa_id', $bdpCoa->id)
            ->with(['source', 'coa'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Resolve COA by trying multiple codes in order of preference
     * Returns the first COA found from the provided codes array
     */
    protected function resolveCoaByCodes(array $codes): ?ChartOfAccount
    {
        foreach ($codes as $code) {
            if (!$code) {
                continue;
            }

            $coa = ChartOfAccount::where('code', $code)->first();
            if ($coa) {
                return $coa;
            }
        }

        return null;
    }
}
