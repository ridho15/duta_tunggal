<?php

namespace App\Services\Reports;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Reports\HppOverheadItem;
use App\Models\Reports\HppPrefix;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class HppReportService
{
    private array $filters = [];
    private ?Collection $rawMaterialProductIds = null;

    private const RAW_MATERIAL_IN_TYPES = ['purchase_in', 'manufacture_in', 'adjustment_in'];
    private const RAW_MATERIAL_OUT_TYPES = ['manufacture_out', 'adjustment_out', 'sales'];

    public function generate(?string $startDate = null, ?string $endDate = null, array $filters = []): array
    {
        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();
        $this->filters = $filters;
        $this->rawMaterialProductIds = null;

    $rawMaterialAccounts = $this->getAccountsByPrefixes($this->getPrefixValues('raw_material_inventory'));
    $rawMaterialPurchaseAccounts = $this->getAccountsByPrefixes($this->getPrefixValues('raw_material_purchase'));
    $directLaborAccounts = $this->getAccountsByPrefixes($this->getPrefixValues('direct_labor'));
    $wipAccounts = $this->getAccountsByPrefixes($this->getPrefixValues('wip_inventory'));

        $openingRawMaterial = $this->calculateRawMaterialBalance($rawMaterialAccounts, $start->copy()->subDay());
        $purchasesRawMaterial = $this->sumPeriodForAccounts($rawMaterialPurchaseAccounts, $start, $end);
        $totalAvailableRawMaterial = $openingRawMaterial + $purchasesRawMaterial;
        $closingRawMaterial = $this->calculateRawMaterialBalance($rawMaterialAccounts, $end);
        $rawMaterialUsed = $totalAvailableRawMaterial - $closingRawMaterial;

        $directLabor = $this->sumPeriodForAccounts($directLaborAccounts, $start, $end);

        $overheadItems = HppOverheadItem::query()
            ->with('prefixes')
            ->orderBy('sort_order')
            ->get()
            ->map(function (HppOverheadItem $item) use ($start, $end) {
                $accounts = $this->getAccountsByPrefixes($item->prefixes->pluck('prefix')->toArray());
                $amount = $this->sumPeriodForAccounts($accounts, $start, $end);

                return [
                    'key' => $item->key,
                    'label' => $item->label,
                    'amount' => round($amount, 2),
                ];
            });

        $totalOverhead = round($overheadItems->sum('amount'), 2);
        $totalProductionCost = $rawMaterialUsed + $directLabor + $totalOverhead;

        $openingWip = $this->calculateBalanceForAccounts($wipAccounts, $start->copy()->subDay());
        $closingWip = $this->calculateBalanceForAccounts($wipAccounts, $end);

        $cogm = $totalProductionCost + $openingWip - $closingWip;

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
        ];
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
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()]);

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

    private function applyJournalFilters(Builder $query): Builder
    {
        $branches = array_filter($this->filters['branches'] ?? []);
        if (!empty($branches)) {
            $query->whereIn('cabang_id', $branches);
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
}
