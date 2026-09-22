<?php

namespace Tests\Feature;

use App\Exports\FinancialStatementExport;
use App\Exports\ProfitLossMultiDivisionExport;
use App\Models\Cabang;
use App\Models\CashBankTransaction;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Reports\CashFlowCashAccount;
use App\Models\User;
use App\Services\BalanceSheetService;
use App\Services\IncomeStatementService;
use App\Services\ProfitLossMultiDivisionService;
use App\Services\Reports\CashFlowReportService;
use App\Services\Reports\FinancialStatementReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class FinancialReportPreviewControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_profit_and_loss_preview_matches_income_statement_service(): void
    {
        $user = User::factory()->create();
        $branch = Cabang::factory()->create(['nama' => 'Profit Preview Branch']);

        $sales = ChartOfAccount::factory()->create([
            'code' => '4-1001',
            'name' => 'Pendapatan Penjualan',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $cogs = ChartOfAccount::factory()->create([
            'code' => '5-1001',
            'name' => 'Harga Pokok Penjualan',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $operatingExpense = ChartOfAccount::factory()->create([
            'code' => '6-1001',
            'name' => 'Beban Gaji',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $otherExpense = ChartOfAccount::factory()->create([
            'code' => '7-9001',
            'name' => 'Beban Lain-lain',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $taxExpense = ChartOfAccount::factory()->create([
            'code' => '9-1001',
            'name' => 'Beban Pajak Penghasilan',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $this->createJournal($sales, '2025-01-10', 0, 1000000, $branch->id);
        $this->createJournal($cogs, '2025-01-11', 400000, 0, $branch->id);
        $this->createJournal($operatingExpense, '2025-01-12', 150000, 0, $branch->id);
        $this->createJournal($otherExpense, '2025-01-13', 50000, 0, $branch->id);
        $this->createJournal($taxExpense, '2025-01-14', 25000, 0, $branch->id);

        $expected = app(IncomeStatementService::class)->generate([
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'cabang_id' => $branch->id,
        ]);

        $response = $this->actingAs($user)->get(route('reports.profit-and-loss.preview', [
            'startDate' => '2025-01-01',
            'endDate' => '2025-01-31',
            'cabang_id' => $branch->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('data', function (array $data) use ($expected) {
            return $data['revenue'] === (float) ($expected['revenue']['total'] ?? 0)
                && $data['gross_profit'] === (float) ($expected['gross_profit'] ?? 0)
                && $data['operating_profit'] === (float) ($expected['operating_profit'] ?? 0)
                && $data['other_net'] === (float) ($expected['net_other_income_expense'] ?? 0)
                && $data['profit_before_tax'] === (float) ($expected['profit_before_tax'] ?? 0)
                && $data['tax'] === (float) ($expected['tax_expense']['total'] ?? 0)
                && $data['net_profit'] === (float) ($expected['net_profit'] ?? 0);
        });
        $response->assertSee('Rp 375.000');
    }

    public function test_profit_loss_multi_division_preview_matches_service_payload(): void
    {
        $user = User::factory()->create();
        $branchA = Cabang::factory()->create(['kode' => 'A01', 'nama' => 'Audit A']);
        $branchB = Cabang::factory()->create(['kode' => 'B01', 'nama' => 'Audit B']);

        $revenueParent = ChartOfAccount::factory()->create([
            'code' => '4100',
            'name' => 'Pendapatan Penjualan',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $revenueLeaf = ChartOfAccount::factory()->create([
            'code' => '4100.01',
            'name' => 'Pendapatan Penjualan Lokal',
            'type' => 'Revenue',
            'parent_id' => $revenueParent->id,
            'is_active' => true,
        ]);

        $cogsParent = ChartOfAccount::factory()->create([
            'code' => '5100',
            'name' => 'Harga Pokok Penjualan',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $cogsLeaf = ChartOfAccount::factory()->create([
            'code' => '5100.01',
            'name' => 'HPP Lokal',
            'type' => 'Expense',
            'parent_id' => $cogsParent->id,
            'is_active' => true,
        ]);

        $opexParent = ChartOfAccount::factory()->create([
            'code' => '6100',
            'name' => 'Biaya Operasional',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $opexLeaf = ChartOfAccount::factory()->create([
            'code' => '6100.01',
            'name' => 'Biaya Gaji',
            'type' => 'Expense',
            'parent_id' => $opexParent->id,
            'is_active' => true,
        ]);

        $this->createJournal($revenueLeaf, '2025-04-10', 0, 1000000, $branchA->id);
        $this->createJournal($revenueLeaf, '2025-04-10', 0, 600000, $branchB->id);
        $this->createJournal($cogsLeaf, '2025-04-11', 300000, 0, $branchA->id);
        $this->createJournal($cogsLeaf, '2025-04-11', 150000, 0, $branchB->id);
        $this->createJournal($opexLeaf, '2025-04-12', 100000, 0, $branchA->id);
        $this->createJournal($opexLeaf, '2025-04-12', 50000, 0, $branchB->id);

        $expected = app(ProfitLossMultiDivisionService::class)->generate('2025-04-01', '2025-04-30', [$branchA->id, $branchB->id]);

        $response = $this->actingAs($user)->get(route('reports.profit-loss-multi-division.preview', [
            'startDate' => '2025-04-01',
            'endDate' => '2025-04-30',
            'cabangIds' => [$branchA->id, $branchB->id],
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected, $branchA, $branchB) {
            return $report['period']['start'] === '2025-04-01'
                && $report['period']['end'] === '2025-04-30'
                && array_column($report['divisions'], 'id') === [$branchA->id, $branchB->id]
                && $report['total_revenue'][$branchA->id] === $expected['total_revenue'][$branchA->id]
                && $report['gross_profit'][$branchA->id] === $expected['gross_profit'][$branchA->id]
                && $report['net_profit'][$branchB->id] === $expected['net_profit'][$branchB->id];
        });
        $response->assertSee('Profit Loss Multiple By Division');
        $response->assertSee('700,000.00');
        $response->assertSee('400,000.00');
    }

    public function test_profit_loss_multi_division_excel_export_downloads_expected_file(): void
    {
        Excel::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.profit-loss-multi-division.excel', [
            'startDate' => '2026-04-01',
            'endDate' => '2026-04-30',
        ]));

        $response->assertOk();

        Excel::assertDownloaded('profit-loss-multi-division-' . now()->format('Ymd_His') . '.xlsx', function ($export) {
            return $export instanceof ProfitLossMultiDivisionExport;
        });
    }

    public function test_cash_flow_preview_matches_cash_flow_service(): void
    {
        $this->seed(\Database\Seeders\Finance\FinanceReportConfigSeeder::class);

        CashFlowCashAccount::create([
            'prefix' => '1-',
            'label' => 'Kas dan Bank (Test)',
            'sort_order' => 99,
        ]);

        $user = User::factory()->create();
        $branch = Cabang::factory()->create(['nama' => 'Cash Flow Preview Branch']);

        $cash = ChartOfAccount::factory()->create([
            'code' => '1-1001',
            'name' => 'Kas',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $revenue = ChartOfAccount::factory()->create([
            'code' => '4-1001',
            'name' => 'Pendapatan Penjualan',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        CashBankTransaction::create([
            'number' => 'CF-PRE-OPEN',
            'date' => '2025-01-01',
            'type' => 'cash_in',
            'account_coa_id' => $revenue->id,
            'offset_coa_id' => $cash->id,
            'amount' => 200000,
            'description' => 'Opening cash preview',
            'cabang_id' => $branch->id,
        ]);

        $expected = app(CashFlowReportService::class)->generate('2025-01-05', '2025-01-31', [
            'branches' => [$branch->id],
            'method' => 'direct',
        ]);

        $response = $this->actingAs($user)->get(route('reports.cash-flow.preview', [
            'startDate' => '2025-01-05',
            'endDate' => '2025-01-31',
            'method' => 'direct',
            'branchIds' => [$branch->id],
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected) {
            return $report['period']['start'] === $expected['period']['start']
                && $report['period']['end'] === $expected['period']['end']
                && $report['opening_balance'] === $expected['opening_balance']
                && $report['net_change'] === $expected['net_change']
                && $report['closing_balance'] === $expected['closing_balance'];
        });
        $response->assertSee('Rp 200.000');
    }

    public function test_cash_flow_preview_uses_current_april_2026_snapshot(): void
    {
        $this->seed(\Database\Seeders\Finance\FinanceReportConfigSeeder::class);

        $user = User::factory()->create();

        $expected = app(CashFlowReportService::class)->generate('2026-04-01', '2026-04-30', [
            'method' => 'direct',
        ]);

        $response = $this->actingAs($user)->get(route('reports.cash-flow.preview', [
            'startDate' => '2026-04-01',
            'endDate' => '2026-04-30',
            'method' => 'direct',
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected) {
            return $report['period']['start'] === '2026-04-01'
                && $report['period']['end'] === '2026-04-30'
                && $report['opening_balance'] === 0.0
                && $report['net_change'] === 0.0
                && $report['closing_balance'] === 0.0
                && count($report['sections'] ?? []) === 3
                && collect($report['sections'] ?? [])->every(function (array $section) {
                    return (float) ($section['total'] ?? 0) === 0.0
                        && collect($section['items'] ?? [])->every(function (array $item) {
                            return (float) ($item['amount'] ?? 0) === 0.0;
                        });
                })
                && $report['opening_balance'] === $expected['opening_balance']
                && $report['net_change'] === $expected['net_change']
                && $report['closing_balance'] === $expected['closing_balance'];
        });
    }

    public function test_balance_sheet_preview_matches_balance_sheet_service_totals(): void
    {
        $user = User::factory()->create();
        $branch = Cabang::factory()->create(['nama' => 'Balance Preview Branch']);

        $cash = ChartOfAccount::factory()->create([
            'code' => '1-1001',
            'name' => 'Kas',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $payable = ChartOfAccount::factory()->create([
            'code' => '2-1001',
            'name' => 'Hutang Dagang',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $capital = ChartOfAccount::factory()->create([
            'code' => '3-1001',
            'name' => 'Modal Disetor',
            'type' => 'Equity',
            'is_active' => true,
        ]);

        $this->createJournal($cash, '2025-01-20', 900000, 0, $branch->id);
        $this->createJournal($payable, '2025-01-20', 0, 250000, $branch->id);
        $this->createJournal($capital, '2025-01-20', 0, 650000, $branch->id);

        $expected = app(BalanceSheetService::class)->generate([
            'as_of_date' => '2025-01-31',
            'cabang_id' => $branch->id,
        ]);

        $response = $this->actingAs($user)->get(route('reports.balance-sheet.preview', [
            'as_of_date' => '2025-01-31',
            'cabang_id' => $branch->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('data', function (array $data) use ($expected) {
            return $data['asset_total'] === (float) ($expected['total_assets'] ?? 0)
                && $data['liab_total'] === (float) ($expected['total_liabilities'] ?? 0)
                && $data['equity_total'] === (float) ($expected['total_equity'] ?? 0)
                && $data['balanced'] === (bool) ($expected['is_balanced'] ?? false)
                && $data['difference'] === (float) ($expected['difference'] ?? 0);
        });
        $response->assertSee('Rp 900.000');
    }

    public function test_balance_sheet_classic_view_matches_balance_sheet_service_totals(): void
    {
        $user = User::factory()->create();
        $branch = Cabang::factory()->create(['nama' => 'Balance Classic Preview Branch']);

        $cash = ChartOfAccount::factory()->create([
            'code' => '1-1001',
            'name' => 'Kas Kecil',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $payable = ChartOfAccount::factory()->create([
            'code' => '2-1001',
            'name' => 'Hutang Usaha',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $capital = ChartOfAccount::factory()->create([
            'code' => '3-1001',
            'name' => 'Modal Pemilik',
            'type' => 'Equity',
            'is_active' => true,
        ]);

        $this->createJournal($cash, '2025-02-10', 1200000, 0, $branch->id);
        $this->createJournal($payable, '2025-02-10', 0, 450000, $branch->id);
        $this->createJournal($capital, '2025-02-10', 0, 750000, $branch->id);

        $expected = app(BalanceSheetService::class)->generate([
            'as_of_date' => '2025-02-28',
            'cabang_id' => $branch->id,
        ]);

        $response = $this->actingAs($user)->get(route('reports.balance-sheet.preview', [
            'as_of_date' => '2025-02-28',
            'cabang_id' => $branch->id,
            'classic_view' => 1,
        ]));

        $response->assertOk();
        $response->assertViewHas('classicView', true);
        $response->assertViewHas('classicData', function (?array $classicData) use ($expected) {
            if (!is_array($classicData)) {
                return false;
            }

            return $classicData['total_assets'] === (float) ($expected['total_assets'] ?? 0)
                && $classicData['total_liabilities'] === (float) ($expected['total_liabilities'] ?? 0)
                && $classicData['total_equity'] === (float) ($expected['total_equity'] ?? 0)
                && $classicData['total_liabilities_and_equity'] === (float) ($expected['total_liabilities_and_equity'] ?? 0)
                && $classicData['is_balanced'] === (bool) ($expected['is_balanced'] ?? false)
                && $classicData['difference'] === (float) ($expected['difference'] ?? 0);
        });
        $response->assertSee('BALANCE SHEET');
        $response->assertSee('1,200,000.00');
    }

    public function test_financial_statement_preview_matches_shared_report_service(): void
    {
        $user = User::factory()->create();
        $branch = Cabang::factory()->create(['nama' => 'Financial Statement Preview Branch']);

        $cash = ChartOfAccount::factory()->create([
            'code' => '1-1001',
            'name' => 'Kas Besar',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $payable = ChartOfAccount::factory()->create([
            'code' => '2-1001',
            'name' => 'Hutang Dagang',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $capital = ChartOfAccount::factory()->create([
            'code' => '3-1001',
            'name' => 'Modal Pemilik',
            'type' => 'Equity',
            'is_active' => true,
        ]);

        $sales = ChartOfAccount::factory()->create([
            'code' => '4-1001',
            'name' => 'Pendapatan Penjualan',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $cogs = ChartOfAccount::factory()->create([
            'code' => '5-1001',
            'name' => 'Harga Pokok Penjualan',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $operatingExpense = ChartOfAccount::factory()->create([
            'code' => '6-1001',
            'name' => 'Beban Operasional',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $this->createJournal($cash, '2025-03-20', 950000, 0, $branch->id);
        $this->createJournal($payable, '2025-03-20', 0, 300000, $branch->id);
        $this->createJournal($capital, '2025-03-20', 0, 650000, $branch->id);
        $this->createJournal($sales, '2025-03-21', 0, 1250000, $branch->id);
        $this->createJournal($cogs, '2025-03-22', 500000, 0, $branch->id);
        $this->createJournal($operatingExpense, '2025-03-23', 175000, 0, $branch->id);

        $expected = app(FinancialStatementReportService::class)->generate([
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-31',
            'cabang_id' => $branch->id,
            'statement_type' => 'all',
        ]);

        $response = $this->actingAs($user)->get(route('reports.financial-statement.preview', [
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-31',
            'cabang_id' => $branch->id,
            'statement_type' => 'all',
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) use ($expected, $branch) {
            return $report['statement_type'] === 'all'
                && $report['branch_name'] === $branch->nama
                && (float) ($report['pl']['revenue'] ?? 0) === (float) ($expected['pl']['revenue'] ?? 0)
                && (float) ($report['pl']['net_profit'] ?? 0) === (float) ($expected['pl']['net_profit'] ?? 0)
                && (float) ($report['bs']['total_assets'] ?? 0) === (float) ($expected['bs']['total_assets'] ?? 0)
                && (float) ($report['bs']['total_liabilities_and_equity'] ?? 0) === (float) ($expected['bs']['total_liabilities_and_equity'] ?? 0)
                && (bool) ($report['bs']['is_balanced'] ?? false) === (bool) ($expected['bs']['is_balanced'] ?? false);
        });
        $response->assertSee('LAPORAN FINANCIAL STATEMENT');
        $response->assertSee('Financial Statement Preview Branch');
        $response->assertSee('Rp 1.250.000');
    }

    public function test_financial_statement_preview_supports_cogm_statement_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.financial-statement.preview', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'statement_type' => 'cogm',
        ]));

        $response->assertOk();
        $response->assertViewHas('report', function (array $report) {
            return $report['statement_type'] === 'cogm'
                && $report['pl'] === null
                && $report['bs'] === null
                && is_array($report['cogm'])
                && array_key_exists('cogm', $report['cogm']);
        });
        $response->assertSee('Harga Pokok Produksi');
    }

    public function test_financial_statement_excel_export_downloads_a_file(): void
    {
        Excel::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.financial-statement.excel', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'statement_type' => 'all',
        ]));

        $response->assertOk();

        Excel::assertDownloaded('financial-statement-all-' . now()->format('Ymd_His') . '.xlsx', function ($export) {
            if (! $export instanceof FinancialStatementExport) {
                return false;
            }

            $titles = collect($export->sheets())->map(fn ($sheet) => $sheet->title())->all();

            return array_slice($titles, 0, 3) === ['Summary', 'Profit Loss', 'Balance Sheet'];
        });
    }

    public function test_financial_statement_excel_export_for_profit_loss_only_uses_summary_and_profit_loss_sheets(): void
    {
        Excel::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.financial-statement.excel', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'statement_type' => 'pl',
        ]));

        $response->assertOk();

        Excel::assertDownloaded('financial-statement-pl-' . now()->format('Ymd_His') . '.xlsx', function ($export) {
            if (! $export instanceof FinancialStatementExport) {
                return false;
            }

            $titles = collect($export->sheets())->map(fn ($sheet) => $sheet->title())->all();

            return $titles === ['Summary', 'Profit Loss'];
        });
    }

    public function test_financial_statement_excel_export_for_cogm_only_uses_summary_and_cogm_sheets(): void
    {
        Excel::fake();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.financial-statement.excel', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'statement_type' => 'cogm',
        ]));

        $response->assertOk();

        Excel::assertDownloaded('financial-statement-cogm-' . now()->format('Ymd_His') . '.xlsx', function ($export) {
            if (! $export instanceof FinancialStatementExport) {
                return false;
            }

            $titles = collect($export->sheets())->map(fn ($sheet) => $sheet->title())->all();

            return $titles === ['Summary', 'COGM'];
        });
    }

    public function test_financial_statement_pdf_export_downloads_a_pdf(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('reports.financial-statement.pdf', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'statement_type' => 'all',
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('financial-statement-all-', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }

    private function createJournal(ChartOfAccount $coa, string $date, float $debit, float $credit, int $branchId): void
    {
        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => $date,
            'reference' => 'TEST',
            'description' => 'preview test entry',
            'debit' => $debit,
            'credit' => $credit,
            'journal_type' => 'manual',
            'cabang_id' => $branchId,
            'source_type' => self::class,
            'source_id' => 0,
        ]);
    }
}