<?php

namespace App\Filament\Resources\StockOpnameTestResource\Pages;

use App\Filament\Resources\StockOpnameTestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockOpnameTests extends ListRecords
{
    protected static string $resource = StockOpnameTestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
