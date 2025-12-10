<?php

namespace App\Filament\Resources\CashBankTransferResource\Pages;

use App\Filament\Resources\CashBankTransferResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCashBankTransfer extends ViewRecord
{
    protected static string $resource = CashBankTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->icon('heroicon-o-pencil'),
            DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }
}