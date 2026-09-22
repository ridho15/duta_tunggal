<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PaymentHubPage extends Page
{
    protected static string $view = 'filament.pages.payment-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Pembayaran Keuangan';

    protected static ?string $navigationLabel = 'Pembayaran';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'payment-hub';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}