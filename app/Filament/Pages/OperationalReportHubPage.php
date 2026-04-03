<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class OperationalReportHubPage extends Page
{
    protected static string $view = 'filament.pages.operational-report-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Reports';

    protected static ?string $navigationLabel = 'Laporan Operasional';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'operational-reports';
}