<?php

namespace App\Exports;

use App\Models\SaleOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SalesReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        $data = collect();

        $orders = $this->query->with(['customer', 'saleOrderItem.product'])->get();

        $totalOrders = $orders->count();
        $totalAmount = $orders->sum('total_amount');
        $completedOrders = $orders->where('status', 'completed')->count();
        $cancelledOrders = $orders->where('status', 'cancelled')->count();
        $draftOrders = $orders->where('status', 'draft')->count();
        $processingOrders = $orders->where('status', 'processing')->count();
        $confirmedOrders = $orders->where('status', 'confirmed')->count();

        // Calculate additional statistics
        $totalQuantity = $orders->sum(function ($order) {
            return $order->saleOrderItem->sum('quantity');
        });
        $averageAmount = $totalOrders > 0 ? $totalAmount / $totalOrders : 0;
        $uniqueProducts = $orders->flatMap(function ($order) {
            return $order->saleOrderItem->pluck('product_id');
        })->unique()->count();

        foreach ($orders as $order) {
            // Header row for each order
            $data->push([
                'No. SO' => $order->so_number,
                'Tanggal' => $order->created_at->format('d/m/Y'),
                'Kode Customer' => $order->customer->code ?? '-',
                'Nama Customer' => $order->customer->name ?? '-',
                'Alamat Customer' => $order->customer->address ?? '-',
                'No. Telp' => $order->customer->phone ?? '-',
                'Email' => $order->customer->email ?? '-',
                'Produk' => '',
                'Qty' => '',
                'Harga Satuan' => '',
                'Subtotal' => '',
                'Total SO' => 'Rp ' . number_format($order->total_amount ?? 0, 0, ',', '.'),
                'Status' => $order->status,
            ]);

            // Item rows
            foreach ($order->saleOrderItem as $item) {
                if (($item->unit_price ?? 0) > 0 && ($item->quantity ?? 0) > 0) {
                    $data->push([
                        'No. SO' => '',
                        'Tanggal' => '',
                        'Kode Customer' => '',
                        'Nama Customer' => '',
                        'Alamat Customer' => '',
                        'No. Telp' => '',
                        'Email' => '',
                        'Produk' => $item->product->name ?? '-',
                        'Qty' => $item->quantity ?? 0,
                        'Harga Satuan' => 'Rp ' . number_format($item->unit_price ?? 0, 0, ',', '.'),
                        'Subtotal' => 'Rp ' . number_format(($item->quantity ?? 0) * ($item->unit_price ?? 0), 0, ',', '.'),
                        'Total SO' => '',
                        'Status' => '',
                    ]);
                }
            }

            // Empty row for separation
            $data->push([
                'No. SO' => '',
                'Tanggal' => '',
                'Kode Customer' => '',
                'Nama Customer' => '',
                'Alamat Customer' => '',
                'No. Telp' => '',
                'Email' => '',
                'Produk' => '',
                'Qty' => '',
                'Harga Satuan' => '',
                'Subtotal' => '',
                'Total SO' => '',
                'Status' => '',
            ]);
        }

        // Summary row
        $data->push([
            'No. SO' => 'SUMMARY',
            'Tanggal' => '',
            'Kode Customer' => '',
            'Nama Customer' => '',
            'Alamat Customer' => '',
            'No. Telp' => '',
            'Email' => '',
            'Produk' => '',
            'Qty' => '',
            'Harga Satuan' => '',
            'Subtotal' => '',
            'Total SO' => 'Total: Rp ' . number_format($totalAmount, 0, ',', '.'),
            'Status' => '',
        ]);

        $data->push([
            'No. SO' => '',
            'Tanggal' => '',
            'Kode Customer' => '',
            'Nama Customer' => '',
            'Alamat Customer' => '',
            'No. Telp' => '',
            'Email' => '',
            'Produk' => 'Total Orders: ' . $totalOrders,
            'Qty' => 'Completed: ' . $completedOrders,
            'Harga Satuan' => 'Cancelled: ' . $cancelledOrders,
            'Subtotal' => '',
            'Total SO' => '',
            'Status' => '',
        ]);

        $data->push([
            'No. SO' => '',
            'Tanggal' => '',
            'Kode Customer' => '',
            'Nama Customer' => '',
            'Alamat Customer' => '',
            'No. Telp' => '',
            'Email' => '',
            'Produk' => 'Draft: ' . $draftOrders,
            'Qty' => 'Processing: ' . $processingOrders,
            'Harga Satuan' => 'Confirmed: ' . $confirmedOrders,
            'Subtotal' => '',
            'Total SO' => '',
            'Status' => '',
        ]);

        $data->push([
            'No. SO' => '',
            'Tanggal' => '',
            'Kode Customer' => '',
            'Nama Customer' => '',
            'Alamat Customer' => '',
            'No. Telp' => '',
            'Email' => '',
            'Produk' => 'Total Qty: ' . $totalQuantity,
            'Qty' => 'Avg Transaction: Rp ' . number_format($averageAmount, 0, ',', '.'),
            'Harga Satuan' => 'Unique Products: ' . $uniqueProducts,
            'Subtotal' => '',
            'Total SO' => '',
            'Status' => '',
        ]);

        return $data;
    }

    public function headings(): array
    {
        return [
            'No. SO',
            'Tanggal',
            'Kode Customer',
            'Nama Customer',
            'Alamat Customer',
            'No. Telp',
            'Email',
            'Produk',
            'Qty',
            'Harga Satuan',
            'Subtotal',
            'Total SO',
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

        // Style for order headers (rows where No. SO is not empty and not SUMMARY)
        $highestRow = $sheet->getHighestRow();
        for ($row = 2; $row <= $highestRow; $row++) {
            $soValue = $sheet->getCell('A' . $row)->getValue();
            if ($soValue === 'SUMMARY') {
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
            } elseif (!empty($soValue)) {
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
            'A' => 15, // No. SO
            'B' => 12, // Tanggal
            'C' => 15, // Kode Customer
            'D' => 25, // Nama Customer
            'E' => 30, // Alamat Customer
            'F' => 15, // No. Telp
            'G' => 25, // Email
            'H' => 30, // Produk
            'I' => 8,  // Qty
            'J' => 15, // Harga Satuan
            'K' => 15, // Subtotal
            'L' => 15, // Total SO
            'M' => 12, // Status
        ];
    }
}