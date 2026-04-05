<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AssetManagementHubPage extends Page
{
    protected static string $view = 'filament.pages.asset-management-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Asset Management';

    protected static ?string $navigationLabel = 'Pusat Manajemen Aset';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'asset-management-hub';
}