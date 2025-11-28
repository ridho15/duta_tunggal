<?php

namespace App\Filament\Resources\VoucherRequestResource\Pages;

use App\Filament\Resources\VoucherRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVoucherRequest extends EditRecord
{
    protected static string $resource = VoucherRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
