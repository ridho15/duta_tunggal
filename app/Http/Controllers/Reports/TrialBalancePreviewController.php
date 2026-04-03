<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Services\TrialBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class TrialBalancePreviewController extends Controller
{
    public function preview(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfYear()->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $cabangId = $request->input('cabang_id');
        $showZeroBalance = filter_var($request->input('show_zero_balance', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $report = app(TrialBalanceService::class)->generate([
                'start_date' => $startDate,
                'end_date' => $endDate,
                'cabang_id' => $cabangId,
                'show_zero_balance' => $showZeroBalance,
            ]);
        } catch (\Throwable $e) {
            Log::error('[TrialBalancePreview] preview failed: ' . $e->getMessage());

            $report = [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'rows' => collect(),
                'grand_totals' => [
                    'beginning_balance' => 0,
                    'period_debit' => 0,
                    'period_credit' => 0,
                    'ending_balance' => 0,
                ],
            ];
        }

        $report['period']['start_date'] = $report['period']['start_date'] ?? $startDate;
        $report['period']['end_date'] = $report['period']['end_date'] ?? $endDate;

        $selectedCabang = $cabangId ? Cabang::find($cabangId) : null;

        return response()->view('reports.preview.trial-balance', [
            'report' => $report,
            'startDate' => Carbon::parse($report['period']['start_date']),
            'endDate' => Carbon::parse($report['period']['end_date']),
            'selectedCabang' => $selectedCabang,
            'showZeroBalance' => $showZeroBalance,
        ]);
    }
}