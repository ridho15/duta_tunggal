<?php

namespace App\Filament\Resources\AccountingPeriodResource\Pages;

use App\Filament\Resources\AccountingPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccountingPeriods extends ListRecords
{
    protected static string $resource = AccountingPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Buka Periode Baru'),
        ];
    }
}
