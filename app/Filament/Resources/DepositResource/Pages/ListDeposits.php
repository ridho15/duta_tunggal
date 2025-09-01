<?php

namespace App\Filament\Resources\DepositResource\Pages;

use App\Filament\Resources\DepositResource;
use App\Filament\Widgets\DepositStatsWidget;
use App\Models\Deposit;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

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
