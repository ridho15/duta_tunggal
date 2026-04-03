<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class WarehouseHubPage extends Page
{
    protected static string $view = 'filament.pages.warehouse-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Gudang';

    protected static ?string $navigationLabel = 'Pusat Gudang';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'warehouse-hub';
}