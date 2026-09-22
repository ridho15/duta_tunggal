<?php

use App\Filament\Pages\DrillDownFinancialReportPage;

it('returns empty data when showPreview is false', function () {
    $page = new DrillDownFinancialReportPage();
    $page->showPreview = false;

    $data = $page->getDrillDownData();

    expect($data)->toBe([]);
});

it('updatedAccountType resets coa_id', function () {
    $page = new DrillDownFinancialReportPage();
    $page->coa_id = 123;
    $page->account_type = 'Expense';

    $page->updatedAccountType();

    expect($page->coa_id)->toBeNull();
});

it('generateReport builds a preview url without toggling inline preview', function () {
    $page = new DrillDownFinancialReportPage();
    $page->showPreview = false;
    $page->generateReport();

    expect($page->showPreview)->toBeFalse()
    ->and($page->getPreviewUrl())->toContain('/reports/drill-down-financial-report/preview');
});

it('resetReport sets showPreview to false', function () {
    $page = new DrillDownFinancialReportPage();
    $page->showPreview = true;
    $page->resetReport();

    expect($page->showPreview)->toBeFalse();
});

it('returns financial statement data when financial statement mode is selected', function () {
    $page = new DrillDownFinancialReportPage();
    $page->showPreview = true;
    $page->start_date = '2026-04-01';
    $page->end_date = '2026-04-30';
    $page->report_mode = 'financial_statement';
    $page->statement_type = 'all';

    $data = $page->getFinancialStatementData();

    expect($data)->toBeArray();
});

it('financial statement preview url includes report mode and statement type', function () {
    $page = new DrillDownFinancialReportPage();
    $page->start_date = '2026-04-01';
    $page->end_date = '2026-04-30';
    $page->report_mode = 'financial_statement';
    $page->statement_type = 'bs';

    expect($page->getPreviewUrl())
        ->toContain('/reports/financial-statement/preview')
        ->toContain('statement_type=bs');
});
