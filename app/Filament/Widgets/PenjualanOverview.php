<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SaleOrderResource\Pages\ListSaleOrders;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PenjualanOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalPenjualan = SaleOrder::where('status', 'completed')->sum('total_amount');
        $listSaleOrderItem = SaleOrderItem::whereHas('saleOrder', function ($query) {
            $query->where('status', 'completed');
        })->select(['id', 'sale_order_id', 'quantity', 'unit_price', 'discount', 'tax'])->get();
        $totalPpn = 0;
        foreach ($listSaleOrderItem as $saleOrderItem) {
            $harga = $saleOrderItem->quantity * $saleOrderItem->unit_price;
            $hargaSetelahDiscount = $harga - ($harga * $saleOrderItem->discount / 100);
            $ppn = $hargaSetelahDiscount * $saleOrderItem->tax / 100;
            $totalPpn += $ppn;
        }

        $jumlahTransaksi = SaleOrder::where('status', 'completed')->count();
        $rata_rata = $totalPenjualan / $jumlahTransaksi;
        return [
            Stat::make('Total Penjualan', "Rp." . number_format($totalPenjualan, 0, ',', '.'))
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Penjualan - PPN', "Rp." . number_format($totalPenjualan - $totalPpn, 0, ',', '.'))
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Jumlah Transaksi', number_format($jumlahTransaksi, 0, ',', '.'))
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Penjualan Rata Rata', number_format($rata_rata, 0, ',', '.'))
                ->description('32k increase')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
