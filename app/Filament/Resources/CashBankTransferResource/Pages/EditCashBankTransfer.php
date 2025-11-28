<?php

namespace App\Filament\Resources\CashBankTransferResource\Pages;

use App\Filament\Resources\CashBankTransferResource;
use App\Services\CashBankService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCashBankTransfer extends EditRecord
{
    protected static string $resource = CashBankTransferResource::class;

    protected function afterSave(): void
    {
        app(CashBankService::class)->postTransfer($this->record);
        Notification::make()->title('Perubahan transfer diposting ke jurnal.')->success()->send();
    }
}
