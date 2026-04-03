<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class DeliveryHubPage extends Page
{
    protected static string $view = 'filament.pages.delivery-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Pengiriman';

    protected static ?string $navigationLabel = 'Pusat Pengiriman';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'delivery-hub';
}