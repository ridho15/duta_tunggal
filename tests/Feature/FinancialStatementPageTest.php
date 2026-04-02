<?php

use App\Filament\Pages\FinancialStatementPage;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\BalanceSheetService;

beforeEach(function () {
    $date = now()->format('Y-m-d');

    // Revenue account
    $this->revenue = ChartOfAccount::create([
        'code' => '4000',
        'name' => 'Penjualan',
        'type' => 'Revenue',
        'is_active' => true,
    ]);

    // COGS account (matches 'HPP' pattern)
    $this->cogs_account = ChartOfAccount::create([
        'code' => '5100',
        'name' => 'HPP Barang Jadi',
        'type' => 'Expense',
        'is_active' => true,
    ]);

    // OPEX account (no HPP/Pokok in name)
    $this->opex_account = ChartOfAccount::create([
        'code' => '5200',
        'name' => 'Beban Gaji',
        'type' => 'Expense',
        'is_active' => true,
    ]);

    // Asset account (current)
    $this->asset = ChartOfAccount::create([
        'code' => '1100',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_current' => true,
        'is_active' => true,
    ]);

    // Equity account
    $this->equity_account = ChartOfAccount::create([
        'code' => '3000',
        'name' => 'Modal',
        'type' => 'Equity',
        'is_active' => true,
    ]);

    // Journal entries
    JournalEntry::create([
        'coa_id' => $this->revenue->id,
        'date' => $date,
        'reference' => 'REV-001',
        'description' => 'Sales',
        'debit' => 0,
        'credit' => 10_000_000,
        'source_type' => 'manual',
        'source_id' => 1,
    ]);

    JournalEntry::create([
        'coa_id' => $this->cogs_account->id,
        'date' => $date,
        'reference' => 'COGS-001',
        'description' => 'Cost of Goods',
        'debit' => 4_000_000,
        'credit' => 0,
        'source_type' => 'manual',
        'source_id' => 1,
    ]);

    JournalEntry::create([
        'coa_id' => $this->opex_account->id,
        'date' => $date,
        'reference' => 'OPEX-001',
        'description' => 'Operating Expense',
        'debit' => 2_000_000,
        'credit' => 0,
        'source_type' => 'manual',
        'source_id' => 1,
    ]);

    JournalEntry::create([
        'coa_id' => $this->asset->id,
        'date' => $date,
        'reference' => 'ASSET-001',
        'description' => 'Cash inflow',
        'debit' => 10_000_000,
        'credit' => 0,
        'source_type' => 'manual',
        'source_id' => 1,
    ]);

    JournalEntry::create([
        'coa_id' => $this->equity_account->id,
        'date' => $date,
        'reference' => 'EQ-001',
        'description' => 'Capital',
        'debit' => 0,
        'credit' => 4_000_000,
        'source_type' => 'manual',
        'source_id' => 1,
    ]);
});

function makePage(string $type = 'all'): FinancialStatementPage
{
    $page = new FinancialStatementPage();
    $page->boot(app(BalanceSheetService::class));
    $page->showPreview = true;
    $page->start_date = now()->startOfMonth()->format('Y-m-d');
    $page->end_date = now()->endOfMonth()->format('Y-m-d');
    $page->statement_type = $type;
    return $page;
}

it('returns empty array when showPreview is false', function () {
    $page = new FinancialStatementPage();
    $page->showPreview = false;

    expect($page->getStatementData())->toBe([]);
});

it('calculates revenue correctly', function () {
    $data = makePage('pl')->getStatementData();

    expect($data['pl']['revenue'])->toBe(10_000_000.0);
});

it('calculates COGS from HPP-named expense accounts only', function () {
    $data = makePage('pl')->getStatementData();

    // HPP account = 4m debit, Beban Gaji = 2m debit
    expect($data['pl']['cogs'])->toBe(4_000_000.0);
});

it('calculates gross profit as revenue minus COGS', function () {
    $data = makePage('pl')->getStatementData();

    // 10m - 4m = 6m
    expect($data['pl']['gross_profit'])->toBe(6_000_000.0);
});

it('calculates OPEX excluding COGS', function () {
    $data = makePage('pl')->getStatementData();

    // Total expense (6m) - COGS (4m) = 2m
    expect($data['pl']['opex'])->toBe(2_000_000.0);
});

it('calculates net profit correctly', function () {
    $data = makePage('pl')->getStatementData();

    // gross_profit (6m) - opex (2m) = 4m
    expect($data['pl']['net_profit'])->toBe(4_000_000.0);
});

it('does NOT include non-Expense accounts even when name contains Pokok', function () {
    // Asset account with "Pokok" in name — must not be counted as COGS
    $fakeAsset = ChartOfAccount::create([
        'code' => '1900',
        'name' => 'Pokok Pinjaman Aset',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    JournalEntry::create([
        'coa_id' => $fakeAsset->id,
        'date' => now()->format('Y-m-d'),
        'reference' => 'FAKE-001',
        'description' => 'Asset with Pokok in name',
        'debit' => 999_000,
        'credit' => 0,
        'source_type' => 'manual',
        'source_id' => 2,
    ]);

    $data = makePage('pl')->getStatementData();

    // COGS must remain 4m regardless of the Asset account
    expect($data['pl']['cogs'])->toBe(4_000_000.0);
});

it('returns balance sheet data structure', function () {
    $data = makePage('bs')->getStatementData();

    expect($data)->toHaveKey('bs');
    expect($data['bs'])->toHaveKey('total_assets');
    expect($data['bs'])->toHaveKey('total_liabilities');
    expect($data['bs'])->toHaveKey('total_equity');
    expect($data['bs'])->toHaveKey('is_balanced');
    expect($data['bs'])->toHaveKey('current_assets');
    expect($data['bs'])->toHaveKey('equity');
});

it('returns both pl and bs when statement_type is all', function () {
    $data = makePage('all')->getStatementData();

    expect($data)->toHaveKey('pl');
    expect($data)->toHaveKey('bs');
});

it('asset total_assets reflects debit journal entries', function () {
    $data = makePage('bs')->getStatementData();

    // Asset account has 10m debit
    expect($data['bs']['total_assets'])->toBe(10_000_000.0);
});

it('generateReport sets showPreview to true', function () {
    $page = new FinancialStatementPage();
    $page->showPreview = false;
    $page->generateReport();

    expect($page->showPreview)->toBeTrue();
});

it('resetReport sets showPreview to false', function () {
    $page = new FinancialStatementPage();
    $page->showPreview = true;
    $page->resetReport();

    expect($page->showPreview)->toBeFalse();
});
