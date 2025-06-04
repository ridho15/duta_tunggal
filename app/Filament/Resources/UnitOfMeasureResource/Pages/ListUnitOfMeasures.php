<?php

namespace App\Filament\Resources\UnitOfMeasureResource\Pages;

use App\Filament\Resources\UnitOfMeasureResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnitOfMeasures extends ListRecords
{
    protected static string $resource = UnitOfMeasureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
