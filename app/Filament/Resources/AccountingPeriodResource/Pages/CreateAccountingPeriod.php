<?php

namespace App\Filament\Resources\AccountingPeriodResource\Pages;

use App\Filament\Resources\AccountingPeriodResource;
use App\Models\AccountingPeriod;
use App\Services\AccountingPeriodService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAccountingPeriod extends CreateRecord
{
    protected static string $resource = AccountingPeriodResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $service = app(AccountingPeriodService::class);
            return $service->createPeriod(
                $data['period_start'],
                $data['period_end'],
                $data['cabang_id'] ?? null
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Gagal Membuat Periode')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
