<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Models\Cabang;
use App\Services\BalanceSheetService;
use App\Services\IncomeStatementService;
use App\Services\TrialBalanceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FinancialReportController extends Controller
{
    protected TrialBalanceService $trialBalanceService;
    protected IncomeStatementService $incomeStatementService;
    protected BalanceSheetService $balanceSheetService;

    public function __construct(
        TrialBalanceService $trialBalanceService,
        IncomeStatementService $incomeStatementService,
        BalanceSheetService $balanceSheetService
    ) {
        $this->trialBalanceService = $trialBalanceService;
        $this->incomeStatementService = $incomeStatementService;
        $this->balanceSheetService = $balanceSheetService;
    }

    /**
     * Resolves the active cabang considering the user's manage_type permissions.
     */
    protected function resolveCabangId(?string $requestedCabangId): ?int
    {
        $user = Auth::user();
        $canManageAll = in_array('all', (array) ($user?->manage_type ?? []), true);

        if (!$canManageAll && $user?->cabang_id) {
            return (int) $user->cabang_id;
        }

        return $requestedCabangId ? (int) $requestedCabangId : null;
    }

    /**
     * Hub Utama Laporan Keuangan (Redirects atau menampilkan tab aktif).
     */
    public function index(Request $request): View
    {
        $tab = $request->query('tab', 'neraca-saldo');

        if ($tab === 'laba-rugi') {
            return $this->incomeStatement($request);
        } elseif ($tab === 'neraca') {
            return $this->balanceSheet($request);
        }

        return $this->trialBalance($request);
    }

    /**
     * Laporan Neraca Saldo (Trial Balance).
     */
    public function trialBalance(Request $request): View
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());
        $cabangId = $this->resolveCabangId($request->query('cabang_id'));
        $showZero = filter_var($request->query('show_zero_balance', false), FILTER_VALIDATE_BOOLEAN);

        $reportData = $this->trialBalanceService->generate([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cabang_id' => $cabangId,
            'show_zero_balance' => $showZero,
        ]);

        $cabangs = Cabang::where('status', 1)->orderBy('nama')->get();
        $selectedCabang = $cabangId ? Cabang::find($cabangId) : null;

        return view('laporan.financial.trial-balance', compact(
            'reportData',
            'startDate',
            'endDate',
            'cabangId',
            'showZero',
            'cabangs',
            'selectedCabang'
        ));
    }

    /**
     * Cetak PDF Neraca Saldo (A4 Landscape).
     */
    public function trialBalancePdf(Request $request): Response
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());
        $cabangId = $this->resolveCabangId($request->query('cabang_id'));
        $showZero = filter_var($request->query('show_zero_balance', false), FILTER_VALIDATE_BOOLEAN);

        $reportData = $this->trialBalanceService->generate([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cabang_id' => $cabangId,
            'show_zero_balance' => $showZero,
        ]);

        $selectedCabang = $cabangId ? Cabang::find($cabangId) : null;

        $pdf = Pdf::loadView('pdf.financial.trial-balance', compact(
            'reportData',
            'startDate',
            'endDate',
            'selectedCabang'
        ))->setPaper('a4', 'landscape');

        $filename = "Neraca_Saldo_{$startDate}_{$endDate}.pdf";
        return $pdf->stream($filename);
    }

    /**
     * Laporan Laba Rugi (Income Statement).
     */
    public function incomeStatement(Request $request): View
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());
        $cabangId = $this->resolveCabangId($request->query('cabang_id'));

        $reportData = $this->incomeStatementService->generate([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cabang_id' => $cabangId,
        ]);

        $cabangs = Cabang::where('status', 1)->orderBy('nama')->get();
        $selectedCabang = $cabangId ? Cabang::find($cabangId) : null;

        return view('laporan.financial.income-statement', compact(
            'reportData',
            'startDate',
            'endDate',
            'cabangId',
            'cabangs',
            'selectedCabang'
        ));
    }

    /**
     * Cetak PDF Laporan Laba Rugi (A4 Portrait).
     */
    public function incomeStatementPdf(Request $request): Response
    {
        $startDate = $request->query('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->query('end_date', now()->endOfMonth()->toDateString());
        $cabangId = $this->resolveCabangId($request->query('cabang_id'));

        $reportData = $this->incomeStatementService->generate([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'cabang_id' => $cabangId,
        ]);

        $selectedCabang = $cabangId ? Cabang::find($cabangId) : null;

        $pdf = Pdf::loadView('pdf.financial.income-statement', compact(
            'reportData',
            'startDate',
            'endDate',
            'selectedCabang'
        ))->setPaper('a4', 'portrait');

        $filename = "Laba_Rugi_{$startDate}_{$endDate}.pdf";
        return $pdf->stream($filename);
    }

    /**
     * Laporan Neraca (Balance Sheet).
     */
    public function balanceSheet(Request $request): View
    {
        $asOfDate = $request->query('as_of_date', now()->toDateString());
        $cabangId = $this->resolveCabangId($request->query('cabang_id'));
        $showZero = filter_var($request->query('show_zero_balance', false), FILTER_VALIDATE_BOOLEAN);

        $reportData = $this->balanceSheetService->generate([
            'as_of_date' => $asOfDate,
            'cabang_id' => $cabangId,
            'display_level' => 'all',
            'show_zero_balance' => $showZero,
        ]);

        $cabangs = Cabang::where('status', 1)->orderBy('nama')->get();
        $selectedCabang = $cabangId ? Cabang::find($cabangId) : null;

        return view('laporan.financial.balance-sheet', compact(
            'reportData',
            'asOfDate',
            'cabangId',
            'showZero',
            'cabangs',
            'selectedCabang'
        ));
    }

    /**
     * Cetak PDF Laporan Neraca (A4 Portrait).
     */
    public function balanceSheetPdf(Request $request): Response
    {
        $asOfDate = $request->query('as_of_date', now()->toDateString());
        $cabangId = $this->resolveCabangId($request->query('cabang_id'));
        $showZero = filter_var($request->query('show_zero_balance', false), FILTER_VALIDATE_BOOLEAN);

        $reportData = $this->balanceSheetService->generate([
            'as_of_date' => $asOfDate,
            'cabang_id' => $cabangId,
            'display_level' => 'all',
            'show_zero_balance' => $showZero,
        ]);

        $selectedCabang = $cabangId ? Cabang::find($cabangId) : null;

        $pdf = Pdf::loadView('pdf.financial.balance-sheet', compact(
            'reportData',
            'asOfDate',
            'selectedCabang'
        ))->setPaper('a4', 'portrait');

        $filename = "Neraca_{$asOfDate}.pdf";
        return $pdf->stream($filename);
    }
}
