<?php

namespace App\Filament\Resources\Reports\BalanceSheetResource\Pages;

use App\Filament\Resources\Reports\BalanceSheetResource;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Illuminate\Support\Carbon;
use App\Models\Cabang;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewBalanceSheet extends Page
{
    protected static string $resource = BalanceSheetResource::class;
    protected static string $view = 'filament.pages.reports.balance-sheet';

    public ?string $as_of_date = null; // position date
    public ?string $cabang_id = null; // single branch selection for compatibility
    public ?array $branches = []; // multi-branch selection
    // Department & Project filters disabled for now
    // public ?array $departments = [];
    // public ?array $projects = [];
    public ?bool $show_comparison = false;
    public ?string $comparison_date = null;
    public ?bool $use_multi_period = false;
    public ?array $selected_periods = [];
    public ?string $display_mode = 'detailed'; // 'total_only', 'parent_only', 'detailed', 'with_zero'
    public ?bool $include_zero_balances = false;

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Filter')
                ->columns(4)
                ->schema([
                    DatePicker::make('as_of_date')->label('Per Tanggal')->default(now())->reactive(),
                    Select::make('cabang_id')->label('Cabang')
                        ->options(fn() => Cabang::query()->pluck('nama','id'))
                        ->searchable(),
                    // Department & Project filters removed until master data is ready
                    Toggle::make('show_comparison')->label('Bandingkan Periode')->reactive(),
                    DatePicker::make('comparison_date')->label('Per Tanggal (Banding)')->visible(fn(Get $get) => $get('show_comparison') === true),
                ]),
            Forms\Components\Section::make('Multi Periode')
                ->columns(2)
                ->schema([
                    Toggle::make('use_multi_period')->label('Gunakan Multi Periode')->reactive(),
                    Select::make('selected_periods')->label('Pilih Periode')
                        ->options([
                            'monthly' => 'Bulanan',
                            'quarterly' => 'Kuartalan',
                            'yearly' => 'Tahunan',
                        ])
                        ->multiple()
                        ->searchable()
                        ->visible(fn(Get $get) => $get('use_multi_period') === true)
                        ->helperText('Pilih periode untuk menampilkan balance sheet akumulasi'),
                ]),
            Forms\Components\Section::make('Opsi Tampilan')
                ->columns(3)
                ->schema([
                    Select::make('display_mode')->label('Mode Tampilan')
                        ->options([
                            'total_only' => 'Hanya Total',
                            'parent_only' => 'Tampilkan Akun Induk',
                            'detailed' => 'Tampilkan Akun Anak',
                            'with_zero' => 'Tampilkan Data dengan Saldo 0',
                        ])
                        ->default('detailed')
                        ->reactive(),
                    Toggle::make('include_zero_balances')->label('Sertakan Saldo 0')
                        ->visible(fn(Get $get) => $get('display_mode') !== 'with_zero')
                        ->helperText('Tampilkan akun dengan saldo 0'),
                ]),
        ];
    }

    public function getReportData(): array
    {
        $asOf = $this->as_of_date ? Carbon::parse($this->as_of_date)->endOfDay() : now()->endOfDay();

        // Check for unbalanced journal entries
        $unbalancedEntries = $this->getUnbalancedJournalEntries($asOf);

        // Group COA types for Balance Sheet
        $assets = ChartOfAccount::whereIn('type', ['Asset', 'Contra Asset'])->get();
        $liabilities = ChartOfAccount::where('type', 'Liability')->get();
        $equities = ChartOfAccount::where('type', 'Equity')->get();

        // Sum per account to respect each account's type and opening balance
        $assetTotal = $assets->sum(fn ($coa) => $this->calculateBalanceForCoa($coa, $asOf));
        $liabTotal = $liabilities->sum(fn ($coa) => $this->calculateBalanceForCoa($coa, $asOf));
        $equityAccountsTotal = $equities->sum(fn ($coa) => $this->calculateBalanceForCoa($coa, $asOf));
        $retained = $this->getRetainedEarnings($asOf);
        $current = $this->getCurrentEarnings($asOf);
        $equityTotal = $equityAccountsTotal + $retained + $current;

        return [
            'assets' => $this->detailByParent($assets, $asOf, positiveFor: 'Asset'),
            'liabilities' => $this->detailByParent($liabilities, $asOf, positiveFor: 'Liability'),
            'equity' => $this->detailByParentWithRetainedEarnings($equities, $asOf, $retained),
            'retained_earnings' => $retained,
            'current_earnings' => $current,
            'asset_total' => $assetTotal,
            'liab_total' => $liabTotal,
            'equity_total' => $equityTotal,
            'balanced' => abs(($assetTotal) - ($liabTotal + $equityTotal)) < 0.01,
            'unbalanced_entries' => $unbalancedEntries,
            'has_unbalanced_entries' => !empty($unbalancedEntries),
        ];
    }

    protected function sumBalance($accounts, Carbon $asOf): float
    {
        // Sum per account with its own opening balance & normal balance
        return $accounts->sum(fn ($coa) => $this->calculateBalanceForCoa($coa, $asOf));
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
                $balance = $this->sumBalance(collect([$coa]), $asOf);
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
                $balance = $this->sumBalance(collect([$coa]), $asOf);
                
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

    protected function getRetainedEarnings(Carbon $asOf): float
    {
        // Sum of past net profits up to previous period; simplified: sum Revenue - Expense before current fiscal year end
        $revenue = ChartOfAccount::where('type', 'Revenue')->pluck('id');
        $expense = ChartOfAccount::where('type', 'Expense')->pluck('id');
        $revQ = JournalEntry::whereIn('coa_id', $revenue)->where('date', '<=', $asOf);
        $expQ = JournalEntry::whereIn('coa_id', $expense)->where('date', '<=', $asOf);
        if (!empty($this->cabang_id)) {
            $revQ->where('cabang_id', $this->cabang_id);
            $expQ->where('cabang_id', $this->cabang_id);
        } elseif (!empty($this->branches)) {
            $revQ->whereIn('cabang_id', $this->branches);
            $expQ->whereIn('cabang_id', $this->branches);
        }
        $rev = (float) (clone $revQ)->sum('credit') - (float) (clone $revQ)->sum('debit');
        $exp = (float) (clone $expQ)->sum('debit') - (float) (clone $expQ)->sum('credit');
        $retainedEarnings = $rev - $exp;
        
        // Only adjust for opening balance imbalance if there are transactions
        // If no transactions exist, retained earnings should be 0
        $hasTransactions = JournalEntry::count() > 0;
        if ($hasTransactions) {
            // Adjust for opening balance imbalance
            // If opening assets > opening liabilities + equity, reduce retained earnings
            $openingAssets = ChartOfAccount::whereIn('type', ['Asset', 'Contra Asset'])->sum('opening_balance');
            $openingLiabilities = ChartOfAccount::where('type', 'Liability')->sum('opening_balance');
            $openingEquity = ChartOfAccount::where('type', 'Equity')->sum('opening_balance');
            $openingImbalance = $openingAssets - $openingLiabilities - $openingEquity;
            
            return $retainedEarnings - $openingImbalance;
        }
        
        return $retainedEarnings;
    }

    protected function getCurrentEarnings(Carbon $asOf): float
    {
        // If we separate retained vs current year, we'd filter by year. For now, treat all-to-date as retained earnings, current earnings = 0
        // Placeholder for future enhancement: filter from fiscal year start to $asOf
        return 0.0;
    }

    protected function getClosingAdjustment(Carbon $asOf): float
    {
        // Calculate the net effect of closing revenue and expense accounts
        // Revenue accounts (normally credit balances) get closed by debiting them and crediting retained earnings
        // Expense accounts (normally debit balances) get closed by crediting them and debiting retained earnings
        // Net effect: (Revenue balances) - (Expense balances) gets added to retained earnings
        
        $revenueAccounts = ChartOfAccount::where('type', 'Revenue')->get();
        $expenseAccounts = ChartOfAccount::where('type', 'Expense')->get();
        
        $revenueBalances = $revenueAccounts->sum(fn ($coa) => $this->calculateBalanceForCoa($coa, $asOf));
        $expenseBalances = $expenseAccounts->sum(fn ($coa) => $this->calculateBalanceForCoa($coa, $asOf));
        
        // Revenue balances are normally positive (credits), expense balances are normally positive (debits)
        // Closing adjustment = Revenue balances - Expense balances
        return $revenueBalances - $expenseBalances;
    }

    public function exportCsv()
    {
        $asOf = $this->as_of_date ? Carbon::parse($this->as_of_date)->endOfDay() : now()->endOfDay();
        $data = $this->getReportData();
        return Excel::download(new \App\Exports\BalanceSheetExport($data, $asOf), 'balance-sheet-'.now()->format('Ymd').'.csv', \Maatwebsite\Excel\Excel::CSV);
    }

    public function exportXlsx()
    {
        $asOf = $this->as_of_date ? Carbon::parse($this->as_of_date)->endOfDay() : now()->endOfDay();
        $data = $this->getReportData();
        return Excel::download(new \App\Exports\BalanceSheetExport($data, $asOf), 'balance-sheet-'.now()->format('Ymd').'.xlsx', \Maatwebsite\Excel\Excel::XLSX);
    }

    public function printPdf()
    {
        $asOf = $this->asOfDate ? Carbon::parse($this->asOfDate)->endOfDay() : now()->endOfDay();
        $data = $this->getReportData();
        $html = view('export.balance-sheet', [
            'data' => $data,
            'asOf' => $asOf,
        ])->render();
        // If barryvdh/laravel-dompdf is installed in the project
        if (class_exists('Barryvdh\\DomPDF\\Facade\\Pdf')) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html);
            return response()->streamDownload(function () use ($pdf) {
                echo $pdf->stream();
            }, 'balance-sheet-'.now()->format('Ymd').'.pdf');
        }
        // Fallback: open printable HTML in new tab
        return redirect()->away('data:text/html,'.rawurlencode($html));
    }

    public function getBalanceSheetData(): array
    {
        $data = $this->getReportData();

        // Apply display mode filtering
        $data = $this->applyDisplayMode($data);

        // Handle multi-period data if periods are selected
        if ($this->use_multi_period && !empty($this->selected_periods)) {
            $data['multi_period_data'] = $this->getMultiPeriodBalanceSheetData();
        }

        return $data;
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

    protected function getMultiPeriodBalanceSheetData(): array
    {
        $multiPeriodData = [];
        $baseDate = $this->as_of_date ? Carbon::parse($this->as_of_date) : now();

        foreach ($this->selected_periods as $period) {
            switch ($period) {
                case 'monthly':
                    // Get last 12 months
                    for ($i = 11; $i >= 0; $i--) {
                        $date = $baseDate->copy()->subMonths($i)->endOfMonth();
                        $multiPeriodData['monthly'][] = [
                            'period' => $date->format('M Y'),
                            'date' => $date->format('Y-m-d'),
                            'data' => $this->getReportDataForDate($date)
                        ];
                    }
                    break;

                case 'quarterly':
                    // Get last 4 quarters
                    for ($i = 3; $i >= 0; $i--) {
                        $date = $baseDate->copy()->subMonths($i * 3)->endOfQuarter();
                        $multiPeriodData['quarterly'][] = [
                            'period' => 'Q' . $date->quarter . ' ' . $date->year,
                            'date' => $date->format('Y-m-d'),
                            'data' => $this->getReportDataForDate($date)
                        ];
                    }
                    break;

                case 'yearly':
                    // Get last 3 years
                    for ($i = 2; $i >= 0; $i--) {
                        $date = $baseDate->copy()->subYears($i)->endOfYear();
                        $multiPeriodData['yearly'][] = [
                            'period' => $date->year,
                            'date' => $date->format('Y-m-d'),
                            'data' => $this->getReportDataForDate($date)
                        ];
                    }
                    break;
            }
        }

        return $multiPeriodData;
    }

    protected function getReportDataForDate(Carbon $asOf): array
    {
        // Clone the logic from getReportData but for a specific date
        $unbalancedEntries = $this->getUnbalancedJournalEntries($asOf);

        $assets = ChartOfAccount::whereIn('type', ['Asset', 'Contra Asset'])->get();
        $liabilities = ChartOfAccount::where('type', 'Liability')->get();
        $equities = ChartOfAccount::where('type', 'Equity')->get();

        $assetTotal = $assets->sum(fn ($coa) => $this->calculateBalanceForCoa($coa, $asOf));
        $liabTotal = $liabilities->sum(fn ($coa) => $this->calculateBalanceForCoa($coa, $asOf));
        $equityAccountsTotal = $equities->sum(fn ($coa) => $this->calculateBalanceForCoa($coa, $asOf));
        $retained = $this->getRetainedEarnings($asOf);
        $current = $this->getCurrentEarnings($asOf);
        $equityTotal = $equityAccountsTotal + $retained + $current;

        return [
            'asset_total' => $assetTotal,
            'liab_total' => $liabTotal,
            'equity_total' => $equityTotal,
            'balanced' => abs(($assetTotal) - ($liabTotal + $equityTotal)) < 0.01,
        ];
    }

    public function getComparisonData(): ?array
    {
        return $this->getCompareData();
    }

    public function getCompareData(): ?array
    {
        // Placeholder for comparison functionality
        return null;
    }

    public function getMultiPeriodData(): ?array
    {
        // Placeholder for multi-period functionality
        return null;
    }

    public function getDrillDownData(): ?array
    {
        // Placeholder for drill-down functionality
        return null;
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

    public function fixUnbalancedEntry($transactionId, $action = 'delete')
    {
        $entries = JournalEntry::where('transaction_id', $transactionId)->get();

        if ($entries->isEmpty()) {
            $this->notify('error', 'Transaction not found');
            return;
        }

        $totalDebit = $entries->sum('debit');
        $totalCredit = $entries->sum('credit');
        $difference = $totalDebit - $totalCredit;

        if (abs($difference) < 0.01) {
            $this->notify('info', 'Transaction is already balanced');
            return;
        }

        if ($action === 'delete') {
            // Delete all entries in the unbalanced transaction
            JournalEntry::where('transaction_id', $transactionId)->delete();
            $this->notify('success', 'Unbalanced transaction deleted successfully');
        } elseif ($action === 'correct') {
            // Create a correcting entry
            $correctingCoa = ChartOfAccount::where('code', '3100')->first(); // Retained Earnings as default

            if ($difference > 0) {
                // Debit > Credit, need credit entry
                JournalEntry::create([
                    'coa_id' => $correctingCoa->id,
                    'date' => now(),
                    'reference' => 'CORRECTION-' . $transactionId,
                    'description' => 'Correcting entry for unbalanced transaction ' . $transactionId,
                    'debit' => 0,
                    'credit' => abs($difference),
                    'journal_type' => 'correction',
                    'transaction_id' => $transactionId,
                ]);
            } else {
                // Credit > Debit, need debit entry
                JournalEntry::create([
                    'coa_id' => $correctingCoa->id,
                    'date' => now(),
                    'reference' => 'CORRECTION-' . $transactionId,
                    'description' => 'Correcting entry for unbalanced transaction ' . $transactionId,
                    'debit' => abs($difference),
                    'credit' => 0,
                    'journal_type' => 'correction',
                    'transaction_id' => $transactionId,
                ]);
            }

            $this->notify('success', 'Correcting entry created successfully');
        }

        // Refresh the page data
        $this->reset();
    }
}
