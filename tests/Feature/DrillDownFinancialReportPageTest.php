<?php

use App\Filament\Pages\DrillDownFinancialReportPage;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;

beforeEach(function () {
    $date = now()->format('Y-m-d');

    $this->revenueCoa = ChartOfAccount::create([
        'code' => '4000',
        'name' => 'Penjualan',
        'type' => 'Revenue',
        'is_active' => true,
    ]);

    $this->expenseCoa = ChartOfAccount::create([
        'code' => '5100',
        'name' => 'Beban Operasional',
        'type' => 'Expense',
        'is_active' => true,
    ]);

    JournalEntry::create([
        'coa_id' => $this->revenueCoa->id,
        'date' => $date,
        'reference' => 'REV-DD-001',
        'description' => 'Revenue entry',
        'debit' => 0,
        'credit' => 5_000_000,
        'source_type' => 'manual',
        'source_id' => 1,
    ]);

    JournalEntry::create([
        'coa_id' => $this->expenseCoa->id,
        'date' => $date,
        'reference' => 'EXP-DD-001',
        'description' => 'Expense entry',
        'debit' => 2_000_000,
        'credit' => 0,
        'source_type' => 'manual',
        'source_id' => 1,
    ]);
});

function makeDrillPage(): DrillDownFinancialReportPage
{
    $page = new DrillDownFinancialReportPage();
    $page->showPreview = true;
    $page->start_date = now()->startOfMonth()->format('Y-m-d');
    $page->end_date = now()->endOfMonth()->format('Y-m-d');
    return $page;
}

it('returns empty data when showPreview is false', function () {
    $page = new DrillDownFinancialReportPage();
    $page->showPreview = false;

    $data = $page->getDrillDownData();

    expect($data)->toBe([]);
});

it('returns grouped journal entries across all accounts', function () {
    $data = makeDrillPage()->getDrillDownData();

    expect($data['count'])->toBe(2);
    expect($data['grouped'])->toHaveCount(2);
});

it('filters entries by account_type', function () {
    $page = makeDrillPage();
    $page->account_type = 'Revenue';

    $data = $page->getDrillDownData();

    expect($data['count'])->toBe(1);
    expect($data['grouped'][0]['coa']->type)->toBe('Revenue');
});

it('filters entries by specific coa_id', function () {
    $page = makeDrillPage();
    $page->coa_id = $this->expenseCoa->id;

    $data = $page->getDrillDownData();

    expect($data['count'])->toBe(1);
    expect($data['grouped'][0]['coa']->id)->toBe($this->expenseCoa->id);
});

it('calculates correct debit and credit totals', function () {
    $data = makeDrillPage()->getDrillDownData();

    expect($data['total_debit'])->toBe(2_000_000.0);
    expect($data['total_credit'])->toBe(5_000_000.0);
});

it('calculates balance as debit minus credit for Expense accounts', function () {
    $page = makeDrillPage();
    $page->account_type = 'Expense';

    $data = $page->getDrillDownData();

    // Expense: normal debit balance = debit - credit = 2m - 0 = 2m
    expect($data['grouped'][0]['balance'])->toBe(2_000_000.0);
});

it('calculates balance as credit minus debit for Revenue accounts', function () {
    $page = makeDrillPage();
    $page->account_type = 'Revenue';

    $data = $page->getDrillDownData();

    // Revenue: normal credit balance = credit - debit = 5m - 0 = 5m
    expect($data['grouped'][0]['balance'])->toBe(5_000_000.0);
});

it('updatedAccountType resets coa_id', function () {
    $page = new DrillDownFinancialReportPage();
    $page->coa_id = $this->revenueCoa->id;
    $page->account_type = 'Expense';

    $page->updatedAccountType();

    expect($page->coa_id)->toBeNull();
});

it('coaOptions returns all accounts when no type filter', function () {
    $page = new DrillDownFinancialReportPage();
    $page->account_type = null;

    $options = $page->getCoaOptionsProperty();

    expect($options)->toHaveCount(ChartOfAccount::count());
});

it('coaOptions filters by account_type', function () {
    $page = new DrillDownFinancialReportPage();
    $page->account_type = 'Revenue';

    $options = $page->getCoaOptionsProperty();

    expect(array_keys($options))->toContain($this->revenueCoa->id);
    expect(array_keys($options))->not()->toContain($this->expenseCoa->id);
});

it('generateReport sets showPreview to true', function () {
    $page = new DrillDownFinancialReportPage();
    $page->showPreview = false;
    $page->generateReport();

    expect($page->showPreview)->toBeTrue();
});

it('resetReport sets showPreview to false', function () {
    $page = new DrillDownFinancialReportPage();
    $page->showPreview = true;
    $page->resetReport();

    expect($page->showPreview)->toBeFalse();
});
