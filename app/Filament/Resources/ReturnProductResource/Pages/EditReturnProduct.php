<?php

namespace App\Filament\Resources\ReturnProductResource\Pages;

use App\Filament\Resources\ReturnProductResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReturnProduct extends EditRecord
{
    protected static string $resource = ReturnProductResource::class;

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
