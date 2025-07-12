<?php

namespace App\Filament\Widgets;

use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    protected static string $view = 'filament.widgets.calendar-widget';

    public function fetchEvents(array $fetchInfo): array
    {
        return [];
    }
}
