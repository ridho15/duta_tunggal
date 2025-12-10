<?php

namespace App\Filament\Resources\CashBankTransactionResource\Pages;

use App\Filament\Resources\CashBankTransactionResource;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCashBankTransaction extends ViewRecord
{
    protected static string $resource = CashBankTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon('heroicon-o-pencil')
        ];
    }
}
