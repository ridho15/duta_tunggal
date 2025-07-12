<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class PenjualanPerKategoriChart extends ChartWidget
{
    protected static ?string $heading = 'Penjualan Per Kategori';

    protected function getData(): array
    {
        return [
            //
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
