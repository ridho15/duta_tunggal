<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class AccountPayableChart extends ChartWidget
{
    protected static ?string $heading = 'Account Payable';

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
