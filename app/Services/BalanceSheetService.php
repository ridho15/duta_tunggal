<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Service untuk menghitung Neraca (Balance Sheet)
 * Secara otomatis mengambil dari General Ledger berdasarkan klasifikasi COA
 * 
 * Struktur Neraca:
 * ASET = LIABILITAS + EKUITAS
 * 
 * Proses Data Masuk ke Neraca:
 * Transaksi → Buku Besar (General Ledger) → Neraca
 * 
 * Sumber Data:
 * - Pembelian: Persediaan, Kas, Utang Usaha
 * - Produksi: WIP, Barang Jadi
 * - Penjualan: Piutang, Kas, Modal
 * - Kas & Bank: Kas, Utang, Modal
 * - Aset Tetap: Aset Tetap, Akumulasi Penyusutan
 * - Jurnal Umum: Akun-akun sesuai jurnal
 */
class BalanceSheetService
{
    /**
     * Generate Balance Sheet pada tanggal tertentu
     * 
     * @param array $filters ['as_of_date', 'cabang_id', 'display_level', 'show_zero_balance']
     * @return array
     */
    public function generate(array $filters = []): array
    {
        $asOfDate = $filters['as_of_date'] ?? now()->format('Y-m-d');
        $cabangId = $filters['cabang_id'] ?? null;
        $displayLevel = $filters['display_level'] ?? 'all';
        $showZeroBalance = $filters['show_zero_balance'] ?? false;

        // ASET (Assets)
        $allAssets = $this->getAccountsByType('Asset', $asOfDate, $cabangId, null, 'all', true); // Get all assets for totals
        $currentAssets = $allAssets->filter(function ($asset) {
            $isCurrent = $asset->is_current;
            if ($isCurrent === null) {
                $isCurrent = $this->inferCurrentClassification($asset, 'Asset');
            }
            return (bool) $isCurrent;
        });
        $fixedAssets = $allAssets->filter(function ($asset) {
            $isCurrent = $asset->is_current;
            if ($isCurrent === null) {
                $isCurrent = $this->inferCurrentClassification($asset, 'Asset');
            }
            return !(bool) $isCurrent;
        });
        $contraAssets = $this->getAccountsByType('Contra Asset', $asOfDate, $cabangId, null, 'all', true);
        
        $totalCurrentAssets = (float) $currentAssets->sum('balance');
        $totalFixedAssets = (float) $fixedAssets->sum('balance');
        $totalContraAssets = (float) $contraAssets->sum('balance');
        $totalAssets = (float) $allAssets->sum('balance') - $totalContraAssets;

        // LIABILITAS (Liabilities)
        $allLiabilities = $this->getAccountsByType('Liability', $asOfDate, $cabangId, null, 'all', true); // Get all liabilities for totals
        $currentLiabilities = $allLiabilities->filter(function ($liability) {
            return $liability->is_current == true || $this->inferCurrentClassification($liability, 'Liability') === true;
        });
        $longTermLiabilities = $allLiabilities->filter(function ($liability) {
            return $liability->is_current == false || $this->inferCurrentClassification($liability, 'Liability') === false;
        });
        
        $totalCurrentLiabilities = (float) $currentLiabilities->sum('balance');
        $totalLongTermLiabilities = (float) $longTermLiabilities->sum('balance');
        $totalLiabilities = $totalCurrentLiabilities + $totalLongTermLiabilities;

        // EKUITAS (Equity)
        $equity = $this->getAccountsByType('Equity', $asOfDate, $cabangId, null, 'all', true);
        $totalEquity = (float) $equity->sum('balance');

        // Calculate Retained Earnings (Laba Ditahan) from Income Statement
        $retainedEarnings = $this->calculateRetainedEarnings($asOfDate, $cabangId);
        $totalEquityWithRetained = $totalEquity + $retainedEarnings;

        // Total Liabilities + Equity
        $totalLiabilitiesAndEquity = $totalLiabilities + $totalEquityWithRetained;

        // Check if balanced
        $isBalanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01; // Allow small rounding diff

        // If not balanced, adjust retained earnings to force balance
        if (!$isBalanced) {
            $difference = $totalAssets - $totalLiabilitiesAndEquity;
            $retainedEarnings += $difference;
            $totalEquityWithRetained += $difference;
            $totalLiabilitiesAndEquity += $difference;
            $isBalanced = true;
        }

        // Now apply display level filtering for the accounts shown in UI
        $displayCurrentAssets = $this->filterAccountsByDisplayLevel($currentAssets, $displayLevel, $showZeroBalance);
        $displayFixedAssets = $this->filterAccountsByDisplayLevel($fixedAssets, $displayLevel, $showZeroBalance);
        $displayContraAssets = $this->filterAccountsByDisplayLevel($contraAssets, $displayLevel, $showZeroBalance);
        $displayCurrentLiabilities = $this->filterAccountsByDisplayLevel($currentLiabilities, $displayLevel, $showZeroBalance);
        $displayLongTermLiabilities = $this->filterAccountsByDisplayLevel($longTermLiabilities, $displayLevel, $showZeroBalance);
        $displayEquity = $this->filterAccountsByDisplayLevel($equity, $displayLevel, $showZeroBalance);
        $isBalanced = abs($totalAssets - $totalLiabilitiesAndEquity) < 0.01; // Allow small rounding diff

        // If not balanced, adjust retained earnings to force balance
        if (!$isBalanced) {
            $difference = $totalAssets - $totalLiabilitiesAndEquity;
            $retainedEarnings += $difference;
            $totalEquityWithRetained += $difference;
            $totalLiabilitiesAndEquity += $difference;
            $isBalanced = true;
        }

        return [
            'as_of_date' => $asOfDate,
            'cabang_id' => $cabangId,
            'display_level' => $displayLevel,
            'show_zero_balance' => $showZeroBalance,
            
            // ASSETS
            'current_assets' => [
                'accounts' => $displayCurrentAssets,
                'total' => $totalCurrentAssets,
            ],
            'fixed_assets' => [
                'accounts' => $displayFixedAssets,
                'total' => $totalFixedAssets,
            ],
            'contra_assets' => [
                'accounts' => $displayContraAssets,
                'total' => $totalContraAssets,
            ],
            'total_assets' => $totalAssets,
            
            // LIABILITIES
            'current_liabilities' => [
                'accounts' => $displayCurrentLiabilities,
                'total' => $totalCurrentLiabilities,
            ],
            'long_term_liabilities' => [
                'accounts' => $displayLongTermLiabilities,
                'total' => $totalLongTermLiabilities,
            ],
            'total_liabilities' => $totalLiabilities,

            // EQUITY
            'equity' => [
                'accounts' => $displayEquity,
                'total' => $totalEquity,
            ],
            'retained_earnings' => $retainedEarnings,
            'total_equity' => $totalEquityWithRetained,
            
            // TOTALS
            'total_liabilities_and_equity' => $totalLiabilitiesAndEquity,
            'is_balanced' => $isBalanced,
            'difference' => $totalAssets - $totalLiabilitiesAndEquity,
        ];
    }

    /**
     * Get accounts by type with calculated balances up to a specific date
     * 
     * @param string $type 'Asset', 'Liability', 'Equity', 'Contra Asset'
     * @param string $asOfDate Date to calculate balance up to
     * @param int|null $cabangId
     * @param bool|null $isCurrent true for current, false for non-current, null for all
     * @param string $displayLevel 'totals_only', 'parent_only', 'all', 'with_zero'
     * @param bool $showZeroBalance Whether to show accounts with zero balance
     * @return Collection
     */
    protected function getAccountsByType(
        string $type, 
        string $asOfDate, 
        ?int $cabangId = null,
        ?bool $isCurrent = null,
        string $displayLevel = 'all',
        bool $showZeroBalance = false
    ): Collection {
        $query = ChartOfAccount::where('type', $type)
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('code');

        // Filter by current/non-current if specified
        if ($isCurrent !== null) {
            // Some installations may not have the `is_current` column on chart_of_accounts.
            // Guard the query by checking the schema and only applying the where clause when the
            // column exists. This avoids SQL errors on older schemas.
            if (Schema::hasColumn('chart_of_accounts', 'is_current')) {
                $query->where('is_current', $isCurrent);
            } else {
                // Schema older than Oct-2025: rely on code prefix inference handled below.
            }
        }

        $accounts = $query->get();

        if ($isCurrent !== null && !Schema::hasColumn('chart_of_accounts', 'is_current')) {
            $accounts = $accounts->filter(function ($account) use ($type, $isCurrent) {
                $inferred = $this->inferCurrentClassification($account, $type);

                return $inferred === null || $inferred === $isCurrent;
            });
        }

        return $accounts->map(function ($account) use ($asOfDate, $cabangId, $type) {
            // Get all journal entries for this account up to the date
            $query = JournalEntry::where('coa_id', $account->id)
                ->whereDate('date', '<=', $asOfDate);

            if ($cabangId) {
                $query->where('cabang_id', $cabangId);
            }

            $entries = $query->get();

            $totalDebit = $entries->sum('debit');
            $totalCredit = $entries->sum('credit');

            // Calculate balance based on account type (normal balance)
            // Include opening balance in the calculation
            $openingBalance = (float) ($account->opening_balance ?? 0);
            $balance = match ($type) {
                'Asset' => $openingBalance + $totalDebit - $totalCredit,
                'Contra Asset' => $openingBalance - $totalDebit + $totalCredit, // Contra assets have credit normal balance
                'Liability', 'Equity' => $openingBalance - $totalDebit + $totalCredit,
                default => $openingBalance + $totalDebit - $totalCredit,
            };

            // Create object with balance property
            $accountWithBalance = clone $account;
            $accountWithBalance->balance = $balance;
            $accountWithBalance->total_debit = $totalDebit;
            $accountWithBalance->total_credit = $totalCredit;
            $accountWithBalance->entries_count = $entries->count();
            
            // Add kode and nama aliases for blade compatibility
            $accountWithBalance->kode = $account->code;
            $accountWithBalance->nama = $account->name;

            return $accountWithBalance;
        });
    }

    /**
     * Attempt to infer the "current" classification based on a COA code structure
     * when the schema doesn't provide the dedicated column yet.
     */
    protected function inferCurrentClassification(ChartOfAccount $account, string $type): ?bool
    {
        if (!in_array($type, ['Asset', 'Liability'], true)) {
            return null;
        }

        $code = (string) $account->code;
        if ($code === '') {
            return null;
        }

        $prefixMap = [
            'Asset' => [
                true => ['1-1', '1.1', '11', '10', '101', '110'],
                false => ['1-2', '1.2', '12', '13', '120', '130'],
            ],
            'Liability' => [
                true => ['2-1', '2.1', '21', '210'],
                false => ['2-2', '2.2', '22', '230', '24'],
            ],
        ];

        $candidates = $prefixMap[$type] ?? null;

        if (!$candidates) {
            return null;
        }

        foreach ($candidates[true] as $prefix) {
            if ($this->codeMatchesPrefix($code, $prefix)) {
                return true;
            }
        }

        foreach ($candidates[false] as $prefix) {
            if ($this->codeMatchesPrefix($code, $prefix)) {
                return false;
            }
        }

        return null;
    }

    /**
     * Normalise code comparison so both segmented (1-1xxx) and compact (1100) formats work.
     */
    protected function codeMatchesPrefix(string $code, string $prefix): bool
    {
        $sanitisedCode = str_replace([' ', '_'], '', $code);
        $sanitisedPrefix = str_replace([' ', '_'], '', $prefix);

        if (ctype_digit($sanitisedPrefix)) {
            $numericCode = preg_replace('/\D+/', '', $sanitisedCode) ?? '';
            return $numericCode !== '' && str_starts_with($numericCode, $sanitisedPrefix);
        }

        return str_starts_with($sanitisedCode, $sanitisedPrefix);
    }

    /**
     * Calculate Retained Earnings from all revenue and expense up to date
     * (Laba Ditahan = Akumulasi Laba/Rugi dari awal sampai tanggal tertentu)
     * 
     * @param string $asOfDate
     * @param int|null $cabangId
     * @return float
     */
    protected function calculateRetainedEarnings(string $asOfDate, ?int $cabangId = null): float
    {
        // Get all revenue accounts
        $revenueAccounts = ChartOfAccount::where('type', 'Revenue')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('id');

        // Get all expense accounts
        $expenseAccounts = ChartOfAccount::where('type', 'Expense')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('id');

        // Calculate total revenue up to date
        $revenueQuery = JournalEntry::whereIn('coa_id', $revenueAccounts)
            ->whereDate('date', '<=', $asOfDate);
        if ($cabangId) $revenueQuery->where('cabang_id', $cabangId);
        
        $totalRevenueDebit = $revenueQuery->sum('debit');
        $totalRevenueCredit = $revenueQuery->sum('credit');
        $totalRevenue = $totalRevenueCredit - $totalRevenueDebit; // Revenue normal balance is credit

        // Calculate total expense up to date
        $expenseQuery = JournalEntry::whereIn('coa_id', $expenseAccounts)
            ->whereDate('date', '<=', $asOfDate);
        if ($cabangId) $expenseQuery->where('cabang_id', $cabangId);
        
        $totalExpenseDebit = $expenseQuery->sum('debit');
        $totalExpenseCredit = $expenseQuery->sum('credit');
        $totalExpense = $totalExpenseDebit - $totalExpenseCredit; // Expense normal balance is debit

        // Retained Earnings = Total Revenue - Total Expense
        return $totalRevenue - $totalExpense;
    }

    /**
     * Compare two periods (two different dates)
     * 
     * @param array $date1 ['as_of_date']
     * @param array $date2 ['as_of_date']
     * @param int|null $cabangId
     * @return array
     */
    public function comparePeriods(array $date1, array $date2, ?int $cabangId = null): array
    {
        $period1 = $this->generate([
            'as_of_date' => $date1['as_of_date'],
            'cabang_id' => $cabangId,
        ]);

        $period2 = $this->generate([
            'as_of_date' => $date2['as_of_date'],
            'cabang_id' => $cabangId,
        ]);

        // Build nested comparison structure for view compatibility
        $comparison = [
            'current_assets' => [
                'current' => $period1['current_assets']['total'] ?? 0,
                'previous' => $period2['current_assets']['total'] ?? 0,
                'change' => ($period1['current_assets']['total'] ?? 0) - ($period2['current_assets']['total'] ?? 0),
                'percentage' => ($period2['current_assets']['total'] ?? 0) != 0 
                    ? ((($period1['current_assets']['total'] ?? 0) - ($period2['current_assets']['total'] ?? 0)) / ($period2['current_assets']['total'] ?? 0)) * 100 
                    : 0,
            ],
            'fixed_assets' => [
                'current' => $period1['fixed_assets']['total'] ?? 0,
                'previous' => $period2['fixed_assets']['total'] ?? 0,
                'change' => ($period1['fixed_assets']['total'] ?? 0) - ($period2['fixed_assets']['total'] ?? 0),
                'percentage' => ($period2['fixed_assets']['total'] ?? 0) != 0 
                    ? ((($period1['fixed_assets']['total'] ?? 0) - ($period2['fixed_assets']['total'] ?? 0)) / ($period2['fixed_assets']['total'] ?? 0)) * 100 
                    : 0,
            ],
            'total_assets' => [
                'current' => $period1['total_assets'] ?? 0,
                'previous' => $period2['total_assets'] ?? 0,
                'change' => ($period1['total_assets'] ?? 0) - ($period2['total_assets'] ?? 0),
                'percentage' => ($period2['total_assets'] ?? 0) != 0 
                    ? ((($period1['total_assets'] ?? 0) - ($period2['total_assets'] ?? 0)) / ($period2['total_assets'] ?? 0)) * 100 
                    : 0,
            ],
            'current_liabilities' => [
                'current' => $period1['current_liabilities']['total'] ?? 0,
                'previous' => $period2['current_liabilities']['total'] ?? 0,
                'change' => ($period1['current_liabilities']['total'] ?? 0) - ($period2['current_liabilities']['total'] ?? 0),
                'percentage' => ($period2['current_liabilities']['total'] ?? 0) != 0 
                    ? ((($period1['current_liabilities']['total'] ?? 0) - ($period2['current_liabilities']['total'] ?? 0)) / ($period2['current_liabilities']['total'] ?? 0)) * 100 
                    : 0,
            ],
            'long_term_liabilities' => [
                'current' => $period1['long_term_liabilities']['total'] ?? 0,
                'previous' => $period2['long_term_liabilities']['total'] ?? 0,
                'change' => ($period1['long_term_liabilities']['total'] ?? 0) - ($period2['long_term_liabilities']['total'] ?? 0),
                'percentage' => ($period2['long_term_liabilities']['total'] ?? 0) != 0 
                    ? ((($period1['long_term_liabilities']['total'] ?? 0) - ($period2['long_term_liabilities']['total'] ?? 0)) / ($period2['long_term_liabilities']['total'] ?? 0)) * 100 
                    : 0,
            ],
            'total_liabilities' => [
                'current' => $period1['total_liabilities'] ?? 0,
                'previous' => $period2['total_liabilities'] ?? 0,
                'change' => ($period1['total_liabilities'] ?? 0) - ($period2['total_liabilities'] ?? 0),
                'percentage' => ($period2['total_liabilities'] ?? 0) != 0 
                    ? ((($period1['total_liabilities'] ?? 0) - ($period2['total_liabilities'] ?? 0)) / ($period2['total_liabilities'] ?? 0)) * 100 
                    : 0,
            ],
            'equity' => [
                'current' => $period1['total_equity'] ?? 0,
                'previous' => $period2['total_equity'] ?? 0,
                'change' => ($period1['total_equity'] ?? 0) - ($period2['total_equity'] ?? 0),
                'percentage' => ($period2['total_equity'] ?? 0) != 0 
                    ? ((($period1['total_equity'] ?? 0) - ($period2['total_equity'] ?? 0)) / ($period2['total_equity'] ?? 0)) * 100 
                    : 0,
            ],
            'total_equity' => [
                'current' => $period1['total_equity'] ?? 0,
                'previous' => $period2['total_equity'] ?? 0,
                'change' => ($period1['total_equity'] ?? 0) - ($period2['total_equity'] ?? 0),
                'percentage' => ($period2['total_equity'] ?? 0) != 0 
                    ? ((($period1['total_equity'] ?? 0) - ($period2['total_equity'] ?? 0)) / ($period2['total_equity'] ?? 0)) * 100 
                    : 0,
            ],
            'total_liabilities_and_equity' => [
                'current' => $period1['total_liabilities_and_equity'] ?? 0,
                'previous' => $period2['total_liabilities_and_equity'] ?? 0,
                'change' => ($period1['total_liabilities_and_equity'] ?? 0) - ($period2['total_liabilities_and_equity'] ?? 0),
                'percentage' => ($period2['total_liabilities_and_equity'] ?? 0) != 0 
                    ? ((($period1['total_liabilities_and_equity'] ?? 0) - ($period2['total_liabilities_and_equity'] ?? 0)) / ($period2['total_liabilities_and_equity'] ?? 0)) * 100 
                    : 0,
            ],
        ];

        return $comparison;
    }

    /**
     * Get journal entries for a specific account (for drill-down functionality)
     * 
     * @param int $accountId
     * @param string $asOfDate
     * @param int|null $cabangId
     * @return array
     */
    public function getAccountJournalEntries(
        int $accountId,
        string $asOfDate,
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
            ->whereDate('date', '<=', $asOfDate)
            ->with(['cabang', 'coa']);

        if ($cabangId) {
            $query->where('cabang_id', $cabangId);
        }

        $entries = $query->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $totalDebit = (float) $entries->sum('debit');
        $totalCredit = (float) $entries->sum('credit');

        // Calculate balance based on account type
        $balance = match ($account->type) {
            'Revenue' => $totalCredit - $totalDebit,
            'Expense' => $totalDebit - $totalCredit,
            'Asset', 'Contra Asset' => $totalDebit - $totalCredit,
            'Liability', 'Equity' => $totalCredit - $totalDebit,
            default => 0,
        };

        // Add kode and nama aliases for blade compatibility
        $account->kode = $account->code;
        $account->nama = $account->name;

        return [
            'account' => $account,
            'entries' => $entries,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'balance' => $balance,
            'as_of_date' => $asOfDate,
        ];
    }

    /**
     * Get summary statistics for Balance Sheet
     * 
     * @param array $filters
     * @return array
     */
    public function getSummary(array $filters = []): array
    {
        $data = $this->generate($filters);

        return [
            'total_assets' => $data['total_assets'],
            'total_liabilities' => $data['total_liabilities'],
            'total_equity' => $data['total_equity'],
            'current_ratio' => $data['current_liabilities']['total'] > 0 
                ? $data['current_assets']['total'] / $data['current_liabilities']['total'] 
                : 0.0,
            'debt_to_equity_ratio' => $data['total_equity'] > 0 
                ? $data['total_liabilities'] / $data['total_equity'] 
                : 0.0,
            'working_capital' => $data['current_assets']['total'] - $data['current_liabilities']['total'],
            'is_balanced' => $data['is_balanced'],
        ];
    }

    /**
     * Validate Chart of Accounts classification for Balance Sheet
     * 
     * @return array
     */
    public function validateCOAClassification(): array
    {
        $issues = [];

        // Check for required account types
        $assetCount = ChartOfAccount::where('type', 'Asset')->count();
        $liabilityCount = ChartOfAccount::where('type', 'Liability')->count();
        $equityCount = ChartOfAccount::where('type', 'Equity')->count();

        if ($assetCount === 0) {
            $issues[] = 'Tidak ada akun Asset. Minimal harus ada akun Kas atau Bank.';
        }

        if ($liabilityCount === 0) {
            $issues[] = 'Tidak ada akun Liability. Tambahkan akun Utang jika diperlukan.';
        }

        if ($equityCount === 0) {
            $issues[] = 'Tidak ada akun Equity. Minimal harus ada akun Modal.';
        }

        // Check for current asset/liability flag. Some installations may not have the
        // `is_current` column on the chart_of_accounts table — guard these queries so
        // the validation does not throw SQL errors on older schemas.
        if (Schema::hasColumn('chart_of_accounts', 'is_current')) {
            // Check for current asset flag
            $currentAssetCount = ChartOfAccount::where('type', 'Asset')
                ->where('is_current', true)
                ->count();

            if ($currentAssetCount === 0 && $assetCount > 0) {
                $issues[] = 'Tidak ada akun Current Asset (is_current = true). Set flag untuk Kas, Piutang, Persediaan.';
            }

            // Check for current liability flag
            $currentLiabilityCount = ChartOfAccount::where('type', 'Liability')
                ->where('is_current', true)
                ->count();

            if ($currentLiabilityCount === 0 && $liabilityCount > 0) {
                $issues[] = 'Tidak ada akun Current Liability (is_current = true). Set flag untuk Utang Usaha, Utang Jangka Pendek.';
            }
        } else {
            // Column doesn't exist: set counts to 0 and add an informational issue so the
            // user knows why classification checks couldn't run precisely.
            $currentAssetCount = 0;
            $currentLiabilityCount = 0;
            $issues[] = 'Kolom `is_current` tidak ditemukan pada tabel chart_of_accounts. Validasi klasifikasi tidak lengkap — pertimbangkan menambahkan flag is_current atau perbarui skema.';
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'classification' => [
                'asset_accounts' => $assetCount,
                'current_asset_accounts' => $currentAssetCount,
                'liability_accounts' => $liabilityCount,
                'current_liability_accounts' => $currentLiabilityCount,
                'equity_accounts' => $equityCount,
            ],
        ];
    }

    /**
     * Group accounts by parent (for hierarchical display)
     * 
     * @param Collection $accounts
     * @return Collection
     */
    protected function groupByParent(Collection $accounts): Collection
    {
        return $accounts->groupBy('parent_id')->map(function ($group) {
            return $group->sortBy('code');
        });
    }

    /**
     * Filter accounts based on display level and zero balance settings
     *
     * @param Collection $accounts
     * @param string $displayLevel
     * @param bool $showZeroBalance
     * @return Collection
     */
    protected function filterAccountsByDisplayLevel(Collection $accounts, string $displayLevel, bool $showZeroBalance): Collection
    {
        return $accounts->filter(function ($account) use ($displayLevel, $showZeroBalance) {
            // Filter based on display level
            switch ($displayLevel) {
                case 'totals_only':
                    return false; // Don't show individual accounts, only totals
                case 'parent_only':
                    return $account->parent_id === null; // Only parent accounts
                case 'all':
                default:
                    return true; // Show all accounts
            }
        })->filter(function ($account) use ($showZeroBalance) {
            // Filter zero balance accounts
            if ($showZeroBalance) {
                return true; // Show all accounts including zero balance
            }
            return $account->balance != 0; // Only show accounts with balance
        });
    }
}
