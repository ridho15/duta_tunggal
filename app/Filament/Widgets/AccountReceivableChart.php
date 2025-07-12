<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class AccountReceivableChart extends ChartWidget
{
    protected static ?string $heading = 'Account Receivable';

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
