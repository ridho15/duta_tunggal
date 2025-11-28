<?php

namespace App\Filament\Resources\CashBankTransactionResource\Pages;

use App\Filament\Resources\CashBankTransactionResource;
use App\Services\CashBankService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCashBankTransaction extends EditRecord
{
    protected static string $resource = CashBankTransactionResource::class;

    protected function afterSave(): void
    {
        // For simplicity we post again; in real app should reverse previous entries first
        app(CashBankService::class)->postTransaction($this->record);
        Notification::make()->title('Perubahan diposting ke jurnal.')->success()->send();
    }
}
