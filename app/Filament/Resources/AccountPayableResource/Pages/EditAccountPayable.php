<?php

namespace App\Filament\Resources\AccountPayableResource\Pages;

use App\Filament\Resources\AccountPayableResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAccountPayable extends EditRecord
{
    protected static string $resource = AccountPayableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
