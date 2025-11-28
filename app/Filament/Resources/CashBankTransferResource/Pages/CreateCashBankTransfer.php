<?php

namespace App\Filament\Resources\CashBankTransferResource\Pages;

use App\Filament\Resources\CashBankTransferResource;
use App\Services\CashBankService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCashBankTransfer extends CreateRecord
{
    protected static string $resource = CashBankTransferResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['number'])) {
            $data['number'] = app(CashBankService::class)->generateTransferNumber();
        }
        return $data;
    }

    protected function afterCreate(): void
    {
        app(CashBankService::class)->postTransfer($this->record);
        Notification::make()->title('Transfer diposting ke jurnal.')->success()->send();
    }
}
