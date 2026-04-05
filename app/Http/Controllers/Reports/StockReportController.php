<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\StockReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockReportController extends Controller
{
    /**
     * Render a print-friendly preview of the stock report.
     */
    public function preview(Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403); // permission enforced

        $report = app(StockReportService::class)->generate([
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'product_ids' => (array) $request->input('product_ids', []),
            'warehouse_ids' => (array) $request->input('warehouse_ids', []),
        ]);

        return view('reports.stock_report_preview', $report);
    }
}
