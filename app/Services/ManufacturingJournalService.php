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
    protected const TEMPORARY_PRODUCTION_CODES = ['1400.04', '1150', '1140'];

    /**
     * Generate journal entries for material issue (Pengambilan Bahan Baku)
     * Dr. 1400.04 Pos Sementara Produksi
     *     Cr. [inventory_coa_id] Persediaan Bahan Baku (based on actual Material Issue Items)
     */
    public function generateJournalForMaterialIssue(MaterialIssue $materialIssue): void
    {
        if ($materialIssue->type !== 'issue' || !$materialIssue->isCompleted()) {
            throw new \Exception('Material issue harus bertipe "issue" dan berstatus "completed".');
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

        $bdpCoa = $this->resolveTemporaryProductionCoa();

        if (!$bdpCoa) {
            \Illuminate\Support\Facades\Log::error('Temporary production COA not found for material issue', [
                'material_issue_id' => $materialIssue->id,
                'production_plan_id' => $materialIssue->production_plan_id,
                'searched_codes' => self::TEMPORARY_PRODUCTION_CODES,
            ]);
            throw new \Exception('COA Pos Sementara Produksi tidak ditemukan. Pastikan COA 1400.04 tersedia.');
        }

        DB::transaction(function () use ($materialIssue, $bdpCoa, $totalCost) {
            $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($materialIssue);
            $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($materialIssue);
            $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($materialIssue);

            // Delete existing journal entries for this material issue
            JournalEntry::where('source_type', MaterialIssue::class)
                ->where('source_id', $materialIssue->id)
                ->delete();

            // Debit: Pos Sementara Produksi (single entry for total)
            $debitEntry = JournalEntry::create([
                'coa_id' => $bdpCoa->id,
                'date' => $materialIssue->issue_date,
                'reference' => $materialIssue->issue_number,
                'description' => 'Pengambilan bahan baku ke pos sementara produksi - ' . ($materialIssue->productionPlan->plan_number ?? $materialIssue->manufacturingOrder->mo_number ?? 'N/A'),
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
                $productInventoryCoa = $item->inventory_coa_id && $item->inventoryCoa
                    ? $item->inventoryCoa
                    : ($item->product?->resolveInventoryCoaOrDefault()
                        ?? $this->resolveCoaByCodes(['1-101', '1140.10', '1140.01', '1140']));

                if (!$productInventoryCoa) {
                    throw new \Exception('COA persediaan tidak ditemukan untuk produk: ' . $item->product->name . '. Atur COA pada Material Issue Item, Product, atau pastikan COA 1-101 (Persediaan Bahan Baku) tersedia.');
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
        *     Cr. 1400.04 Pos Sementara Produksi
     */
    public function generateJournalForMaterialReturn(MaterialIssue $materialIssue): void
    {
        if ($materialIssue->type !== 'return' || !$materialIssue->isCompleted()) {
            throw new \Exception('Material issue harus bertipe "return" dan berstatus "completed".');
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

        $bdpCoa = $this->resolveTemporaryProductionCoa();

        if (!$bdpCoa) {
            \Illuminate\Support\Facades\Log::error('Temporary production COA not found for material return', [
                'material_issue_id' => $materialIssue->id,
                'production_plan_id' => $materialIssue->production_plan_id,
                'searched_codes' => self::TEMPORARY_PRODUCTION_CODES,
            ]);
            throw new \Exception('COA Pos Sementara Produksi tidak ditemukan. Pastikan COA 1400.04 tersedia.');
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
                $productInventoryCoa = $item->inventory_coa_id && $item->inventoryCoa
                    ? $item->inventoryCoa
                    : ($item->product?->resolveInventoryCoaOrDefault()
                        ?? $this->resolveCoaByCodes(['1-101', '1140.10', '1140.01', '1140']));

                if (!$productInventoryCoa) {
                    throw new \Exception('COA persediaan tidak ditemukan untuk produk: ' . $item->product->name . '. Atur COA pada Material Issue Item, Product, atau pastikan COA 1-101 (Persediaan Bahan Baku) tersedia.');
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

            // Credit: Pos Sementara Produksi (single entry for total)
            $creditEntry = JournalEntry::create([
                'coa_id' => $bdpCoa->id,
                'date' => $materialIssue->issue_date,
                'reference' => $materialIssue->issue_number,
                'description' => 'Retur bahan baku dari pos sementara produksi - ' . ($materialIssue->productionPlan->plan_number ?? $materialIssue->manufacturingOrder->mo_number ?? 'N/A'),
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
          * Dr. [Product.inventory_coa_id or 1140.02] Persediaan Barang Produksi
        *     Cr. [1400.04 / BOM.work_in_progress_coa_id fallback] Pos Sementara Produksi
     */
    public function generateJournalForProductionCompletion(Production $production): void
    {
        if ($production->status !== 'finished') {
            throw new \Exception('Produksi harus berstatus "finished".');
        }

        $manufacturingOrder = $production->manufacturingOrder;
        if (!$manufacturingOrder) {
            throw new \Exception('Produksi tidak memiliki Manufacturing Order terkait.');
        }

        // Load required relationships
        $manufacturingOrder->load(['productionPlan.product.billOfMaterial', 'productionPlan.billOfMaterial']);

        $bom = $manufacturingOrder->productionPlan->product->billOfMaterial->firstWhere('is_active', true)
            ?? $manufacturingOrder->productionPlan?->billOfMaterial;

        if (!$bom) {
            throw new \Exception('BOM aktif tidak ditemukan untuk Manufacturing Order: ' . $manufacturingOrder->mo_number . '.');
        }

        $this->syncLaborAndOverheadAllocations($manufacturingOrder, Carbon::parse($production->production_date), $production->production_number);

        // Total cost to move from BDP to Finished Goods must include:
        // - Raw material issues (type=issue, completed) for this MO
        // - Minus raw material returns (type=return, completed) for this MO
        // - Plus any labor & overhead allocations posted to BDP and linked to this MO
        $totalCost = $this->calculateManufacturingOrderBDPTotal($manufacturingOrder);
        if ($totalCost <= 0) {
            throw new \Exception('Total biaya BDP untuk MO bernilai nol atau negatif; jurnal penyelesaian tidak dapat dibuat.');
        }

        $bdpCoa = $this->resolveTemporaryProductionCoa();
        if (!$bdpCoa) {
            throw new \Exception('COA Pos Sementara Produksi tidak ditemukan. Pastikan COA 1400.04 tersedia.');
        }

        $finishedProduct = $manufacturingOrder->productionPlan->product;
        $barangJadiCoa = $this->resolveFinishedGoodsInventoryCoa($finishedProduct);
        if (!$barangJadiCoa) {
            throw new \Exception('COA persediaan barang jadi tidak ditemukan pada product. Pastikan inventory COA product sudah diatur.');
        }

        DB::transaction(function () use ($production, $bdpCoa, $barangJadiCoa, $totalCost, $manufacturingOrder) {
            $branchId = app(\App\Services\JournalBranchResolver::class)->resolve($production);
            $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($production);
            $projectId = app(\App\Services\JournalBranchResolver::class)->resolveProject($production);
            // Delete existing journal entries for this production
            JournalEntry::where('source_type', Production::class)
                ->where('source_id', $production->id)
                ->delete();

            // Debit: Persediaan Barang Produksi
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
    * - Raw material costs from actual material issue/return
    * - Labor costs from BOM (TKL)
    * - Overhead costs from BOM (BOP)
     */
    protected function calculateManufacturingOrderBDPTotal(\App\Models\ManufacturingOrder $mo): float
    {
        // Get BOM from ProductionPlan
        $bom = $mo->productionPlan?->billOfMaterial;

        if (!$bom || !$bom->is_active) {
            throw new \Exception('BOM aktif tidak ditemukan untuk Manufacturing Order: ' . $mo->mo_number . '.');
        }

        $bom->loadMissing('items.product');

        // Calculate standard costs from BOM
        $materialCost = $bom->items->sum(function ($item) {
            return (float) $item->quantity * (float) ($item->product->cost_price ?? 0);
        });

        $laborCost = (float) ($bom->labor_cost ?? 0);
        $overheadCost = (float) ($bom->overhead_cost ?? 0);

        // Adjust with actual material issues and returns
        $issuesTotal = $this->sumCompletedMaterialIssueTotalForManufacturingOrder($mo, 'issue');
        $returnsTotal = $this->sumCompletedMaterialIssueTotalForManufacturingOrder($mo, 'return');

        $actualMaterialCost = $issuesTotal - $returnsTotal;
        $materialCostToUse = $actualMaterialCost > 0 ? $actualMaterialCost : $materialCost * (float) $mo->productionPlan->quantity;

        $bdpCoa = $this->resolveTemporaryProductionCoa();
        $allocationsTotal = 0;
        if ($bdpCoa) {
            $allocationsTotal = JournalEntry::where('coa_id', $bdpCoa->id)
                ->where('journal_type', 'manufacturing_allocation')
                ->where('source_type', \App\Models\ManufacturingOrder::class)
                ->where('source_id', $mo->id)
                ->sum('debit');
        }

        return max(0, $materialCostToUse + $allocationsTotal);
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
        ?int $laborExpenseCoa = null,
        ?int $overheadExpenseCoa = null,
        string $description = 'Alokasi biaya TKL & BOP ke produksi',
        ?\App\Models\ManufacturingOrder $manufacturingOrder = null
    ): void {
        $totalCost = $laborCost + $overheadCost;

        if ($totalCost <= 0) {
            throw new \Exception('Total biaya tenaga kerja dan overhead harus lebih besar dari 0.');
        }

        $bdpCoa = $this->resolveTemporaryProductionCoa();
        if (!$bdpCoa) {
            throw new \Exception('COA Pos Sementara Produksi tidak ditemukan. Pastikan COA 1400.04 tersedia.');
        }

        $laborExpenseCoa = $laborExpenseCoa ?? $this->resolveDefaultManufacturingLaborCoaId();
        $overheadExpenseCoa = $overheadExpenseCoa ?? $this->resolveDefaultManufacturingOverheadCoaId();

        if ($laborCost > 0 && ! $laborExpenseCoa) {
            throw new \Exception('COA TKL produksi tidak ditemukan. Silakan pilih COA TKL yang valid.');
        }

        if ($overheadCost > 0 && ! $overheadExpenseCoa) {
            throw new \Exception('COA overhead produksi tidak ditemukan. Silakan pilih COA overhead yang valid.');
        }

        DB::transaction(function () use (
            $bdpCoa,
            $laborExpenseCoa,
            $overheadExpenseCoa,
            $laborCost,
            $overheadCost,
            $totalCost,
            $reference,
            $date,
            $description,
            $manufacturingOrder
        ) {
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

            if ($laborCost > 0) {
                JournalEntry::create([
                    'coa_id' => $laborExpenseCoa,
                    'date' => $date,
                    'reference' => $reference,
                    'description' => $description . ' (TKL)',
                    'debit' => 0,
                    'credit' => $laborCost,
                    'journal_type' => 'manufacturing_allocation',
                    'cabang_id' => null,
                    'source_type' => $manufacturingOrder ? \App\Models\ManufacturingOrder::class : null,
                    'source_id' => $manufacturingOrder ? $manufacturingOrder->id : null,
                ]);
            }

            if ($overheadCost > 0) {
                JournalEntry::create([
                    'coa_id' => $overheadExpenseCoa,
                    'date' => $date,
                    'reference' => $reference,
                    'description' => $description . ' (BOP)',
                    'debit' => 0,
                    'credit' => $overheadCost,
                    'journal_type' => 'manufacturing_allocation',
                    'cabang_id' => null,
                    'source_type' => $manufacturingOrder ? \App\Models\ManufacturingOrder::class : null,
                    'source_id' => $manufacturingOrder ? $manufacturingOrder->id : null,
                ]);
            }
        });
    }

    /**
      * Get temporary production balance
     */
    public function getBDPBalance(): float
    {
          $bdpCoa = $this->resolveTemporaryProductionCoa();
        
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
        $bdpCoa = $this->resolveTemporaryProductionCoa();
        
        if (!$bdpCoa) {
            return collect();
        }

        return JournalEntry::where('coa_id', $bdpCoa->id)
            ->with(['source', 'coa'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function syncLaborAndOverheadAllocations(
        \App\Models\ManufacturingOrder $manufacturingOrder,
        Carbon $date,
        string $reference,
        ?int $expenseCoaId = null
    ): void {
        $bom = $manufacturingOrder->productionPlan?->billOfMaterial;

        if (! $bom || ! $bom->is_active) {
            return;
        }

        $temporaryProductionCoa = $this->resolveTemporaryProductionCoa();
        if (! $temporaryProductionCoa) {
            return;
        }

        $manufacturingOrder->loadMissing([
            'productionPlan.billOfMaterial.laborCoa',
            'productionPlan.billOfMaterial.overheadCoa',
            'productionPlan.product.manufacturingLaborCoa',
            'productionPlan.product.manufacturingOverheadCoa',
        ]);

        $quantity = (float) ($manufacturingOrder->productionPlan->quantity ?? 0);
        $laborAmount = (float) ($bom->labor_cost ?? 0) * $quantity;
        $overheadAmount = (float) ($bom->overhead_cost ?? 0) * $quantity;
        $product = $manufacturingOrder->productionPlan->product;
        $laborExpenseCoaId = $expenseCoaId
            ?? $this->resolveManufacturingCreditCoaId($bom, $product, 'labor');
        $overheadExpenseCoaId = $expenseCoaId
            ?? $this->resolveManufacturingCreditCoaId($bom, $product, 'overhead');

        if ($laborAmount > 0 && ! $laborExpenseCoaId) {
            throw new \Exception('COA TKL produksi belum dikonfigurasi pada product/BOM dan fallback tidak ditemukan.');
        }

        if ($overheadAmount > 0 && ! $overheadExpenseCoaId) {
            throw new \Exception('COA overhead produksi belum dikonfigurasi pada product/BOM dan fallback tidak ditemukan.');
        }

        DB::transaction(function () use ($manufacturingOrder, $date, $reference, $temporaryProductionCoa, $laborExpenseCoaId, $overheadExpenseCoaId, $laborAmount, $overheadAmount) {
            $branchResolver = app(\App\Services\JournalBranchResolver::class);
            $branchId = $branchResolver->resolve($manufacturingOrder);
            $departmentId = $branchResolver->resolveDepartment($manufacturingOrder);
            $projectId = $branchResolver->resolveProject($manufacturingOrder);

            JournalEntry::where('journal_type', 'manufacturing_allocation')
                ->where('source_type', \App\Models\ManufacturingOrder::class)
                ->where('source_id', $manufacturingOrder->id)
                ->delete();

            $this->createAutomaticAllocationEntry(
                $temporaryProductionCoa->id,
                $laborExpenseCoaId,
                $date,
                $reference,
                $laborAmount,
                'Alokasi otomatis TKL - ' . $manufacturingOrder->mo_number,
                $branchId,
                $departmentId,
                $projectId,
                $manufacturingOrder
            );

            $this->createAutomaticAllocationEntry(
                $temporaryProductionCoa->id,
                $overheadExpenseCoaId,
                $date,
                $reference,
                $overheadAmount,
                'Alokasi otomatis BOP - ' . $manufacturingOrder->mo_number,
                $branchId,
                $departmentId,
                $projectId,
                $manufacturingOrder
            );
        });
    }

    protected function createAutomaticAllocationEntry(
        int $debitCoaId,
        int $creditCoaId,
        Carbon $date,
        string $reference,
        float $amount,
        string $description,
        ?int $branchId,
        ?int $departmentId,
        ?int $projectId,
        \App\Models\ManufacturingOrder $manufacturingOrder
    ): void {
        if ($amount <= 0) {
            return;
        }

        JournalEntry::create([
            'coa_id' => $debitCoaId,
            'date' => $date,
            'reference' => $reference,
            'description' => $description,
            'debit' => $amount,
            'credit' => 0,
            'journal_type' => 'manufacturing_allocation',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => \App\Models\ManufacturingOrder::class,
            'source_id' => $manufacturingOrder->id,
        ]);

        JournalEntry::create([
            'coa_id' => $creditCoaId,
            'date' => $date,
            'reference' => $reference,
            'description' => $description,
            'debit' => 0,
            'credit' => $amount,
            'journal_type' => 'manufacturing_allocation',
            'cabang_id' => $branchId,
            'department_id' => $departmentId,
            'project_id' => $projectId,
            'source_type' => \App\Models\ManufacturingOrder::class,
            'source_id' => $manufacturingOrder->id,
        ]);
    }

    /**
     * Generate journal entries for production in progress (Produksi Dalam Proses / WIP entry)
     * Dr. 1-201  Persediaan Barang Dalam Proses - WIP INVENTORY  (material + labor + overhead)
     *     Cr. 1400.04 Pos Sementara Produksi                        (material cost portion)
      *     Cr. 5230   Biaya Tenaga Kerja Proses Produksi              (labor + overhead portion)
     */
    public function generateJournalForProductionInProgress(Production $production): void
    {
        $manufacturingOrder = $production->manufacturingOrder;
        if (!$manufacturingOrder || !$manufacturingOrder->id) {
            return;
        }

                // Material cost = net of completed material issues for this MO
                $issuesTotal = $this->sumCompletedMaterialIssueTotalForManufacturingOrder($manufacturingOrder, 'issue');
                $returnsTotal = $this->sumCompletedMaterialIssueTotalForManufacturingOrder($manufacturingOrder, 'return');
        $materialCost = max(0, (float)$issuesTotal - (float)$returnsTotal);

        // Labor + overhead cost = BOM amounts × production plan quantity
        $bom = $manufacturingOrder->productionPlan?->billOfMaterial;
        $quantity = (float)($manufacturingOrder->productionPlan?->quantity ?? 0);
        $laborAmount = 0.0;
        $overheadAmount = 0.0;
        if ($bom && $bom->is_active) {
            $laborAmount = (float)($bom->labor_cost ?? 0) * $quantity;
            $overheadAmount = (float)($bom->overhead_cost ?? 0) * $quantity;
        }

        $laborOverheadCost = $laborAmount + $overheadAmount;
        $totalWipCost = $materialCost + $laborOverheadCost;
        if ($totalWipCost <= 0) {
            return;
        }

        $wipCoa = $this->resolveCoaByCodes(['1-201']);
        if (!$wipCoa) {
            throw new \Exception('COA Persediaan Barang Dalam Proses - WIP (1-201) tidak ditemukan. Pastikan COA 1-201 tersedia.');
        }
        $posSementaraCoa = $this->resolveTemporaryProductionCoa();
        if (!$posSementaraCoa) {
            throw new \Exception('COA Pos Sementara Produksi (1400.04) tidak ditemukan.');
        }
        $finishedProduct = $manufacturingOrder->productionPlan?->product;
        $laborCoaId = $this->resolveManufacturingCreditCoaId($bom, $finishedProduct, 'labor');
        $overheadCoaId = $this->resolveManufacturingCreditCoaId($bom, $finishedProduct, 'overhead');

        if ($laborAmount > 0 && ! $laborCoaId) {
            throw new \Exception('COA TKL produksi belum dikonfigurasi pada product/BOM dan fallback tidak ditemukan.');
        }

        if ($overheadAmount > 0 && ! $overheadCoaId) {
            throw new \Exception('COA overhead produksi belum dikonfigurasi pada product/BOM dan fallback tidak ditemukan.');
        }

        DB::transaction(function () use (
            $production, $manufacturingOrder, $wipCoa, $posSementaraCoa,
            $totalWipCost, $materialCost, $laborAmount, $overheadAmount, $laborCoaId, $overheadCoaId
        ) {
            $branchId    = app(\App\Services\JournalBranchResolver::class)->resolve($production);
            $departmentId = app(\App\Services\JournalBranchResolver::class)->resolveDepartment($production);
            $projectId   = app(\App\Services\JournalBranchResolver::class)->resolveProject($production);
            $date        = $production->production_date ?? now();
            $reference   = $production->production_number;
            $description = 'Produksi in progress - ' . $manufacturingOrder->mo_number;

            // Remove previously posted WIP journal for this production (idempotent)
            JournalEntry::where('source_type', Production::class)
                ->where('source_id', $production->id)
                ->where('journal_type', 'manufacturing_wip')
                ->delete();

            // Debit: WIP Inventory (1-201) = total cost
            JournalEntry::create([
                'coa_id'       => $wipCoa->id,
                'date'         => $date,
                'reference'    => $reference,
                'description'  => $description,
                'debit'        => $totalWipCost,
                'credit'       => 0,
                'journal_type' => 'manufacturing_wip',
                'cabang_id'    => $branchId,
                'department_id' => $departmentId,
                'project_id'   => $projectId,
                'source_type'  => Production::class,
                'source_id'    => $production->id,
            ]);

            // Credit: Pos Sementara Produksi (1400.04) = material cost
            if ($materialCost > 0) {
                JournalEntry::create([
                    'coa_id'       => $posSementaraCoa->id,
                    'date'         => $date,
                    'reference'    => $reference,
                    'description'  => $description . ' (bahan baku)',
                    'debit'        => 0,
                    'credit'       => $materialCost,
                    'journal_type' => 'manufacturing_wip',
                    'cabang_id'    => $branchId,
                    'department_id' => $departmentId,
                    'project_id'   => $projectId,
                    'source_type'  => Production::class,
                    'source_id'    => $production->id,
                ]);
            }

            if ($laborAmount > 0) {
                JournalEntry::create([
                    'coa_id'       => $laborCoaId,
                    'date'         => $date,
                    'reference'    => $reference,
                    'description'  => $description . ' (tenaga kerja langsung)',
                    'debit'        => 0,
                    'credit'       => $laborAmount,
                    'journal_type' => 'manufacturing_wip',
                    'cabang_id'    => $branchId,
                    'department_id' => $departmentId,
                    'project_id'   => $projectId,
                    'source_type'  => Production::class,
                    'source_id'    => $production->id,
                ]);
            }

            if ($overheadAmount > 0) {
                JournalEntry::create([
                    'coa_id'       => $overheadCoaId,
                    'date'         => $date,
                    'reference'    => $reference,
                    'description'  => $description . ' (overhead produksi)',
                    'debit'        => 0,
                    'credit'       => $overheadAmount,
                    'journal_type' => 'manufacturing_wip',
                    'cabang_id'    => $branchId,
                    'department_id' => $departmentId,
                    'project_id'   => $projectId,
                    'source_type'  => Production::class,
                    'source_id'    => $production->id,
                ]);
            }
        });
    }

    protected function sumCompletedMaterialIssueTotalForManufacturingOrder(\App\Models\ManufacturingOrder $mo, string $type): float
    {
        $issuesTotal = \App\Models\MaterialIssue::where('manufacturing_order_id', $mo->id)
            ->where('status', 'completed')
            ->where('type', $type)
            ->sum('total_cost');

        if ($issuesTotal > 0 || ! $mo->production_plan_id) {
            return (float) $issuesTotal;
        }

        return (float) \App\Models\MaterialIssue::whereNull('manufacturing_order_id')
            ->where('production_plan_id', $mo->production_plan_id)
            ->where('status', 'completed')
            ->where('type', $type)
            ->sum('total_cost');
    }

    protected function resolveTemporaryProductionCoa(): ?ChartOfAccount
    {
        return $this->resolveCoaByCodes(self::TEMPORARY_PRODUCTION_CODES);
    }

    protected function resolveDefaultManufacturingExpenseCoaId(): ?int
    {
        return $this->resolveCoaByCodes(['5100.10', '6000', '6100.10', '6100'])?->id
            ?? ChartOfAccount::where('type', 'Expense')->orderBy('code')->value('id');
    }

    protected function resolveDefaultManufacturingLaborCoaId(): ?int
    {
        return $this->resolveCoaByCodes(['5230', '6-201', '6000'])?->id
            ?? $this->resolveDefaultManufacturingExpenseCoaId();
    }

    protected function resolveDefaultManufacturingOverheadCoaId(): ?int
    {
        return $this->resolveCoaByCodes(['6-202', '6100.10', '6100', '6000'])?->id
            ?? $this->resolveDefaultManufacturingExpenseCoaId();
    }

    protected function resolveManufacturingCreditCoaId(?\App\Models\BillOfMaterial $bom, ?\App\Models\Product $product, string $type): ?int
    {
        if ($type === 'labor') {
            return $bom?->labor_coa_id
                ?? $product?->manufacturing_labor_coa_id
                ?? $this->resolveDefaultManufacturingLaborCoaId();
        }

        return $bom?->overhead_coa_id
            ?? $product?->manufacturing_overhead_coa_id
            ?? $this->resolveDefaultManufacturingOverheadCoaId();
    }

    protected function resolveFinishedGoodsInventoryCoa(?\App\Models\Product $product): ?ChartOfAccount
    {
        return $product?->resolveInventoryCoaOrDefault()
            ?? $this->resolveCoaByCodes(['1140.02']);
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
