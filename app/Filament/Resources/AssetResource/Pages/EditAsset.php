<?php

namespace App\Filament\Resources\AssetResource\Pages;

use App\Filament\Resources\AssetResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAsset extends EditRecord
{
    protected static string $resource = AssetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()->icon('heroicon-o-eye')->color('primary'),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }
}
