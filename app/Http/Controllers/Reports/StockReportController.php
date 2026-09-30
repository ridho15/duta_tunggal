<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\StockReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class StockReportController extends Controller
{
    /**
     * Render a print-friendly preview of the stock report.
     */
    public function preview(Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403); // permission enforced

        $validator = Validator::make($request->all(), [
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'product_ids' => ['nullable', 'array'],
            'warehouse_ids' => ['nullable', 'array'],
        ]);

        $startDate = $validator->errors()->has('start_date') ? null : $request->input('start_date');
        $endDate = $validator->errors()->has('end_date') ? null : $request->input('end_date');

        $report = app(StockReportService::class)->generate([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'product_ids' => (array) $request->input('product_ids', []),
            'warehouse_ids' => (array) $request->input('warehouse_ids', []),
        ]);

        return view('reports.stock_report_preview', $report);
    }
}
