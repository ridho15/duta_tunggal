<?php

namespace App\Filament\Resources\AgeingScheduleResource\Pages;

use App\Filament\Resources\AgeingScheduleResource;
use App\Models\AgeingSchedule;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAgeingSchedules extends ListRecords
{
    protected static string $resource = AgeingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->color('primary')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $listAgeingSchedule = AgeingSchedule::get();
                })
        ];
    }
}
