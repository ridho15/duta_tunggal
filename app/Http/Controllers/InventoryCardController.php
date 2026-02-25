<?php

namespace App\Http\Controllers;

use App\Exports\InventoryCardExport;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class InventoryCardController extends Controller
{
    private const IN_TYPES  = ['purchase_in', 'manufacture_in', 'transfer_in', 'adjustment_in'];
    private const OUT_TYPES = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];

    /**
     * Halaman cetak (print preview) — buka di browser, user tinggal Ctrl+P.
     */
    public function printView(Request $request)
    {
        $data = $this->buildReportData($request);
        $data['isPdf'] = false;
        return view('reports.inventory-card-print', compact('data'));
    }

    /**
     * Download PDF.
     */
    public function downloadPdf(Request $request)
    {
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
        $start = $request->input('start')
            ? Carbon::parse($request->input('start'))->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $end = $request->input('end')
            ? Carbon::parse($request->input('end'))->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $productId   = $request->integer('product_id')   ?: null;
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $productIds   = $productId   ? [$productId]   : [];
        $warehouseIds = $warehouseId ? [$warehouseId] : [];

        $openingData = $this->aggregate($productIds, $warehouseIds)
            ->where('date', '<', $start->toDateTimeString())
            ->get()->keyBy(fn ($r) => $r->product_id . '-' . $r->warehouse_id);

        $periodData = $this->aggregate($productIds, $warehouseIds)
            ->whereBetween('date', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->get()->keyBy(fn ($r) => $r->product_id . '-' . $r->warehouse_id);

        $keys = $openingData->keys()->merge($periodData->keys())->unique()->values();

        $empty = ['opening_qty' => 0.0, 'opening_value' => 0.0, 'qty_in' => 0.0,
                  'value_in' => 0.0, 'qty_out' => 0.0, 'value_out' => 0.0,
                  'closing_qty' => 0.0, 'closing_value' => 0.0];

        // Filter labels
        $productLabel   = $productId   ? (Product::find($productId)?->name ?? '-')   : 'Semua Produk';
        $warehouseLabel = $warehouseId ? (Warehouse::find($warehouseId)?->name ?? '-') : 'Semua Gudang';

        if ($keys->isEmpty()) {
            return [
                'period'          => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                'product_label'   => $productLabel,
                'warehouse_label' => $warehouseLabel,
                'rows'            => [],
                'totals'          => $empty,
            ];
        }

        $products   = Product::whereIn('id', $keys->map(fn ($k) => (int) explode('-', $k)[0])->unique())->get()->keyBy('id');
        $warehouses = Warehouse::whereIn('id', $keys->map(fn ($k) => (int) explode('-', $k)[1])->unique())->get()->keyBy('id');

        $rows   = [];
        $totals = $empty;

        foreach ($keys as $key) {
            [$pid, $wid] = array_map('intval', explode('-', $key));

            $o  = $openingData[$key] ?? null;
            $m  = $periodData[$key]  ?? null;

            $oQty  = ($o->qty_in   ?? 0) - ($o->qty_out   ?? 0);
            $oVal  = ($o->value_in ?? 0) - ($o->value_out ?? 0);
            $qIn   = $m->qty_in    ?? 0;
            $vIn   = $m->value_in  ?? 0;
            $qOut  = $m->qty_out   ?? 0;
            $vOut  = $m->value_out ?? 0;

            if (($qIn == 0) && ($qOut == 0) && ($vIn == 0) && ($vOut == 0)) {
                continue;
            }

            $rows[] = [
                'product_name'   => $products->get($pid)?->name  ?? '-',
                'product_sku'    => $products->get($pid)?->sku   ?? null,
                'warehouse_name' => $warehouses->get($wid)?->name ?? '-',
                'warehouse_code' => $warehouses->get($wid)?->kode ?? null,
                'opening_qty'    => $oQty,
                'opening_value'  => $oVal,
                'qty_in'         => $qIn,
                'value_in'       => $vIn,
                'qty_out'        => $qOut,
                'value_out'      => $vOut,
                'closing_qty'    => $oQty + $qIn - $qOut,
                'closing_value'  => $oVal + $vIn - $vOut,
            ];

            $totals['opening_qty']   += $oQty;
            $totals['opening_value'] += $oVal;
            $totals['qty_in']        += $qIn;
            $totals['value_in']      += $vIn;
            $totals['qty_out']       += $qOut;
            $totals['value_out']     += $vOut;
            $totals['closing_qty']   += $oQty + $qIn - $qOut;
            $totals['closing_value'] += $oVal + $vIn - $vOut;
        }

        return [
            'period'          => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'product_label'   => $productLabel,
            'warehouse_label' => $warehouseLabel,
            'rows'            => $rows,
            'totals'          => $totals,
        ];
    }

    private function aggregate(array $productIds, array $warehouseIds)
    {
        $inList  = "'" . implode("','", self::IN_TYPES)  . "'";
        $outList = "'" . implode("','", self::OUT_TYPES) . "'";

        $query = StockMovement::query()->selectRaw(
            'product_id, warehouse_id, '
            . "SUM(CASE WHEN type IN ($inList)  THEN quantity           ELSE 0 END) AS qty_in, "
            . "SUM(CASE WHEN type IN ($outList) THEN quantity           ELSE 0 END) AS qty_out, "
            . "SUM(CASE WHEN type IN ($inList)  THEN COALESCE(value,0) ELSE 0 END) AS value_in, "
            . "SUM(CASE WHEN type IN ($outList) THEN COALESCE(value,0) ELSE 0 END) AS value_out"
        )->groupBy('product_id', 'warehouse_id');

        if (! empty($productIds))   $query->whereIn('product_id',   $productIds);
        if (! empty($warehouseIds)) $query->whereIn('warehouse_id', $warehouseIds);

        return $query;
    }
}
