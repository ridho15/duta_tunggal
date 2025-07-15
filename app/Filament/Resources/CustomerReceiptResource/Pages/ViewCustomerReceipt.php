<?php

namespace App\Filament\Resources\CustomerReceiptResource\Pages;

use App\Filament\Resources\CustomerReceiptResource;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerReceipt extends ViewRecord
{
    protected static string $resource = CustomerReceiptResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square')
        ];
    }
}
