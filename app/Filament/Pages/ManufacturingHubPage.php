<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class ManufacturingHubPage extends Page
{
    protected static string $view = 'filament.pages.manufacturing-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = 'Manufaktur';

    protected static ?string $navigationLabel = 'Pusat Manufaktur';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'manufacturing-hub';
}