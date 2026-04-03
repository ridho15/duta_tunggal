<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AccountingHubPage extends Page
{
    protected static string $view = 'filament.pages.accounting-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Akuntansi Keuangan';

    protected static ?string $navigationLabel = 'Pusat Akuntansi';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'accounting-hub';
}