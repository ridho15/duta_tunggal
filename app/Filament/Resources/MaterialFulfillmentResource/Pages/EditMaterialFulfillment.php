<?php

namespace App\Filament\Resources\MaterialFulfillmentResource\Pages;

use App\Filament\Resources\MaterialFulfillmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialFulfillment extends EditRecord
{
    protected static string $resource = MaterialFulfillmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
