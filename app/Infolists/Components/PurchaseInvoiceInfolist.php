<?php

namespace App\Infolists\Components;

use Filament\Infolists\Components\Component;

class PurchaseInvoiceInfolist extends Component
{
    protected string $view = 'infolists.components.purchase-invoice-infolist';

    public static function make(): static
    {
        return app(static::class);
    }
}
