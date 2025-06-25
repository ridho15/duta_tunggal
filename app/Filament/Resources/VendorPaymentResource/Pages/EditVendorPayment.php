<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVendorPayment extends EditRecord
{
    protected static string $resource = VendorPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
