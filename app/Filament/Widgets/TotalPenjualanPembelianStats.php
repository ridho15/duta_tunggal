<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use App\Models\SaleOrder;
use Carbon\Carbon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TotalPenjualanPembelianStats extends BaseWidget
{
    use InteractsWithPageFilters;
    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfDay();

        $totalSalesMtd = SaleOrder::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->where('status', 'completed')
            ->sum('total_amount');

        $totalPurchasesMtd = PurchaseOrder::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->where('status', 'completed')
            ->sum('total_amount');
        return [
            Stat::make('Total Penjualan', "Rp." . number_format($totalSalesMtd, 0, ',', '.'))
                ->description('Total Penjualan')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Pembelian', "Rp." . number_format($totalPurchasesMtd, 0, ',', '.'))
                ->description('Total Pembelian')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('info'),
        ];
    }

    protected function getColumns(): int
    {
        return 2;
    }
}
