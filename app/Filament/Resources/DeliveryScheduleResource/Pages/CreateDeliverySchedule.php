<?php

namespace App\Filament\Resources\DeliveryScheduleResource\Pages;

use App\Filament\Resources\DeliveryScheduleResource;
use App\Services\DeliveryScheduleService;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliverySchedule extends CreateRecord
{
    protected static string $resource = DeliveryScheduleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validasi pengirim di server (driver+kendaraan master untuk internal; nama ekspedisi untuk ekspedisi).
        app(DeliveryScheduleService::class)->validateSender($data, 'data.');

        if (empty($data['schedule_number'])) {
            $data['schedule_number'] = app(DeliveryScheduleService::class)->generateScheduleNumber();
        }

        return $data;
    }
}
