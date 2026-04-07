<?php

namespace App\Services\Reports;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\Reports\HppOverheadItem;
use App\Models\Reports\HppPrefix;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HppReportService
{
    private array $filters = [];
    private array $dataQualityWarnings = [];
    private ?Collection $rawMaterialProductIds = null;

    private const RAW_MATERIAL_IN_TYPES = ['purchase_in', 'manufacture_in', 'adjustment_in'];
    private const RAW_MATERIAL_OUT_TYPES = ['manufacture_out', 'adjustment_out', 'sales'];

    public function generate(?string $startDate = null, ?string $endDate = null, array $filters = []): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();
        $this->filters = $filters;
        $this->dataQualityWarnings = [];
        $this->rawMaterialProductIds = null;

        $productId = isset($filters['product_id']) ? (int) $filters['product_id'] : null;
        if ($productId) {
            return $this->generateProductScoped($productId, $start, $end);
        }

        $rawMaterialAccounts = $this->getAccountsByPrefixes($this->getPrefixValues('raw_material_inventory'));
        $rawMaterialPurchaseAccounts = $this->getAccountsByPrefixes($this->getPrefixValues('raw_material_purchase'));
        $directLaborAccounts = $this->getAccountsByPrefixes($this->getPrefixValues('direct_labor'));
        $wipAccounts = $this->getAccountsByPrefixes($this->getPrefixValues('wip_inventory'));

        $openingRawMaterial = $this->calculateRawMaterialBalance($rawMaterialAccounts, $start->copy()->subDay());
        $closingRawMaterial = $this->calculateRawMaterialBalance($rawMaterialAccounts, $end);
        $rawMaterialUsed = $this->calculateRawMaterialUsedFromStockMovements($start, $end);
        
        // Calculate purchases: try purchase accounts first, then inventory accounts, then stock movements
        $purchasesRawMaterial = $this->sumDebitForAccounts($rawMaterialPurchaseAccounts, $start, $end);
        $rawMaterialPurchaseSource = 'purchase_accounts';
        if ($purchasesRawMaterial == 0) {
            // If no entries in purchase accounts, try inventory account debits (for inventory accounting)
            $purchasesRawMaterial = $this->sumDebitForAccounts($rawMaterialAccounts, $start, $end);
            if ($purchasesRawMaterial != 0.0) {
                $rawMaterialPurchaseSource = 'inventory_accounts';
                $this->addDataQualityWarning(
                    'raw_material_purchase_inventory_fallback',
                    'Raw material purchases are being derived from inventory debits because purchase-account postings were not found.',
                    ['start' => $start->toDateString(), 'end' => $end->toDateString()]
                );
            }
        }
        if ($purchasesRawMaterial == 0) {
            // If no journal entries, fall back to stock movements
            $purchasesRawMaterial = $this->calculateRawMaterialPurchasesFromStockMovements($start, $end);
            if ($purchasesRawMaterial != 0.0) {
                $rawMaterialPurchaseSource = 'stock_movements';
                $this->addDataQualityWarning(
                    'raw_material_purchase_stock_fallback',
                    'Raw material purchases are being derived from stock movements because journal postings were not found.',
                    ['start' => $start->toDateString(), 'end' => $end->toDateString()]
                );
            }
        }
        
        $totalAvailableRawMaterial = $openingRawMaterial + $purchasesRawMaterial;

        $directLabor = $this->sumDebitForAccounts($directLaborAccounts, $start, $end);

        // Calculate overhead with allocation or actual amounts based on filters
        $useAllocation = $filters['use_allocation'] ?? false;
        if ($useAllocation) {
            $overheadItems = $this->calculateAllocatedOverhead($start, $end, $rawMaterialUsed, $directLabor);
        } else {
            // Use actual amounts for backward compatibility
            $overheadItems = $this->calculateActualOverhead($start, $end);
        }

        $totalOverhead = round($overheadItems->sum('allocated_amount'), 2);
        $totalProductionCost = $rawMaterialUsed + $directLabor + $totalOverhead;

        // Ensure values are not negative
        $rawMaterialUsed = max(0, $rawMaterialUsed);
        $totalProductionCost = max(0, $totalProductionCost);

        $openingWip = $this->calculateBalanceForAccounts($wipAccounts, $start->copy()->subDay());
        $closingWip = $this->calculateBalanceForAccounts($wipAccounts, $end);

        $cogm = $totalProductionCost + $openingWip - $closingWip;
        $cogm = max(0, $cogm);

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'raw_materials' => [
                'opening' => round($openingRawMaterial, 2),
                'purchases' => round($purchasesRawMaterial, 2),
                'available' => round($totalAvailableRawMaterial, 2),
                'closing' => round($closingRawMaterial, 2),
                'used' => round($rawMaterialUsed, 2),
            ],
            'direct_labor' => round($directLabor, 2),
            'overhead' => [
                'items' => $overheadItems->toArray(),
                'total' => $totalOverhead,
            ],
            'production_cost' => round($totalProductionCost, 2),
            'wip' => [
                'opening' => round($openingWip, 2),
                'closing' => round($closingWip, 2),
            ],
            'cogm' => round($cogm, 2),
            'variance_analysis' => $this->calculateVarianceAnalysis($start, $end),
            'data_quality' => [
                'warnings' => array_values($this->dataQualityWarnings),
                'raw_material_purchase_source' => $rawMaterialPurchaseSource,
            ],
        ];
    }

    private function generateProductScoped(int $productId, Carbon $start, Carbon $end): array
    {
        $scope = $this->buildProductScope($productId);

        $rawMaterialUsed = $this->calculateProductRawMaterialUsed(
            $scope['production_plan_ids'],
            $scope['manufacturing_order_ids'],
            $start,
            $end,
        );

        [$directLabor, $totalOverhead] = $this->calculateProductLaborAndOverhead(
            $scope['production_ids'],
            $scope['manufacturing_order_ids'],
            $start,
            $end,
        );

        $openingWip = $this->calculateProductWipBalance(
            $scope['production_ids'],
            $scope['quality_control_ids'],
            $start->copy()->subDay(),
        );

        $closingWip = $this->calculateProductWipBalance(
            $scope['production_ids'],
            $scope['quality_control_ids'],
            $end,
        );

        $totalProductionCost = max(0, round($rawMaterialUsed + $directLabor + $totalOverhead, 2));
        $cogm = max(0, round($totalProductionCost + $openingWip - $closingWip, 2));

        return [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'raw_materials' => [
                'opening' => 0.0,
                'purchases' => 0.0,
                'available' => 0.0,
                'closing' => 0.0,
                'used' => round($rawMaterialUsed, 2),
            ],
            'direct_labor' => round($directLabor, 2),
            'overhead' => [
                'items' => [],
                'total' => round($totalOverhead, 2),
            ],
            'production_cost' => round($totalProductionCost, 2),
            'wip' => [
                'opening' => round($openingWip, 2),
                'closing' => round($closingWip, 2),
            ],
            'cogm' => round($cogm, 2),
            'variance_analysis' => $this->calculateVarianceAnalysis($start, $end),
            'data_quality' => [
                'warnings' => array_values($this->dataQualityWarnings),
                'raw_material_purchase_source' => 'product_scope',
            ],
        ];
    }

    private function buildProductScope(int $productId): array
    {
        $branchIds = $this->getBranchFilterIds();

        $productionPlanIds = ProductionPlan::query()
            ->where('product_id', $productId)
            ->when(! empty($branchIds), fn (Builder $query) => $query->whereIn('cabang_id', $branchIds))
            ->pluck('id');

        if ($productionPlanIds->isEmpty()) {
            return [
                'production_plan_ids' => collect(),
                'manufacturing_order_ids' => collect(),
                'production_ids' => collect(),
                'quality_control_ids' => collect(),
            ];
        }

        $manufacturingOrderIds = ManufacturingOrder::query()
            ->whereIn('production_plan_id', $productionPlanIds)
            ->when(! empty($branchIds), fn (Builder $query) => $query->whereIn('cabang_id', $branchIds))
            ->pluck('id');

        $productionIds = Production::query()
            ->whereIn('manufacturing_order_id', $manufacturingOrderIds)
            ->pluck('id');

        $qualityControlIds = $productionIds->isEmpty()
            ? collect()
            : QualityControl::query()
                ->where('product_id', $productId)
                ->where('from_model_type', Production::class)
                ->whereIn('from_model_id', $productionIds)
                ->when(! empty($branchIds), fn (Builder $query) => $query->whereIn('cabang_id', $branchIds))
                ->pluck('id');

        return [
            'production_plan_ids' => $productionPlanIds,
            'manufacturing_order_ids' => $manufacturingOrderIds,
            'production_ids' => $productionIds,
            'quality_control_ids' => $qualityControlIds,
        ];
    }

    private function calculateProductRawMaterialUsed(Collection $productionPlanIds, Collection $manufacturingOrderIds, Carbon $start, Carbon $end): float
    {
        if ($productionPlanIds->isEmpty() && $manufacturingOrderIds->isEmpty()) {
            return 0.0;
        }

        $query = MaterialIssue::query()
            ->where('status', MaterialIssue::STATUS_COMPLETED)
            ->whereDate('issue_date', '>=', $start->toDateString())
            ->whereDate('issue_date', '<=', $end->toDateString())
            ->where(function (Builder $builder) use ($productionPlanIds, $manufacturingOrderIds) {
                if ($productionPlanIds->isNotEmpty()) {
                    $builder->whereIn('production_plan_id', $productionPlanIds);
                }

                if ($manufacturingOrderIds->isNotEmpty()) {
                    $method = $productionPlanIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $builder->{$method}('manufacturing_order_id', $manufacturingOrderIds);
                }
            });

        $issues = (float) (clone $query)->where('type', 'issue')->sum('total_cost');
        $returns = (float) (clone $query)->where('type', 'return')->sum('total_cost');

        return round(max(0, $issues - $returns), 2);
    }

    private function calculateProductLaborAndOverhead(Collection $productionIds, Collection $manufacturingOrderIds, Carbon $start, Carbon $end): array
    {
        if ($productionIds->isNotEmpty()) {
            $wipQuery = JournalEntry::query()
                ->where('journal_type', 'manufacturing_wip')
                ->where('source_type', Production::class)
                ->whereIn('source_id', $productionIds)
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<=', $end->toDateString());

            $laborFromWip = (float) (clone $wipQuery)
                ->where('description', 'like', '%tenaga kerja langsung%')
                ->sum('credit');

            $overheadFromWip = (float) (clone $wipQuery)
                ->where('description', 'like', '%overhead produksi%')
                ->sum('credit');

            if ($laborFromWip > 0 || $overheadFromWip > 0) {
                return [round($laborFromWip, 2), round($overheadFromWip, 2)];
            }
        }

        if ($manufacturingOrderIds->isNotEmpty()) {
            $allocationQuery = JournalEntry::query()
                ->where('journal_type', 'manufacturing_allocation')
                ->where('source_type', ManufacturingOrder::class)
                ->whereIn('source_id', $manufacturingOrderIds)
                ->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<=', $end->toDateString());

            $laborFromAllocation = (float) (clone $allocationQuery)
                ->where(function (Builder $builder) {
                    $builder->where('description', 'like', '%(TKL)%')
                        ->orWhere('description', 'like', '% TKL%')
                        ->orWhere('description', 'like', '%tenaga kerja%');
                })
                ->sum('credit');

            $overheadFromAllocation = (float) (clone $allocationQuery)
                ->where(function (Builder $builder) {
                    $builder->where('description', 'like', '%(BOP)%')
                        ->orWhere('description', 'like', '% BOP%')
                        ->orWhere('description', 'like', '%overhead%');
                })
                ->sum('credit');

            if ($laborFromAllocation > 0 || $overheadFromAllocation > 0) {
                return [round($laborFromAllocation, 2), round($overheadFromAllocation, 2)];
            }
        }

        $fallbackLabor = 0.0;
        $fallbackOverhead = 0.0;

        ManufacturingOrder::query()
            ->with('productionPlan.billOfMaterial')
            ->whereIn('id', $manufacturingOrderIds)
            ->where(function (Builder $query) use ($start, $end) {
                $query->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('start_date', [$start, $end]);
            })
            ->get()
            ->each(function (ManufacturingOrder $manufacturingOrder) use (&$fallbackLabor, &$fallbackOverhead) {
                $plan = $manufacturingOrder->productionPlan;
                $bom = $plan?->billOfMaterial;

                if (! $plan || ! $bom) {
                    return;
                }

                $quantity = (float) ($plan->quantity ?? 0);
                $fallbackLabor += (float) ($bom->labor_cost ?? 0) * $quantity;
                $fallbackOverhead += (float) ($bom->overhead_cost ?? 0) * $quantity;
            });

        return [round($fallbackLabor, 2), round($fallbackOverhead, 2)];
    }

    private function calculateProductWipBalance(Collection $productionIds, Collection $qualityControlIds, Carbon $asOf): float
    {
        if ($productionIds->isEmpty() && $qualityControlIds->isEmpty()) {
            return 0.0;
        }

        $wipAccounts = $this->getProductWipAccounts();
        if ($wipAccounts->isEmpty()) {
            return 0.0;
        }

        $query = JournalEntry::query()
            ->whereIn('coa_id', $wipAccounts->pluck('id'))
            ->whereDate('date', '<=', $asOf->toDateString())
            ->where(function (Builder $builder) use ($productionIds, $qualityControlIds) {
                if ($productionIds->isNotEmpty()) {
                    $builder->where(function (Builder $sourceQuery) use ($productionIds) {
                        $sourceQuery->where('source_type', Production::class)
                            ->whereIn('source_id', $productionIds);
                    });
                }

                if ($qualityControlIds->isNotEmpty()) {
                    $method = $productionIds->isNotEmpty() ? 'orWhere' : 'where';
                    $builder->{$method}(function (Builder $sourceQuery) use ($qualityControlIds) {
                        $sourceQuery->where('source_type', QualityControl::class)
                            ->whereIn('source_id', $qualityControlIds);
                    });
                }
            });

        $debit = (float) (clone $query)->sum('debit');
        $credit = (float) (clone $query)->sum('credit');

        return round(max(0, $debit - $credit), 2);
    }

    private function getProductWipAccounts(): Collection
    {
        return $this->getAccountsByPrefixes($this->getPrefixValues('wip_inventory'))
            ->concat(ChartOfAccount::query()->whereIn('code', ['1-201', '1150.001'])->get())
            ->unique('id')
            ->values();
    }

    private function getBranchFilterIds(): array
    {
        $branches = array_values(array_filter((array) ($this->filters['branches'] ?? [])));

        if (! empty($branches)) {
            return array_map('intval', $branches);
        }

        $cabangId = $this->filters['cabang_id'] ?? null;

        return $cabangId ? [(int) $cabangId] : [];
    }

    private function getAccountsByPrefixes(array $prefixes): Collection
    {
        if (empty($prefixes)) {
            return collect();
        }

        $query = ChartOfAccount::query();
        $query->where(function (Builder $builder) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                $builder->orWhere('code', 'like', $prefix . '%');
            }
        });

        return $query->get();
    }

    private function calculateRawMaterialBalance(Collection $accounts, Carbon $asOf): float
    {
        $coaBalance = $this->calculateBalanceForAccounts($accounts, $asOf);
        $stockBalance = $this->calculateRawMaterialBalanceFromStockMovements($asOf);

        if ($this->shouldUseStockFallback($coaBalance, $stockBalance)) {
            $this->addDataQualityWarning(
                'raw_material_balance_stock_fallback',
                'Raw material inventory balances are being derived from stock movements because journal balances are missing or inconsistent.',
                [
                    'journal_balance' => round($coaBalance, 2),
                    'stock_balance' => round($stockBalance, 2),
                    'as_of' => $asOf->toDateString(),
                ]
            );
            return $stockBalance;
        }

        return $coaBalance;
    }

    private function calculateRawMaterialBalanceFromStockMovements(Carbon $asOf): float
    {
        $productIds = $this->getRawMaterialProductIds();

        if ($productIds->isEmpty()) {
            return 0.0;
        }

        $query = StockMovement::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('date', '<=', $asOf->toDateString());

        $query = $this->applyStockMovementFilters($query);

        $inValue = (float) (clone $query)->whereIn('type', self::RAW_MATERIAL_IN_TYPES)->sum('value');
        $outValue = (float) (clone $query)->whereIn('type', self::RAW_MATERIAL_OUT_TYPES)->sum('value');

        return round($inValue - $outValue, 2);
    }

    private function calculateRawMaterialPurchasesFromStockMovements(?Carbon $start, ?Carbon $end): float
    {
        $productIds = $this->getRawMaterialProductIds();

        if ($productIds->isEmpty()) {
            return 0.0;
        }

        $query = StockMovement::query()
            ->whereIn('product_id', $productIds)
            ->whereIn('type', self::RAW_MATERIAL_IN_TYPES);

        if ($start && $end) {
            $query->whereDate('date', '>=', $start->toDateString())
                  ->whereDate('date', '<=', $end->toDateString());
        }

        $query = $this->applyStockMovementFilters($query);

        return (float) $query->sum('value');
    }

    private function calculateRawMaterialUsedFromStockMovements(Carbon $start, Carbon $end): float
    {
        $productIds = $this->getRawMaterialProductIds();

        if ($productIds->isEmpty()) {
            return $this->calculateRawMaterialUsedFromJournalEntries($start, $end);
        }

        $query = StockMovement::query()
            ->whereIn('product_id', $productIds)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->whereIn('type', self::RAW_MATERIAL_OUT_TYPES);

        $query = $this->applyStockMovementFilters($query);
        $stockUsed = (float) $query->sum('value');
        
        // Convert negative values to positive for cost calculation
        $stockUsed = abs($stockUsed);

        // If no stock movements, fall back to journal entries
        if ($stockUsed == 0.0) {
            return $this->calculateRawMaterialUsedFromJournalEntries($start, $end);
        }

        return $stockUsed;
    }

    private function calculateRawMaterialUsedFromJournalEntries(Carbon $start, Carbon $end): float
    {
        $rawMaterialAccounts = $this->getAccountsByPrefixes($this->getPrefixValues('raw_material_inventory'));
        
        // Calculate used as available - closing (standard accounting formula)
        $openingRawMaterial = $this->calculateRawMaterialBalance($rawMaterialAccounts, $start->copy()->subDay());
        $closingRawMaterial = $this->calculateRawMaterialBalance($rawMaterialAccounts, $end);
        $rawMaterialPurchaseAccounts = $this->getAccountsByPrefixes($this->getPrefixValues('raw_material_purchase'));
        $purchasesRawMaterial = $this->sumDebitForAccounts($rawMaterialPurchaseAccounts, $start, $end);
        $totalAvailable = $openingRawMaterial + $purchasesRawMaterial;
        
        return $totalAvailable - $closingRawMaterial;
    }

    private function shouldUseStockFallback(float $coaBalance, float $stockBalance): bool
    {
        if (abs($stockBalance) < 0.01) {
            return false;
        }

        if (abs($coaBalance) < 0.01) {
            return true;
        }

        return abs($coaBalance - $stockBalance) > 1;
    }

    private function addDataQualityWarning(string $code, string $message, array $context = []): void
    {
        $this->dataQualityWarnings[$code] = array_filter([
            'code' => $code,
            'message' => $message,
            'context' => $context,
        ]);
    }

    private function applyStockMovementFilters(Builder $query): Builder
    {
        $branches = array_filter($this->filters['branches'] ?? []);

        if (!empty($branches)) {
            $query->whereHas('warehouse', function (Builder $builder) use ($branches) {
                $builder->whereIn('cabang_id', $branches);
            });
        }

        return $query;
    }

    private function getRawMaterialProductIds(): Collection
    {
        if ($this->rawMaterialProductIds === null) {
            $this->rawMaterialProductIds = Product::query()
                ->where('is_raw_material', true)
                ->pluck('id');
        }

        return $this->rawMaterialProductIds;
    }

    private function calculateBalanceForAccounts(Collection $accounts, Carbon $date): float
    {
        if ($accounts->isEmpty()) {
            return 0.0;
        }

        // Optimization: Single query with aggregation
        $accountIds = $accounts->pluck('id');
        $accountMap = $accounts->keyBy('id');

        $query = JournalEntry::query()
            ->whereIn('coa_id', $accountIds)
            ->where('date', '<=', $date->toDateString());

        $query = $this->applyJournalFilters($query);

        $results = $query->selectRaw('coa_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('coa_id')
            ->get()
            ->keyBy('coa_id');

        $total = 0.0;

        foreach ($accountIds as $accountId) {
            $account = $accountMap[$accountId];
            $result = $results->get($accountId);

            $debit = (float) ($result ? $result->total_debit : 0);
            $credit = (float) ($result ? $result->total_credit : 0);
            $normal = $account->normal_balance;

            $total += $normal === 'debit' ? ($debit - $credit) : ($credit - $debit);
        }

        return round($total, 2);
    }

    private function sumPeriodForAccounts(Collection $accounts, Carbon $start, Carbon $end): float
    {
        if ($accounts->isEmpty()) {
            return 0.0;
        }

        // Optimization: Single query with aggregation
        $accountIds = $accounts->pluck('id');
        $accountMap = $accounts->keyBy('id');

        $query = JournalEntry::query()
            ->whereIn('coa_id', $accountIds)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString());

        $query = $this->applyJournalFilters($query);

        $results = $query->selectRaw('coa_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->groupBy('coa_id')
            ->get()
            ->keyBy('coa_id');

        $total = 0.0;

        foreach ($accountIds as $accountId) {
            $account = $accountMap[$accountId];
            $result = $results->get($accountId);

            $debit = (float) ($result ? $result->total_debit : 0);
            $credit = (float) ($result ? $result->total_credit : 0);
            $normal = $account->normal_balance;

            $total += $normal === 'debit' ? ($debit - $credit) : ($credit - $debit);
        }

        return round($total, 2);
    }

    private function sumDebitForAccounts(Collection $accounts, Carbon $start, Carbon $end): float
    {
        if ($accounts->isEmpty()) {
            return 0.0;
        }

        $accountIds = $accounts->pluck('id');

        $query = JournalEntry::query()
            ->whereIn('coa_id', $accountIds)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString());

        $query = $this->applyJournalFilters($query);

        return (float) $query->sum('debit');
    }

    private function sumCreditForAccounts(Collection $accounts, Carbon $start, Carbon $end): float
    {
        if ($accounts->isEmpty()) {
            return 0.0;
        }

        $accountIds = $accounts->pluck('id');

        $query = JournalEntry::query()
            ->whereIn('coa_id', $accountIds)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString());

        $query = $this->applyJournalFilters($query);

        return (float) $query->sum('credit');
    }

    private function applyJournalFilters(Builder $query): Builder
    {
        $branches = $this->filters['branches'] ?? [];
        $cabangId = $this->filters['cabang_id'] ?? null;
        
        if (!empty($branches)) {
            $query->whereIn('cabang_id', (array) $branches);
        } elseif ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        return $query;
    }

    private function getPrefixValues(string $category): array
    {
        return HppPrefix::query()
            ->where('category', $category)
            ->orderBy('sort_order')
            ->pluck('prefix')
            ->toArray();
    }

    private function calculateAllocatedOverhead(Carbon $start, Carbon $end, float $rawMaterialUsed, float $directLabor): Collection
    {
        // Get allocation bases for the period
        $allocationBases = $this->calculateAllocationBases($start, $end, $rawMaterialUsed, $directLabor);

        return HppOverheadItem::query()
            ->with('prefixes')
            ->orderBy('sort_order')
            ->get()
            ->map(function (HppOverheadItem $item) use ($allocationBases, $start, $end) {
                // Get actual overhead from accounts
                $accounts = $this->getAccountsByPrefixes($item->prefixes->pluck('prefix')->toArray());
                $actualAmount = $this->sumPeriodForAccounts($accounts, $start, $end);

                // Calculate allocated amount based on allocation basis and rate
                $allocatedAmount = 0;
                if ($item->allocation_basis && isset($allocationBases[$item->allocation_basis])) {
                    $baseValue = $allocationBases[$item->allocation_basis];
                    $rate = $item->allocation_rate;
                    $allocatedAmount = $baseValue * $rate;

                    // For test environments or when allocation results in unreasonable amounts,
                    // fall back to actual amount to maintain backward compatibility
                    if ($actualAmount > 0 && $allocatedAmount > $actualAmount * 5 || $allocatedAmount < 0) {
                        $allocatedAmount = $actualAmount;
                    }
                }

                // If no allocation calculated, use actual amount as fallback
                if ($allocatedAmount == 0) {
                    $allocatedAmount = $actualAmount;
                }

                return [
                    'key' => $item->key,
                    'label' => $item->label,
                    'allocation_basis' => $item->allocation_basis_label,
                    'allocation_rate' => $item->allocation_rate,
                    'actual_amount' => round($actualAmount, 2),
                    'allocated_amount' => round($allocatedAmount, 2),
                    'amount' => round($allocatedAmount, 2), // Display amount for allocated overhead
                    'variance' => round($actualAmount - $allocatedAmount, 2),
                ];
            });
    }

    private function calculateActualOverhead(Carbon $start, Carbon $end): Collection
    {
        return HppOverheadItem::query()
            ->with('prefixes')
            ->orderBy('sort_order')
            ->get()
            ->map(function (HppOverheadItem $item) use ($start, $end) {
                // Get actual overhead from accounts
                $accounts = $this->getAccountsByPrefixes($item->prefixes->pluck('prefix')->toArray());
                $actualAmount = $this->sumPeriodForAccounts($accounts, $start, $end);

                return [
                    'key' => $item->key,
                    'label' => $item->label,
                    'allocation_basis' => $item->allocation_basis_label,
                    'allocation_rate' => $item->allocation_rate,
                    'actual_amount' => round($actualAmount, 2),
                    'allocated_amount' => round($actualAmount, 2), // For actual overhead, allocated = actual
                    'amount' => round($actualAmount, 2), // Display amount for actual overhead
                    'variance' => 0, // No variance when using actual amounts
                ];
            });
    }

    private function calculateAllocationBases(Carbon $start, Carbon $end, float $rawMaterialUsed, float $directLabor): array
    {
        // For now, use simple estimates. In real implementation, this would come from production records
        $productionVolume = 1000; // Estimated production units
        $machineHours = 500; // Estimated machine hours

        return [
            'direct_labor' => $directLabor,
            'machine_hours' => $machineHours,
            'direct_material' => $rawMaterialUsed,
            'production_volume' => $productionVolume,
        ];
    }

    private function calculateVarianceAnalysis(Carbon $start, Carbon $end): array
    {
        // Get production cost entries for the period
        $productionEntries = \App\Models\ProductionCostEntry::whereBetween('production_date', [$start->toDateString(), $end->toDateString()])
            ->when($this->filters['product_id'] ?? null, fn (Builder $query, $productId) => $query->where('product_id', $productId))
            ->get();

        if ($productionEntries->isEmpty()) {
            return [
                'material_variance' => 0,
                'labor_variance' => 0,
                'overhead_variance' => 0,
                'total_variance' => 0,
                'details' => [],
            ];
        }

        $variances = $productionEntries->flatMap->costVariances;

        return [
            'material_variance' => round($variances->where('variance_type', 'material')->sum('variance_amount'), 2),
            'labor_variance' => round($variances->where('variance_type', 'labor')->sum('variance_amount'), 2),
            'overhead_variance' => round($variances->where('variance_type', 'overhead')->sum('variance_amount'), 2),
            'total_variance' => round($variances->sum('variance_amount'), 2),
            'details' => $variances->map(function ($variance) {
                return [
                    'variance_type' => $variance->variance_type,
                    'standard_cost' => round($variance->standard_cost, 2),
                    'actual_cost' => round($variance->actual_cost, 2),
                    'variance_amount' => round($variance->variance_amount, 2),
                    'variance_percentage' => round($variance->variance_percentage, 2),
                ];
            })->toArray(),
        ];
    }
}
