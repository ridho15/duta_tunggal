<?php

namespace App\Http\Controllers\Gudang;

use App\Exports\InventoryReportExport;
use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\Reports\InventoryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class InventoryReportController extends Controller
{
    protected InventoryReportService $inventoryReportService;

    public function __construct(InventoryReportService $inventoryReportService)
    {
        $this->inventoryReportService = $inventoryReportService;
    }

    /**
     * Tampilan Utama Laporan Inventori & Mutasi Stok (3 Mode: Posisi Stok, Riwayat Mutasi, Aging Stok).
     */
    public function index(Request $request)
    {
        $type = in_array($request->input('type'), ['movement', 'aging', 'stock'], true)
            ? $request->input('type')
            : 'stock';

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $productId = $request->integer('product_id') ?: null;
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $filters = [
            'type' => $type,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        // Query terpaginasi sesuai mode tab
        $query = match ($type) {
            'movement' => $this->inventoryReportService->movementQuery($filters),
            'aging' => $this->inventoryReportService->agingQuery($filters),
            default => $this->inventoryReportService->stockQuery($filters),
        };

        $records = $query->paginate(20)->withQueryString();

        // Master Data untuk filter
        $warehouses = Warehouse::orderBy('name')->get(['id', 'kode', 'name']);
        $products = Product::orderBy('name')->get(['id', 'sku', 'name']);

        // KPI Ringkasan
        $kpi = [
            'total_sku' => InventoryStock::distinct('product_id')->count(),
            'total_qty' => (float) InventoryStock::sum('qty_available'),
            'total_reserved' => (float) InventoryStock::sum('qty_reserved'),
            'low_stock_count' => InventoryStock::whereColumn('qty_available', '<=', 'qty_min')->where('qty_min', '>', 0)->count(),
            'total_warehouses' => Warehouse::count(),
        ];

        return view('gudang.laporan.index', compact(
            'records',
            'type',
            'warehouseId',
            'productId',
            'startDate',
            'endDate',
            'warehouses',
            'products',
            'kpi'
        ));
    }

    /**
     * Ekspor Laporan Inventori ke PDF (A4 Landscape).
     */
    public function exportPdf(Request $request)
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(300);

        $type = in_array($request->input('type'), ['movement', 'aging', 'stock'], true)
            ? $request->input('type')
            : 'stock';

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $productId = $request->integer('product_id') ?: null;
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $filters = [
            'type' => $type,
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        $payload = $this->inventoryReportService->pdfPayload($filters);

        // Optimasi PDF: Batasi rendering dompdf agar tidak memory exhaustion jika data sangat banyak
        $data = $payload['data'] ?? collect();
        if ($data instanceof \Illuminate\Support\Collection && $data->count() > 250) {
            $payload['is_truncated'] = true;
            $payload['total_untruncated'] = $data->count();
            $payload['data'] = $data->take(250);
        } else {
            $payload['is_truncated'] = false;
        }

        $pdf = Pdf::loadView('reports.inventory_report', $payload)->setPaper('a4', 'landscape');
        $filename = 'Laporan_Inventori_' . ucfirst($type) . '_' . now()->format('Ymd_His') . '.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Ekspor Laporan Inventori ke Excel (.xlsx).
     */
    public function exportExcel(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $type = in_array($request->input('type'), ['movement', 'aging', 'stock'], true)
            ? $request->input('type')
            : 'stock';

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $productId = $request->integer('product_id') ?: null;
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $filename = 'Laporan_Inventori_' . ucfirst($type) . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new InventoryReportExport($warehouseId, $productId, $type, $startDate, $endDate),
            $filename
        );
    }
}
