<?php

namespace App\Filament\Resources\TaxSettingResource\Pages;

use App\Filament\Resources\TaxSettingResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaxSettings extends ListRecords
{
    protected static string $resource = TaxSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
