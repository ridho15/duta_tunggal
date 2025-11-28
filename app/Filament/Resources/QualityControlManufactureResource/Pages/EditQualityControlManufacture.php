<?php

namespace App\Filament\Resources\QualityControlManufactureResource\Pages;

use App\Filament\Resources\QualityControlManufactureResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQualityControlManufacture extends EditRecord
{
    protected static string $resource = QualityControlManufactureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }
}