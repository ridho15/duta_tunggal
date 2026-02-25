<?php

namespace App\Exports;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryCardExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    private const IN_TYPES  = ['purchase_in', 'manufacture_in', 'transfer_in', 'adjustment_in'];
    private const OUT_TYPES = ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'];

    public function __construct(
        protected ?string $startDate    = null,
        protected ?string $endDate      = null,
        protected ?int    $productId    = null,
        protected ?int    $warehouseId  = null,
    ) {}

    public function headings(): array
    {
        return [
            'No.',
            'Produk',
            'SKU',
            'Gudang',
            'Saldo Awal (Qty)',
            'Saldo Awal (Nilai)',
            'Masuk (Qty)',
            'Masuk (Nilai)',
            'Keluar (Qty)',
            'Keluar (Nilai)',
            'Saldo Akhir (Qty)',
            'Saldo Akhir (Nilai)',
        ];
    }

    public function array(): array
    {
        $rows = $this->buildData();
        $data = [];

        foreach ($rows['rows'] as $i => $row) {
            $data[] = [
                $i + 1,
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

        // Totals row
        if (! empty($rows['rows'])) {
            $data[] = [
                '',
                'TOTAL',
                '',
                '',
                $rows['totals']['opening_qty'],
                $rows['totals']['opening_value'],
                $rows['totals']['qty_in'],
                $rows['totals']['value_in'],
                $rows['totals']['qty_out'],
                $rows['totals']['value_out'],
                $rows['totals']['closing_qty'],
                $rows['totals']['closing_value'],
            ];
        }

        return $data;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();

        // Header row (row 1) style
        $sheet->getStyle("A1:L1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']]],
        ]);

        // Total row (last row)
        $sheet->getStyle("A{$lastRow}:L{$lastRow}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E5E7EB']],
        ]);

        // Border for all data
        $sheet->getStyle("A1:L{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
        ]);

        return [];
    }

    // -------------------------------------------------------------------------

    private function buildData(): array
    {
        $start = $this->startDate
            ? Carbon::parse($this->startDate)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $end = $this->endDate
            ? Carbon::parse($this->endDate)->endOfDay()
            : now()->endOfMonth()->endOfDay();

        $productIds   = $this->productId   ? [$this->productId]   : [];
        $warehouseIds = $this->warehouseId ? [$this->warehouseId] : [];

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

        if ($keys->isEmpty()) {
            return ['period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                    'rows' => [], 'totals' => $empty];
        }

        $products   = Product::whereIn('id', $keys->map(fn ($k) => (int) explode('-', $k)[0])->unique())->get()->keyBy('id');
        $warehouses = Warehouse::whereIn('id', $keys->map(fn ($k) => (int) explode('-', $k)[1])->unique())->get()->keyBy('id');

        $rows   = [];
        $totals = $empty;

        foreach ($keys as $key) {
            [$pid, $wid] = array_map('intval', explode('-', $key));

            $o  = $openingData[$key] ?? null;
            $m  = $periodData[$key]  ?? null;

            $oQty   = ($o->qty_in   ?? 0) - ($o->qty_out   ?? 0);
            $oVal   = ($o->value_in ?? 0) - ($o->value_out ?? 0);
            $qIn    = $m->qty_in    ?? 0;
            $vIn    = $m->value_in  ?? 0;
            $qOut   = $m->qty_out   ?? 0;
            $vOut   = $m->value_out ?? 0;

            $rows[] = [
                'product_name'   => $products->get($pid)?->name  ?? '-',
                'product_sku'    => $products->get($pid)?->sku   ?? null,
                'warehouse_name' => $warehouses->get($wid)?->name ?? '-',
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

        return ['period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
                'rows' => $rows, 'totals' => $totals];
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
