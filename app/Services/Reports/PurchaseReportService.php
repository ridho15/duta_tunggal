<?php

namespace App\Services\Reports;

use App\Helpers\MoneyHelper;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PurchaseReportService
{
    public function query(array $filters = [], ?User $user = null): Builder
    {
        $query = PurchaseOrder::query()
            ->when($filters['start_date'] ?? null, fn ($builder, $startDate) => $builder->whereDate('order_date', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn ($builder, $endDate) => $builder->whereDate('order_date', '<=', $endDate))
            ->when($filters['supplier_id'] ?? null, fn ($builder, $supplierId) => $builder->where('supplier_id', $supplierId))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->with(['supplier', 'purchaseOrderItem.product']);

        if ($user && ! in_array('all', $user->manage_type ?? [], true)) {
            $query->where('cabang_id', $user->cabang_id);
        }

        return match ($filters['sort_by_total'] ?? null) {
            'asc' => $query->orderBy('total_amount', 'asc'),
            'desc' => $query->orderBy('total_amount', 'desc'),
            default => $query,
        };
    }

    public function exportCollectionFromQuery(Builder $query): Collection
    {
        return $this->exportCollectionFromOrders($query->get());
    }

    public function pdfPayload(array $filters = [], ?User $user = null): array
    {
        $orders = $this->orders($filters, $user);

        return [
            'rows' => $this->pdfRowsFromOrders($orders),
            'summary' => $this->summary($orders),
        ];
    }

    public function summary(Collection $orders): array
    {
        $totalOrders = $orders->count();
        $totalAmount = (float) $orders->sum('total_amount');

        return [
            'total_orders' => $totalOrders,
            'total_amount' => $totalAmount,
            'average_amount' => $totalOrders > 0 ? $totalAmount / $totalOrders : 0.0,
            'total_quantity' => (float) $orders->sum(fn ($order) => $order->purchaseOrderItem->sum('quantity')),
            'unique_products' => $orders->flatMap(fn ($order) => $order->purchaseOrderItem->pluck('product_id'))->unique()->count(),
            'status_counts' => [
                'draft' => $orders->where('status', 'draft')->count(),
                'approved' => $orders->where('status', 'approved')->count(),
                'partially_received' => $orders->where('status', 'partially_received')->count(),
                'completed' => $orders->where('status', 'completed')->count(),
                'closed' => $orders->where('status', 'closed')->count(),
                'processing' => $orders->where('status', 'processing')->count(),
                'confirmed' => $orders->where('status', 'confirmed')->count(),
                'cancelled' => $orders->where('status', 'cancelled')->count() + $orders->where('status', 'canceled')->count(),
            ],
        ];
    }

    private function orders(array $filters = [], ?User $user = null): Collection
    {
        return $this->query($filters, $user)->get();
    }

    private function exportCollectionFromOrders(Collection $orders): Collection
    {
        $data = collect();
        $summary = $this->summary($orders);

        foreach ($orders as $order) {
            $data->push([
                'No. PO' => $order->po_number,
                'Tanggal' => $order->order_date->format('d/m/Y'),
                'Kode Supplier' => $order->supplier->code ?? '-',
                'Nama Supplier' => $order->supplier->perusahaan ?? '-',
                'Alamat Supplier' => $order->supplier->address ?? '-',
                'No. Telp' => $order->supplier->phone ?? '-',
                'Email' => $order->supplier->email ?? '-',
                'Produk' => '',
                'Qty' => '',
                'Harga Satuan' => '',
                'Subtotal' => '',
                'Total PO' => MoneyHelper::rupiah($order->total_amount ?? 0),
                'Status' => $order->status,
            ]);

            foreach ($order->purchaseOrderItem as $item) {
                $data->push([
                    'No. PO' => '',
                    'Tanggal' => '',
                    'Kode Supplier' => '',
                    'Nama Supplier' => '',
                    'Alamat Supplier' => '',
                    'No. Telp' => '',
                    'Email' => '',
                    'Produk' => $item->product->name ?? '-',
                    'Qty' => $item->quantity ?? 0,
                    'Harga Satuan' => MoneyHelper::rupiah($item->unit_price ?? 0),
                    'Subtotal' => MoneyHelper::rupiah(($item->quantity ?? 0) * ($item->unit_price ?? 0)),
                    'Total PO' => '',
                    'Status' => '',
                ]);
            }

            $data->push([
                'No. PO' => '',
                'Tanggal' => '',
                'Kode Supplier' => '',
                'Nama Supplier' => '',
                'Alamat Supplier' => '',
                'No. Telp' => '',
                'Email' => '',
                'Produk' => '',
                'Qty' => '',
                'Harga Satuan' => '',
                'Subtotal' => '',
                'Total PO' => '',
                'Status' => '',
            ]);
        }

        $data->push([
            'No. PO' => 'SUMMARY',
            'Tanggal' => '',
            'Kode Supplier' => '',
            'Nama Supplier' => '',
            'Alamat Supplier' => '',
            'No. Telp' => '',
            'Email' => '',
            'Produk' => '',
            'Qty' => '',
            'Harga Satuan' => '',
            'Subtotal' => '',
            'Total PO' => 'Total: ' . MoneyHelper::rupiah($summary['total_amount']),
            'Status' => '',
        ]);

        $data->push([
            'No. PO' => '',
            'Tanggal' => '',
            'Kode Supplier' => '',
            'Nama Supplier' => '',
            'Alamat Supplier' => '',
            'No. Telp' => '',
            'Email' => '',
            'Produk' => 'Total Orders: ' . $summary['total_orders'],
            'Qty' => 'Completed: ' . $summary['status_counts']['completed'],
            'Harga Satuan' => 'Cancelled: ' . $summary['status_counts']['cancelled'],
            'Subtotal' => '',
            'Total PO' => '',
            'Status' => '',
        ]);

        $data->push([
            'No. PO' => '',
            'Tanggal' => '',
            'Kode Supplier' => '',
            'Nama Supplier' => '',
            'Alamat Supplier' => '',
            'No. Telp' => '',
            'Email' => '',
            'Produk' => 'Draft: ' . $summary['status_counts']['draft'],
            'Qty' => 'Processing: ' . $summary['status_counts']['processing'],
            'Harga Satuan' => 'Confirmed: ' . ($summary['status_counts']['confirmed'] + $summary['status_counts']['approved']),
            'Subtotal' => '',
            'Total PO' => '',
            'Status' => '',
        ]);

        $data->push([
            'No. PO' => '',
            'Tanggal' => '',
            'Kode Supplier' => '',
            'Nama Supplier' => '',
            'Alamat Supplier' => '',
            'No. Telp' => '',
            'Email' => '',
            'Produk' => 'Total Qty: ' . $summary['total_quantity'],
            'Qty' => 'Avg Transaction: ' . MoneyHelper::rupiah($summary['average_amount']),
            'Harga Satuan' => 'Unique Products: ' . $summary['unique_products'],
            'Subtotal' => '',
            'Total PO' => '',
            'Status' => '',
        ]);

        return $data;
    }

    private function pdfRowsFromOrders(Collection $orders): Collection
    {
        return $orders->map(function ($order) {
            return [
                'po_number' => $order->po_number,
                'order_date' => $order->order_date,
                'supplier_code' => $order->supplier->code ?? '-',
                'supplier_name' => $order->supplier->perusahaan ?? '-',
                'supplier_address' => $order->supplier->address ?? '-',
                'supplier_phone' => $order->supplier->phone ?? '-',
                'total_amount' => $order->total_amount ?? 0,
                'status' => $order->status ?? '-',
            ];
        });
    }
}
