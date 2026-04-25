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

function staticPropertyValue(string $class, string $property): mixed
{
    $reflection = new ReflectionClass($class);
    $prop = $reflection->getProperty($property);
    $prop->setAccessible(true);

    return $prop->getValue();
}

test('finance report hub page is configured as the parent menu', function () {
    expect(staticPropertyValue(FinanceReportHubPage::class, 'navigationGroup'))->toBe('Laporan Keuangan')
        ->and(staticPropertyValue(FinanceReportHubPage::class, 'navigationLabel'))->toBe('Laporan Keuangan')
        ->and(FinanceReportHubPage::getUrl())->toContain('/admin/finance-reports');
});

test('finance report navigation items are nested under the finance hub', function () {
    $children = [
        BukuBesarPage::class,
        TrialBalancePage::class,
        FinancialStatementPage::class,
        DrillDownFinancialReportPage::class,
        ProfitLossMultiDivisionPage::class,
        CostOfGoodsManufacturingPage::class,
        AlkGraficPage::class,
        JournalConsolidationPage::class,
        BalanceSheetResource::class,
        ProfitAndLossResource::class,
        CashFlowResource::class,
        HppResource::class,
        AgeingReportResource::class,
        StockMutationReportResource::class,
    ];

    foreach ($children as $child) {
        expect(staticPropertyValue($child, 'navigationParentItem'))->toBe('Laporan Keuangan');
    }
});

test('detailed finance reports are hidden from sidebar and only exposed through the finance hub', function () {
    foreach ([
        BukuBesarPage::class,
        TrialBalancePage::class,
        FinancialStatementPage::class,
        DrillDownFinancialReportPage::class,
        ProfitLossMultiDivisionPage::class,
        CostOfGoodsManufacturingPage::class,
        AlkGraficPage::class,
        JournalConsolidationPage::class,
    ] as $pageClass) {
        expect($pageClass::shouldRegisterNavigation())->toBeFalse();
    }

    foreach ([
        BalanceSheetResource::class,
        ProfitAndLossResource::class,
        CashFlowResource::class,
        HppResource::class,
        AgeingReportResource::class,
        StockMutationReportResource::class,
    ] as $resourceClass) {
        expect(staticPropertyValue($resourceClass, 'shouldRegisterNavigation'))->toBeFalse();
    }
});

test('legacy duplicate report navigation entries are hidden from sidebar', function () {
    expect(IncomeStatementPage::shouldRegisterNavigation())->toBeFalse()
        ->and(ViewAgeingReport::shouldRegisterNavigation())->toBeFalse();
});