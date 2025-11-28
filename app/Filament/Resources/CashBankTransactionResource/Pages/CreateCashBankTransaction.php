<?php

namespace App\Filament\Resources\CashBankTransactionResource\Pages;

use App\Filament\Resources\CashBankTransactionResource;
use App\Services\CashBankService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCashBankTransaction extends CreateRecord
{
    protected static string $resource = CashBankTransactionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['number'])) {
            $data['number'] = app(CashBankService::class)->generateNumber('CB');
        }
        return $data;
    }

    protected function afterCreate(): void
    {
        $service = app(CashBankService::class);
        $service->postTransaction($this->record);
        Notification::make()->title('Transaksi Kas/Bank diposting ke jurnal.')->success()->send();
    }
}
