<?php

namespace App\Exports;

use App\Services\Reports\InventoryReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

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
        $service = app(InventoryReportService::class);
        $filters = [
            'warehouse_id' => $this->warehouse_id,
            'product_id' => $this->product_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'type' => $this->type,
        ];

        return match ($this->type) {
            'movement' => $service->movementRows($filters),
            'aging' => $service->agingRows($filters),
            default => $service->stockRows($filters),
        };
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