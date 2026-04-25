<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class FinancePurchaseHubPage extends Page
{
    protected static string $view = 'filament.pages.finance-purchase-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Keuangan Pembelian';

    protected static ?string $navigationLabel = 'Pusat Keuangan Pembelian';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'finance-purchase-hub';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}