<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PosShiftExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        return $this->query->get()->map(function ($shift) {
            return [
                'shift_number' => $shift->shift_number,
                'cashier' => $shift->user?->name ?? 'Kasir',
                'warehouse' => $shift->warehouse?->name ?? '-',
                'opened_at' => $shift->opened_at ? $shift->opened_at->format('d/m/Y H:i') : '-',
                'closed_at' => $shift->closed_at ? $shift->closed_at->format('d/m/Y H:i') : 'Masih Buka',
                'starting_cash' => (float) $shift->starting_cash,
                'cash_sales_total' => (float) $shift->cash_sales_total,
                'non_cash_sales_total' => (float) $shift->non_cash_sales_total,
                'total_sales' => (float) $shift->total_sales,
                'total_transactions' => (int) $shift->total_transactions,
                'cash_actual' => $shift->cash_actual !== null ? (float) $shift->cash_actual : '-',
                'cash_difference' => $shift->cash_difference !== null ? (float) $shift->cash_difference : '-',
                'status' => $shift->status === 'closed' ? 'Selesai (Ditutup)' : 'Aktif (Buka)',
                'notes' => $shift->notes ?: '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No. Shift',
            'Kasir',
            'Gudang',
            'Waktu Buka',
            'Waktu Tutup',
            'Modal Awal (Rp)',
            'Penjualan Tunai (Rp)',
            'Penjualan Non-Tunai (Rp)',
            'Total Omzet (Rp)',
            'Jml Transaksi',
            'Kas Aktual (Rp)',
            'Selisih Kas (Rp)',
            'Status',
            'Catatan',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:N1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '15803D'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
            ],
        ]);

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 18,
            'C' => 18,
            'D' => 18,
            'E' => 18,
            'F' => 18,
            'G' => 20,
            'H' => 22,
            'I' => 20,
            'J' => 15,
            'K' => 18,
            'L' => 18,
            'M' => 16,
            'N' => 25,
        ];
    }
}
