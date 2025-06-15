<?php

namespace App\Filament\Resources\PurchaseReceiptItemResource\Pages;

use App\Filament\Resources\PurchaseReceiptItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseReceiptItem extends EditRecord
{
    protected static string $resource = PurchaseReceiptItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
