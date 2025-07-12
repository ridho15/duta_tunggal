<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class Penjualan7HariChart extends ChartWidget
{
    protected static ?string $heading = 'Penjualan 7 Hari';

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
