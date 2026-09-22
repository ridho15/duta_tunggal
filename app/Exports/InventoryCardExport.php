<?php

namespace App\Exports;

use App\Services\Reports\InventoryCardReportService;
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
        return app(InventoryCardReportService::class)->exportRows([
            'start' => $this->startDate,
            'end' => $this->endDate,
            'product_id' => $this->productId,
            'warehouse_id' => $this->warehouseId,
        ]);
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
}
