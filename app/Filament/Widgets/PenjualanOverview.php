<?php

namespace App\Filament\Widgets;

use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PenjualanOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $start = $this->filters['tanggalMulai'] ?? now()->subDays(7);
        $end = $this->filters['tanggalAkhir'] ?? now();

        $totalPenjualan = SaleOrder::where('status', 'completed')
            ->whereBetween('order_date', [$start, $end])
            ->sum('total_amount');

        $listSaleOrderItem = SaleOrderItem::whereHas('saleOrder', function ($query) use ($start, $end) {
            $query->where('status', 'completed')
                ->whereBetween('order_date', [$start, $end]);
        })->get();

        $totalPpn = 0;
        foreach ($listSaleOrderItem as $item) {
            $harga = $item->quantity * $item->unit_price;
            $hargaSetelahDiskon = $harga - ($harga * $item->discount / 100);
            $ppn = $hargaSetelahDiskon * $item->tax / 100;
            $totalPpn += $ppn;
        }

        $jumlahTransaksi = SaleOrder::where('status', 'completed')
            ->whereBetween('order_date', [$start, $end])
            ->count();

        $rata_rata = ($jumlahTransaksi > 0) ? $totalPenjualan / $jumlahTransaksi : 0;

        return [
            Stat::make('Total Penjualan', "Rp." . number_format($totalPenjualan, 0, ',', '.'))
                ->description('Total Penjualan')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Penjualan - PPN', "Rp." . number_format($totalPenjualan - $totalPpn, 0, ',', '.'))
                ->description('Tanpa PPN')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Jumlah Transaksi', number_format($jumlahTransaksi, 0, ',', '.'))
                ->description('Transaksi Selesai')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Penjualan Rata-Rata', "Rp." . number_format($rata_rata, 0, ',', '.'))
                ->description('Rata-rata / Transaksi')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
