<?php

namespace App\Filament\Resources\InventoryStockResource\Pages;

use App\Filament\Resources\InventoryStockResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryStock extends EditRecord
{
    protected static string $resource = InventoryStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }
}
