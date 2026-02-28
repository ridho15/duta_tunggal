<?php

namespace App\Filament\Pages;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\BalanceSheetService;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * ALK Grafik = Analisis Laporan Keuangan (Financial Statement Analysis with Charts)
 * Includes key financial ratios: Liquidity, Solvency, Profitability, Activity
 */
class AlkGraficPage extends Page
{
    protected static string $view = 'filament.pages.alk-grafic-page';

    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationGroup = 'Finance - Laporan';

    protected static ?string $navigationLabel = 'ALK Grafik (Analisis Laporan Keuangan)';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'alk-grafik';

    // Filter state
    public bool $showPreview = false;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $cabang_id = null;

    protected BalanceSheetService $balanceSheetService;

    public function boot(BalanceSheetService $balanceSheetService): void
    {
        $this->balanceSheetService = $balanceSheetService;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')
                ->label('Tampilkan Analisis')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->action(fn () => $this->generateReport()),

            \Filament\Actions\Action::make('reset')
                ->label('Reset')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn () => $this->showPreview)
                ->action(fn () => $this->resetReport()),
        ];
    }

    public function generateReport(): void
    {
        $this->showPreview = true;
    }

    public function resetReport(): void
    {
        $this->showPreview = false;
    }

    public function mount(): void
    {
        $this->start_date = now()->startOfYear()->format('Y-m-d');
        $this->end_date = now()->endOfMonth()->format('Y-m-d');
    }

    public function getAlkData(): array
    {
        if (!$this->showPreview) {
            return [];
        }

        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date)->endOfDay();

        // Balance Sheet data
        $bs = $this->balanceSheetService->generate([
            'as_of_date' => $this->end_date,
            'cabang_id'  => $this->cabang_id,
        ]);

        $totalAssets      = $bs['total_assets'] ?? 0;
        $totalLiabilities = $bs['total_liabilities'] ?? 0;
        $totalEquity      = $bs['total_equity'] ?? 0;

        // Get current assets / current liabilities from COA
        $currentAssetIds  = ChartOfAccount::where('type', 'Asset')
            ->where(fn ($q) => $q->where('name', 'like', '%Lancar%')->orWhere('name', 'like', '%Current%')->orWhere('name', 'like', '%Kas%')->orWhere('name', 'like', '%Piutang%')->orWhere('name', 'like', '%Persediaan%'))
            ->pluck('id');
        $currentLiabIds   = ChartOfAccount::where('type', 'Liability')
            ->where(fn ($q) => $q->where('name', 'like', '%Lancar%')->orWhere('name', 'like', '%Current%')->orWhere('name', 'like', '%Utang%')->orWhere('name', 'like', '%Jangka Pendek%'))
            ->pluck('id');

        $currentAssets = $this->netBalance($currentAssetIds, $end, 'Asset');
        $currentLiabilities = $this->netBalance($currentLiabIds, $end, 'Liability');

        // P&L for profitability ratios
        $revenue   = $this->periodSum(ChartOfAccount::where('type', 'Revenue')->pluck('id'), $start, $end, 'credit');
        $netProfit = $revenue - $this->periodSum(ChartOfAccount::where('type', 'Expense')->pluck('id'), $start, $end, 'debit');

        // Ratios
        $currentRatio = $currentLiabilities > 0 ? round($currentAssets / $currentLiabilities, 2) : null;
        $debtToEquity = $totalEquity > 0 ? round($totalLiabilities / $totalEquity, 2) : null;
        $roa          = $totalAssets > 0 ? round(($netProfit / $totalAssets) * 100, 2) : null;
        $roe          = $totalEquity > 0 ? round(($netProfit / $totalEquity) * 100, 2) : null;
        $profitMargin = $revenue > 0 ? round(($netProfit / $revenue) * 100, 2) : null;

        // Monthly trend for chart (last 6 months)
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd   = now()->subMonths($i)->endOfMonth();
            $rev = $this->periodSum(ChartOfAccount::where('type', 'Revenue')->pluck('id'), $monthStart, $monthEnd, 'credit');
            $exp = $this->periodSum(ChartOfAccount::where('type', 'Expense')->pluck('id'), $monthStart, $monthEnd, 'debit');
            $trend[] = [
                'month'   => $monthStart->format('M Y'),
                'revenue' => $rev,
                'expense' => $exp,
                'profit'  => $rev - $exp,
            ];
        }

        return [
            'total_assets'       => $totalAssets,
            'total_liabilities'  => $totalLiabilities,
            'total_equity'       => $totalEquity,
            'current_assets'     => $currentAssets,
            'current_liabilities'=> $currentLiabilities,
            'revenue'            => $revenue,
            'net_profit'         => $netProfit,
            'current_ratio'      => $currentRatio,
            'debt_to_equity'     => $debtToEquity,
            'roa'                => $roa,
            'roe'                => $roe,
            'profit_margin'      => $profitMargin,
            'trend'              => $trend,
            'period'             => $start->format('d M Y') . ' s/d ' . $end->format('d M Y'),
        ];
    }

    protected function netBalance($ids, $asOf, string $type): float
    {
        if (empty($ids) || (is_object($ids) && $ids->isEmpty())) return 0.0;
        $debit  = (float) JournalEntry::whereIn('coa_id', $ids)->where('date', '<=', $asOf)->sum('debit');
        $credit = (float) JournalEntry::whereIn('coa_id', $ids)->where('date', '<=', $asOf)->sum('credit');
        return in_array($type, ['Asset', 'Expense']) ? $debit - $credit : $credit - $debit;
    }

    protected function periodSum($ids, $start, $end, string $col): float
    {
        if (empty($ids) || (is_object($ids) && $ids->isEmpty())) return 0.0;
        $q = JournalEntry::whereIn('coa_id', $ids)->whereBetween('date', [$start, $end]);
        if ($this->cabang_id) $q->where('cabang_id', $this->cabang_id);
        return (float) $q->sum($col);
    }
}
