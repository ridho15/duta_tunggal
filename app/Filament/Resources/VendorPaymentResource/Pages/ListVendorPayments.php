<?php

namespace App\Filament\Resources\VendorPaymentResource\Pages;

use App\Filament\Resources\VendorPaymentResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorPayments extends ListRecords
{
    protected static string $resource = VendorPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
