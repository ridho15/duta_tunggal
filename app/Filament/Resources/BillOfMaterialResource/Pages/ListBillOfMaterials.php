<?php

namespace App\Filament\Resources\BillOfMaterialResource\Pages;

use App\Filament\Resources\BillOfMaterialResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBillOfMaterials extends ListRecords
{
    protected static string $resource = BillOfMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
