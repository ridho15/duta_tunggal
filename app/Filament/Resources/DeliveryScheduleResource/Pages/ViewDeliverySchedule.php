<?php

namespace App\Filament\Resources\DeliveryScheduleResource\Pages;

use App\Filament\Resources\DeliveryScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDeliverySchedule extends ViewRecord
{
    protected static string $resource = DeliveryScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }
}
