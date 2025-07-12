<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class PenjualanIntervalChart extends ChartWidget
{
    protected static ?string $heading = 'Penjualan Interval';

    protected function getData(): array
    {
        return [
            //
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
