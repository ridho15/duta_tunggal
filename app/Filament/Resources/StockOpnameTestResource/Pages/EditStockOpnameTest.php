<?php

namespace App\Filament\Resources\StockOpnameTestResource\Pages;

use App\Filament\Resources\StockOpnameTestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockOpnameTest extends EditRecord
{
    protected static string $resource = StockOpnameTestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
