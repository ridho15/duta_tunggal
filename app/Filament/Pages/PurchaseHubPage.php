<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PurchaseHubPage extends Page
{
    protected static string $view = 'filament.pages.purchase-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Pembelian';

    protected static ?string $navigationLabel = 'Pusat Pembelian';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'purchase-hub';
}