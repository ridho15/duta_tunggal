<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class InventoryHubPage extends Page
{
    protected static string $view = 'filament.pages.inventory-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Pusat Inventory';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'inventory-hub';

    public static function canAccess(): bool
    {
        return true;
    }
}