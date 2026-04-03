<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\AccountReceivable;
use App\Models\AccountPayable;
use App\Models\Cabang;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
            $data = $page->getReportData();
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
        $asOfDate   = $request->input('as_of_date', now()->toDateString());
        $cabangId   = $request->input('cabang_id');
        $reportType = $request->input('report_type', 'receivables');
        $asOf       = Carbon::parse($asOfDate);

        // Closure: calc aging bucket
        $calcBucket = function ($record) use ($asOf): string {
            $days = 0;
            if (!empty($record->ageingSchedule?->days_outstanding)) {
                $days = (int) $record->ageingSchedule->days_outstanding;
            } elseif ($record->invoice?->invoice_date) {
                $days = (int) Carbon::parse($record->invoice->invoice_date)->diffInDays($asOf, false);
            }
            if ($days <= 30)  return 'Current';
            if ($days <= 60)  return '31–60';
            if ($days <= 90)  return '61–90';
            return '>90';
        };

        // AR records
        if ($reportType !== 'payables') {
            $arQ = AccountReceivable::with(['customer','invoice','ageingSchedule','cabang'])
                ->where('remaining', '>', 0);
            if ($cabangId) {
                $arQ->where('cabang_id', $cabangId);
            }
            $arRecords = $arQ->orderBy('id')->get();
            foreach ($arRecords as $rec) {
                $days = 0;
                if (!empty($rec->ageingSchedule?->days_outstanding)) {
                    $days = (int) $rec->ageingSchedule->days_outstanding;
                } elseif ($rec->invoice?->invoice_date) {
                    $days = (int) Carbon::parse($rec->invoice->invoice_date)->diffInDays($asOf, false);
                }
                $rec->days_outstanding_computed = $days;
                $rec->aging_bucket_computed     = $calcBucket($rec);
            }
        } else {
            $arRecords = collect();
        }

        // AP records
        if ($reportType !== 'receivables') {
            $apQ = AccountPayable::with(['supplier','invoice','ageingSchedule'])
                ->where('remaining', '>', 0);
            if ($cabangId) {
                $apQ->whereHas('invoice', fn($q) => $q->where('cabang_id', $cabangId));
            }
            $apRecords = $apQ->orderBy('id')->get();
            foreach ($apRecords as $rec) {
                $days = 0;
                if (!empty($rec->ageingSchedule?->days_outstanding)) {
                    $days = (int) $rec->ageingSchedule->days_outstanding;
                } elseif ($rec->invoice?->invoice_date) {
                    $days = (int) Carbon::parse($rec->invoice->invoice_date)->diffInDays($asOf, false);
                }
                $rec->days_outstanding_computed = $days;
                $rec->aging_bucket_computed     = $calcBucket($rec);
            }
        } else {
            $apRecords = collect();
        }

        // Summaries per bucket
        $buckets = ['Current', '31–60', '61–90', '>90'];
        $arSummary = [];
        $apSummary = [];
        foreach ($buckets as $b) {
            $arSummary[$b] = $arRecords->where('aging_bucket_computed', $b)->sum('remaining');
            $apSummary[$b] = $apRecords->where('aging_bucket_computed', $b)->sum('remaining');
        }

        // Expected cash flow (AR/AP due within next 30 days)
        $futureDate = now()->addDays(30);
        $expectedInflow  = AccountReceivable::whereHas('invoice', fn($q) => $q->whereBetween('due_date', [now(), $futureDate]))->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))->sum('remaining');
        $expectedOutflow = AccountPayable::whereHas('invoice', function ($q) use ($futureDate, $cabangId) {
            $q->whereBetween('due_date', [now(), $futureDate]);
            if ($cabangId) {
                $q->where('cabang_id', $cabangId);
            }
        })->sum('remaining');

        // Overdue AR/AP
        $overdueAR = AccountReceivable::whereHas('invoice', fn($q) => $q->where('due_date', '<', now()))->when($cabangId, fn($q) => $q->where('cabang_id', $cabangId))->sum('remaining');
        $overdueAP = AccountPayable::whereHas('invoice', function ($q) use ($cabangId) {
            $q->where('due_date', '<', now());
            if ($cabangId) {
                $q->where('cabang_id', $cabangId);
            }
        })->sum('remaining');

        return response(view('reports.preview.ageing-report', [
            'arRecords'      => $arRecords,
            'apRecords'      => $apRecords,
            'arSummary'      => $arSummary,
            'apSummary'      => $apSummary,
            'asOfDate'       => $asOfDate,
            'cabangId'       => $cabangId,
            'reportType'     => $reportType,
            'expectedInflow' => $expectedInflow,
            'expectedOutflow'=> $expectedOutflow,
            'overdueAR'      => $overdueAR,
            'overdueAP'      => $overdueAP,
        ]));
    }
}
