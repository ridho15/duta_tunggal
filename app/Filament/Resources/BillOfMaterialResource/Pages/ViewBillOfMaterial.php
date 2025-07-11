<?php

namespace App\Filament\Resources\BillOfMaterialResource\Pages;

use App\Filament\Resources\BillOfMaterialResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBillOfMaterial extends ViewRecord
{
    protected static string $resource = BillOfMaterialResource::class;

    protected function getActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
            EditAction::make()
                ->icon('heroicon-o-pencil-square')
        ];
    }
}
