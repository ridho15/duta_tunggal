<?php

namespace App\Filament\Resources\OtherSaleResource\Pages;

use App\Filament\Resources\OtherSaleResource;
use Filament\Actions;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditOtherSale extends EditRecord
{
    protected static string $resource = OtherSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }
}
