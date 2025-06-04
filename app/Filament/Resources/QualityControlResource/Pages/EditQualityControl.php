<?php

namespace App\Filament\Resources\QualityControlResource\Pages;

use App\Filament\Resources\QualityControlResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditQualityControl extends EditRecord
{
    protected static string $resource = QualityControlResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
