<?php

namespace App\Filament\Resources\Reports\ProfitAndLossResource\Pages;

use App\Filament\Resources\Reports\ProfitAndLossResource;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Reports\HppPrefix;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Carbon;

class ViewProfitAndLoss extends Page
{
    protected static string $resource = ProfitAndLossResource::class;
    protected static string $view = 'filament.pages.reports.profit-and-loss';

    public bool $showPreview = false;

    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?string $cabang_id = null;
    public ?array $branches = [];
    public ?bool $compare = false;
    public ?string $compareStartDate = null;
    public ?string $compareEndDate = null;

    public function mount(): void
    {
        $this->startDate = request('startDate', now()->startOfMonth()->toDateString());
        $this->endDate = request('endDate', now()->endOfMonth()->toDateString());
        $this->cabang_id = request('cabang_id');
        $this->compare = filter_var(request('compare', false), FILTER_VALIDATE_BOOL);
        $this->compareStartDate = request('compareStartDate');
        $this->compareEndDate = request('compareEndDate');
        $this->showPreview = filter_var(request('preview', false), FILTER_VALIDATE_BOOL);

        $this->form->fill([
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'cabang_id' => $this->cabang_id,
            'compare' => $this->compare,
            'compareStartDate' => $this->compareStartDate,
            'compareEndDate' => $this->compareEndDate,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Tampilkan Laporan')
                ->icon('heroicon-o-eye')
                ->color('primary')
                ->action(fn () => $this->generateReport()),

            Action::make('reset')
                ->label('Reset')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->visible(fn () => $this->showPreview)
                ->action(fn () => $this->resetReport()),
        ];
    }

    public function generateReport(): void
    {
        $this->dispatch('open-report-preview', url: $this->getPreviewUrl());
    }

    public function resetReport(): void
    {
        $this->redirect(static::getResource()::getUrl('index'));
    }

    public function getPreviewUrl(): string
    {
        return static::getResource()::getUrl('index') . '?' . http_build_query(array_filter([
            'preview' => 1,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'cabang_id' => $this->cabang_id,
            'compare' => $this->compare ? 1 : 0,
            'compareStartDate' => $this->compareStartDate,
            'compareEndDate' => $this->compareEndDate,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Filter')
                ->columns(4)
                ->schema([
                    DatePicker::make('startDate')->label('Start Date')->default(now()->startOfMonth())->reactive(),
                    DatePicker::make('endDate')->label('End Date')->default(now()->endOfMonth())->reactive(),
                    Select::make('cabang_id')->label('Cabang')
                        ->options(\App\Models\Cabang::query()->pluck('nama','id'))
                        ->searchable(),
                    Forms\Components\Toggle::make('compare')->label('Bandingkan Periode')->reactive(),
                    DatePicker::make('compareStartDate')->label('Compare Start')->visible(fn(Get $get) => $get('compare') === true),
                    DatePicker::make('compareEndDate')->label('Compare End')->visible(fn(Get $get) => $get('compare') === true),
                ]),
        ];
    }

    public function getReportData(): array
    {
        $start = $this->startDate ? Carbon::parse($this->startDate)->startOfDay() : now()->startOfMonth();
        $end = $this->endDate ? Carbon::parse($this->endDate)->endOfDay() : now()->endOfMonth();

        // Fetch Revenue and Expense accounts
        $revenueAccounts = ChartOfAccount::where('type', 'Revenue')->get();
        $expenseAccounts = ChartOfAccount::where('type', 'Expense')->get();

        $revenue = $this->sumByAccounts($revenueAccounts, $start, $end);
        $expense = $this->sumByAccounts($expenseAccounts, $start, $end);

        $grossProfit = $revenue - ($this->getCogs($start, $end));
        $operatingProfit = $grossProfit - $this->getOperatingExpenses($start, $end);
        $otherNet = $this->getOtherIncomeExpense($start, $end);
        $profitBeforeTax = $operatingProfit + $otherNet;
        $tax = $this->getTaxExpense($start, $end);
        $netProfit = $profitBeforeTax - $tax;

        return [
            'revenue' => $revenue,
            'expense' => $expense,
            'gross_profit' => $grossProfit,
            'operating_profit' => $operatingProfit,
            'other_net' => $otherNet,
            'profit_before_tax' => $profitBeforeTax,
            'tax' => $tax,
            'net_profit' => $netProfit,
        ];
    }

    protected function sumByAccounts($accounts, $start, $end): float
    {
        $ids = $accounts->pluck('id');
        // For revenue (credit normal), use credit - debit; for expense (debit normal), use debit - credit
        $type = optional($accounts->first())->type;
        $query = JournalEntry::whereIn('coa_id', $ids)->whereBetween('date', [$start, $end]);
        
        if (!empty($this->cabang_id)) {
            $query->where('cabang_id', $this->cabang_id);
        }
        
        if ($type === 'Revenue') {
            return (float) (clone $query)->sum('credit') - (float) (clone $query)->sum('debit');
        }
        return (float) (clone $query)->sum('debit') - (float) (clone $query)->sum('credit');
    }

    protected function getCogs($start, $end): float
    {
        $codes = $this->getHppPrefixes('cogs_code');
        $prefixes = $this->getHppPrefixes('cogs_prefix');

        $query = ChartOfAccount::query();

        if (!empty($codes) || !empty($prefixes)) {
            $query->where(function ($q) use ($codes, $prefixes) {
                $hasCondition = false;

                if (!empty($codes)) {
                    $q->whereIn('code', $codes);
                    $hasCondition = true;
                }

                foreach ($prefixes as $prefix) {
                    if ($hasCondition) {
                        $q->orWhere('code', 'like', $prefix . '%');
                    } else {
                        $q->where('code', 'like', $prefix . '%');
                        $hasCondition = true;
                    }
                }
            });
        } else {
            $query->where('type', 'Expense')
                ->where('name', 'like', '%HPP%');
        }

        $cogsAccounts = $query->get();

        return $this->sumByAccounts($cogsAccounts, $start, $end);
    }

    private function getHppPrefixes(string $category): array
    {
        return HppPrefix::query()
            ->where('category', $category)
            ->orderBy('sort_order')
            ->pluck('prefix')
            ->filter()
            ->values()
            ->toArray();
    }

    protected function getOperatingExpenses($start, $end): float
    {
        $expenseAccounts = ChartOfAccount::where('type', 'Expense')
            ->where('name', 'not like', '%HPP%')
            ->get();
        return $this->sumByAccounts($expenseAccounts, $start, $end);
    }

    protected function getOtherIncomeExpense($start, $end): float
    {
        $otherIncome = ChartOfAccount::where('type', 'Revenue')->where('name', 'like', '%Lain%')->get();
        $otherExpense = ChartOfAccount::where('type', 'Expense')->where('name', 'like', '%Lain%')->get();
        return $this->sumByAccounts($otherIncome, $start, $end) - $this->sumByAccounts($otherExpense, $start, $end);
    }

    protected function getTaxExpense($start, $end): float
    {
        $taxAccounts = ChartOfAccount::where('type', 'Expense')->where('name', 'like', '%Pajak%')->get();
        return $this->sumByAccounts($taxAccounts, $start, $end);
    }
}
