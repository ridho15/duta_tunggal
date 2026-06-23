<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class SalesHubPage extends Page
{
    protected static string $view = 'filament.pages.sales-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Penjualan';

    protected static ?string $navigationLabel = 'Penjualan';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'sales-hub';
}