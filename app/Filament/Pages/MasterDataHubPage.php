<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class MasterDataHubPage extends Page
{
    protected static string $view = 'filament.pages.master-data-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';

    protected static ?string $navigationGroup = 'Master Data';

    protected static ?string $navigationLabel = 'Data Master';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'master-data-hub';
}