<?php

namespace App\Http\Controllers\Penjualan;

use App\Exports\SalesReportExport;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Reports\SalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class SalesReportController extends Controller
{
    protected SalesReportService $salesReportService;

    public function __construct(SalesReportService $salesReportService)
    {
        $this->salesReportService = $salesReportService;
    }

    /**
     * Tampilan Utama Laporan Penjualan (3 Mode: Invoice, Delivery Order, Sales Order).
     */
    public function index(Request $request)
    {
        $mode = SalesReportService::resolveMode($request->all());
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $customerId = $request->integer('customer_id') ?: null;
        $soNumber = trim($request->input('so_number', ''));
        $status = $request->input('status');
        $sortByTotal = $request->input('sort_by_total');

        $filters = [
            'mode' => $mode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'customer_id' => $customerId,
            'so_number' => $soNumber ?: null,
            'status' => $status ?: null,
            'sort_by_total' => $sortByTotal ?: null,
        ];

        // Query data terpaginasi
        $query = $this->salesReportService->query($filters, Auth::user());
        $records = $query->paginate(20)->withQueryString();

        // Summary KPI & metadata dari service
        $payload = $this->salesReportService->pdfPayload($filters, Auth::user());
        $summaryLines = $payload['summary_lines'] ?? [];
        $footnote = ($mode === SalesReportService::MODE_INVOICE)
            ? '* HPP ditandai estimasi (cost_price master saat ini) bila invoice belum memiliki snapshot HPP dari jurnal.'
            : null;

        $customers = Customer::orderBy('name')->get(['id', 'code', 'name']);
        $modeOptions = SalesReportService::modeOptions();
        $statusOptions = SalesReportService::statusOptions($mode);
        $statusLabel = SalesReportService::statusFilterLabel($mode);

        return view('penjualan.laporan.index', compact(
            'records',
            'mode',
            'startDate',
            'endDate',
            'customerId',
            'soNumber',
            'status',
            'sortByTotal',
            'summaryLines',
            'footnote',
            'customers',
            'modeOptions',
            'statusOptions',
            'statusLabel'
        ));
    }

    /**
     * Ekspor Laporan Penjualan ke format PDF (A4 Landscape).
     */
    public function exportPdf(Request $request)
    {
        $mode = SalesReportService::resolveMode($request->all());
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $customerId = $request->integer('customer_id') ?: null;
        $soNumber = trim($request->input('so_number', ''));
        $status = $request->input('status');
        $sortByTotal = $request->input('sort_by_total');

        $filters = [
            'mode' => $mode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'customer_id' => $customerId,
            'so_number' => $soNumber ?: null,
            'status' => $status ?: null,
            'sort_by_total' => $sortByTotal ?: null,
        ];

        $payload = $this->salesReportService->pdfPayload($filters, Auth::user());

        $view = $payload['mode'] === SalesReportService::MODE_ORDER ? 'reports.sales_report' : 'reports.sales_report_modes';

        $pdf = Pdf::loadView($view, array_merge($payload, [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]))->setPaper('a4', 'landscape');

        $filename = 'Laporan_Penjualan_' . ucfirst($payload['mode']) . '_' . $startDate . '_' . $endDate . '.pdf';
        return $pdf->stream($filename);
    }

    /**
     * Ekspor Laporan Penjualan ke format Excel (XLSX).
     */
    public function exportExcel(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $mode = SalesReportService::resolveMode($request->all());
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $customerId = $request->integer('customer_id') ?: null;
        $soNumber = trim($request->input('so_number', ''));
        $status = $request->input('status');
        $sortByTotal = $request->input('sort_by_total');

        $filters = [
            'mode' => $mode,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'customer_id' => $customerId,
            'so_number' => $soNumber ?: null,
            'status' => $status ?: null,
            'sort_by_total' => $sortByTotal ?: null,
        ];

        $query = $this->salesReportService->query($filters, Auth::user());
        $filename = 'Laporan_Penjualan_' . ucfirst($mode) . '_' . $startDate . '_' . $endDate . '.xlsx';

        return Excel::download(new SalesReportExport($query, $mode), $filename);
    }
}
