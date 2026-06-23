<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentStatus;
use App\Helpers\MoneyHelper;
use App\Support\AccountReceivableQuery;
use Filament\Widgets\StatsOverviewWidget;
use Livewire\Livewire;

class AccountReceivableStatsWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = null;
    
    protected function getStats(): array
    {
        // Get the current page instance to access filters
        $livewire = Livewire::current();
        $tableFilters = $livewire->tableFilters ?? [];

        $query = AccountReceivableQuery::filtered($tableFilters);

        // Calculate totals based on filtered data
        $totals = $query->selectRaw('
            SUM(total) as total_amount,
            SUM(paid) as paid_amount, 
            SUM(remaining) as remaining_amount,
            COUNT(*) as record_count
        ')->first();
        
        return [
            StatsOverviewWidget\Stat::make('Total Amount', MoneyHelper::rupiah($totals->total_amount ?? 0))
                ->description($totals->record_count . ' records')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
                
            StatsOverviewWidget\Stat::make('Paid Amount', MoneyHelper::rupiah($totals->paid_amount ?? 0))
                ->description('Already received')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            StatsOverviewWidget\Stat::make('Outstanding', MoneyHelper::rupiah($totals->remaining_amount ?? 0))
                ->description('Remaining to collect')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
