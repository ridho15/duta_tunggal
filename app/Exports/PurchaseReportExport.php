<?php

namespace App\Exports;

use App\Models\PurchaseOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PurchaseReportExport implements FromCollection, WithHeadings
{
    protected $start_date;
    protected $end_date;
    protected $supplier_id;

    public function __construct($start_date, $end_date, $supplier_id)
    {
        $this->start_date = $start_date;
        $this->end_date = $end_date;
        $this->supplier_id = $supplier_id;
    }

    public function collection()
    {
        return PurchaseOrder::query()
            ->when($this->start_date, fn($q) => $q->whereDate('order_date', '>=', $this->start_date))
            ->when($this->end_date, fn($q) => $q->whereDate('order_date', '<=', $this->end_date))
            ->when($this->supplier_id, fn($q) => $q->where('supplier_id', $this->supplier_id))
            ->select('po_number', 'order_date', 'supplier_id', 'total_amount', 'status')
            ->get()
            ->map(function ($order) {
                return [
                    'No. PO' => $order->po_number,
                    'Tanggal' => $order->order_date->format('Y-m-d'),
                    'Supplier' => $order->supplier->name ?? '-',
                    'Total' => 'Rp ' . number_format($order->total_amount ?? 0, 0, ',', '.'),
                    'Status' => $order->status,
                ];
            });
    }

    public function headings(): array
    {
        return ['No. PO', 'Tanggal', 'Supplier', 'Total', 'Status'];
    }
}