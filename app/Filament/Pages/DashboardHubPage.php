<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class DashboardHubPage extends Page
{
    protected static string $view = 'filament.pages.dashboard-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Dashboard';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'dashboard-hub';
}