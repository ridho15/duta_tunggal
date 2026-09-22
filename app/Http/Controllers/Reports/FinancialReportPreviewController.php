<?php

namespace App\Http\Controllers\Reports;

use App\Exports\AlkGrafikExport;
use App\Exports\GenericViewExport;
use App\Exports\FinancialStatementExport;
use App\Exports\ProfitLossMultiDivisionExport;
use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Services\IncomeStatementService;
use App\Services\ProfitLossMultiDivisionService;
use App\Services\Reports\AlkGrafikReportService;
use App\Services\Reports\AgeingReportService;
use App\Services\Reports\DrillDownFinancialReportService;
use App\Services\Reports\FinancialStatementReportService;
use App\Services\Reports\JournalConsolidationReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

use App\Filament\Resources\Reports\BalanceSheetResource\Pages\ViewBalanceSheet;
use App\Filament\Resources\Reports\ProfitAndLossResource\Pages\ViewProfitAndLoss;
use App\Filament\Resources\Reports\CashFlowResource\Pages\ViewCashFlow;
use App\Filament\Resources\Reports\HppResource\Pages\ViewHpp;

class FinancialReportPreviewController extends Controller
{
    // ── Balance Sheet ──────────────────────────────────────────────────────────
    public function balanceSheet(Request $request)
    {
        $page = new ViewBalanceSheet();
        $page->as_of_date          = $request->input('as_of_date', now()->toDateString());
        $page->cabang_id           = $request->input('cabang_id');
        $page->branches            = (array) $request->input('branches', []);
        $page->show_comparison     = filter_var($request->input('show_comparison', false), FILTER_VALIDATE_BOOLEAN);
        $page->comparison_date     = $request->input('comparison_date');
        $page->use_multi_period    = filter_var($request->input('use_multi_period', false), FILTER_VALIDATE_BOOLEAN);
        $page->selected_periods    = (array) $request->input('selected_periods', []);
        $page->display_mode        = $request->input('display_mode', 'detailed');
        $page->include_zero_balances = filter_var($request->input('include_zero_balances', false), FILTER_VALIDATE_BOOLEAN);
        $page->classic_view        = filter_var($request->input('classic_view', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $data       = $page->getReportData();
            $classicData = $page->classic_view ? $page->getClassicReportData() : null;
        } catch (\Throwable $e) {
            $data = ['assets'=>[],'liabilities'=>[],'equity'=>[],'asset_total'=>0,'liab_total'=>0,'equity_total'=>0,'balanced'=>true,'difference'=>0,'retained_earnings'=>0,'current_earnings'=>0,'balance_warning'=>null,'unbalanced_entries'=>[],'has_unbalanced_entries'=>false];
            $classicData = null;
            \Illuminate\Support\Facades\Log::error('[FinancialPreview] balanceSheet: ' . $e->getMessage());
        }

        return response(view('reports.preview.balance-sheet', [
            'data'        => $data,
            'classicData' => $classicData,
            'classicView' => $page->classic_view,
            'asOfDate'    => $page->as_of_date,
        ]));
    }
    public function financialStatement(Request $request)
    {
        $report = $this->buildFinancialStatementReport($request);

        return response(view('reports.preview.financial-statement', [
            'report' => $report,
        ]));
    }

    public function financialStatementPdf(Request $request)
    {
        $report = $this->buildFinancialStatementReport($request);
        $filename = $this->financialStatementFilename($report) . '.pdf';

        $pdf = Pdf::loadView('exports.financial-statement', [
            'report' => $report,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function financialStatementExcel(Request $request)
    {
        $report = $this->buildFinancialStatementReport($request);
        $filename = $this->financialStatementFilename($report) . '.xlsx';

        return Excel::download(new FinancialStatementExport($report), $filename);
    }

    public function alkGrafik(Request $request)
    {
        $report = $this->buildAlkGrafikReport($request);

        return response(view('reports.preview.alk-grafik', [
            'report' => $report,
        ]));
    }

    public function alkGrafikPdf(Request $request)
    {
        $report = $this->buildAlkGrafikReport($request);
        $filename = $this->alkGrafikFilename($report) . '.pdf';

        $pdf = Pdf::loadView('exports.alk-grafik', [
            'report' => $report,
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function alkGrafikExcel(Request $request)
    {
        $report = $this->buildAlkGrafikReport($request);
        $filename = $this->alkGrafikFilename($report) . '.xlsx';

        return Excel::download(new AlkGrafikExport($report), $filename);
    }

    // ── Profit & Loss ──────────────────────────────────────────────────────────
    public function profitAndLoss(Request $request)
    {
        $page = new ViewProfitAndLoss();
        $page->startDate        = $request->input('startDate', now()->startOfMonth()->toDateString());
        $page->endDate          = $request->input('endDate',   now()->endOfMonth()->toDateString());
        $page->cabang_id        = $request->input('cabang_id');
        $page->branches         = (array) $request->input('branches', []);
        $page->compare          = filter_var($request->input('compare', false), FILTER_VALIDATE_BOOLEAN);
        $page->compareStartDate = $request->input('compareStartDate');
        $page->compareEndDate   = $request->input('compareEndDate');

        try {
            $report = app(IncomeStatementService::class)->generate([
                'start_date' => $page->startDate,
                'end_date' => $page->endDate,
                'cabang_id' => $page->cabang_id ? (int) $page->cabang_id : null,
            ]);

            $data = [
                'revenue' => (float) ($report['revenue']['total'] ?? 0),
                'expense' => (float) ($report['expense']['total'] ?? 0),
                'gross_profit' => (float) ($report['gross_profit'] ?? 0),
                'operating_profit' => (float) ($report['operating_profit'] ?? 0),
                'other_net' => (float) ($report['net_other_income_expense'] ?? 0),
                'profit_before_tax' => (float) ($report['profit_before_tax'] ?? 0),
                'tax' => (float) ($report['tax_expense']['total'] ?? 0),
                'net_profit' => (float) ($report['net_profit'] ?? 0),
            ];
        } catch (\Throwable $e) {
            $data = ['revenue'=>0,'expense'=>0,'gross_profit'=>0,'operating_profit'=>0,'other_net'=>0,'profit_before_tax'=>0,'tax'=>0,'net_profit'=>0];
            \Illuminate\Support\Facades\Log::error('[FinancialPreview] profitAndLoss: ' . $e->getMessage());
        }

        return response(view('reports.preview.profit-and-loss', [
            'data'      => $data,
            'startDate' => $page->startDate,
            'endDate'   => $page->endDate,
        ]));
    }

    public function profitLossMultiDivision(Request $request)
    {
        $report = $this->buildProfitLossMultiDivisionReport($request);

        return response(view('reports.preview.profit-loss-multi-division', [
            'report' => $report,
        ]));
    }

    public function profitLossMultiDivisionExcel(Request $request)
    {
        $report = $this->buildProfitLossMultiDivisionReport($request);
        $filename = $this->profitLossMultiDivisionFilename() . '.xlsx';

        return Excel::download(new ProfitLossMultiDivisionExport($report), $filename);
    }

    // ── Cash Flow ──────────────────────────────────────────────────────────────
    public function cashFlow(Request $request)
    {
        $page = new ViewCashFlow();
        $page->startDate = $request->input('startDate', now()->startOfMonth()->toDateString());
        $page->endDate   = $request->input('endDate',   now()->endOfMonth()->toDateString());
        $page->method    = $request->input('method', 'direct');
        $page->branchIds = (array) $request->input('branchIds', []);

        try {
            $report = $page->getReportData();
        } catch (\Throwable $e) {
            $report = ['period'=>['start'=>$page->startDate,'end'=>$page->endDate],'method'=>$page->method,'sections'=>[],'opening_balance'=>0,'closing_balance'=>0,'net_change'=>0];
            \Illuminate\Support\Facades\Log::error('[FinancialPreview] cashFlow: ' . $e->getMessage());
        }

        $selectedBranches = !empty($page->branchIds)
            ? Cabang::whereIn('id', array_filter($page->branchIds))->orderBy('nama')->pluck('nama')->toArray()
            : [];

        return response(view('reports.preview.cash-flow', [
            'report'           => $report,
            'selectedBranches' => $selectedBranches,
        ]));
    }

    // ── HPP (Cost of Goods Manufactured) ──────────────────────────────────────
    public function hpp(Request $request)
    {
        $page = new ViewHpp();
        $page->startDate = $request->input('startDate', now()->startOfMonth()->toDateString());
        $page->endDate   = $request->input('endDate',   now()->endOfMonth()->toDateString());
        $page->branchIds = (array) $request->input('branchIds', []);

        try {
            $report = $page->getReportData();
        } catch (\Throwable $e) {
            $report = ['period'=>['start'=>$page->startDate,'end'=>$page->endDate],'raw_materials'=>['opening'=>0,'purchases'=>0,'available'=>0,'closing'=>0,'used'=>0],'direct_labor'=>0,'overhead'=>['items'=>[],'total'=>0],'wip'=>['opening'=>0,'closing'=>0],'production_cost'=>0,'cogm'=>0];
            \Illuminate\Support\Facades\Log::error('[FinancialPreview] hpp: ' . $e->getMessage());
        }

        $selectedBranches = !empty($page->branchIds)
            ? Cabang::whereIn('id', array_filter($page->branchIds))->orderBy('nama')->pluck('nama')->toArray()
            : [];

        return response(view('reports.preview.hpp', [
            'report'           => $report,
            'selectedBranches' => $selectedBranches,
        ]));
    }

    // ── Ageing Report ─────────────────────────────────────────────────────────
    public function ageingReport(Request $request)
    {
        $report = app(AgeingReportService::class)->generate([
            'as_of_date' => $request->input('as_of_date', now()->toDateString()),
            'cabang_id' => $request->input('cabang_id'),
            'report_type' => $request->input('report_type', 'receivables'),
        ]);

        return response(view('reports.preview.ageing-report', $report));
    }

    public function drillDownFinancialReport(Request $request)
    {
        $filters = [
            'start_date' => $request->input('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => $request->input('end_date', now()->endOfMonth()->toDateString()),
            'account_type' => $request->input('account_type'),
            'coa_id' => $request->input('coa_id'),
            'cabang_id' => $request->input('cabang_id'),
        ];

        try {
            $report = app(DrillDownFinancialReportService::class)->generate($filters);
        } catch (\Throwable $e) {
            $report = [
                'grouped' => [],
                'total_debit' => 0.0,
                'total_credit' => 0.0,
                'count' => 0,
                'filters' => $filters,
                'period' => ($filters['start_date'] ?? now()->startOfMonth()->toDateString()) . ' s/d ' . ($filters['end_date'] ?? now()->endOfMonth()->toDateString()),
            ];
            \Illuminate\Support\Facades\Log::error('[FinancialPreview] drillDownFinancialReport: ' . $e->getMessage());
        }

        $selectedBranch = ! empty($report['filters']['cabang_id'])
            ? Cabang::find($report['filters']['cabang_id'])
            : null;

        return response(view('reports.preview.drill-down-financial-report', [
            'report' => $report,
            'selectedBranch' => $selectedBranch,
        ]));
    }

    public function drillDownFinancialReportPdf(Request $request)
    {
        $report = $this->buildDrillDownFinancialReport($request);
        $filename = $this->drillDownFinancialReportFilename($report) . '.pdf';

        $pdf = Pdf::loadView('exports.drill-down-financial-report', [
            'report' => $report,
        ])
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
            ]);

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    public function drillDownFinancialReportExcel(Request $request)
    {
        $report = $this->buildDrillDownFinancialReport($request);
        $filename = $this->drillDownFinancialReportFilename($report) . '.xlsx';

        return Excel::download(new GenericViewExport(view('exports.drill-down-financial-report', [
            'report' => $report,
        ])), $filename);
    }

    // ── Journal Consolidation ───────────────────────────────────────────────
    public function journalConsolidation(Request $request)
    {
        $filters = [
            'start_date' => $request->input('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => $request->input('end_date', now()->endOfMonth()->toDateString()),
            'branch_ids' => (array) $request->input('branch_ids', []),
            'journal_type' => $request->input('journal_type'),
            'group_by_branch' => filter_var($request->input('group_by_branch', true), FILTER_VALIDATE_BOOL),
        ];

        try {
            $report = app(JournalConsolidationReportService::class)->generate($filters);
        } catch (\Throwable $e) {
            $report = [
                'grouped' => [],
                'coa_summary' => [],
                'filters' => $filters,
                'count' => 0,
                'total_debit' => 0.0,
                'total_credit' => 0.0,
                'difference' => 0.0,
                'balanced' => true,
                'period' => $filters['start_date'] . ' s/d ' . $filters['end_date'],
            ];
            \Illuminate\Support\Facades\Log::error('[FinancialPreview] journalConsolidation: ' . $e->getMessage());
        }

        $selectedBranches = ! empty($report['filters']['branch_ids'])
            ? Cabang::whereIn('id', $report['filters']['branch_ids'])->orderBy('nama')->get(['id', 'nama'])
            : collect();

        return response(view('reports.preview.journal-consolidation', [
            'report' => $report,
            'selectedBranches' => $selectedBranches,
        ]));
    }

    protected function buildFinancialStatementReport(Request $request): array
    {
        return app(FinancialStatementReportService::class)->generate([
            'start_date' => $request->input('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => $request->input('end_date', now()->endOfMonth()->toDateString()),
            'cabang_id' => $request->input('cabang_id'),
            'statement_type' => $request->input('statement_type', 'all'),
        ]);
    }

    protected function buildAlkGrafikReport(Request $request): array
    {
        return app(AlkGrafikReportService::class)->generate([
            'start_date' => $request->input('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => $request->input('end_date', now()->endOfMonth()->toDateString()),
            'cabang_id' => $request->input('cabang_id'),
        ]);
    }

    protected function buildProfitLossMultiDivisionReport(Request $request): array
    {
        return app(ProfitLossMultiDivisionService::class)->generate(
            $request->input('startDate', now()->startOfYear()->toDateString()),
            $request->input('endDate', now()->endOfYear()->toDateString()),
            array_map('intval', array_filter((array) $request->input('cabangIds', [])))
        );
    }

    protected function buildDrillDownFinancialReport(Request $request): array
    {
        return app(DrillDownFinancialReportService::class)->generate([
            'start_date' => $request->input('start_date', now()->startOfMonth()->toDateString()),
            'end_date' => $request->input('end_date', now()->endOfMonth()->toDateString()),
            'account_type' => $request->input('account_type'),
            'coa_id' => $request->input('coa_id'),
            'cabang_id' => $request->input('cabang_id'),
        ]);
    }

    protected function financialStatementFilename(array $report): string
    {
        $type = str_replace('_', '-', (string) ($report['statement_type'] ?? 'all'));

        return 'financial-statement-' . $type . '-' . now()->format('Ymd_His');
    }

    protected function alkGrafikFilename(array $report): string
    {
        return 'alk-grafik-' . now()->format('Ymd_His');
    }

    protected function profitLossMultiDivisionFilename(): string
    {
        return 'profit-loss-multi-division-' . now()->format('Ymd_His');
    }

    protected function drillDownFinancialReportFilename(array $report): string
    {
        return 'drill-down-financial-report-' . str_replace(' ', '-', strtolower((string) ($report['filters']['account_type'] ?? 'all'))) . '-' . now()->format('Ymd_His');
    }
}
