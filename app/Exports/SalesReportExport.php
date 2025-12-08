<?php

namespace App\Exports;

use App\Models\SaleOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SalesReportExport implements FromCollection, WithHeadings
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function collection()
    {
        return $this->query
            ->get()
            ->map(function ($order) {
                return [
                    'No. SO' => $order->so_number,
                    'Tanggal' => $order->created_at->format('Y-m-d'),
                    'Kode Customer' => $order->customer->code ?? '-',
                    'Nama Customer' => $order->customer->name ?? '-',
                    'Total' => 'Rp ' . number_format($order->total_amount ?? 0, 0, ',', '.'),
                    'Status' => $order->status,
                ];
            });
    }

    public function headings(): array
    {
        return ['No. SO', 'Tanggal', 'Kode Customer', 'Nama Customer', 'Total', 'Status'];
    }
}