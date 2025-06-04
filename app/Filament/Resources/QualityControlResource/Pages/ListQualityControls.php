<?php

namespace App\Filament\Resources\QualityControlResource\Pages;

use App\Filament\Resources\QualityControlResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListQualityControls extends ListRecords
{
    protected static string $resource = QualityControlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
