<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class TopCustomerChart extends ChartWidget
{
    protected static ?string $heading = 'Top Customer';

    protected function getData(): array
    {
        return [
            //
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
