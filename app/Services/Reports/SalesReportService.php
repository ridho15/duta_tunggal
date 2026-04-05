<?php

namespace App\Services\Reports;

use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SalesReportService
{
    public function query(array $filters = [], ?User $user = null): Builder
    {
        $query = SaleOrder::query()
            ->when($filters['start_date'] ?? null, fn ($builder, $startDate) => $builder->whereDate('created_at', '>=', $startDate))
            ->when($filters['end_date'] ?? null, fn ($builder, $endDate) => $builder->whereDate('created_at', '<=', $endDate))
            ->when($filters['customer_id'] ?? null, fn ($builder, $customerId) => $builder->where('customer_id', $customerId))
            ->when($filters['so_number'] ?? null, fn ($builder, $soNumber) => $builder->where('so_number', 'like', '%' . $soNumber . '%'))
            ->when($filters['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->with(['customer', 'saleOrderItem.product']);

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

    public function pdfRows(array $filters = [], ?User $user = null): Collection
    {
        return $this->pdfRowsFromOrders($this->orders($filters, $user));
    }

    public function summary(Collection $orders): array
    {
        $totalOrders = $orders->count();
        $totalAmount = (float) $orders->sum('total_amount');
        $statusCounts = [
            'draft' => $orders->where('status', 'draft')->count(),
            'processing' => $orders->where('status', 'processing')->count(),
            'confirmed' => $orders->where('status', 'confirmed')->count(),
            'completed' => $orders->where('status', 'completed')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count() + $orders->where('status', 'canceled')->count(),
        ];

        $totalQuantity = (float) $orders->sum(fn ($order) => $order->saleOrderItem->sum('quantity'));
        $uniqueProducts = $orders->flatMap(fn ($order) => $order->saleOrderItem->pluck('product_id'))->unique()->count();
        $totalDpp = 0.0;

        $orders->each(function ($order) use (&$totalDpp): void {
            $totalDpp += $order->saleOrderItem->sum(function ($item) {
                $lineBase = ($item->quantity ?? 0) * ($item->unit_price ?? 0);
                $afterDiscount = $lineBase * (1 - (($item->discount ?? 0) / 100));
                $taxResult = \App\Services\TaxService::compute(
                    $afterDiscount,
                    $item->tax ?? 0,
                    $item->tipe_pajak ?? 'Exclusive'
                );

                return $taxResult['dpp'];
            });
        });

        return [
            'total_orders' => $totalOrders,
            'total_amount' => $totalAmount,
            'average_amount' => $totalOrders > 0 ? $totalAmount / $totalOrders : 0.0,
            'total_quantity' => $totalQuantity,
            'unique_products' => $uniqueProducts,
            'total_dpp' => $totalDpp,
            'status_counts' => $statusCounts,
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
                'Discount (%)' => '',
                'Tax Rate (%)' => '',
                'Tipe Pajak' => '',
                'DPP' => '',
                'PPN Amount' => '',
                'Item Subtotal' => '',
                'Subtotal' => '',
                'Total SO' => 'Rp ' . number_format($order->total_amount ?? 0, 0, ',', '.'),
                'Status' => $order->status,
            ]);

            foreach ($order->saleOrderItem as $item) {
                if (($item->unit_price ?? 0) <= 0 || ($item->quantity ?? 0) <= 0) {
                    continue;
                }

                $lineBase = ($item->quantity ?? 0) * ($item->unit_price ?? 0);
                $discountPct = $item->discount ?? 0;
                $afterDiscount = $lineBase * (1 - ($discountPct / 100));
                $taxRate = $item->tax ?? 0;
                $taxResult = \App\Services\TaxService::compute($afterDiscount, $taxRate, $item->tipe_pajak ?? 'Exclusive');

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
                    'Discount (%)' => number_format($discountPct, 2),
                    'Tax Rate (%)' => number_format($taxRate, 2),
                    'Tipe Pajak' => $item->tipe_pajak ?? '-',
                    'DPP' => 'Rp ' . number_format($taxResult['dpp'] ?? 0, 0, ',', '.'),
                    'PPN Amount' => 'Rp ' . number_format($taxResult['ppn'] ?? 0, 0, ',', '.'),
                    'Item Subtotal' => 'Rp ' . number_format($taxResult['total'] ?? 0, 0, ',', '.'),
                    'Subtotal' => '',
                    'Total SO' => '',
                    'Status' => '',
                ]);
            }

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
                'Discount (%)' => '',
                'Tax Rate (%)' => '',
                'Tipe Pajak' => '',
                'DPP' => '',
                'PPN Amount' => '',
                'Item Subtotal' => '',
                'Subtotal' => '',
                'Total SO' => '',
                'Status' => '',
            ]);
        }

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
            'Discount (%)' => '',
            'Tax Rate (%)' => '',
            'Tipe Pajak' => '',
            'DPP' => 'Total DPP: Rp ' . number_format($summary['total_dpp'], 0, ',', '.'),
            'PPN Amount' => '',
            'Item Subtotal' => '',
            'Subtotal' => '',
            'Total SO' => 'Total: Rp ' . number_format($summary['total_amount'], 0, ',', '.'),
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
            'Produk' => 'Total Orders: ' . $summary['total_orders'],
            'Qty' => 'Completed: ' . $summary['status_counts']['completed'],
            'Harga Satuan' => 'Cancelled: ' . $summary['status_counts']['cancelled'],
            'Discount (%)' => '',
            'Tax Rate (%)' => '',
            'Tipe Pajak' => '',
            'DPP' => '',
            'PPN Amount' => '',
            'Item Subtotal' => '',
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
            'Produk' => 'Draft: ' . $summary['status_counts']['draft'],
            'Qty' => 'Processing: ' . $summary['status_counts']['processing'],
            'Harga Satuan' => 'Confirmed: ' . $summary['status_counts']['confirmed'],
            'Discount (%)' => '',
            'Tax Rate (%)' => '',
            'Tipe Pajak' => '',
            'DPP' => '',
            'PPN Amount' => '',
            'Item Subtotal' => '',
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
            'Produk' => 'Total Qty: ' . $summary['total_quantity'],
            'Qty' => 'Avg Transaction: Rp ' . number_format($summary['average_amount'], 0, ',', '.'),
            'Harga Satuan' => 'Unique Products: ' . $summary['unique_products'],
            'Discount (%)' => '',
            'Tax Rate (%)' => '',
            'Tipe Pajak' => '',
            'DPP' => '',
            'PPN Amount' => '',
            'Item Subtotal' => '',
            'Subtotal' => '',
            'Total SO' => '',
            'Status' => '',
        ]);

        return $data;
    }

    private function pdfRowsFromOrders(Collection $orders): Collection
    {
        return $orders->map(function ($order) {
            return [
                'so_number' => mb_convert_encoding($order->so_number ?? '', 'UTF-8', 'UTF-8'),
                'created_at' => $order->created_at,
                'customer_code' => mb_convert_encoding($order->customer->code ?? '-', 'UTF-8', 'UTF-8'),
                'customer_name' => mb_convert_encoding($order->customer->name ?? '-', 'UTF-8', 'UTF-8'),
                'total_amount' => $order->total_amount ?? 0,
                'status' => mb_convert_encoding($order->status ?? '', 'UTF-8', 'UTF-8'),
            ];
        });
    }
}