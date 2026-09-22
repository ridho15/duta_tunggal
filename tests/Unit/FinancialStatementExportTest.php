<?php

namespace Tests\Unit;

use App\Exports\FinancialStatementExport;
use Tests\TestCase;

class FinancialStatementExportTest extends TestCase
{
    public function test_all_statement_type_builds_summary_profit_loss_and_balance_sheet_sheets(): void
    {
        $export = new FinancialStatementExport($this->sampleReport('all'));

        $titles = collect($export->sheets())
            ->map(fn ($sheet) => $sheet->title())
            ->all();

        $this->assertSame(['Summary', 'Profit Loss', 'Balance Sheet', 'COGM'], $titles);
    }

    public function test_profit_loss_statement_type_only_builds_summary_and_profit_loss_sheets(): void
    {
        $export = new FinancialStatementExport($this->sampleReport('pl'));

        $titles = collect($export->sheets())
            ->map(fn ($sheet) => $sheet->title())
            ->all();

        $this->assertSame(['Summary', 'Profit Loss'], $titles);
    }

    public function test_balance_sheet_statement_type_only_builds_summary_and_balance_sheet_sheets(): void
    {
        $export = new FinancialStatementExport($this->sampleReport('bs'));

        $titles = collect($export->sheets())
            ->map(fn ($sheet) => $sheet->title())
            ->all();

        $this->assertSame(['Summary', 'Balance Sheet'], $titles);
    }

    public function test_cogm_statement_type_only_builds_summary_and_cogm_sheets(): void
    {
        $export = new FinancialStatementExport($this->sampleReport('cogm'));

        $titles = collect($export->sheets())
            ->map(fn ($sheet) => $sheet->title())
            ->all();

        $this->assertSame(['Summary', 'COGM'], $titles);
    }

    private function sampleReport(string $statementType): array
    {
        return [
            'statement_type' => $statementType,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'period_label' => '01 Apr 2026 s/d 30 Apr 2026',
            'branch_name' => 'Cabang Test',
            'pl' => in_array($statementType, ['all', 'pl'], true) ? [
                'revenue' => 1000000,
                'cogs' => 400000,
                'opex' => 150000,
                'gross_profit' => 600000,
                'operating_profit' => 450000,
                'profit_before_tax' => 450000,
                'net_profit' => 450000,
                'sales_revenue_accounts' => [],
                'cogs_accounts' => [],
                'operating_expense_accounts' => [],
                'other_income_accounts' => [],
                'other_expense_accounts' => [],
                'tax_accounts' => [],
            ] : null,
            'bs' => in_array($statementType, ['all', 'bs'], true) ? [
                'current_assets' => ['accounts' => [], 'total' => 1000000],
                'fixed_assets' => ['accounts' => [], 'total' => 0],
                'contra_assets' => ['accounts' => [], 'total' => 0],
                'current_liabilities' => ['accounts' => [], 'total' => 250000],
                'long_term_liabilities' => ['accounts' => [], 'total' => 0],
                'equity' => ['accounts' => [], 'total' => 750000],
                'total_assets' => 1000000,
                'total_liabilities' => 250000,
                'total_equity' => 750000,
                'total_liabilities_and_equity' => 1000000,
                'is_balanced' => true,
                'retained_earnings' => 0,
            ] : null,
            'cogm' => $statementType === 'all' || $statementType === 'cogm' ? [
                'raw_materials' => [
                    'opening' => 100000,
                    'purchases' => 250000,
                    'available' => 350000,
                    'closing' => 50000,
                    'used' => 300000,
                ],
                'direct_labor' => 125000,
                'overhead' => [
                    'items' => [
                        ['label' => 'Listrik Pabrik', 'amount' => 40000],
                    ],
                    'total' => 40000,
                ],
                'wip' => [
                    'opening' => 20000,
                    'closing' => 10000,
                ],
                'production_cost' => 465000,
                'cogm' => 455000,
            ] : null,
        ];
    }
}