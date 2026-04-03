<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\Warehouse;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class StockReportController extends Controller
{
    private const IN_TYPES = ['purchase_in', 'manufacture_in', 'transfer_in', 'adjustment_in', 'opname_in', 'beginning', 'return_in'];

    private const OUT_TYPES = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out', 'opname_out', 'return_out'];

    /**
     * Render a print-friendly preview of the stock report.
     */
    public function preview(Request $request)
    {
        abort_if(! Auth::user()?->can('view any inventory stock'), 403); // permission enforced

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
            ->orderBy('product_id')
            ->orderBy('rak_id');

        if (!empty($productIds)) {
            $stockQuery->whereIn('product_id', $productIds);
        }

        if (!empty($warehouseIds)) {
            $stockQuery->whereIn('warehouse_id', $warehouseIds);
        }

        $stocks = $stockQuery->get();
        $stockIndex = $stocks->keyBy(fn ($stock) => $this->movementKey($stock->product_id, $stock->warehouse_id, $stock->rak_id));

        // ----------------------------------------------------------------
        // 2. Fetch movements aligned to stock movement ledger
        // ----------------------------------------------------------------
        $movementBaseQuery = StockMovement::query()
            ->with(['product', 'warehouse', 'rak'])
            ->when(!empty($productIds), fn ($query) => $query->whereIn('product_id', $productIds))
            ->when(!empty($warehouseIds), fn ($query) => $query->whereIn('warehouse_id', $warehouseIds));

        $movementKeyQuery = (clone $movementBaseQuery)
            ->select('product_id', 'warehouse_id', 'rak_id')
            ->distinct()
            ->get();

        $movementPeriodSummary = $this->summarizeMovements(
            (clone $movementBaseQuery)->whereBetween('date', [$startDate->toDateTimeString(), $endDate->toDateTimeString()])
        );

        $movementOpeningSummary = $this->summarizeMovements(
            (clone $movementBaseQuery)->where('date', '<', $startDate->toDateTimeString())
        );

        $movementClosingSummary = $this->summarizeMovements(
            (clone $movementBaseQuery)->where('date', '<=', $endDate->toDateTimeString())
        );

        $productMap = Product::query()
            ->when(!empty($productIds), fn ($query) => $query->whereIn('id', $productIds))
            ->get()
            ->keyBy('id');

        $warehouseMap = Warehouse::query()
            ->when(!empty($warehouseIds), fn ($query) => $query->whereIn('id', $warehouseIds))
            ->get()
            ->keyBy('id');

        $rakIds = $movementKeyQuery->pluck('rak_id')
            ->merge($stocks->pluck('rak_id'))
            ->filter()
            ->unique()
            ->values();

        $rakMap = Rak::query()
            ->when($rakIds->isNotEmpty(), fn ($query) => $query->whereIn('id', $rakIds))
            ->get()
            ->keyBy('id');

        $keys = $stockIndex->keys()
            ->merge($movementKeyQuery->map(fn ($row) => $this->movementKey($row->product_id, $row->warehouse_id, $row->rak_id)))
            ->unique()
            ->values();

        // ----------------------------------------------------------------
        // 3. Build report rows
        // ----------------------------------------------------------------
        $rows = $keys->map(function (string $key) use ($stockIndex, $movementPeriodSummary, $movementOpeningSummary, $movementClosingSummary, $productMap, $warehouseMap, $rakMap) {
            [$productId, $warehouseId, $rakId] = array_map(fn ($segment) => $segment === 'null' ? null : (int) $segment, explode(':', $key));

            $stock = $stockIndex->get($key);
            $period = $movementPeriodSummary->get($key, ['qty_in' => 0.0, 'qty_out' => 0.0]);
            $opening = $movementOpeningSummary->get($key, ['net_qty' => null]);
            $closing = $movementClosingSummary->get($key, ['net_qty' => null]);

            $qtyAvailable = (float) ($stock?->qty_available ?? 0);
            $qtyReserved = (float) ($stock?->qty_reserved ?? 0);
            $snapshotQtyOnHand = $stock ? $qtyAvailable - $qtyReserved : null;

            $qtyOnHand = $snapshotQtyOnHand ?? (float) ($closing['net_qty'] ?? 0);
            $totalIn = (float) ($period['qty_in'] ?? 0);
            $totalOut = (float) ($period['qty_out'] ?? 0);
            $openingQty = $opening['net_qty'];

            if ($openingQty === null) {
                $openingQty = $qtyOnHand - $totalIn + $totalOut;
            }

            $product = $stock?->product ?? $productMap->get($productId);
            $warehouse = $stock?->warehouse ?? $warehouseMap->get($warehouseId);
            $rak = $stock?->rak ?? ($rakId ? $rakMap->get($rakId) : null);
            $costPrice = (float) ($product?->cost_price ?? 0);
            $totalValue = $qtyOnHand * $costPrice;
            $qtyMin = (float) ($stock?->qty_min ?? 0);

            if ($qtyOnHand <= 0) {
                $status = 'Habis';
            } elseif ($qtyMin > 0 && $qtyOnHand <= $qtyMin) {
                $status = 'Min';
            } else {
                $status = 'Normal';
            }

            return [
                'product_code'    => $product?->sku ?? '-',
                'product_name'    => $product?->name ?? '-',
                'warehouse_name'  => $warehouse?->name ?? '-',
                'warehouse_code'  => $warehouse?->kode ?? '-',
                'rak_name'        => $rak?->name ?? '-',
                'qty_available'   => $qtyAvailable,
                'qty_reserved'    => $qtyReserved,
                'qty_on_hand'     => $qtyOnHand,
                'qty_min'         => $qtyMin,
                'opening_qty'     => $openingQty,
                'total_in'        => $totalIn,
                'total_out'       => $totalOut,
                'cost_price'      => $costPrice,
                'total_value'     => $totalValue,
                'status'          => $status,
            ];
        })->filter(fn (array $row) => $row['qty_on_hand'] != 0 || $row['total_in'] != 0 || $row['total_out'] != 0 || $row['qty_reserved'] != 0)->values();

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

    private function summarizeMovements($query): Collection
    {
        $inTypes = "'" . implode("','", self::IN_TYPES) . "'";
        $outTypes = "'" . implode("','", self::OUT_TYPES) . "'";

        return $query
            ->selectRaw(
                'product_id, warehouse_id, rak_id, '
                . "SUM(CASE WHEN type IN ($inTypes) THEN quantity ELSE 0 END) as qty_in, "
                . "SUM(CASE WHEN type IN ($outTypes) THEN quantity ELSE 0 END) as qty_out"
            )
            ->groupBy('product_id', 'warehouse_id', 'rak_id')
            ->get()
            ->mapWithKeys(function ($row) {
                $key = $this->movementKey($row->product_id, $row->warehouse_id, $row->rak_id);
                $qtyIn = (float) ($row->qty_in ?? 0);
                $qtyOut = (float) ($row->qty_out ?? 0);

                return [
                    $key => [
                        'qty_in' => $qtyIn,
                        'qty_out' => $qtyOut,
                        'net_qty' => $qtyIn - $qtyOut,
                    ],
                ];
            });
    }

    private function movementKey(?int $productId, ?int $warehouseId, ?int $rakId): string
    {
        return implode(':', [
            $productId ?? 'null',
            $warehouseId ?? 'null',
            $rakId ?? 'null',
        ]);
    }
}
