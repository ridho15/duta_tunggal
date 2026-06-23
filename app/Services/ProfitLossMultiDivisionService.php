<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * Service: Profit & Loss Multiple By Division
 *
 * Generates a P&L report broken down by each Cabang (division/branch).
 * Each division appears as a pair of columns: Balance and Vtc% (vertical %).
 *
 * Report structure:
 *   REVENUE (4xxx)
 *   ├─ account rows
 *   └─ Total Revenue
 *   COST OF GOODS SOLD (5xxx)
 *   └─ Total COGS
 *   GROSS PROFIT
 *   OPERATING EXPENSES (6xxx grouped by parent)
 *   └─ Total Operating Expenses
 *   OPERATING PROFIT
 *   OTHER INCOME / EXPENSE (7xxx, 8xxx, 9xxx)
 *   NET PROFIT
 */
class ProfitLossMultiDivisionService
{
    /**
     * Generate the full P&L multi-division dataset.
     *
     * @param  string       $startDate   Y-m-d
     * @param  string       $endDate     Y-m-d
     * @param  array<int>   $cabangIds   Empty = all active cabangs
     * @return array{
     *   divisions: array,
     *   rows: array,
     *   totals: array,
     *   period: array,
     * }
     */
    public function generate(string $startDate, string $endDate, array $cabangIds = []): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end   = Carbon::parse($endDate)->endOfDay();

        // 1. Determine which divisions to show
        $divisions = $this->getDivisions($cabangIds);

        // 2. Load all active COA accounts (Revenue + Expense) ordered by code
        $allAccounts = ChartOfAccount::whereIn('type', ['Revenue', 'Expense'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->keyBy('id');

        // 3. Bulk load journal entry balances grouped by coa_id + cabang_id
        $balanceMap = $this->loadBalanceMap($allAccounts->keys()->toArray(), $start, $end, $divisions);

        // 4. Build COA tree (parent → children)
        $childrenOf = $this->buildChildMap($allAccounts);

        // 5. Identify leaf accounts (no children among loaded accounts)
        $leafIds = $allAccounts->keys()
            ->filter(fn ($id) => empty($childrenOf[$id]))
            ->flip()
            ->toArray();

        // 6. Build rows for each section
        $divIds = $divisions->pluck('id')->toArray();

        [$revenueRows, $totalRevenue] = $this->buildRevenueRows($allAccounts, $childrenOf, $leafIds, $balanceMap, $divIds);
        [$cogsRows,    $totalCogs]    = $this->buildCogsRows($allAccounts, $childrenOf, $leafIds, $balanceMap, $divIds);
        $grossProfit                  = $this->subtractVectors($totalRevenue, $totalCogs, $divIds);
        [$opexSections, $totalOpex]   = $this->buildOpexSections($allAccounts, $childrenOf, $leafIds, $balanceMap, $divIds);
        $operatingProfit              = $this->subtractVectors($grossProfit, $totalOpex, $divIds);
        [$otherRows,   $totalOther]   = $this->buildOtherRows($allAccounts, $childrenOf, $leafIds, $balanceMap, $divIds);
        $netProfit                    = $this->addVectors($operatingProfit, $totalOther, $divIds);

        // 7. Compute Vtc% (vertical %) = balance / total_revenue * 100
        $vtcRevenue  = $this->computeVtc($totalRevenue, $totalRevenue, $divIds);
        $vtcCogs     = $this->computeVtc($totalCogs,    $totalRevenue, $divIds);
        $vtcGross    = $this->computeVtc($grossProfit,  $totalRevenue, $divIds);
        $vtcOpex     = $this->computeVtc($totalOpex,    $totalRevenue, $divIds);
        $vtcOpProfit = $this->computeVtc($operatingProfit, $totalRevenue, $divIds);
        $vtcNet      = $this->computeVtc($netProfit,    $totalRevenue, $divIds);

        return [
            'divisions'         => $divisions->values()->toArray(),
            'revenue_rows'      => $revenueRows,
            'total_revenue'     => $totalRevenue,
            'cogs_rows'         => $cogsRows,
            'total_cogs'        => $totalCogs,
            'gross_profit'      => $grossProfit,
            'opex_sections'     => $opexSections,
            'total_opex'        => $totalOpex,
            'operating_profit'  => $operatingProfit,
            'other_rows'        => $otherRows,
            'total_other'       => $totalOther,
            'net_profit'        => $netProfit,
            'vtc'               => [
                'revenue'          => $vtcRevenue,
                'cogs'             => $vtcCogs,
                'gross_profit'     => $vtcGross,
                'total_opex'       => $vtcOpex,
                'operating_profit' => $vtcOpProfit,
                'net_profit'       => $vtcNet,
            ],
            'period' => [
                'start' => $startDate,
                'end'   => $endDate,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get divisions to include in the report.
     */
    private function getDivisions(array $cabangIds): \Illuminate\Database\Eloquent\Collection
    {
        $query = Cabang::query()->orderBy('kode');

        if (!empty($cabangIds)) {
            $query->whereIn('id', $cabangIds);
        }

        return $query->get();
    }

    /**
     * Single bulk query: sum debit/credit per (coa_id, cabang_id).
     *
     * Returns: [ coa_id => [ cabang_id => ['debit' => x, 'credit' => y] ] ]
     */
    private function loadBalanceMap(array $coaIds, Carbon $start, Carbon $end, $divisions): array
    {
        if (empty($coaIds)) {
            return [];
        }

        $divIds = $divisions->pluck('id')->toArray();

        $rows = JournalEntry::withoutGlobalScopes()
            ->selectRaw('coa_id, cabang_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->whereIn('coa_id', $coaIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when(!empty($divIds), fn ($q) => $q->whereIn('cabang_id', $divIds))
            ->groupBy('coa_id', 'cabang_id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->coa_id][$row->cabang_id] = [
                'debit'  => (float) $row->total_debit,
                'credit' => (float) $row->total_credit,
            ];
        }

        return $map;
    }

    /**
     * Build map: parent_id => [child_ids].
     */
    private function buildChildMap(\Illuminate\Database\Eloquent\Collection $accounts): array
    {
        $map = [];
        foreach ($accounts as $account) {
            if ($account->parent_id !== null) {
                $map[$account->parent_id][] = $account->id;
            }
        }
        return $map;
    }

    /**
     * Get all leaf descendant IDs of a given account recursively.
     *
     * @return int[]
     */
    private function getLeafDescendants(int $accountId, array $childrenOf, array $leafIds): array
    {
        if (isset($leafIds[$accountId])) {
            return [$accountId];
        }

        $children = $childrenOf[$accountId] ?? [];
        $leaves   = [];

        foreach ($children as $childId) {
            $leaves = array_merge($leaves, $this->getLeafDescendants($childId, $childrenOf, $leafIds));
        }

        return $leaves;
    }

    /**
     * Compute balance for a single account in a single division.
     * Revenue: credit - debit; Expense: debit - credit.
     */
    private function getAccountBalance(int $coaId, int $divId, string $type, array $balanceMap): float
    {
        $entry = $balanceMap[$coaId][$divId] ?? ['debit' => 0.0, 'credit' => 0.0];

        if ($type === 'Revenue') {
            return $entry['credit'] - $entry['debit'];
        }
        // Expense
        return $entry['debit'] - $entry['credit'];
    }

    /**
     * Sum balances of all leaf descendants for each division.
     *
     * @return array<int, float>  [div_id => amount]
     */
    private function sumLeafBalances(array $leafIds, array $divIds, string $type, array $balanceMap): array
    {
        $totals = array_fill_keys($divIds, 0.0);

        foreach ($leafIds as $leafId) {
            foreach ($divIds as $divId) {
                $totals[$divId] += $this->getAccountBalance($leafId, $divId, $type, $balanceMap);
            }
        }

        return $totals;
    }

    /**
     * Build revenue section rows and return [rows, total_per_division].
     *
     * @return array{0: array, 1: array<int,float>}
     */
    private function buildRevenueRows(
        \Illuminate\Database\Eloquent\Collection $allAccounts,
        array $childrenOf,
        array $leafIds,
        array $balanceMap,
        array $divIds
    ): array {
        $revenueAccounts = $allAccounts->filter(fn ($a) => $a->type === 'Revenue');

        // Top-level revenue parents (no parent or parent not in revenue set)
        $revenueIds = $revenueAccounts->pluck('id')->flip()->toArray();
        $topLevelRevenue = $revenueAccounts->filter(
            fn ($a) => $a->parent_id === null || !isset($revenueIds[$a->parent_id])
        );

        $rows = [];
        $grandTotal = array_fill_keys($divIds, 0.0);

        foreach ($topLevelRevenue as $account) {
            // Section header row
            $rows[] = [
                'type'  => 'section_header',
                'code'  => $account->code,
                'name'  => strtoupper($account->name),
                'level' => 0,
            ];

            // Children (leaf accounts)
            $children = $childrenOf[$account->id] ?? [];
            $groupTotal = array_fill_keys($divIds, 0.0);

            foreach ($children as $childId) {
                if (!isset($allAccounts[$childId])) {
                    continue;
                }
                $child = $allAccounts[$childId];

                // Only leaf-level accounts
                if (!isset($leafIds[$childId])) {
                    // Recurse into sub-groups
                    [$subRows, $subTotal] = $this->buildGroupRows($childId, $allAccounts, $childrenOf, $leafIds, $balanceMap, $divIds, 'Revenue', 1);
                    $rows = array_merge($rows, $subRows);
                    foreach ($divIds as $d) {
                        $groupTotal[$d] += $subTotal[$d];
                    }
                    continue;
                }

                $balances = [];
                foreach ($divIds as $d) {
                    $b = $this->getAccountBalance($childId, $d, 'Revenue', $balanceMap);
                    $balances[$d]      = $b;
                    $groupTotal[$d]   += $b;
                }

                $rows[] = [
                    'type'     => 'account',
                    'code'     => $child->code,
                    'name'     => $child->name,
                    'level'    => 1,
                    'balances' => $balances,
                ];
            }

            // Subtotal for this parent group
            $rows[] = [
                'type'     => 'subtotal',
                'name'     => 'Total ' . strtoupper($account->name),
                'level'    => 0,
                'balances' => $groupTotal,
                'bold'     => true,
            ];

            foreach ($divIds as $d) {
                $grandTotal[$d] += $groupTotal[$d];
            }
        }

        // Grand total revenue row
        $rows[] = [
            'type'     => 'total_revenue',
            'name'     => 'Total Revenue',
            'level'    => 0,
            'balances' => $grandTotal,
            'bold'     => true,
        ];

        return [$rows, $grandTotal];
    }

    /**
     * Recursively build rows for a non-leaf account group.
     *
     * @return array{0: array, 1: array<int,float>}
     */
    private function buildGroupRows(
        int $accountId,
        \Illuminate\Database\Eloquent\Collection $allAccounts,
        array $childrenOf,
        array $leafIds,
        array $balanceMap,
        array $divIds,
        string $type,
        int $level
    ): array {
        if (!isset($allAccounts[$accountId])) {
            return [[], array_fill_keys($divIds, 0.0)];
        }

        $account  = $allAccounts[$accountId];
        $children = $childrenOf[$accountId] ?? [];
        $rows     = [];
        $total    = array_fill_keys($divIds, 0.0);

        // Header for this sub-group
        $rows[] = [
            'type'  => 'section_header',
            'code'  => $account->code,
            'name'  => strtoupper($account->name),
            'level' => $level,
        ];

        foreach ($children as $childId) {
            if (!isset($allAccounts[$childId])) {
                continue;
            }
            $child = $allAccounts[$childId];

            if (!isset($leafIds[$childId])) {
                [$subRows, $subTotal] = $this->buildGroupRows($childId, $allAccounts, $childrenOf, $leafIds, $balanceMap, $divIds, $type, $level + 1);
                $rows = array_merge($rows, $subRows);
                foreach ($divIds as $d) {
                    $total[$d] += $subTotal[$d];
                }
                continue;
            }

            $balances = [];
            foreach ($divIds as $d) {
                $b = $this->getAccountBalance($childId, $d, $type, $balanceMap);
                $balances[$d]  = $b;
                $total[$d]    += $b;
            }

            $rows[] = [
                'type'     => 'account',
                'code'     => $child->code,
                'name'     => $child->name,
                'level'    => $level + 1,
                'balances' => $balances,
            ];
        }

        // Subtotal row for the group
        $rows[] = [
            'type'     => 'subtotal',
            'name'     => 'Total ' . strtoupper($account->name),
            'level'    => $level,
            'balances' => $total,
            'bold'     => true,
        ];

        return [$rows, $total];
    }

    /**
     * Build COGS section (accounts starting with '5').
     *
     * @return array{0: array, 1: array<int,float>}
     */
    private function buildCogsRows(
        \Illuminate\Database\Eloquent\Collection $allAccounts,
        array $childrenOf,
        array $leafIds,
        array $balanceMap,
        array $divIds
    ): array {
        $cogsAccounts = $allAccounts->filter(fn ($a) => $a->type === 'Expense' && str_starts_with($a->code, '5'));

        $rows = [];
        $grandTotal = array_fill_keys($divIds, 0.0);

        if ($cogsAccounts->isEmpty()) {
            return [$rows, $grandTotal];
        }

        $cogsIds = $cogsAccounts->pluck('id')->flip()->toArray();
        $topLevel = $cogsAccounts->filter(
            fn ($a) => $a->parent_id === null || !isset($cogsIds[$a->parent_id])
        );

        foreach ($topLevel as $account) {
            [$subRows, $subTotal] = $this->buildGroupRows(
                $account->id, $allAccounts, $childrenOf, $leafIds, $balanceMap, $divIds, 'Expense', 0
            );
            $rows = array_merge($rows, $subRows);
            foreach ($divIds as $d) {
                $grandTotal[$d] += $subTotal[$d];
            }
        }

        $rows[] = [
            'type'     => 'total_cogs',
            'name'     => 'Total Cost Of Goods Sold',
            'level'    => 0,
            'balances' => $grandTotal,
            'bold'     => true,
        ];

        return [$rows, $grandTotal];
    }

    /**
     * Build Operating Expense sections grouped by top-level parent (6xxx).
     *
     * @return array{0: array, 1: array<int,float>}
     */
    private function buildOpexSections(
        \Illuminate\Database\Eloquent\Collection $allAccounts,
        array $childrenOf,
        array $leafIds,
        array $balanceMap,
        array $divIds
    ): array {
        // Operating expenses: type = Expense, code starts with 6
        $opexAccounts = $allAccounts->filter(fn ($a) => $a->type === 'Expense' && str_starts_with($a->code, '6'));

        $sections    = [];
        $grandTotal  = array_fill_keys($divIds, 0.0);

        if ($opexAccounts->isEmpty()) {
            return [$sections, $grandTotal];
        }

        $opexIds  = $opexAccounts->pluck('id')->flip()->toArray();
        $topLevel = $opexAccounts->filter(
            fn ($a) => $a->parent_id === null || !isset($opexIds[$a->parent_id])
        );

        foreach ($topLevel as $account) {
            [$sectionRows, $sectionTotal] = $this->buildGroupRows(
                $account->id, $allAccounts, $childrenOf, $leafIds, $balanceMap, $divIds, 'Expense', 0
            );

            $sections[] = [
                'account' => $account,
                'rows'    => $sectionRows,
                'total'   => $sectionTotal,
            ];

            foreach ($divIds as $d) {
                $grandTotal[$d] += $sectionTotal[$d];
            }
        }

        return [$sections, $grandTotal];
    }

    /**
     * Build Other Income / Expense (7xxx, 8xxx, 9xxx).
     *
     * @return array{0: array, 1: array<int,float>}
     */
    private function buildOtherRows(
        \Illuminate\Database\Eloquent\Collection $allAccounts,
        array $childrenOf,
        array $leafIds,
        array $balanceMap,
        array $divIds
    ): array {
        // Revenues outside 4xxx (e.g. 7xxx other income)
        $otherRevenue = $allAccounts->filter(fn ($a) => $a->type === 'Revenue' && !str_starts_with($a->code, '4'));
        // Expenses outside 5xxx and 6xxx (e.g. 8xxx interest, 9xxx other)
        $otherExpense = $allAccounts->filter(
            fn ($a) => $a->type === 'Expense' && !str_starts_with($a->code, '5') && !str_starts_with($a->code, '6')
        );

        $rows  = [];
        $total = array_fill_keys($divIds, 0.0);

        foreach ($otherRevenue as $account) {
            if (!isset($leafIds[$account->id])) {
                continue;
            }
            $balances = [];
            foreach ($divIds as $d) {
                $b = $this->getAccountBalance($account->id, $d, 'Revenue', $balanceMap);
                $balances[$d] = $b;
                $total[$d]   += $b;
            }
            $rows[] = [
                'type'     => 'account',
                'code'     => $account->code,
                'name'     => $account->name,
                'level'    => 1,
                'balances' => $balances,
                'subtype'  => 'other_income',
            ];
        }

        foreach ($otherExpense as $account) {
            if (!isset($leafIds[$account->id])) {
                continue;
            }
            $balances = [];
            foreach ($divIds as $d) {
                $b  = $this->getAccountBalance($account->id, $d, 'Expense', $balanceMap);
                $balances[$d] = -$b; // subtract from net
                $total[$d]   -= $b;
            }
            $rows[] = [
                'type'     => 'account',
                'code'     => $account->code,
                'name'     => $account->name,
                'level'    => 1,
                'balances' => $balances,
                'subtype'  => 'other_expense',
            ];
        }

        return [$rows, $total];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Vector arithmetic helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Subtract vector b from vector a: result[d] = a[d] - b[d].
     */
    public function subtractVectors(array $a, array $b, array $divIds): array
    {
        $result = array_fill_keys($divIds, 0.0);
        foreach ($divIds as $d) {
            $result[$d] = ($a[$d] ?? 0.0) - ($b[$d] ?? 0.0);
        }
        return $result;
    }

    /**
     * Add vector b to vector a: result[d] = a[d] + b[d].
     */
    public function addVectors(array $a, array $b, array $divIds): array
    {
        $result = array_fill_keys($divIds, 0.0);
        foreach ($divIds as $d) {
            $result[$d] = ($a[$d] ?? 0.0) + ($b[$d] ?? 0.0);
        }
        return $result;
    }

    /**
     * Compute Vtc% for each division: value[d] / base[d] * 100.
     * Returns 0 when base is 0.
     */
    public function computeVtc(array $values, array $base, array $divIds): array
    {
        $result = array_fill_keys($divIds, 0.0);
        foreach ($divIds as $d) {
            $b = $base[$d] ?? 0.0;
            $result[$d] = $b != 0.0 ? round(($values[$d] / $b) * 100, 2) : 0.0;
        }
        return $result;
    }

    /**
     * Compute Vtc% for each row's per-division balance vs per-division revenue total.
     *
     * @param  array<int,float> $balances   [div_id => amount]
     * @param  array<int,float> $revenue    [div_id => total_revenue]
     * @return array<int,float>             [div_id => pct]
     */
    public function rowVtc(array $balances, array $revenue, array $divIds): array
    {
        return $this->computeVtc($balances, $revenue, $divIds);
    }
}
