<?php

namespace App\Filament\Widgets;

use App\Models\Deposit;
use Filament\Widgets\StatsOverviewWidget;

class DepositStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalDeposits = Deposit::sum('amount');
        $totalUsed = Deposit::sum('used_amount');
        $totalRemaining = Deposit::sum('remaining_amount');
        $activeDeposits = Deposit::where('status', 'active')->count();

        return [
            StatsOverviewWidget\Stat::make('Total Deposits', 'Rp ' . number_format($totalDeposits))
                ->description('All deposits combined')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary'),
                
            StatsOverviewWidget\Stat::make('Total Used', 'Rp ' . number_format($totalUsed))
                ->description('Amount already used')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('warning'),
                
            StatsOverviewWidget\Stat::make('Total Remaining', 'Rp ' . number_format($totalRemaining))
                ->description('Available balance')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
                
            StatsOverviewWidget\Stat::make('Active Deposits', $activeDeposits)
                ->description('Currently active deposits')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),
        ];
    }
}
