<?php

namespace Tests\Unit;

use App\Support\Reports\ProfitLossMultiDivisionExportPresenter;
use Tests\TestCase;

class ProfitLossMultiDivisionExportPresenterTest extends TestCase
{
    public function test_it_shapes_profit_loss_multi_division_rows_with_vtc_percentages(): void
    {
        $reportData = [
            'divisions' => [
                ['id' => 10, 'kode' => 'A', 'nama' => 'Alpha'],
                ['id' => 20, 'kode' => 'B', 'nama' => 'Beta'],
            ],
            'period' => ['start' => '2025-01-01', 'end' => '2025-12-31'],
            'revenue_rows' => [
                ['type' => 'section_header', 'name' => 'Pendapatan Usaha'],
                ['type' => 'account', 'code' => '4100.10', 'name' => 'Sales', 'balances' => [10 => 1000.0, 20 => 500.0]],
                ['type' => 'total_revenue', 'name' => 'Total Revenue', 'balances' => [10 => 1000.0, 20 => 500.0]],
            ],
            'total_revenue' => [10 => 1000.0, 20 => 500.0],
            'cogs_rows' => [
                ['type' => 'account', 'code' => '5000.10', 'name' => 'COGS', 'balances' => [10 => 400.0, 20 => 200.0]],
            ],
            'gross_profit' => [10 => 600.0, 20 => 300.0],
            'opex_sections' => [
                ['rows' => [
                    ['type' => 'subtotal', 'name' => 'Total Selling Expense', 'balances' => [10 => 100.0, 20 => 50.0]],
                ]],
            ],
            'total_opex' => [10 => 100.0, 20 => 50.0],
            'operating_profit' => [10 => 500.0, 20 => 250.0],
            'other_rows' => [
                ['code' => '8000.10', 'name' => 'Other Income', 'balances' => [10 => 50.0, 20 => 25.0]],
            ],
            'net_profit' => [10 => 550.0, 20 => 275.0],
        ];

        $rows = app(ProfitLossMultiDivisionExportPresenter::class)->rows($reportData);

        $grossProfitRow = collect($rows)->first(fn (array $row) => ($row[1] ?? null) === 'Gross Profit');
        $netProfitRow = collect($rows)->first(fn (array $row) => ($row[1] ?? null) === 'Net Profit');

        $this->assertSame(['PT. DUTA TUNGGAL'], $rows[0]);
        $this->assertSame(['AccountNo', 'AccountName', 'ALPHA Balance', 'ALPHA Vtc%', 'BETA Balance', 'BETA Vtc%'], $rows[4]);
        $this->assertSame(['', 'PENDAPATAN USAHA'], $rows[5]);
        $this->assertSame(['4100.10', '  Sales', '1,000.00', '100.00', '500.00', '100.00'], $rows[6]);
        $this->assertSame(['', 'Gross Profit', '600.00', '60.00', '300.00', '60.00'], $grossProfitRow);
        $this->assertSame(['', 'Net Profit', '550.00', '55.00', '275.00', '55.00'], $netProfitRow);
    }
}