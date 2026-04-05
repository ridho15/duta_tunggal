<?php

namespace App\Http\Controllers;

use App\Exports\InventoryCardExport;
use App\Services\Reports\InventoryCardReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class InventoryCardController extends Controller
{
    /**
     * Halaman cetak (print preview) — buka di browser, user tinggal Ctrl+P.
     */
    public function printView(Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403);

        $data = $this->buildReportData($request);
        $data['isPdf'] = false;
        return view('reports.inventory-card-print', compact('data'));
    }

    /**
     * Download PDF.
     */
    public function downloadPdf(Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403);

        $data = $this->buildReportData($request);
        $data['isPdf'] = true;

        $pdf = Pdf::loadView('reports.inventory-card-print', compact('data'))
            ->setPaper('a4', 'landscape');

        $filename = 'kartu-persediaan-' . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Download Excel.
     */
    public function downloadExcel(Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403);

        $export   = new InventoryCardExport(
            startDate:   $request->input('start'),
            endDate:     $request->input('end'),
            productId:   $request->integer('product_id') ?: null,
            warehouseId: $request->integer('warehouse_id') ?: null,
        );

        $filename = 'kartu-persediaan-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download($export, $filename);
    }

    // -------------------------------------------------------------------------

    private function buildReportData(Request $request): array
    {
        return app(InventoryCardReportService::class)->reportData([
            'start' => $request->input('start'),
            'end' => $request->input('end'),
            'product_id' => $request->integer('product_id') ?: null,
            'warehouse_id' => $request->integer('warehouse_id') ?: null,
        ]);
    }
}
