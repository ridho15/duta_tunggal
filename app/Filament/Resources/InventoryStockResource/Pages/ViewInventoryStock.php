<?php

namespace App\Filament\Resources\InventoryStockResource\Pages;

use App\Filament\Resources\InventoryStockResource;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryStock extends ViewRecord
{
    protected static string $resource = InventoryStockResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
            ->icon('heroicon-o-pencil-square')
        ];
    }
}
