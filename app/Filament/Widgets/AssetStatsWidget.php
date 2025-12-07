<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssetStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalAssets = Asset::count();
        $activeAssets = Asset::whereIn('status', ['active', 'posted'])->count();
        $fullyDepreciatedAssets = Asset::where('status', 'fully_depreciated')->count();
        $totalAssetValue = Asset::sum('purchase_cost');
        $totalBookValue = Asset::sum('book_value');

        return [
            Stat::make('Total Aset', $totalAssets)
                ->description('Jumlah total aset')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make('Aset Aktif', $activeAssets)
                ->description('Aset yang masih aktif (active/posted)')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Aset Fully Depreciated', $fullyDepreciatedAssets)
                ->description('Aset yang sudah fully depreciated')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('warning'),

            Stat::make('Total Nilai Aset', 'Rp ' . number_format($totalAssetValue, 0, ',', '.'))
                ->description('Total nilai pembelian aset')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('info'),

            Stat::make('Total Book Value', 'Rp ' . number_format($totalBookValue, 0, ',', '.'))
                ->description('Total nilai buku aset saat ini')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('gray'),
        ];
    }
}