<?php

namespace App\Filament\Widgets;

use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    public function fetchEvents(array $fetchInfo): array
    {
        return [];
    }

    public function getFormSchema(): array
    {
        return [
            Grid::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    DatePicker::make('tanggalMulai')
                        ->label('Tanggal Mulai')
                        ->default(Carbon::now()->subDays(7)),
                    DatePicker::make('tanggalAkhir')
                        ->label('Tanggal Akhir')
                        ->default(Carbon::now())
                ])
        ];
    }
}
