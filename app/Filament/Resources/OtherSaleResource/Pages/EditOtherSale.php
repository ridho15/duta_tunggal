<?php

namespace App\Filament\Resources\OtherSaleResource\Pages;

use App\Filament\Resources\OtherSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOtherSale extends EditRecord
{
    protected static string $resource = OtherSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
