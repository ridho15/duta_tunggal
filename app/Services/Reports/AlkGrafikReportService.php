<?php

namespace App\Services\Reports;

use App\Models\Cabang;
use App\Services\BalanceSheetService;
use App\Services\IncomeStatementService;
use Illuminate\Support\Carbon;

class AlkGrafikReportService
{
    public function __construct(
        protected BalanceSheetService $balanceSheetService,
        protected IncomeStatementService $incomeStatementService,
    ) {
    }

    public function generate(array $filters = []): array
    {
        $startDate = Carbon::parse($filters['start_date'] ?? now()->startOfMonth()->toDateString())->toDateString();
        $endDate = Carbon::parse($filters['end_date'] ?? now()->endOfMonth()->toDateString())->toDateString();
        $cabangId = blank($filters['cabang_id'] ?? null) ? null : (int) $filters['cabang_id'];

        $balanceSheet = $this->balanceSheetService->generate([
            'as_of_date' => $endDate,
            'cabang_id' => $cabangId,
            'display_level' => 'all',
            'show_zero_balance' => true,
        ]);

        $incomeStatement = $this->incomeStatementService->generate([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cabang_id' => $cabangId,
        ]);

        $summary = $this->buildSummary($balanceSheet, $incomeStatement);

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'period_label' => $this->periodLabel($startDate, $endDate),
            'branch_name' => $cabangId ? Cabang::query()->whereKey($cabangId)->value('nama') : null,
            'summary' => $summary,
            'ratios' => $this->buildRatios($summary),
            'trend' => $this->buildTrend($startDate, $endDate, $cabangId),
        ];
    }

    protected function buildSummary(array $balanceSheet, array $incomeStatement): array
    {
        return [
            'total_assets' => (float) ($balanceSheet['total_assets'] ?? 0),
            'total_liabilities' => (float) ($balanceSheet['total_liabilities'] ?? 0),
            'total_equity' => (float) ($balanceSheet['total_equity'] ?? 0),
            'current_assets' => (float) data_get($balanceSheet, 'current_assets.total', 0),
            'current_liabilities' => (float) data_get($balanceSheet, 'current_liabilities.total', 0),
            'revenue' => (float) data_get($incomeStatement, 'revenue.total', 0),
            'expense' => (float) data_get($incomeStatement, 'expense.total', 0),
            'net_profit' => (float) ($incomeStatement['net_profit'] ?? 0),
            'is_balanced' => (bool) ($balanceSheet['is_balanced'] ?? false),
            'difference' => (float) ($balanceSheet['difference'] ?? 0),
        ];
    }

    protected function buildRatios(array $summary): array
    {
        $currentAssets = (float) ($summary['current_assets'] ?? 0);
        $currentLiabilities = (float) ($summary['current_liabilities'] ?? 0);
        $totalLiabilities = (float) ($summary['total_liabilities'] ?? 0);
        $totalEquity = (float) ($summary['total_equity'] ?? 0);
        $totalAssets = (float) ($summary['total_assets'] ?? 0);
        $revenue = (float) ($summary['revenue'] ?? 0);
        $netProfit = (float) ($summary['net_profit'] ?? 0);

        return [
            'current_ratio' => $currentLiabilities > 0 ? round($currentAssets / $currentLiabilities, 2) : null,
            'debt_to_equity' => $totalEquity > 0 ? round($totalLiabilities / $totalEquity, 2) : null,
            'roa' => $totalAssets > 0 ? round(($netProfit / $totalAssets) * 100, 2) : null,
            'roe' => $totalEquity > 0 ? round(($netProfit / $totalEquity) * 100, 2) : null,
            'profit_margin' => $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : null,
        ];
    }

    protected function buildTrend(string $startDate, string $endDate, ?int $cabangId): array
    {
        $start = Carbon::parse($startDate)->startOfMonth();
        $end = Carbon::parse($endDate)->endOfMonth();

        if ($start->diffInMonths($end) >= 12) {
            $start = $end->copy()->subMonths(11)->startOfMonth();
        }

        $baseStart = Carbon::parse($startDate);
        $baseEnd = Carbon::parse($endDate);
        $cursor = $start->copy();
        $trend = [];

        while ($cursor->lte($end)) {
            $monthStart = $cursor->copy()->startOfMonth()->max($baseStart);
            $monthEnd = $cursor->copy()->endOfMonth()->min($baseEnd);

            $incomeStatement = $this->incomeStatementService->generate([
                'start_date' => $monthStart->toDateString(),
                'end_date' => $monthEnd->toDateString(),
                'cabang_id' => $cabangId,
            ]);

            $revenue = (float) data_get($incomeStatement, 'revenue.total', 0);
            $expense = (float) data_get($incomeStatement, 'expense.total', 0);

            $trend[] = [
                'month' => $cursor->translatedFormat('M Y'),
                'revenue' => $revenue,
                'expense' => $expense,
                'profit' => (float) ($incomeStatement['net_profit'] ?? ($revenue - $expense)),
            ];

            $cursor->addMonth();
        }

        return $trend;
    }

    protected function periodLabel(string $startDate, string $endDate): string
    {
        return Carbon::parse($startDate)->format('d M Y') . ' s/d ' . Carbon::parse($endDate)->format('d M Y');
    }
}