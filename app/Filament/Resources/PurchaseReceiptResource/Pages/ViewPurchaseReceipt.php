<?php

namespace App\Filament\Resources\PurchaseReceiptResource\Pages;

use App\Filament\Resources\PurchaseReceiptResource;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseReceipt extends ViewRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square')
        ];
    }
}
