<?php

namespace App\Filament\Resources\DeliveryScheduleResource\Pages;

use App\Filament\Resources\DeliveryScheduleResource;
use App\Services\DeliveryScheduleService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDeliverySchedule extends EditRecord
{
    protected static string $resource = DeliveryScheduleResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        app(DeliveryScheduleService::class)->validateSender($data, 'data.');

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->label('Lihat Jadwal'),
            DeleteAction::make()->icon('heroicon-o-trash')->label('Hapus Jadwal'),
        ];
    }
}
