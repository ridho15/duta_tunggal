<?php

namespace App\Filament\Pages;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\BalanceSheetService;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class FinancialStatementPage extends Page
{
    protected static string $view = 'filament.pages.financial-statement-page';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Finance - Laporan';

    protected static ?string $navigationLabel = 'Financial Statement';

    protected static ?int $navigationSort = 7;

    protected static ?string $slug = 'financial-statement';

    // Filter state
    public bool $showPreview = false;
    public ?string $start_date = null;
    public ?string $end_date = null;
    public ?int $cabang_id = null;
    public string $statement_type = 'all'; // 'all', 'pl', 'bs'

    protected BalanceSheetService $balanceSheetService;

    public function boot(BalanceSheetService $balanceSheetService): void
    {
        $this->balanceSheetService = $balanceSheetService;
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('preview')
                ->label('Tampilkan Laporan')
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
        $this->start_date = now()->startOfMonth()->format('Y-m-d');
        $this->end_date = now()->endOfMonth()->format('Y-m-d');
    }

    public function getStatementData(): array
    {
        if (!$this->showPreview) {
            return [];
        }

        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = Carbon::parse($this->end_date)->endOfDay();

        $result = [];

        // Profit & Loss
        if (in_array($this->statement_type, ['all', 'pl'])) {
            $revenueAccounts = ChartOfAccount::where('type', 'Revenue')->get();
            $expenseAccounts = ChartOfAccount::where('type', 'Expense')->get();

            $revenue = $this->sumAccounts($revenueAccounts->pluck('id'), $start, $end, 'credit');
            $cogs     = $this->sumAccounts(
                ChartOfAccount::where('type', 'Expense')->where('name', 'like', '%HPP%')->orWhere('name', 'like', '%Pokok%')->pluck('id'),
                $start, $end, 'debit'
            );
            $grossProfit = $revenue - $cogs;
            $opex = $this->sumAccounts($expenseAccounts->pluck('id'), $start, $end, 'debit') - $cogs;
            $netProfit = $grossProfit - $opex;

            $result['pl'] = [
                'revenue'      => $revenue,
                'cogs'         => $cogs,
                'gross_profit' => $grossProfit,
                'opex'         => $opex,
                'net_profit'   => $netProfit,
                'period'       => $start->format('d M Y') . ' s/d ' . $end->format('d M Y'),
            ];
        }

        // Balance Sheet
        if (in_array($this->statement_type, ['all', 'bs'])) {
            $bsData = $this->balanceSheetService->generate([
                'as_of_date' => $this->end_date,
                'cabang_id'  => $this->cabang_id,
            ]);
            $result['bs'] = $bsData;
        }

        return $result;
    }

    protected function sumAccounts($ids, $start, $end, string $column): float
    {
        if ($ids->isEmpty()) return 0.0;
        $query = JournalEntry::whereIn('coa_id', $ids)->whereBetween('date', [$start, $end]);
        if ($this->cabang_id) $query->where('cabang_id', $this->cabang_id);
        return (float) $query->sum($column);
    }
}
