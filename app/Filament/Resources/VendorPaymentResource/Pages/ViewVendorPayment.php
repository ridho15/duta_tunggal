<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorPayment extends ViewRecord
{
    protected static string $resource = VendorPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
