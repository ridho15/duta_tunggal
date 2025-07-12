<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class ProdukTerlarisChart extends ChartWidget
{
    protected static ?string $heading = 'Produk Terlaris';

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
