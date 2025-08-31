<?php

namespace App\Filament\Resources\DepositAdjustmentResource\Pages;

use App\Filament\Resources\DepositAdjustmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDepositAdjustments extends ListRecords
{
    protected static string $resource = DepositAdjustmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create New Deposit'),
        ];
    }

    public function getTitle(): string
    {
        return 'Deposit Adjustments (Finance Only)';
    }
}
