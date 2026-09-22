<?php

namespace App\Exports;

use App\Services\Reports\PurchaseReportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PurchaseReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        return app(PurchaseReportService::class)->exportCollectionFromQuery($this->query);
    }

    public function headings(): array
    {
        return [
            'No. PO',
            'Tanggal',
            'Kode Supplier',
            'Nama Supplier',
            'Alamat Supplier',
            'No. Telp',
            'Email',
            'Produk',
            'Qty',
            'Harga Satuan',
            'Subtotal',
            'Total PO',
            'Status'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Style for headings
        $sheet->getStyle('A1:M1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4F81BD'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Style for order headers (rows where No. PO is not empty and not SUMMARY)
        $highestRow = $sheet->getHighestRow();
        for ($row = 2; $row <= $highestRow; $row++) {
            $poValue = $sheet->getCell('A' . $row)->getValue();
            if ($poValue === 'SUMMARY') {
                // Style for summary header
                $sheet->getStyle('A' . $row . ':M' . $row)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'size' => 14,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '9C27B0'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
                // Style for summary data rows (next 3 rows)
                for ($i = 1; $i <= 3; $i++) {
                    $nextRow = $row + $i;
                    if ($nextRow <= $highestRow) {
                        $sheet->getStyle('A' . $nextRow . ':M' . $nextRow)->applyFromArray([
                            'font' => [
                                'bold' => true,
                                'size' => 12,
                                'color' => ['rgb' => '000000'],
                            ],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'E1BEE7'],
                            ],
                            'borders' => [
                                'allBorders' => [
                                    'borderStyle' => Border::BORDER_THIN,
                                    'color' => ['rgb' => '000000'],
                                ],
                            ],
                        ]);
                    }
                }
                break; // Assuming summary is at the end
            } elseif (!empty($poValue)) {
                $sheet->getStyle('A' . $row . ':M' . $row)->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => '000000'],
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D9E1F2'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
            } else {
                // Style for item rows
                $sheet->getStyle('A' . $row . ':M' . $row)->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000'],
                        ],
                    ],
                ]);
            }
        }

        // Auto height for all rows
        foreach ($sheet->getRowIterator() as $row) {
            $sheet->getRowDimension($row->getRowIndex())->setRowHeight(-1);
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, // No. PO
            'B' => 12, // Tanggal
            'C' => 15, // Kode Supplier
            'D' => 25, // Nama Supplier
            'E' => 30, // Alamat Supplier
            'F' => 15, // No. Telp
            'G' => 25, // Email
            'H' => 30, // Produk
            'I' => 8,  // Qty
            'J' => 15, // Harga Satuan
            'K' => 15, // Subtotal
            'L' => 15, // Total PO
            'M' => 12, // Status
        ];
    }
}