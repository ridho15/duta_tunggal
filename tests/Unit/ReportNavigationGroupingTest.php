<?php

use App\Filament\Pages\AlkGraficPage;
use App\Filament\Pages\BukuBesarPage;
use App\Filament\Pages\CostOfGoodsManufacturingPage;
use App\Filament\Pages\DrillDownFinancialReportPage;
use App\Filament\Pages\FinancialStatementPage;
use App\Filament\Pages\FinanceReportHubPage;
use App\Filament\Pages\IncomeStatementPage;
use App\Filament\Pages\JournalConsolidationPage;
use App\Filament\Pages\ProfitLossMultiDivisionPage;
use App\Filament\Pages\TrialBalancePage;
use App\Filament\Pages\ViewAgeingReport;
use App\Filament\Resources\Reports\AgeingReportResource;
use App\Filament\Resources\Reports\BalanceSheetResource;
use App\Filament\Resources\Reports\CashFlowResource;
use App\Filament\Resources\Reports\HppResource;
use App\Filament\Resources\Reports\ProfitAndLossResource;
use App\Filament\Resources\Reports\StockMutationReportResource;

if (! function_exists('staticPropertyValue')) {
    function staticPropertyValue(string $class, string $property): mixed
    {
        $reflection = new ReflectionClass($class);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue();
    }
}

test('finance report hub page is hidden from navigation in Tahap 1', function () {
    expect(FinanceReportHubPage::shouldRegisterNavigation())->toBeFalse();
});

test('financial statement page is registered under LAPORAN group', function () {
    expect(FinancialStatementPage::shouldRegisterNavigation())->toBeTrue()
        ->and(staticPropertyValue(FinancialStatementPage::class, 'navigationGroup'))->toBe('LAPORAN')
        ->and(staticPropertyValue(FinancialStatementPage::class, 'navigationLabel'))->toBe('Laporan Keuangan')
        ->and(staticPropertyValue(FinancialStatementPage::class, 'navigationSort'))->toBe(5)
        ->and(staticPropertyValue(FinancialStatementPage::class, 'navigationIcon'))->toBe('heroicon-o-document-chart-bar');
});

test('detailed finance sub-reports are hidden from sidebar navigation', function () {
    foreach ([
        BukuBesarPage::class,
        TrialBalancePage::class,
        DrillDownFinancialReportPage::class,
        ProfitLossMultiDivisionPage::class,
        CostOfGoodsManufacturingPage::class,
        AlkGraficPage::class,
        JournalConsolidationPage::class,
    ] as $pageClass) {
        expect($pageClass::shouldRegisterNavigation())->toBeFalse("Expected {$pageClass} to be hidden from navigation");
    }

    foreach ([
        BalanceSheetResource::class,
        ProfitAndLossResource::class,
        CashFlowResource::class,
        HppResource::class,
        AgeingReportResource::class,
        StockMutationReportResource::class,
    ] as $resourceClass) {
        expect(staticPropertyValue($resourceClass, 'shouldRegisterNavigation'))
            ->toBeFalse("Expected {$resourceClass} to be hidden from navigation");
    }
});

test('legacy duplicate report navigation entries are hidden from sidebar', function () {
    expect(IncomeStatementPage::shouldRegisterNavigation())->toBeFalse()
        ->and(ViewAgeingReport::shouldRegisterNavigation())->toBeFalse();
});