<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class FinanceReportHubPage extends Page
{
    protected static string $view = 'filament.pages.finance-report-hub-page';

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationGroup = 'Laporan Keuangan';

    protected static ?string $navigationLabel = 'Laporan Keuangan';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'finance-reports';
}