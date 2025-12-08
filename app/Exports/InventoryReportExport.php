<?php

namespace App\Exports;

use App\Models\InventoryStock;
use App\Models\StockMovement;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Carbon\Carbon;

class InventoryReportExport implements FromCollection, WithHeadings
{
    protected $warehouse_id;
    protected $product_id;
    protected $type;
    protected $start_date;
    protected $end_date;

    public function __construct($warehouse_id, $product_id, $type, $start_date = null, $end_date = null)
    {
        $this->warehouse_id = $warehouse_id;
        $this->product_id = $product_id;
        $this->type = $type;
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }

    public function collection()
    {
        if ($this->type === 'movement') {
            return $this->getMovementData();
        } elseif ($this->type === 'aging') {
            return $this->getAgingData();
        } else {
            return $this->getStockData();
        }
    }

    private function getStockData()
    {
        return InventoryStock::query()
            ->when($this->warehouse_id, fn($q) => $q->where('warehouse_id', $this->warehouse_id))
            ->when($this->product_id, fn($q) => $q->where('product_id', $this->product_id))
            ->with(['product', 'warehouse', 'rak'])
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get()
            ->map(function ($stock) {
                $onHand = $stock->qty_available - $stock->qty_reserved;
                $status = $this->getStockStatus($stock, $onHand);

                return [
                    'Gudang' => $stock->warehouse->name ?? '-',
                    'Kode Produk' => $stock->product->code ?? '-',
                    'Nama Produk' => $stock->product->name ?? '-',
                    'Rak' => $stock->rak->name ?? '-',
                    'Qty Tersedia' => $stock->qty_available,
                    'Qty Dipesan' => $stock->qty_reserved,
                    'Qty Minimum' => $stock->qty_min,
                    'Qty On Hand' => $onHand,
                    'Status' => $status,
                ];
            });
    }

    private function getMovementData()
    {
        return StockMovement::query()
            ->when($this->start_date, fn($q) => $q->whereDate('date', '>=', $this->start_date))
            ->when($this->end_date, fn($q) => $q->whereDate('date', '<=', $this->end_date))
            ->when($this->warehouse_id, fn($q) => $q->where('warehouse_id', $this->warehouse_id))
            ->when($this->product_id, fn($q) => $q->where('product_id', $this->product_id))
            ->with(['product', 'warehouse', 'rak'])
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($movement) {
                $reference = '-';
                if ($movement->from_model_type && $movement->from_model_id) {
                    $modelName = class_basename($movement->from_model_type);
                    $reference = $modelName . ' #' . $movement->from_model_id;
                }

                return [
                    'Tanggal' => $movement->date,
                    'Kode Produk' => $movement->product->code ?? '-',
                    'Nama Produk' => $movement->product->name ?? '-',
                    'Gudang' => $movement->warehouse->name ?? '-',
                    'Rak' => $movement->rak->name ?? '-',
                    'Tipe Movement' => $movement->type,
                    'Quantity' => $movement->quantity,
                    'Nilai' => $movement->value,
                    'Referensi' => $reference,
                    'Catatan' => $movement->notes ?? '-',
                ];
            });
    }

    private function getAgingData()
    {
        return InventoryStock::query()
            ->when($this->warehouse_id, fn($q) => $q->where('warehouse_id', $this->warehouse_id))
            ->when($this->product_id, fn($q) => $q->where('product_id', $this->product_id))
            ->with(['product', 'warehouse', 'rak'])
            ->orderBy('warehouse_id')
            ->orderBy('product_id')
            ->get()
            ->map(function ($stock) {
                $onHand = $stock->qty_available - $stock->qty_reserved;

                // Get last movement date
                $lastMovement = StockMovement::where('product_id', $stock->product_id)
                    ->where('warehouse_id', $stock->warehouse_id)
                    ->orderBy('date', 'desc')
                    ->first();

                $lastMovementDate = $lastMovement ? $lastMovement->date : null;
                $agingDays = $lastMovement ? Carbon::parse($lastMovement->date)->diffInDays(now()) : 999;
                $agingCategory = $this->getAgingCategory($agingDays);

                return [
                    'Gudang' => $stock->warehouse->name ?? '-',
                    'Kode Produk' => $stock->product->code ?? '-',
                    'Nama Produk' => $stock->product->name ?? '-',
                    'Rak' => $stock->rak->name ?? '-',
                    'Qty Tersedia' => $stock->qty_available,
                    'Qty Dipesan' => $stock->qty_reserved,
                    'Qty On Hand' => $onHand,
                    'Terakhir Movement' => $lastMovementDate,
                    'Hari Aging' => $agingDays === 999 ? 'Tidak Ada Data' : $agingDays,
                    'Kategori Aging' => $agingCategory,
                ];
            });
    }

    private function getStockStatus($stock, $onHand)
    {
        if ($onHand <= 0) return 'Habis';
        if ($onHand <= $stock->qty_min) return 'Minimum';
        return 'Normal';
    }

    private function getAgingCategory($days)
    {
        if ($days <= 30) return 'Aktif';
        if ($days <= 90) return 'Slow Moving';
        if ($days <= 180) return 'Stagnan';
        return 'Dead Stock';
    }

    public function headings(): array
    {
        if ($this->type === 'movement') {
            return ['Tanggal', 'Kode Produk', 'Nama Produk', 'Gudang', 'Rak', 'Tipe Movement', 'Quantity', 'Nilai', 'Referensi', 'Catatan'];
        } elseif ($this->type === 'aging') {
            return ['Gudang', 'Kode Produk', 'Nama Produk', 'Rak', 'Qty Tersedia', 'Qty Dipesan', 'Qty On Hand', 'Terakhir Movement', 'Hari Aging', 'Kategori Aging'];
        } else {
            return ['Gudang', 'Kode Produk', 'Nama Produk', 'Rak', 'Qty Tersedia', 'Qty Dipesan', 'Qty Minimum', 'Qty On Hand', 'Status'];
        }
    }
}