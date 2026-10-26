<?php

namespace App\Http\Controllers\Pembelian;

use App\Exports\PurchaseReportExport;
use App\Http\Controllers\Controller;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\Reports\PurchaseReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class PurchaseReportController extends Controller
{
    protected PurchaseReportService $purchaseReportService;

    public function __construct(PurchaseReportService $purchaseReportService)
    {
        $this->purchaseReportService = $purchaseReportService;
    }

    /**
     * Tampilan Utama Laporan Pembelian Supplier.
     */
    public function index(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $supplierId = $request->integer('supplier_id') ?: null;
        $status = $request->input('status');
        $sortByTotal = $request->input('sort_by_total');

        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'supplier_id' => $supplierId,
            'status' => $status ?: null,
            'sort_by_total' => $sortByTotal ?: null,
        ];

        // Query data terpaginasi
        $query = $this->purchaseReportService->query($filters, Auth::user());
        $records = $query->paginate(20)->withQueryString();

        // Ringkasan KPI dan data agregat
        $payload = $this->purchaseReportService->pdfPayload($filters, Auth::user());
        $summary = $payload['summary'] ?? [];

        $suppliers = Supplier::orderBy('perusahaan')->get(['id', 'code', 'perusahaan']);

        // Status opsi untuk filter
        $statusOptions = defined('App\Models\PurchaseOrder::STATUS_LABELS')
            ? PurchaseOrder::STATUS_LABELS
            : [
                'draft' => 'Draft',
                'pending' => 'Pending Approval',
                'approved' => 'Disetujui',
                'processing' => 'Diproses',
                'partial' => 'Diterima Sebagian',
                'completed' => 'Selesai',
                'cancelled' => 'Dibatalkan',
            ];

        return view('pembelian.laporan.index', compact(
            'records',
            'startDate',
            'endDate',
            'supplierId',
            'status',
            'sortByTotal',
            'summary',
            'suppliers',
            'statusOptions'
        ));
    }

    /**
     * Ekspor Laporan Pembelian ke PDF (A4 Landscape).
     */
    public function exportPdf(Request $request)
    {
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $supplierId = $request->integer('supplier_id') ?: null;
        $status = $request->input('status');
        $sortByTotal = $request->input('sort_by_total');

        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'supplier_id' => $supplierId,
            'status' => $status ?: null,
            'sort_by_total' => $sortByTotal ?: null,
        ];

        $payload = $this->purchaseReportService->pdfPayload($filters, Auth::user());

        $pdf = Pdf::loadView('reports.purchase_report', array_merge($payload, [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]))->setPaper('a4', 'landscape');

        $filename = 'Laporan_Pembelian_' . $startDate . '_' . $endDate . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Ekspor Laporan Pembelian ke Excel (.xlsx).
     */
    public function exportExcel(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $supplierId = $request->integer('supplier_id') ?: null;
        $status = $request->input('status');
        $sortByTotal = $request->input('sort_by_total');

        $filters = [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'supplier_id' => $supplierId,
            'status' => $status ?: null,
            'sort_by_total' => $sortByTotal ?: null,
        ];

        $query = $this->purchaseReportService->query($filters, Auth::user());
        $filename = 'Laporan_Pembelian_' . $startDate . '_' . $endDate . '.xlsx';

        return Excel::download(new PurchaseReportExport($query), $filename);
    }
}
