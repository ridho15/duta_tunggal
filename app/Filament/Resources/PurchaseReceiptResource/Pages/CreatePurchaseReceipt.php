<?php

namespace App\Filament\Resources\PurchaseReceiptResource\Pages;

use App\Filament\Resources\PurchaseReceiptResource;
use App\Services\PurchaseReceiptService;
use Filament\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseReceipt extends CreateRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data;
    }
}
