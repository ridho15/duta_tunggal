<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VendorCustomerSummaryExport implements FromCollection, WithHeadings
{
    protected $query;
    protected $type;

    public function __construct($query, $type)
    {
        $this->query = $query;
        $this->type = $type;
    }

    public function collection()
    {
        return $this->query->get()->map(function ($item, $index) {
            return [
                'No' => $index + 1,
                'Kode' => $item->code ?? '-',
                'Nama' => $item->name ?? '-',
                'Jumlah Transaksi' => $item->transaction_count ?? 0,
                'Total Nilai' => 'Rp ' . number_format($item->total_amount ?? 0, 0, ',', '.'),
                'Rata-rata' => 'Rp ' . number_format($item->average_amount ?? 0, 0, ',', '.'),
                'Transaksi Terakhir' => $item->last_transaction_date
                    ? (\is_string($item->last_transaction_date)
                        ? $item->last_transaction_date
                        : $item->last_transaction_date->format('Y-m-d'))
                    : '-',
                'Status' => $item->status_summary === 'active' ? 'Aktif' : 'Tidak Aktif',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No',
            'Kode',
            'Nama',
            'Jumlah Transaksi',
            'Total Nilai',
            'Rata-rata',
            'Transaksi Terakhir',
            'Status',
        ];
    }
}