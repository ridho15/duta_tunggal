<?php

namespace App\Filament\Resources\CashBankTransferResource\Pages;

use App\Filament\Resources\CashBankTransferResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListCashBankTransfers extends ListRecords
{
    protected static string $resource = CashBankTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
