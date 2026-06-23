<?php

namespace App\Filament\Resources\CustomerReturnResource\Pages;

use App\Filament\Resources\CustomerReturnResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCustomerReturn extends EditRecord
{
    protected static string $resource = CustomerReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
