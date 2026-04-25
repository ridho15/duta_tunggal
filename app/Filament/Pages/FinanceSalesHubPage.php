<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class FinanceSalesHubPage extends Page
{
    protected static string $view = 'filament.pages.finance-sales-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Keuangan Penjualan';

    protected static ?string $navigationLabel = 'Pusat Keuangan Penjualan';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'finance-sales-hub';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}