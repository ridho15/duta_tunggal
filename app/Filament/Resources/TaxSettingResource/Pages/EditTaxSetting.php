<?php

namespace App\Filament\Resources\TaxSettingResource\Pages;

use App\Filament\Resources\TaxSettingResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaxSetting extends EditRecord
{
    protected static string $resource = TaxSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }
}
