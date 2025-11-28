<?php

namespace App\Filament\Resources\QualityControlPurchaseResource\Pages;

use App\Filament\Resources\QualityControlPurchaseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditQualityControlPurchase extends EditRecord
{
    protected static string $resource = QualityControlPurchaseResource::class;

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