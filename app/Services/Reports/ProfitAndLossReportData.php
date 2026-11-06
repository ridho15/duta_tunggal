<?php

namespace App\Services\Reports;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Reports\HppPrefix;
use Illuminate\Support\Carbon;

/**
 * Data laporan untuk halaman Blade. Diekstrak dari ViewProfitAndLoss (halaman Filament) tanpa bagian UI Filament;
 * properti diisi controller, lalu getReportData() menghitung laporannya.
 */
class ProfitAndLossReportData
{
    public bool $showPreview = false;

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $cabang_id = null;

    public ?array $branches = [];

    public ?bool $compare = false;

    public ?string $compareStartDate = null;

    public ?string $compareEndDate = null;

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
