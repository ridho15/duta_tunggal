<?php

namespace App\Filament\Resources\CashBankTransactionResource\Pages;

use App\Filament\Resources\CashBankTransactionResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListCashBankTransactions extends ListRecords
{
    protected static string $resource = CashBankTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
