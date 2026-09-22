<?php

namespace App\Services\Reports;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Carbon\Carbon;

class InventoryCardReportService
{
    private const IN_TYPES = ['purchase_in', 'manufacture_in', 'transfer_in', 'adjustment_in'];

    private const OUT_TYPES = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];

    public function reportData(array $filters = []): array
    {
        $start = ($filters['start'] ?? null)
            ? Carbon::parse($filters['start'])->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $end = ($filters['end'] ?? null)
            ? Carbon::parse($filters['end'])->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $productId = $filters['product_id'] ?? null;
        $warehouseId = $filters['warehouse_id'] ?? null;
        $productIds = $productId ? [$productId] : [];
        $warehouseIds = $warehouseId ? [$warehouseId] : [];

        $openingData = $this->aggregate($productIds, $warehouseIds)
            ->where('date', '<', $start->toDateTimeString())
            ->get()->keyBy(fn ($row) => $row->product_id . '-' . $row->warehouse_id);

        $periodData = $this->aggregate($productIds, $warehouseIds)
            ->whereBetween('date', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->get()->keyBy(fn ($row) => $row->product_id . '-' . $row->warehouse_id);

        $keys = $openingData->keys()->merge($periodData->keys())->unique()->values();

        $emptyTotals = [
            'opening_qty' => 0.0,
            'opening_value' => 0.0,
            'qty_in' => 0.0,
            'value_in' => 0.0,
            'qty_out' => 0.0,
            'value_out' => 0.0,
            'closing_qty' => 0.0,
            'closing_value' => 0.0,
        ];

        $productLabel = $productId ? (Product::find($productId)?->name ?? '-') : 'Semua Produk';
        $warehouseLabel = $warehouseId ? (Warehouse::find($warehouseId)?->name ?? '-') : 'Semua Gudang';

        if ($keys->isEmpty()) {
            return [
                'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                'product_label' => $productLabel,
                'warehouse_label' => $warehouseLabel,
                'rows' => [],
                'totals' => $emptyTotals,
            ];
        }

        $products = Product::whereIn('id', $keys->map(fn ($key) => (int) explode('-', $key)[0])->unique())->get()->keyBy('id');
        $warehouses = Warehouse::whereIn('id', $keys->map(fn ($key) => (int) explode('-', $key)[1])->unique())->get()->keyBy('id');

        $rows = [];
        $totals = $emptyTotals;

        foreach ($keys as $key) {
            [$productKey, $warehouseKey] = array_map('intval', explode('-', $key));
            $opening = $openingData[$key] ?? null;
            $movement = $periodData[$key] ?? null;

            $openingQty = ($opening->qty_in ?? 0) - ($opening->qty_out ?? 0);
            $openingValue = ($opening->value_in ?? 0) - ($opening->value_out ?? 0);
            $qtyIn = $movement->qty_in ?? 0;
            $valueIn = $movement->value_in ?? 0;
            $qtyOut = $movement->qty_out ?? 0;
            $valueOut = $movement->value_out ?? 0;

            if (($qtyIn == 0) && ($qtyOut == 0) && ($valueIn == 0) && ($valueOut == 0)) {
                continue;
            }

            $closingQty = $openingQty + $qtyIn - $qtyOut;
            $closingValue = $openingValue + $valueIn - $valueOut;

            $rows[] = [
                'product_name' => $products->get($productKey)?->name ?? '-',
                'product_sku' => $products->get($productKey)?->sku ?? null,
                'warehouse_name' => $warehouses->get($warehouseKey)?->name ?? '-',
                'warehouse_code' => $warehouses->get($warehouseKey)?->kode ?? null,
                'opening_qty' => $openingQty,
                'opening_value' => $openingValue,
                'qty_in' => $qtyIn,
                'value_in' => $valueIn,
                'qty_out' => $qtyOut,
                'value_out' => $valueOut,
                'closing_qty' => $closingQty,
                'closing_value' => $closingValue,
            ];

            $totals['opening_qty'] += $openingQty;
            $totals['opening_value'] += $openingValue;
            $totals['qty_in'] += $qtyIn;
            $totals['value_in'] += $valueIn;
            $totals['qty_out'] += $qtyOut;
            $totals['value_out'] += $valueOut;
            $totals['closing_qty'] += $closingQty;
            $totals['closing_value'] += $closingValue;
        }

        return [
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'product_label' => $productLabel,
            'warehouse_label' => $warehouseLabel,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    public function exportRows(array $filters = []): array
    {
        $report = $this->reportData($filters);
        $rows = [];

        foreach ($report['rows'] as $index => $row) {
            $rows[] = [
                $index + 1,
                $row['product_name'],
                $row['product_sku'] ?? '-',
                $row['warehouse_name'],
                $row['opening_qty'],
                $row['opening_value'],
                $row['qty_in'],
                $row['value_in'],
                $row['qty_out'],
                $row['value_out'],
                $row['closing_qty'],
                $row['closing_value'],
            ];
        }

        if (! empty($report['rows'])) {
            $rows[] = [
                '',
                'TOTAL',
                '',
                '',
                $report['totals']['opening_qty'],
                $report['totals']['opening_value'],
                $report['totals']['qty_in'],
                $report['totals']['value_in'],
                $report['totals']['qty_out'],
                $report['totals']['value_out'],
                $report['totals']['closing_qty'],
                $report['totals']['closing_value'],
            ];
        }

        return $rows;
    }

    private function aggregate(array $productIds, array $warehouseIds)
    {
        $inList = "'" . implode("','", self::IN_TYPES) . "'";
        $outList = "'" . implode("','", self::OUT_TYPES) . "'";

        $query = StockMovement::query()->selectRaw(
            'product_id, warehouse_id, '
            . "SUM(CASE WHEN type IN ($inList) THEN quantity ELSE 0 END) AS qty_in, "
            . "SUM(CASE WHEN type IN ($outList) THEN quantity ELSE 0 END) AS qty_out, "
            . "SUM(CASE WHEN type IN ($inList) THEN COALESCE(value,0) ELSE 0 END) AS value_in, "
            . "SUM(CASE WHEN type IN ($outList) THEN COALESCE(value,0) ELSE 0 END) AS value_out"
        )->groupBy('product_id', 'warehouse_id');

        if (! empty($productIds)) {
            $query->whereIn('product_id', $productIds);
        }

        if (! empty($warehouseIds)) {
            $query->whereIn('warehouse_id', $warehouseIds);
        }

        return $query;
    }
}