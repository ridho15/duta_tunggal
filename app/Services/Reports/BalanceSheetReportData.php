<?php

namespace App\Services\Reports;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\BalanceSheetService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Data laporan untuk halaman Blade. Diekstrak dari ViewBalanceSheet (halaman Filament) tanpa bagian UI Filament;
 * properti diisi controller, lalu getReportData() menghitung laporannya.
 */
class BalanceSheetReportData
{
    public bool $showPreview = false;

    public bool $classic_view = false;

    public ?string $as_of_date = null;

    public ?string $cabang_id = null;

    public ?array $branches = [];

    public ?bool $show_comparison = false;

    public ?string $comparison_date = null;

    public ?bool $use_multi_period = false;

    public ?array $selected_periods = [];

    public ?string $display_mode = 'detailed';

    public ?bool $include_zero_balances = false;

    public function getReportData(): array
    {
        $asOf = $this->as_of_date ? Carbon::parse($this->as_of_date)->endOfDay() : now()->endOfDay();

        return $this->applyDisplayMode($this->legacyReportDataFromService(
            $this->getFullReportServiceData($asOf),
            $asOf,
        ));
    }

    protected function sumBalance($accounts, Carbon $asOf): float
    {
        // Sum per account with its own opening balance & normal balance
        return $accounts->sum(fn ($coa) => $this->calculateBalanceForCoa($coa, $asOf));
    }

    /**
     * Get report data formatted for the classic two-column balance sheet layout.
     * Returns asset groups and liability/equity groups each as a flat list of
     * parent→children sections suitable for a side-by-side table render.
     */
    public function getClassicReportData(): array
    {
        $asOf = $this->as_of_date ? Carbon::parse($this->as_of_date)->endOfDay() : now()->endOfDay();
        $showZero = (bool) ($this->include_zero_balances ?? false);

        $serviceData = $this->getFullReportServiceData($asOf);
        $assetGroups = $this->buildClassicGroups($this->classicAssetAccounts($serviceData), $asOf, $showZero);
        $liabilityGroups = $this->buildClassicGroups($this->liabilityAccounts($serviceData), $asOf, $showZero);
        $equityGroups = $this->buildClassicGroups($this->equityAccounts($serviceData), $asOf, $showZero);

        $retained = (float) ($serviceData['retained_earnings'] ?? 0);
        $current = 0.0;
        $totalAssets = (float) ($serviceData['total_assets'] ?? 0);
        $totalLiabilities = (float) ($serviceData['total_liabilities'] ?? 0);
        $totalEquity = (float) ($serviceData['total_equity'] ?? 0);
        $totalLiabAndEquity = (float) ($serviceData['total_liabilities_and_equity'] ?? 0);
        $difference = (float) ($serviceData['difference'] ?? 0);

        return [
            'asset_groups'                => $assetGroups,
            'liability_groups'            => $liabilityGroups,
            'equity_groups'               => $equityGroups,
            'retained_earnings'           => $retained,
            'current_earnings'            => $current,
            'total_assets'                => $totalAssets,
            'total_liabilities'           => $totalLiabilities,
            'total_equity'                => $totalEquity,
            'total_liabilities_and_equity' => $totalLiabAndEquity,
            'is_balanced'                 => (bool) ($serviceData['is_balanced'] ?? abs($difference) < 0.01),
            'difference'                  => $difference,
        ];
    }

    protected function getFullReportServiceData(Carbon $asOf): array
    {
        return $this->balanceSheetService()->generate([
            'as_of_date' => $asOf->toDateString(),
            'cabang_id' => $this->resolvedCabangId(),
            'display_level' => 'all',
            'show_zero_balance' => true,
        ]);
    }

    protected function legacyReportDataFromService(array $serviceData, Carbon $asOf): array
    {
        $unbalancedEntries = $this->getUnbalancedJournalEntries($asOf);
        $difference = (float) ($serviceData['difference'] ?? 0);
        $balanced = (bool) ($serviceData['is_balanced'] ?? abs($difference) < 0.01);

        return [
            'assets' => $this->detailByParent($this->legacyAssetAccounts($serviceData), $asOf, positiveFor: 'Asset'),
            'liabilities' => $this->detailByParent($this->liabilityAccounts($serviceData), $asOf, positiveFor: 'Liability'),
            'equity' => $this->detailByParentWithRetainedEarnings(
                $this->equityAccounts($serviceData),
                $asOf,
                (float) ($serviceData['retained_earnings'] ?? 0),
            ),
            'retained_earnings' => (float) ($serviceData['retained_earnings'] ?? 0),
            'current_earnings' => 0.0,
            'asset_total' => (float) ($serviceData['total_assets'] ?? 0),
            'liab_total' => (float) ($serviceData['total_liabilities'] ?? 0),
            'equity_total' => (float) ($serviceData['total_equity'] ?? 0),
            'balanced' => $balanced,
            'difference' => $difference,
            'balance_warning' => $balanced
                ? null
                : 'Total aset tidak sama dengan total kewajiban dan ekuitas. Selisih ini mencerminkan ledger aktual dan harus ditelusuri ke jurnal sumber.',
            'unbalanced_entries' => $unbalancedEntries,
            'has_unbalanced_entries' => !empty($unbalancedEntries),
        ];
    }

    protected function legacyAssetAccounts(array $serviceData): Collection
    {
        return $this->assetAccounts($serviceData)
            ->merge($this->contraAssetAccounts($serviceData)->map(function ($account) {
                $legacyAccount = clone $account;
                $legacyAccount->legacy_balance = -abs((float) ($account->balance ?? 0));

                return $legacyAccount;
            }))
            ->sortBy('code')
            ->values();
    }

    protected function classicAssetAccounts(array $serviceData): Collection
    {
        return $this->legacyAssetAccounts($serviceData);
    }

    protected function assetAccounts(array $serviceData): Collection
    {
        return $this->serviceAccounts($serviceData, 'current_assets')
            ->merge($this->serviceAccounts($serviceData, 'fixed_assets'))
            ->sortBy('code')
            ->values();
    }

    protected function contraAssetAccounts(array $serviceData): Collection
    {
        return $this->serviceAccounts($serviceData, 'contra_assets');
    }

    protected function liabilityAccounts(array $serviceData): Collection
    {
        return $this->serviceAccounts($serviceData, 'current_liabilities')
            ->merge($this->serviceAccounts($serviceData, 'long_term_liabilities'))
            ->sortBy('code')
            ->values();
    }

    protected function equityAccounts(array $serviceData): Collection
    {
        return $this->serviceAccounts($serviceData, 'equity');
    }

    protected function serviceAccounts(array $serviceData, string $section): Collection
    {
        return collect($serviceData[$section]['accounts'] ?? [])
            ->map(function ($account) {
                $legacyAccount = clone $account;
                $legacyAccount->legacy_balance = (float) ($account->balance ?? 0);

                return $legacyAccount;
            })
            ->values();
    }

    protected function resolvedCabangId(): ?int
    {
        if (!empty($this->cabang_id)) {
            return (int) $this->cabang_id;
        }

        return !empty($this->branches) && count($this->branches) === 1
            ? (int) $this->branches[0]
            : null;
    }

    protected function balanceSheetService(): BalanceSheetService
    {
        return app(BalanceSheetService::class);
    }

    /**
     * Build parent→children groups for the classic two-column balance sheet.
     *
     * Groups accounts by their direct parent_id.  Each group contains:
     *   parent_code, parent_name, children[], total, total_label
     *
     * Accounts whose parent is not in the same collection are treated as
     * stand-alone rows in an "Uncategorised" group.
     *
     * @param  \Illuminate\Support\Collection $accounts  Active COA records of the relevant type
     * @param  \Carbon\Carbon                 $asOf
     * @param  bool                           $showZero  Whether to include zero-balance child rows
     * @return array
     */
    protected function buildClassicGroups($accounts, Carbon $asOf, bool $showZero = false): array
    {
        // Pre-compute balance for every account (avoids N+1 compared to individual queries)
        $accountsById = $accounts->keyBy('id')->map(function ($coa) use ($asOf) {
            if (isset($coa->legacy_balance)) {
                $coa->computed_balance = (float) $coa->legacy_balance;
            } elseif (isset($coa->balance)) {
                $coa->computed_balance = (float) $coa->balance;
            } else {
                $coa->computed_balance = $this->calculateBalanceForCoa($coa, $asOf);
            }

            return $coa;
        });

        $accountIdSet    = $accountsById->keys()->toArray();
        // IDs that appear as parent_id for at least one other account in this collection
        $referencedParentIds = $accountsById
            ->pluck('parent_id')
            ->filter()
            ->unique()
            ->intersect($accountIdSet)
            ->toArray();

        // Group accounts by parent_id
        $grouped = $accountsById->groupBy('parent_id');
        $groups  = [];

        // Iterate over each parent that IS referenced inside the collection
        foreach ($referencedParentIds as $parentId) {
            $parent = $accountsById->get($parentId);
            if (!$parent) {
                continue;
            }

            $children   = ($grouped->get($parentId) ?? collect())->sortBy('code')->values();
            $childItems = [];
            $groupTotal = 0.0;

            foreach ($children as $child) {
                // Skip children that are themselves parent accounts (they get their own group)
                if (in_array($child->id, $referencedParentIds, true)) {
                    continue;
                }

                $balance = (float) $child->computed_balance;
                if (!$showZero && abs($balance) < 0.005) {
                    continue;
                }

                $childItems[] = [
                    'code'    => $child->code,
                    'name'    => $child->name,
                    'balance' => $balance,
                ];
                $groupTotal += $balance;
            }

            if (!empty($childItems) || $showZero) {
                $groups[] = [
                    'parent_code'  => $parent->code,
                    'parent_name'  => $parent->name,
                    'children'     => $childItems,
                    'total'        => $groupTotal,
                    'total_label'  => 'TOTAL ' . mb_strtoupper($parent->name),
                ];
            }
        }

        // Accounts with no parent inside the collection become standalone leaf groups
        $standaloneAccounts = $accountsById->filter(function ($a) use ($accountIdSet, $referencedParentIds) {
            $parentInCollection = $a->parent_id && in_array($a->parent_id, $accountIdSet, true);
            $isSelfParent       = in_array($a->id, $referencedParentIds, true);
            return !$parentInCollection && !$isSelfParent;
        })->sortBy('code')->values();

        foreach ($standaloneAccounts as $acct) {
            $balance = (float) $acct->computed_balance;
            if (!$showZero && abs($balance) < 0.005) {
                continue;
            }
            $groups[] = [
                'parent_code' => $acct->code,
                'parent_name' => $acct->name,
                'children'    => [[
                    'code'    => $acct->code,
                    'name'    => $acct->name,
                    'balance' => $balance,
                ]],
                'total'       => $balance,
                'total_label' => 'TOTAL ' . mb_strtoupper($acct->name),
            ];
        }

        // Sort by parent code ascending
        usort($groups, fn ($a, $b) => strcmp($a['parent_code'], $b['parent_code']));

        return $groups;
    }

    protected function calculateBalanceForCoa(ChartOfAccount $coa, Carbon $asOf): float
    {
        $query = JournalEntry::where('coa_id', $coa->id)->where('date', '<=', $asOf);
        if (!empty($this->cabang_id)) {
            $query->where('cabang_id', $this->cabang_id);
        } elseif (!empty($this->branches)) {
            $query->whereIn('cabang_id', $this->branches);
        }
        $debit = (float) (clone $query)->sum('debit');
        $credit = (float) (clone $query)->sum('credit');

        // Calculate period activity
        $netActivity = $debit - $credit;

        // Apply normal balance sign to period activity
        $sign = match ($coa->type) {
            'Asset', 'Expense' => 1,  // Debit normal: + (debits - credits)
            'Contra Asset', 'Liability', 'Equity', 'Revenue' => -1, // Credit normal: - (debits - credits)
            default => 1,
        };

        // Balance = Opening Balance + (Period Activity * Sign)
        return $coa->opening_balance + ($netActivity * $sign);
    }

    protected function detailByParent($accounts, Carbon $asOf, string $positiveFor): array
    {
        $grouped = $accounts->groupBy('parent_id');
        $details = [];
        foreach ($grouped as $parentId => $list) {
            // Parent name
            $parentName = $parentId ? optional(ChartOfAccount::find($parentId))->name : 'Tanpa Induk';
            $items = [];
            $subtotal = 0;
            foreach ($list as $coa) {
                $balance = isset($coa->legacy_balance)
                    ? (float) $coa->legacy_balance
                    : $this->sumBalance(collect([$coa]), $asOf);
                $sign = ($positiveFor === 'Asset' || $positiveFor === 'Expense') ? 1 : 1; // keep positive presentation
                $items[] = [
                    'coa' => $coa,
                    'balance' => $balance * $sign,
                ];
                $subtotal += $balance * $sign;
            }
            $details[] = [
                'parent' => $parentName,
                'items' => $items,
                'subtotal' => $subtotal,
            ];
        }
        return $details;
    }

    protected function detailByParentWithRetainedEarnings($accounts, Carbon $asOf, float $retainedEarnings): array
    {
        $grouped = $accounts->groupBy('parent_id');
        $details = [];
        foreach ($grouped as $parentId => $list) {
            // Parent name
            $parentName = $parentId ? optional(ChartOfAccount::find($parentId))->name : 'Tanpa Induk';
            $items = [];
            $subtotal = 0;
            foreach ($list as $coa) {
                $balance = isset($coa->legacy_balance)
                    ? (float) $coa->legacy_balance
                    : $this->sumBalance(collect([$coa]), $asOf);
                
                // Note: Retained earnings is calculated separately and added to equity total,
                // so don't add it here to avoid double-counting
                // if (str_contains(strtolower($coa->name), 'ditahan') || str_contains(strtolower($coa->name), 'retained')) {
                //     $balance += $retainedEarnings;
                // }
                
                $sign = 1; // keep positive presentation for equity
                $items[] = [
                    'coa' => $coa,
                    'balance' => $balance * $sign,
                ];
                $subtotal += $balance * $sign;
            }
            $details[] = [
                'parent' => $parentName,
                'items' => $items,
                'subtotal' => $subtotal,
            ];
        }
        return $details;
    }

    protected function applyDisplayMode(array $data): array
    {
        switch ($this->display_mode) {
            case 'total_only':
                // Only show totals, remove detailed breakdowns
                return [
                    'assets' => [],
                    'liabilities' => [],
                    'equity' => [],
                    'asset_total' => $data['asset_total'],
                    'liab_total' => $data['liab_total'],
                    'equity_total' => $data['equity_total'],
                    'balanced' => $data['balanced'],
                    'retained_earnings' => $data['retained_earnings'],
                    'current_earnings' => $data['current_earnings'],
                ];

            case 'parent_only':
                // Only show parent accounts (group by parent_id)
                $data['assets'] = $this->filterParentAccounts($data['assets']);
                $data['liabilities'] = $this->filterParentAccounts($data['liabilities']);
                $data['equity'] = $this->filterParentAccounts($data['equity']);
                break;

            case 'detailed':
                // Show all accounts (default behavior)
                break;

            case 'with_zero':
                // Show all accounts including zero balances
                $this->include_zero_balances = true;
                break;
        }

        // Filter zero balances if not explicitly requested
        if (!$this->include_zero_balances && $this->display_mode !== 'with_zero') {
            $data['assets'] = $this->filterZeroBalances($data['assets']);
            $data['liabilities'] = $this->filterZeroBalances($data['liabilities']);
            $data['equity'] = $this->filterZeroBalances($data['equity']);
        }

        return $data;
    }

    protected function filterParentAccounts(array $sections): array
    {
        $filtered = [];
        foreach ($sections as $section) {
            $filteredSection = [
                'parent' => $section['parent'],
                'items' => [],
                'subtotal' => $section['subtotal']
            ];

            // Only include parent-level items (those without sub-accounts or main parents)
            foreach ($section['items'] as $item) {
                if ($this->isParentAccount($item['coa'])) {
                    $filteredSection['items'][] = $item;
                }
            }

            if (!empty($filteredSection['items'])) {
                $filtered[] = $filteredSection;
            }
        }
        return $filtered;
    }

    protected function filterZeroBalances(array $sections): array
    {
        $filtered = [];
        foreach ($sections as $section) {
            $filteredSection = [
                'parent' => $section['parent'],
                'items' => [],
                'subtotal' => $section['subtotal']
            ];

            foreach ($section['items'] as $item) {
                if (abs($item['balance']) > 0.01) { // Not zero
                    $filteredSection['items'][] = $item;
                }
            }

            // Only include section if it has items or subtotal is not zero
            if (!empty($filteredSection['items']) || abs($filteredSection['subtotal']) > 0.01) {
                $filtered[] = $filteredSection;
            }
        }
        return $filtered;
    }

    protected function isParentAccount($coa): bool
    {
        // Check if this account has child accounts
        return ChartOfAccount::where('parent_id', $coa->id)->exists();
    }

    protected function getUnbalancedJournalEntries(Carbon $asOf): array
    {
        // Get all unique transaction_ids that have journal entries up to the asOf date
        $transactionIds = JournalEntry::where('date', '<=', $asOf)
            ->whereNotNull('transaction_id')
            ->distinct()
            ->pluck('transaction_id')
            ->toArray();

        $unbalancedEntries = [];

        foreach ($transactionIds as $transactionId) {
            $entries = JournalEntry::where('transaction_id', $transactionId)
                ->where('date', '<=', $asOf)
                ->get();

            $totalDebit = $entries->sum('debit');
            $totalCredit = $entries->sum('credit');

            // Check if transaction is not balanced (debit != credit)
            if (abs($totalDebit - $totalCredit) > 0.01) {
                $unbalancedEntries[] = [
                    'transaction_id' => $transactionId,
                    'entries' => $entries,
                    'total_debit' => $totalDebit,
                    'total_credit' => $totalCredit,
                    'difference' => $totalDebit - $totalCredit,
                ];
            }
        }

        return $unbalancedEntries;
    }
}
