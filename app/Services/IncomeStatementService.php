<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk menghitung Laporan Laba Rugi (Income Statement)
 * Secara otomatis mengambil dari General Ledger berdasarkan klasifikasi COA
 * 
 * Struktur Lengkap Income Statement:
 * 1. Pendapatan Usaha (Sales Revenue) - COA type: Revenue (4-xxxx)
 * 2. Harga Pokok Penjualan (COGS) - COA type: Expense dengan prefix 5-1xxx
 *    → Laba Kotor (Gross Profit) = Pendapatan – HPP
 * 3. Beban Operasional - COA type: Expense dengan prefix 6-xxxx
 *    → Laba Operasional (Operating Profit) = Laba Kotor – Beban Operasional
 * 4. Pendapatan/Beban Lain-lain - COA type: Revenue/Expense dengan prefix 7-xxxx
 *    → Laba Sebelum Pajak = Laba Operasional ± Pendapatan/Beban Lain
 * 5. Pajak Penghasilan - COA type: Expense dengan prefix 8-xxxx
 *    → Laba Bersih (Net Profit) = Laba Sebelum Pajak – Pajak
 */
class IncomeStatementService
{
    /**
     * Generate Income Statement lengkap dengan 5 tingkat perhitungan
     * 
     * @param array $filters ['start_date', 'end_date', 'cabang_id']
     * @return array
     */
    public function generate(array $filters = []): array
    {
        $startDate = $filters['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $filters['end_date'] ?? now()->endOfMonth()->format('Y-m-d');
        $cabangId = $filters['cabang_id'] ?? null;

        // 1. SALES REVENUE (Pendapatan Usaha) - type: Revenue, prefix: 4xxx
        // Load accounts and compute aggregates in a single pass (avoid N+1 queries)
        $salesRevenue = $this->getAccountsByCodePrefix('4', 'Revenue', $startDate, $endDate, $cabangId, 0);
        $totalSalesRevenue = (float) $salesRevenue->sum('balance');

        // Re-compute percentage_of_revenue now that we know totalSalesRevenue
        if ($totalSalesRevenue > 0) {
            $salesRevenue = $salesRevenue->map(function ($acc) use ($totalSalesRevenue) {
                $acc['percentage_of_revenue'] = ($acc['balance'] / $totalSalesRevenue) * 100;
                return $acc;
            });
        }

        // 2. COST OF GOODS SOLD (Harga Pokok Penjualan) - type: Expense, prefix: 5xxx
        $cogs = $this->getAccountsByCodePrefix('5', 'Expense', $startDate, $endDate, $cabangId, $totalSalesRevenue);
        $totalCOGS = (float) $cogs->sum('balance');

        // GROSS PROFIT (Laba Kotor) = Sales Revenue - COGS
        $grossProfit = $totalSalesRevenue - $totalCOGS;

        // 3. OPERATING EXPENSES (Beban Operasional) - type: Expense, prefix: 6xxxx
    $operatingExpenses = $this->getAccountsByCodePrefix('6', 'Expense', $startDate, $endDate, $cabangId, $totalSalesRevenue);
    $totalOperatingExpenses = (float) $operatingExpenses->sum('balance');

        // OPERATING PROFIT (Laba Operasional) = Gross Profit - Operating Expenses
        $operatingProfit = $grossProfit - $totalOperatingExpenses;

        // 4. OTHER INCOME/EXPENSE (Pendapatan/Beban Lain-lain) - prefix: 7xxxx & 8xxxx
    $otherIncome = $this->getAccountsByCodePrefix('7', 'Revenue', $startDate, $endDate, $cabangId, $totalSalesRevenue);
    $otherExpense = $this->getAccountsByCodePrefixes(['7', '8'], 'Expense', $startDate, $endDate, $cabangId, $totalSalesRevenue);
    $totalOtherIncome = (float) $otherIncome->sum('balance');
    $totalOtherExpense = (float) $otherExpense->sum('balance');
        $netOtherIncomeExpense = $totalOtherIncome - $totalOtherExpense;

        // PROFIT BEFORE TAX (Laba Sebelum Pajak) = Operating Profit + Other Income/Expense
        $profitBeforeTax = $operatingProfit + $netOtherIncomeExpense;

        // 5. TAX EXPENSE (Pajak Penghasilan) - type: Expense, prefix: 9xxxx
    $taxExpense = $this->getAccountsByCodePrefix('9', 'Expense', $startDate, $endDate, $cabangId, $totalSalesRevenue);
    $totalTaxExpense = (float) $taxExpense->sum('balance');

        // NET PROFIT (Laba Bersih) = Profit Before Tax - Tax Expense
        $netProfit = $profitBeforeTax - $totalTaxExpense;

        // BACKWARD COMPATIBILITY: Get ALL revenue and expense accounts for legacy tests
    $allRevenueAccounts = $this->getAccountsByType('Revenue', $startDate, $endDate, $cabangId);
    $allExpenseAccounts = $this->getAccountsByType('Expense', $startDate, $endDate, $cabangId);
    $totalAllRevenue = (float) $allRevenueAccounts->sum('balance');
    $totalAllExpense = (float) $allExpenseAccounts->sum('balance');

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            // Level 1: Sales Revenue
            'sales_revenue' => [
                'accounts' => $salesRevenue,
                'total' => $totalSalesRevenue,
            ],
            // Level 2: Cost of Goods Sold
            'cogs' => [
                'accounts' => $cogs,
                'total' => $totalCOGS,
            ],
            // Calculated: Gross Profit
            'gross_profit' => (float) $grossProfit,
            'gross_profit_margin' => $totalSalesRevenue > 0 
                ? ($grossProfit / $totalSalesRevenue) * 100 
                : 0,
            
            // Level 3: Operating Expenses
            'operating_expenses' => [
                'accounts' => $operatingExpenses,
                'total' => $totalOperatingExpenses,
            ],
            // Calculated: Operating Profit
            'operating_profit' => (float) $operatingProfit,
            'operating_profit_margin' => $totalSalesRevenue > 0 
                ? ($operatingProfit / $totalSalesRevenue) * 100 
                : 0,
            
            // Level 4: Other Income/Expense
            'other_income' => [
                'accounts' => $otherIncome,
                'total' => $totalOtherIncome,
            ],
            'other_expense' => [
                'accounts' => $otherExpense,
                'total' => $totalOtherExpense,
            ],
            'net_other_income_expense' => (float) $netOtherIncomeExpense,
            
            // Calculated: Profit Before Tax
            'profit_before_tax' => (float) $profitBeforeTax,
            
            // Level 5: Tax Expense
            'tax_expense' => [
                'accounts' => $taxExpense,
                'total' => $totalTaxExpense,
            ],
            
            // Final: Net Profit
            'net_profit' => (float) $netProfit,
            'net_profit_margin' => $totalSalesRevenue > 0 
                ? ($netProfit / $totalSalesRevenue) * 100 
                : 0,
            // Primary profitability flag (LEGACY behaviour: based on all revenue - all expense)
            // Kept as `is_profit` for backward compatibility with existing callers/tests.
            'is_profit' => ($totalAllRevenue - $totalAllExpense) >= 0,
            // New flag that explicitly indicates profitability based on 5-level `net_profit` calculation
            'is_profit_net' => $netProfit >= 0,
            
            // BACKWARD COMPATIBILITY: Legacy keys untuk old tests (ALL revenue/expense regardless of code prefix)
            'revenue' => [
                'accounts' => $allRevenueAccounts,
                'total' => $totalAllRevenue,
            ],
            'expense' => [
                'accounts' => $allExpenseAccounts,
                'total' => $totalAllExpense,
            ],
            'net_income' => (float) ($totalAllRevenue - $totalAllExpense), // Legacy calculation
            // Legacy profitability flag (based on total revenue/expense)
            'is_profit_legacy' => ($totalAllRevenue - $totalAllExpense) >= 0,
        ];
    }

    /**
     * Get accounts by code prefix and type with calculated balances
     * 
     * @param string $codePrefix e.g., '4-', '5-1', '6-', '7-', '8-'
     * @param string $type 'Revenue' or 'Expense'
     * @param string $startDate
     * @param string $endDate
     * @param int|null $cabangId
     * @param float $totalRevenue Total revenue untuk perhitungan persentase
     * @return Collection
     */
    protected function getAccountsByCodePrefix(
        string $codePrefix, 
        string $type, 
        string $startDate, 
        string $endDate, 
        ?int $cabangId = null,
        float $totalRevenue = 0
    ): Collection {
        // Get all accounts matching code prefix and type
        $accounts = ChartOfAccount::where('type', $type)
            ->where('code', 'LIKE', $codePrefix . '%')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get();

        return $accounts->map(function ($account) use ($startDate, $endDate, $cabangId, $type, $totalRevenue) {
            // Get journal entries for this account in the period
            $query = JournalEntry::where('coa_id', $account->id)
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate);

            if ($cabangId) {
                $query->where('cabang_id', $cabangId);
            }

            $entries = $query->get();

            $totalDebit = $entries->sum('debit');
            $totalCredit = $entries->sum('credit');

            // Calculate balance based on account type
            // Revenue: Credit increases, Debit decreases
            // Expense: Debit increases, Credit decreases
            $balance = match ($type) {
                'Revenue' => $totalCredit - $totalDebit,
                'Expense' => $totalDebit - $totalCredit,
                default => 0,
            };

            // Calculate percentage of revenue
            $percentageOfRevenue = $totalRevenue > 0 ? ($balance / $totalRevenue) * 100 : 0;

            return [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'parent_id' => $account->parent_id,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'balance' => $balance,
                'entries_count' => $entries->count(),
                'percentage_of_revenue' => $percentageOfRevenue,
            ];
        })->filter(fn($acc) => $acc['balance'] != 0); // Only show accounts with activity
    }

    /**
     * Get accounts by multiple code prefixes and type with calculated balances
     * 
     * @param array $codePrefixes e.g., ['7', '8']
     * @param string $type 'Revenue' or 'Expense'
     * @param string $startDate
     * @param string $endDate
     * @param int|null $cabangId
     * @param float $totalRevenue Total revenue untuk perhitungan persentase
     * @return Collection
     */
    protected function getAccountsByCodePrefixes(
        array $codePrefixes, 
        string $type, 
        string $startDate, 
        string $endDate, 
        ?int $cabangId = null,
        float $totalRevenue = 0
    ): Collection {
        $allAccounts = collect();

        foreach ($codePrefixes as $prefix) {
            $accounts = $this->getAccountsByCodePrefix($prefix, $type, $startDate, $endDate, $cabangId, $totalRevenue);
            $allAccounts = $allAccounts->merge($accounts);
        }

        return $allAccounts;
    }

    /**
     * Get accounts by type with calculated balances for a period
     * Legacy method for backward compatibility
     * 
     * @param string $type 'Revenue' or 'Expense'
     * @param string $startDate
     * @param string $endDate
     * @param int|null $cabangId
     * @return Collection
     */
    protected function getAccountsByType(string $type, string $startDate, string $endDate, ?int $cabangId = null): Collection
    {
        // Get all accounts of this type
        $accounts = ChartOfAccount::where('type', $type)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code')
            ->get();

        return $accounts->map(function ($account) use ($startDate, $endDate, $cabangId, $type) {
            // Get journal entries for this account in the period
            $query = JournalEntry::where('coa_id', $account->id)
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate);

            if ($cabangId) {
                $query->where('cabang_id', $cabangId);
            }

            $entries = $query->get();

            $totalDebit = $entries->sum('debit');
            $totalCredit = $entries->sum('credit');

            // Calculate balance based on account type
            // Revenue: Credit increases, Debit decreases
            // Expense: Debit increases, Credit decreases
            $balance = match ($type) {
                'Revenue' => $totalCredit - $totalDebit,
                'Expense' => $totalDebit - $totalCredit,
                default => 0,
            };

            return [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'parent_id' => $account->parent_id,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'balance' => $balance,
                'entries_count' => $entries->count(),
            ];
        })->filter(fn($acc) => $acc['balance'] != 0); // Only show accounts with activity
    }

    /**
     * Get summary statistics for Income Statement (struktur lengkap)
     * 
     * @param array $filters
     * @return array
     */
    public function getSummary(array $filters = []): array
    {
        $data = $this->generate($filters);

        return [
            // Level 1
            'total_sales_revenue' => $data['sales_revenue']['total'],
            'sales_revenue_accounts_count' => $data['sales_revenue']['accounts']->count(),
            
            // Level 2
            'total_cogs' => $data['cogs']['total'],
            'cogs_accounts_count' => $data['cogs']['accounts']->count(),
            'gross_profit' => $data['gross_profit'],
            'gross_profit_margin' => $data['gross_profit_margin'],
            
            // Level 3
            'total_operating_expenses' => $data['operating_expenses']['total'],
            'operating_expenses_accounts_count' => $data['operating_expenses']['accounts']->count(),
            'operating_profit' => $data['operating_profit'],
            'operating_profit_margin' => $data['operating_profit_margin'],
            
            // Level 4
            'total_other_income' => $data['other_income']['total'],
            'total_other_expense' => $data['other_expense']['total'],
            'net_other_income_expense' => $data['net_other_income_expense'],
            'profit_before_tax' => $data['profit_before_tax'],
            
            // Level 5
            'total_tax_expense' => $data['tax_expense']['total'],
            'tax_rate' => $data['profit_before_tax'] > 0 
                ? ($data['tax_expense']['total'] / $data['profit_before_tax']) * 100 
                : 0,
            
            // Final
            'net_profit' => $data['net_profit'],
            'net_profit_margin' => $data['net_profit_margin'],
            'is_profit' => $data['is_profit'],
            
            // BACKWARD COMPATIBILITY
            'total_revenue' => $data['revenue']['total'],
            'total_expense' => $data['expense']['total'],
            'net_income' => $data['net_income'],
            'revenue_accounts_count' => $data['revenue']['accounts']->count(),
            'expense_accounts_count' => $data['expense']['accounts']->count(),
            'profit_margin' => $data['revenue']['total'] > 0 
                ? ($data['net_income'] / $data['revenue']['total']) * 100 
                : 0,
        ];
    }

    /**
     * Get grouped accounts by parent for hierarchical display (struktur lengkap)
     * 
     * @param array $filters
     * @return array
     */
    public function getGroupedByParent(array $filters = []): array
    {
        $data = $this->generate($filters);

        return [
            'period' => $data['period'],
            'sales_revenue' => [
                'grouped' => $this->groupAccountsByParent($data['sales_revenue']['accounts']),
                'total' => $data['sales_revenue']['total'],
            ],
            'cogs' => [
                'grouped' => $this->groupAccountsByParent($data['cogs']['accounts']),
                'total' => $data['cogs']['total'],
            ],
            'gross_profit' => $data['gross_profit'],
            'operating_expenses' => [
                'grouped' => $this->groupAccountsByParent($data['operating_expenses']['accounts']),
                'total' => $data['operating_expenses']['total'],
            ],
            'operating_profit' => $data['operating_profit'],
            'other_income' => [
                'grouped' => $this->groupAccountsByParent($data['other_income']['accounts']),
                'total' => $data['other_income']['total'],
            ],
            'other_expense' => [
                'grouped' => $this->groupAccountsByParent($data['other_expense']['accounts']),
                'total' => $data['other_expense']['total'],
            ],
            'profit_before_tax' => $data['profit_before_tax'],
            'tax_expense' => [
                'grouped' => $this->groupAccountsByParent($data['tax_expense']['accounts']),
                'total' => $data['tax_expense']['total'],
            ],
            'net_profit' => $data['net_profit'],
            'is_profit' => $data['is_profit'],
        ];
    }

    /**
     * Group accounts by parent for hierarchical structure
     * 
     * @param Collection $accounts
     * @return Collection
     */
    protected function groupAccountsByParent(Collection $accounts): Collection
    {
        $grouped = collect();

        // Get parent accounts
        $parents = $accounts->where('parent_id', null);

        foreach ($parents as $parent) {
            // Get children
            $children = $accounts->where('parent_id', $parent['id']);

            $grouped->push([
                'account' => $parent,
                'children' => $children,
                'subtotal' => $parent['balance'] + $children->sum('balance'),
            ]);
        }

        // Add orphan children (accounts without parent in the result set)
        $orphans = $accounts->whereNotNull('parent_id')
            ->whereNotIn('parent_id', $parents->pluck('id'));

        foreach ($orphans as $orphan) {
            $grouped->push([
                'account' => $orphan,
                'children' => collect(),
                'subtotal' => $orphan['balance'],
            ]);
        }

        return $grouped;
    }

    /**
     * Compare two periods for comprehensive trend analysis
     * 
     * @param array $currentPeriod ['start_date', 'end_date']
     * @param array $previousPeriod ['start_date', 'end_date']
     * @param int|null $cabangId
     * @return array
     */
    public function comparePeriods(array $currentPeriod, array $previousPeriod, ?int $cabangId = null): array
    {
        $current = $this->generate([
            'start_date' => $currentPeriod['start_date'],
            'end_date' => $currentPeriod['end_date'],
            'cabang_id' => $cabangId,
        ]);

        $previous = $this->generate([
            'start_date' => $previousPeriod['start_date'],
            'end_date' => $previousPeriod['end_date'],
            'cabang_id' => $cabangId,
        ]);

        return [
            'current' => $current,
            'previous' => $previous,
            'changes' => [
                'sales_revenue' => [
                    'amount' => $current['sales_revenue']['total'] - $previous['sales_revenue']['total'],
                    'percentage' => $previous['sales_revenue']['total'] > 0 
                        ? (($current['sales_revenue']['total'] - $previous['sales_revenue']['total']) / $previous['sales_revenue']['total']) * 100 
                        : 0,
                ],
                'cogs' => [
                    'amount' => $current['cogs']['total'] - $previous['cogs']['total'],
                    'percentage' => $previous['cogs']['total'] > 0 
                        ? (($current['cogs']['total'] - $previous['cogs']['total']) / $previous['cogs']['total']) * 100 
                        : 0,
                ],
                'gross_profit' => [
                    'amount' => $current['gross_profit'] - $previous['gross_profit'],
                    'percentage' => $previous['gross_profit'] != 0 
                        ? (($current['gross_profit'] - $previous['gross_profit']) / abs($previous['gross_profit'])) * 100 
                        : 0,
                ],
                'operating_expenses' => [
                    'amount' => $current['operating_expenses']['total'] - $previous['operating_expenses']['total'],
                    'percentage' => $previous['operating_expenses']['total'] > 0 
                        ? (($current['operating_expenses']['total'] - $previous['operating_expenses']['total']) / $previous['operating_expenses']['total']) * 100 
                        : 0,
                ],
                'operating_profit' => [
                    'amount' => $current['operating_profit'] - $previous['operating_profit'],
                    'percentage' => $previous['operating_profit'] != 0 
                        ? (($current['operating_profit'] - $previous['operating_profit']) / abs($previous['operating_profit'])) * 100 
                        : 0,
                ],
                'net_other_income_expense' => [
                    'amount' => $current['net_other_income_expense'] - $previous['net_other_income_expense'],
                    'percentage' => $previous['net_other_income_expense'] != 0 
                        ? (($current['net_other_income_expense'] - $previous['net_other_income_expense']) / abs($previous['net_other_income_expense'])) * 100 
                        : 0,
                ],
                'profit_before_tax' => [
                    'amount' => $current['profit_before_tax'] - $previous['profit_before_tax'],
                    'percentage' => $previous['profit_before_tax'] != 0 
                        ? (($current['profit_before_tax'] - $previous['profit_before_tax']) / abs($previous['profit_before_tax'])) * 100 
                        : 0,
                ],
                'tax_expense' => [
                    'amount' => $current['tax_expense']['total'] - $previous['tax_expense']['total'],
                    'percentage' => $previous['tax_expense']['total'] > 0 
                        ? (($current['tax_expense']['total'] - $previous['tax_expense']['total']) / $previous['tax_expense']['total']) * 100 
                        : 0,
                ],
                'net_profit' => [
                    'amount' => $current['net_profit'] - $previous['net_profit'],
                    'percentage' => $previous['net_profit'] != 0 
                        ? (($current['net_profit'] - $previous['net_profit']) / abs($previous['net_profit'])) * 100 
                        : 0,
                ],
                // BACKWARD COMPATIBILITY
                'revenue' => [
                    'amount' => $current['revenue']['total'] - $previous['revenue']['total'],
                    'percentage' => $previous['revenue']['total'] > 0 
                        ? (($current['revenue']['total'] - $previous['revenue']['total']) / $previous['revenue']['total']) * 100 
                        : 0,
                ],
                'expense' => [
                    'amount' => $current['expense']['total'] - $previous['expense']['total'],
                    'percentage' => $previous['expense']['total'] > 0 
                        ? (($current['expense']['total'] - $previous['expense']['total']) / $previous['expense']['total']) * 100 
                        : 0,
                ],
                'net_income' => [
                    'amount' => $current['net_income'] - $previous['net_income'],
                    'percentage' => $previous['net_income'] != 0 
                        ? (($current['net_income'] - $previous['net_income']) / abs($previous['net_income'])) * 100 
                        : 0,
                ],
            ],
        ];
    }

    /**
     * Validate if Chart of Accounts is properly classified untuk Income Statement lengkap
     * 
     * @return array
     */
    public function validateCOAClassification(): array
    {
        $issues = [];

        // Check for Revenue accounts (any revenue, not just 4-xxxx)
        $allRevenueCount = ChartOfAccount::where('type', 'Revenue')->count();
        
        // Check for Sales Revenue accounts (prefix 4-)
        $salesRevenueCount = ChartOfAccount::where('type', 'Revenue')
            ->where('code', 'LIKE', '4-%')
            ->count();
        if ($salesRevenueCount === 0 && $allRevenueCount === 0) {
            $issues[] = 'Tidak ada akun Revenue. Pastikan ada akun pendapatan di COA.';
        } elseif ($salesRevenueCount === 0 && $allRevenueCount > 0) {
            // Ada revenue tapi tidak ada yang prefix 4-, masih OK untuk backward compatibility
        }

        // Check for Expense accounts (any expense)
        $allExpenseCount = ChartOfAccount::where('type', 'Expense')->count();
        if ($allExpenseCount === 0) {
            $issues[] = 'Tidak ada akun Expense. Pastikan ada akun beban di COA.';
        }

        // Check for COGS accounts (prefix 5-1)
        $cogsCount = ChartOfAccount::where('type', 'Expense')
            ->where('code', 'LIKE', '5-1%')
            ->count();
        if ($cogsCount === 0 && $salesRevenueCount > 0) {
            // Only warn if we have proper sales revenue accounts (for complete 5-level structure)
            $issues[] = 'Tidak ada akun Harga Pokok Penjualan (COGS) dengan prefix 5-1. HPP diperlukan untuk menghitung Laba Kotor.';
        }

        // Check for Operating Expenses accounts (prefix 6-)
        $opexCount = ChartOfAccount::where('type', 'Expense')
            ->where('code', 'LIKE', '6-%')
            ->count();
        if ($opexCount === 0 && $salesRevenueCount > 0 && $cogsCount > 0) {
            // Only warn if we have sales revenue and COGS (for complete 5-level structure)
            $issues[] = 'Tidak ada akun Beban Operasional dengan prefix 6-. Beban operasional diperlukan untuk menghitung Laba Operasional.';
        }

        // Optional: Check for Other Income/Expense (prefix 7-)
        $otherCount = ChartOfAccount::whereIn('type', ['Revenue', 'Expense'])
            ->where('code', 'LIKE', '7-%')
            ->count();

        // Optional: Check for Tax Expense (prefix 8-)
        $taxCount = ChartOfAccount::where('type', 'Expense')
            ->where('code', 'LIKE', '8-%')
            ->count();

        // Check for accounts with journal entries but no proper type
        $invalidTypes = ChartOfAccount::whereNotIn('type', [
            'Asset', 'Liability', 'Equity', 'Revenue', 'Expense', 'Contra Asset'
        ])->count();

        if ($invalidTypes > 0) {
            $issues[] = "Ada {$invalidTypes} akun dengan tipe tidak valid.";
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'classification' => [
                'sales_revenue_accounts' => $salesRevenueCount,
                'cogs_accounts' => $cogsCount,
                'operating_expense_accounts' => $opexCount,
                'other_income_expense_accounts' => $otherCount,
                'tax_expense_accounts' => $taxCount,
            ],
            // BACKWARD COMPATIBILITY
            'revenue_accounts' => $allRevenueCount,
            'expense_accounts' => $allExpenseCount,
        ];
    }

    /**
     * Get journal entries for a specific account (for drill-down functionality)
     * 
     * @param int $accountId
     * @param string $startDate
     * @param string $endDate
     * @param int|null $cabangId
     * @return array
     */
    public function getAccountJournalEntries(
        int $accountId,
        string $startDate,
        string $endDate,
        ?int $cabangId = null
    ): array {
        $account = ChartOfAccount::find($accountId);
        
        if (!$account) {
            return [
                'account' => null,
                'entries' => collect(),
                'total_debit' => 0,
                'total_credit' => 0,
                'balance' => 0,
            ];
        }

        $query = JournalEntry::where('coa_id', $accountId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->with(['cabang', 'coa']);

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $entries = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');

        // Calculate balance based on account type
        $balance = match ($account->type) {
            'Revenue' => $totalCredit - $totalDebit,
            'Expense' => $totalDebit - $totalCredit,
            'Asset', 'Contra Asset' => $totalDebit - $totalCredit,
            'Liability', 'Equity' => $totalCredit - $totalDebit,
            default => 0,
        };

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
            ],
            'entries' => $entries,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => $balance,
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ];
    }
}
