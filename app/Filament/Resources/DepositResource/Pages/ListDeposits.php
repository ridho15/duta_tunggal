<?php

namespace App\Filament\Resources\DepositResource\Pages;

use App\Filament\Resources\DepositResource;
use App\Models\Deposit;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\StatsOverviewWidget\Card;

class ListDeposits extends ListRecords
{
    protected static string $resource = DepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle')
                ->label('Create New Deposit'),
        ];
    }

    public function getTitle(): string
    {
        return 'Deposit Management';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DepositStatsWidget::class,
        ];
    }
}

class DepositStatsWidget extends \Filament\Widgets\StatsOverviewWidget
{
    protected function getStats(): array
    {
        $totalDeposits = Deposit::sum('amount');
        $totalUsed = Deposit::sum('used_amount');
        $totalRemaining = Deposit::sum('remaining_amount');
        $activeDeposits = Deposit::where('status', 'active')->count();

        return [
            Card::make('Total Deposits', 'Rp ' . number_format($totalDeposits))
                ->description('All deposits combined')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('primary'),
                
            Card::make('Total Used', 'Rp ' . number_format($totalUsed))
                ->description('Amount already used')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('warning'),
                
            Card::make('Total Remaining', 'Rp ' . number_format($totalRemaining))
                ->description('Available balance')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
                
            Card::make('Active Deposits', $activeDeposits)
                ->description('Currently active deposits')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('info'),
        ];
    }
}
