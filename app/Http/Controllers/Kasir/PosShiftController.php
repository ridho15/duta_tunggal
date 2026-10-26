<?php

namespace App\Http\Controllers\Kasir;

use App\Exports\PosShiftExport;
use App\Http\Controllers\Controller;
use App\Models\PosShift;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PosShiftService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class PosShiftController extends Controller
{
    protected PosShiftService $posShiftService;

    public function __construct(PosShiftService $posShiftService)
    {
        $this->posShiftService = $posShiftService;
    }

    /**
     * Daftar Riwayat Shift Kasir & Laporan Penjualan POS (Z-Report Rekap).
     */
    public function index(Request $request): View
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $userId = $request->integer('user_id') ?: null;
        $status = $request->input('status');
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $query = PosShift::withoutGlobalScopes()
            ->when($startDate, fn($q) => $q->whereDate('opened_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('opened_at', '<=', $endDate))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->with(['user', 'warehouse', 'cabang'])
            ->latest('opened_at');

        // Summary KPI
        $kpiQuery = (clone $query)->where('status', 'closed');
        $kpi = [
            'total_sales' => (float) (clone $kpiQuery)->sum('total_sales'),
            'total_cash' => (float) (clone $kpiQuery)->sum('cash_sales_total'),
            'total_non_cash' => (float) (clone $kpiQuery)->sum('non_cash_sales_total'),
            'total_transactions' => (int) (clone $kpiQuery)->sum('total_transactions'),
            'closed_shifts' => (int) (clone $kpiQuery)->count(),
            'open_shifts' => PosShift::withoutGlobalScopes()->where('status', 'open')->count(),
            'total_difference' => (float) (clone $kpiQuery)->sum('cash_difference'),
        ];

        $shifts = $query->paginate(20)->withQueryString();
        $cashiers = User::orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::orderBy('name')->get(['id', 'kode', 'name']);

        return view('kasir.shift.index', compact(
            'shifts',
            'kpi',
            'startDate',
            'endDate',
            'userId',
            'status',
            'warehouseId',
            'cashiers',
            'warehouses'
        ));
    }

    /**
     * Alias ke index untuk route kasir.laporan.index.
     */
    public function report(Request $request): View
    {
        return $this->index($request);
    }

    /**
     * Ekspor Rekap Shift Kasir ke PDF (A4 Landscape).
     */
    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $userId = $request->integer('user_id') ?: null;
        $status = $request->input('status');
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $query = PosShift::withoutGlobalScopes()
            ->when($startDate, fn($q) => $q->whereDate('opened_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('opened_at', '<=', $endDate))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->with(['user', 'warehouse', 'cabang'])
            ->latest('opened_at');

        $kpiQuery = (clone $query)->where('status', 'closed');
        $kpi = [
            'total_sales' => (float) (clone $kpiQuery)->sum('total_sales'),
            'total_cash' => (float) (clone $kpiQuery)->sum('cash_sales_total'),
            'total_non_cash' => (float) (clone $kpiQuery)->sum('non_cash_sales_total'),
            'total_transactions' => (int) (clone $kpiQuery)->sum('total_transactions'),
            'closed_shifts' => (int) (clone $kpiQuery)->count(),
            'total_difference' => (float) (clone $kpiQuery)->sum('cash_difference'),
        ];

        $shifts = $query->get();

        $pdf = Pdf::loadView('reports.pos_shift_report', [
            'shifts' => $shifts,
            'kpi' => $kpi,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ])->setPaper('a4', 'landscape');

        $filename = 'Laporan_Shift_Kasir_' . $startDate . '_' . $endDate . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Ekspor Rekap Shift Kasir ke Excel (.xlsx).
     */
    public function exportExcel(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $userId = $request->integer('user_id') ?: null;
        $status = $request->input('status');
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $query = PosShift::withoutGlobalScopes()
            ->when($startDate, fn($q) => $q->whereDate('opened_at', '>=', $startDate))
            ->when($endDate, fn($q) => $q->whereDate('opened_at', '<=', $endDate))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($warehouseId, fn($q) => $q->where('warehouse_id', $warehouseId))
            ->with(['user', 'warehouse', 'cabang'])
            ->latest('opened_at');

        $filename = 'Laporan_Shift_Kasir_' . $startDate . '_' . $endDate . '.xlsx';
        return Excel::download(new PosShiftExport($query), $filename);
    }

    /**
     * Buka shift kasir dengan modal awal.
     */
    public function open(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'starting_cash' => 'required|numeric|min:0',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'notes' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $shift = $this->posShiftService->openShift(
            $user,
            (float) $validated['starting_cash'],
            $validated['warehouse_id'] ?? null,
            $user->cabang_id,
            $validated['notes'] ?? null
        );

        return redirect()->route('kasir.pos.index')
            ->with('success', "Shift kasir {$shift->shift_number} berhasil dibuka dengan modal awal Rp " . number_format($shift->starting_cash, 0, ',', '.') . ".");
    }

    /**
     * Tutup shift kasir dan rekap fisik uang laci.
     */
    public function close(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cash_actual' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $shift = $this->posShiftService->getActiveShift($user, $user->cabang_id);

        if (!$shift) {
            return redirect()->route('kasir.pos.index')
                ->with('error', 'Tidak ada shift kasir yang sedang aktif.');
        }

        $closedShift = $this->posShiftService->closeShift(
            $shift,
            (float) $validated['cash_actual'],
            $validated['notes'] ?? null
        );

        return redirect()->route('kasir.shift.summary', $closedShift)
            ->with('success', "Shift kasir {$closedShift->shift_number} berhasil ditutup.");
    }

    /**
     * Tampilan Z-Report / Ringkasan penutupan shift kasir.
     */
    public function summary(PosShift $posShift): View
    {
        $posShift->loadMissing(['user', 'cabang', 'warehouse']);
        $summary = $this->posShiftService->getShiftSummary($posShift);

        return view('kasir.shift.summary', [
            'shift' => $posShift,
            'summary' => $summary,
        ]);
    }
}
