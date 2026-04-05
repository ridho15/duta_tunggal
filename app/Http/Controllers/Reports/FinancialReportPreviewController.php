<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Services\IncomeStatementService;
use App\Services\Reports\AgeingReportService;
use Illuminate\Http\Request;

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
}
