<?php

namespace App\Filament\Resources\AgeingScheduleResource\Pages;

use App\Filament\Resources\AgeingScheduleResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAgeingSchedule extends EditRecord
{
    protected static string $resource = AgeingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }
}
