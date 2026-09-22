<?php

namespace App\Filament\Widgets;

use App\Helpers\MoneyHelper;
use App\Filament\Resources\PurchaseInvoiceResource;
use App\Support\AccountPayableQuery;
use Filament\Widgets\StatsOverviewWidget;
use Livewire\Livewire;

class AccountPayableStatsWidget extends StatsOverviewWidget
{
    protected static ?string $pollingInterval = null;
    
    protected function getStats(): array
    {
        // Get the current page instance to access filters
        $livewire = Livewire::current();
        $tableFilters = $livewire->tableFilters ?? [];
        
        $query = AccountPayableQuery::filtered($tableFilters);

        $records = $query->with('invoice')->get();
        $totalAmount = $records->sum(fn ($record) => PurchaseInvoiceResource::invoiceAmountToIdr($record->invoice, $record->total));
        $paidAmount = $records->sum(fn ($record) => PurchaseInvoiceResource::invoiceAmountToIdr($record->invoice, $record->paid));
        $remainingAmount = $records->sum(fn ($record) => PurchaseInvoiceResource::invoiceAmountToIdr($record->invoice, $record->remaining));
        
        return [
            StatsOverviewWidget\Stat::make('Total Hutang', MoneyHelper::rupiah($totalAmount))
                ->description($records->count() . ' data, dikonversi ke IDR')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('primary'),
                
            StatsOverviewWidget\Stat::make('Sudah Dibayar', MoneyHelper::rupiah($paidAmount))
                ->description('Telah dibayar, dikonversi ke IDR')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
                
            StatsOverviewWidget\Stat::make('Sisa Hutang', MoneyHelper::rupiah($remainingAmount))
                ->description('Sisa yang harus dibayar, dikonversi ke IDR')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
