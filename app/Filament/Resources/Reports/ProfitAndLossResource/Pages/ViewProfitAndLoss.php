<?php

namespace App\Filament\Resources\Reports\ProfitAndLossResource\Pages;

use App\Filament\Resources\Reports\ProfitAndLossResource;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Reports\HppPrefix;
use Filament\Actions;
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

    public ?string $startDate = null;
    public ?string $endDate = null;
    public ?array $branches = [];
    public ?bool $compare = false;
    public ?string $compareStartDate = null;
    public ?string $compareEndDate = null;

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Section::make('Filter')
                ->columns(4)
                ->schema([
                    DatePicker::make('startDate')->label('Start Date')->default(now()->startOfMonth())->reactive(),
                    DatePicker::make('endDate')->label('End Date')->default(now()->endOfMonth())->reactive(),
                    Select::make('branches')->label('Branch')
                        ->options([])->multiple()->searchable(),
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
        if ($type === 'Revenue') {
            return (float) JournalEntry::whereIn('coa_id', $ids)
                ->whereBetween('date', [$start, $end])
                ->sum('credit') - (float) JournalEntry::whereIn('coa_id', $ids)
                ->whereBetween('date', [$start, $end])
                ->sum('debit');
        }
        return (float) JournalEntry::whereIn('coa_id', $ids)
            ->whereBetween('date', [$start, $end])
            ->sum('debit') - (float) JournalEntry::whereIn('coa_id', $ids)
            ->whereBetween('date', [$start, $end])
            ->sum('credit');
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
