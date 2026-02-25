<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class StockReportController extends Controller
{
    /**
     * Render a print-friendly preview of the stock report.
     */
    public function preview(Request $request)
    {
        $startDate   = $request->input('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : now()->startOfMonth()->startOfDay();
        $endDate     = $request->input('end_date')   ? Carbon::parse($request->input('end_date'))->endOfDay()     : now()->endOfDay();
        $productIds  = array_filter((array) $request->input('product_ids', []));
        $warehouseIds = array_filter((array) $request->input('warehouse_ids', []));

        // ----------------------------------------------------------------
        // 1. Fetch current inventory stocks (snapshot)
        // ----------------------------------------------------------------
        $stockQuery = InventoryStock::query()
            ->with(['product', 'warehouse', 'rak'])
            ->orderBy('warehouse_id')
            ->orderBy('product_id');

        if (!empty($productIds)) {
            $stockQuery->whereIn('product_id', $productIds);
        }

        if (!empty($warehouseIds)) {
            $stockQuery->whereIn('warehouse_id', $warehouseIds);
        }

        $stocks = $stockQuery->get();

        // ----------------------------------------------------------------
        // 2. Fetch movements within the period for each stock row
        // ----------------------------------------------------------------
        $movementQuery = StockMovement::query()
            ->selectRaw('product_id, warehouse_id, type,
                SUM(CASE WHEN type LIKE "%_in%"  OR type IN ("purchase_in","manufacture_in","transfer_in","adjustment_in","opname_in","beginning") THEN quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN type LIKE "%_out%" OR type IN ("sales","transfer_out","manufacture_out","adjustment_out","opname_out") THEN quantity ELSE 0 END) as total_out')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('product_id', 'warehouse_id', 'type');

        if (!empty($productIds)) {
            $movementQuery->whereIn('product_id', $productIds);
        }
        if (!empty($warehouseIds)) {
            $movementQuery->whereIn('warehouse_id', $warehouseIds);
        }

        // Simpler movement summary: total in & total out per product+warehouse
        $movSummaryQuery = StockMovement::query()
            ->selectRaw('product_id, warehouse_id,
                SUM(CASE WHEN quantity > 0 THEN quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN quantity < 0 THEN ABS(quantity) ELSE 0 END) as total_out')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('product_id', 'warehouse_id');

        if (!empty($productIds)) {
            $movSummaryQuery->whereIn('product_id', $productIds);
        }
        if (!empty($warehouseIds)) {
            $movSummaryQuery->whereIn('warehouse_id', $warehouseIds);
        }

        // Index the movement summaries by product_id-warehouse_id
        $movSummaries = $movSummaryQuery->get()->keyBy(function ($row) {
            return $row->product_id . '-' . $row->warehouse_id;
        });

        // ----------------------------------------------------------------
        // 3. Build report rows
        // ----------------------------------------------------------------
        $rows = $stocks->map(function ($stock) use ($movSummaries) {
            $key     = $stock->product_id . '-' . $stock->warehouse_id;
            $mov     = $movSummaries->get($key);
            $totalIn  = $mov ? (float) $mov->total_in  : 0;
            $totalOut = $mov ? (float) $mov->total_out : 0;

            $qtyOnHand   = $stock->qty_available - $stock->qty_reserved;
            $costPrice   = (float) ($stock->product->cost_price ?? 0);
            $totalValue  = $qtyOnHand * $costPrice;

            // Determine status
            if ($qtyOnHand <= 0) {
                $status = 'Habis';
            } elseif ($qtyOnHand <= $stock->qty_min) {
                $status = 'Min';
            } else {
                $status = 'Normal';
            }

            return [
                'product_code'    => $stock->product->sku ?? '-',
                'product_name'    => $stock->product->name ?? '-',
                'warehouse_name'  => $stock->warehouse->name ?? '-',
                'warehouse_code'  => $stock->warehouse->kode ?? '-',
                'rak_name'        => $stock->rak?->name ?? '-',
                'qty_available'   => $stock->qty_available,
                'qty_reserved'    => $stock->qty_reserved,
                'qty_on_hand'     => $qtyOnHand,
                'qty_min'         => $stock->qty_min,
                'total_in'        => $totalIn,
                'total_out'       => $totalOut,
                'cost_price'      => $costPrice,
                'total_value'     => $totalValue,
                'status'          => $status,
            ];
        });

        // ----------------------------------------------------------------
        // 4. Summary totals
        // ----------------------------------------------------------------
        $totals = [
            'items'         => $rows->count(),
            'qty_on_hand'   => $rows->sum('qty_on_hand'),
            'qty_available' => $rows->sum('qty_available'),
            'qty_reserved'  => $rows->sum('qty_reserved'),
            'total_in'      => $rows->sum('total_in'),
            'total_out'     => $rows->sum('total_out'),
            'total_value'   => $rows->sum('total_value'),
        ];

        // Resolve filter names for display
        $selectedProducts  = !empty($productIds)  ? Product::whereIn('id', $productIds)->pluck('name') : collect();
        $selectedWarehouses = !empty($warehouseIds) ? Warehouse::whereIn('id', $warehouseIds)->pluck('name') : collect();

        return view('reports.stock_report_preview', compact(
            'rows',
            'totals',
            'startDate',
            'endDate',
            'selectedProducts',
            'selectedWarehouses',
        ));
    }
}
