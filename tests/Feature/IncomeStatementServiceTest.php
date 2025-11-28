<?php

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use App\Services\IncomeStatementService;

beforeEach(function () {
    $this->service = new IncomeStatementService();
});

test('service can generate basic income statement', function () {
    // Create Revenue account
    $revenueAccount = ChartOfAccount::factory()->create([
        'type' => 'Revenue',
        'code' => '4-1000',
        'name' => 'Pendapatan Penjualan',
        'is_active' => true,
    ]);

    // Create Expense account
    $expenseAccount = ChartOfAccount::factory()->create([
        'type' => 'Expense',
        'code' => '6-1000',
        'name' => 'Beban Gaji',
        'is_active' => true,
    ]);

    // Create journal entries
    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 1000000, // Revenue increases with credit
        'journal_type' => 'sales',
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expenseAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 300000, // Expense increases with debit
        'credit' => 0,
        'journal_type' => 'manual',
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['revenue']['total'])->toEqual(1000000.0);
    expect($result['expense']['total'])->toEqual(300000.0);
    expect($result['net_income'])->toEqual(700000.0);
    expect($result['is_profit'])->toBeTrue();
});

test('service correctly calculates revenue from credit entries', function () {
    $revenueAccount = ChartOfAccount::factory()->create([
        'type' => 'Revenue',
        'code' => '4-2000',
        'name' => 'Pendapatan Jasa',
    ]);

    // Revenue: Credit = increases, Debit = decreases
    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 500000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 100000, // Sales return (debit decreases revenue)
        'credit' => 0,
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    // Net revenue = 500000 (credit) - 100000 (debit) = 400000
    expect($result['revenue']['total'])->toEqual(400000.0);
});

test('service correctly calculates expense from debit entries', function () {
    $expenseAccount = ChartOfAccount::factory()->create([
        'type' => 'Expense',
        'code' => '6-2000',
        'name' => 'Beban Listrik',
    ]);

    // Expense: Debit = increases, Credit = decreases
    JournalEntry::factory()->create([
        'coa_id' => $expenseAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 200000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expenseAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 50000, // Expense reversal (credit decreases expense)
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    // Net expense = 200000 (debit) - 50000 (credit) = 150000
    expect($result['expense']['total'])->toEqual(150000.0);
});

test('service filters entries by date range', function () {
    $revenueAccount = ChartOfAccount::factory()->create(['type' => 'Revenue']);

    // Entry within range
    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => '2024-01-15',
        'debit' => 0,
        'credit' => 1000000,
    ]);

    // Entry outside range
    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => '2024-02-15',
        'debit' => 0,
        'credit' => 500000,
    ]);

    $result = $this->service->generate([
        'start_date' => '2024-01-01',
        'end_date' => '2024-01-31',
    ]);

    expect($result['revenue']['total'])->toEqual(1000000.0);
});

test('service filters entries by cabang', function () {
    $cabang1 = Cabang::factory()->create(['kode' => 'JKT', 'nama' => 'Jakarta']);
    $cabang2 = Cabang::factory()->create(['kode' => 'BDG', 'nama' => 'Bandung']);

    $revenueAccount = ChartOfAccount::factory()->create(['type' => 'Revenue']);

    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 1000000,
        'cabang_id' => $cabang1->id,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 500000,
        'cabang_id' => $cabang2->id,
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
        'cabang_id' => $cabang1->id,
    ]);

    expect($result['revenue']['total'])->toEqual(1000000.0);
});

test('service calculates net income as profit when revenue exceeds expense', function () {
    $revenueAccount = ChartOfAccount::factory()->create(['type' => 'Revenue']);
    $expenseAccount = ChartOfAccount::factory()->create(['type' => 'Expense']);

    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 2000000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expenseAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 800000,
        'credit' => 0,
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['net_income'])->toEqual(1200000.0);
    expect($result['is_profit'])->toBeTrue();
});

test('service calculates net income as loss when expense exceeds revenue', function () {
    $revenueAccount = ChartOfAccount::factory()->create(['type' => 'Revenue']);
    $expenseAccount = ChartOfAccount::factory()->create(['type' => 'Expense']);

    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 500000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expenseAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 800000,
        'credit' => 0,
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['net_income'])->toEqual(-300000.0);
    expect($result['is_profit'])->toBeFalse();
});

test('service provides summary statistics', function () {
    $revenueAccount = ChartOfAccount::factory()->create(['type' => 'Revenue']);
    $expenseAccount = ChartOfAccount::factory()->create(['type' => 'Expense']);

    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 1000000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expenseAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 400000,
        'credit' => 0,
    ]);

    $summary = $this->service->getSummary([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($summary['total_revenue'])->toEqual(1000000.0);
    expect($summary['total_expense'])->toEqual(400000.0);
    expect($summary['net_income'])->toEqual(600000.0);
    expect($summary['is_profit'])->toBeTrue();
    expect($summary['revenue_accounts_count'])->toBe(1);
    expect($summary['expense_accounts_count'])->toBe(1);
    expect($summary['profit_margin'])->toEqual(60.0); // (600000 / 1000000) * 100
});

test('service handles multiple revenue accounts', function () {
    $revenue1 = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-1000', 'name' => 'Penjualan Produk']);
    $revenue2 = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-2000', 'name' => 'Penjualan Jasa']);

    JournalEntry::factory()->create([
        'coa_id' => $revenue1->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 2000000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $revenue2->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 1500000,
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['revenue']['total'])->toEqual(3500000.0);
    expect($result['revenue']['accounts'])->toHaveCount(2);
});

test('service handles multiple expense accounts', function () {
    $expense1 = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6-1000', 'name' => 'Beban Gaji']);
    $expense2 = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6-2000', 'name' => 'Beban Sewa']);
    $expense3 = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6-3000', 'name' => 'Beban Utilitas']);

    JournalEntry::factory()->create([
        'coa_id' => $expense1->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 500000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expense2->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 300000,
        'credit' => 0,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expense3->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 150000,
        'credit' => 0,
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['expense']['total'])->toEqual(950000.0);
    expect($result['expense']['accounts'])->toHaveCount(3);
});

test('service excludes inactive accounts', function () {
    $activeRevenue = ChartOfAccount::factory()->create(['type' => 'Revenue', 'is_active' => true]);
    $inactiveRevenue = ChartOfAccount::factory()->create(['type' => 'Revenue', 'is_active' => false]);

    JournalEntry::factory()->create([
        'coa_id' => $activeRevenue->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 1000000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $inactiveRevenue->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 500000,
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['revenue']['total'])->toEqual(1000000.0);
});

test('service excludes accounts with zero balance', function () {
    $revenue1 = ChartOfAccount::factory()->create(['type' => 'Revenue']);
    $revenue2 = ChartOfAccount::factory()->create(['type' => 'Revenue']);

    JournalEntry::factory()->create([
        'coa_id' => $revenue1->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 1000000,
    ]);

    // Revenue2 has no entries (zero balance)

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['revenue']['accounts'])->toHaveCount(1);
});

test('service can compare two periods', function () {
    $revenueAccount = ChartOfAccount::factory()->create(['type' => 'Revenue']);
    $expenseAccount = ChartOfAccount::factory()->create(['type' => 'Expense']);

    // Current period
    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => '2024-02-15',
        'debit' => 0,
        'credit' => 2000000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expenseAccount->id,
        'date' => '2024-02-15',
        'debit' => 800000,
        'credit' => 0,
    ]);

    // Previous period
    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => '2024-01-15',
        'debit' => 0,
        'credit' => 1500000,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expenseAccount->id,
        'date' => '2024-01-15',
        'debit' => 600000,
        'credit' => 0,
    ]);

    $comparison = $this->service->comparePeriods(
        ['start_date' => '2024-02-01', 'end_date' => '2024-02-29'],
        ['start_date' => '2024-01-01', 'end_date' => '2024-01-31'],
    );

    expect($comparison['current']['revenue']['total'])->toEqual(2000000.0);
    expect($comparison['previous']['revenue']['total'])->toEqual(1500000.0);
    expect($comparison['changes']['revenue']['amount'])->toEqual(500000.0);
    expect($comparison['changes']['revenue']['percentage'])->toBeGreaterThan(33.0);
});

test('service validates COA classification', function () {
    ChartOfAccount::factory()->create(['type' => 'Revenue']);
    ChartOfAccount::factory()->create(['type' => 'Expense']);

    $validation = $this->service->validateCOAClassification();

    expect($validation['is_valid'])->toBeTrue();
    expect($validation['revenue_accounts'])->toBeGreaterThan(0);
    expect($validation['expense_accounts'])->toBeGreaterThan(0);
});

test('service detects missing revenue accounts', function () {
    // Only create expense, no revenue
    ChartOfAccount::factory()->create(['type' => 'Expense']);

    $validation = $this->service->validateCOAClassification();

    expect($validation['is_valid'])->toBeFalse();
    expect($validation['issues'])->toContain('Tidak ada akun Revenue. Pastikan ada akun pendapatan di COA.');
});

test('service detects missing expense accounts', function () {
    // Only create revenue, no expense
    ChartOfAccount::factory()->create(['type' => 'Revenue']);

    $validation = $this->service->validateCOAClassification();

    expect($validation['is_valid'])->toBeFalse();
    expect($validation['issues'])->toContain('Tidak ada akun Expense. Pastikan ada akun beban di COA.');
});

test('service returns empty result when no entries exist', function () {
    ChartOfAccount::factory()->create(['type' => 'Revenue']);
    ChartOfAccount::factory()->create(['type' => 'Expense']);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['revenue']['total'])->toEqual(0.0);
    expect($result['expense']['total'])->toEqual(0.0);
    expect($result['net_income'])->toEqual(0.0);
    expect($result['is_profit'])->toBeTrue(); // Zero is considered not a loss
});

test('service handles parent-child account relationships', function () {
    $parentRevenue = ChartOfAccount::factory()->create([
        'type' => 'Revenue',
        'code' => '4-0000',
        'name' => 'Pendapatan',
        'parent_id' => null,
    ]);

    $childRevenue = ChartOfAccount::factory()->create([
        'type' => 'Revenue',
        'code' => '4-1000',
        'name' => 'Pendapatan Penjualan',
        'parent_id' => $parentRevenue->id,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $childRevenue->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 1000000,
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['revenue']['total'])->toEqual(1000000.0);
    
    // Find the child account in results
    $childAccount = $result['revenue']['accounts']->firstWhere('id', $childRevenue->id);
    expect($childAccount)->not->toBeNull();
    expect($childAccount['parent_id'])->toBe($parentRevenue->id);
});

// ============================================================================
// COMPREHENSIVE INCOME STATEMENT TESTS (5-LEVEL STRUCTURE)
// ============================================================================

test('service generates complete 5-level income statement structure', function () {
    // 1. Sales Revenue (4-xxxx)
    $salesRevenue = ChartOfAccount::factory()->create([
        'type' => 'Revenue',
        'code' => '4-1000',
        'name' => 'Penjualan Produk',
    ]);
    
    // 2. COGS (5-1xxx)
    $cogs = ChartOfAccount::factory()->create([
        'type' => 'Expense',
        'code' => '5-1000',
        'name' => 'Harga Pokok Penjualan',
    ]);
    
    // 3. Operating Expenses (6-xxxx)
    $opex = ChartOfAccount::factory()->create([
        'type' => 'Expense',
        'code' => '6-1000',
        'name' => 'Beban Gaji',
    ]);
    
    // 4. Other Income (7-xxxx)
    $otherIncome = ChartOfAccount::factory()->create([
        'type' => 'Revenue',
        'code' => '7-1000',
        'name' => 'Pendapatan Bunga',
    ]);
    
    // 5. Tax Expense (9-xxxx)
    $tax = ChartOfAccount::factory()->create([
        'type' => 'Expense',
        'code' => '9-1000',
        'name' => 'Pajak Penghasilan',
    ]);

    // Create journal entries
    JournalEntry::factory()->create(['coa_id' => $salesRevenue->id, 'date' => now(), 'credit' => 10000000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $cogs->id, 'date' => now(), 'debit' => 4000000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $opex->id, 'date' => now(), 'debit' => 2000000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $otherIncome->id, 'date' => now(), 'credit' => 500000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $tax->id, 'date' => now(), 'debit' => 1000000, 'credit' => 0]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    // Verify all 5 levels
    expect($result['sales_revenue']['total'])->toEqual(10000000.0);
    expect($result['cogs']['total'])->toEqual(4000000.0);
    expect($result['gross_profit'])->toEqual(6000000.0); // 10M - 4M
    expect($result['operating_expenses']['total'])->toEqual(2000000.0);
    expect($result['operating_profit'])->toEqual(4000000.0); // 6M - 2M
    expect($result['other_income']['total'])->toEqual(500000.0);
    expect($result['profit_before_tax'])->toEqual(4500000.0); // 4M + 500K
    expect($result['tax_expense']['total'])->toEqual(1000000.0);
    expect($result['net_profit'])->toEqual(3500000.0); // 4.5M - 1M
    expect($result['is_profit'])->toBeTrue();
});

test('service calculates gross profit correctly', function () {
    $salesRevenue = ChartOfAccount::factory()->create([
        'type' => 'Revenue',
        'code' => '4-1000',
        'name' => 'Penjualan',
    ]);
    
    $cogs = ChartOfAccount::factory()->create([
        'type' => 'Expense',
        'code' => '5-1000',
        'name' => 'HPP',
    ]);

    JournalEntry::factory()->create(['coa_id' => $salesRevenue->id, 'date' => now(), 'credit' => 5000000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $cogs->id, 'date' => now(), 'debit' => 3000000, 'credit' => 0]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['gross_profit'])->toEqual(2000000.0);
    expect($result['gross_profit_margin'])->toEqual(40.0); // (2M / 5M) * 100
});

test('service calculates operating profit correctly', function () {
    $salesRevenue = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-1000']);
    $cogs = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '5-1000']);
    $opex1 = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6-1000', 'name' => 'Beban Gaji']);
    $opex2 = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6-2000', 'name' => 'Beban Sewa']);

    JournalEntry::factory()->create(['coa_id' => $salesRevenue->id, 'date' => now(), 'credit' => 8000000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $cogs->id, 'date' => now(), 'debit' => 3000000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $opex1->id, 'date' => now(), 'debit' => 1500000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $opex2->id, 'date' => now(), 'debit' => 500000, 'credit' => 0]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['gross_profit'])->toEqual(5000000.0); // 8M - 3M
    expect($result['operating_expenses']['total'])->toEqual(2000000.0); // 1.5M + 500K
    expect($result['operating_profit'])->toEqual(3000000.0); // 5M - 2M
    expect($result['operating_profit_margin'])->toEqual(37.5); // (3M / 8M) * 100
});

test('service handles other income and expenses correctly', function () {
    $salesRevenue = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-1000']);
    $cogs = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '5-1000']);
    $otherIncome = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '7-1000', 'name' => 'Pendapatan Bunga']);
    $otherExpense = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '7-2000', 'name' => 'Beban Bunga']);

    JournalEntry::factory()->create(['coa_id' => $salesRevenue->id, 'date' => now(), 'credit' => 5000000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $cogs->id, 'date' => now(), 'debit' => 2000000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $otherIncome->id, 'date' => now(), 'credit' => 300000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $otherExpense->id, 'date' => now(), 'debit' => 100000, 'credit' => 0]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['other_income']['total'])->toEqual(300000.0);
    expect($result['other_expense']['total'])->toEqual(100000.0);
    expect($result['net_other_income_expense'])->toEqual(200000.0); // 300K - 100K
    expect($result['operating_profit'])->toEqual(3000000.0); // 5M - 2M
    expect($result['profit_before_tax'])->toEqual(3200000.0); // 3M + 200K
});

test('service calculates tax expense and net profit correctly', function () {
    $salesRevenue = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-1000']);
    $cogs = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '5-1000']);
    $tax = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '9-1000', 'name' => 'Pajak Penghasilan']);

    JournalEntry::factory()->create(['coa_id' => $salesRevenue->id, 'date' => now(), 'credit' => 10000000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $cogs->id, 'date' => now(), 'debit' => 6000000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $tax->id, 'date' => now(), 'debit' => 800000, 'credit' => 0]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['profit_before_tax'])->toEqual(4000000.0); // 10M - 6M
    expect($result['tax_expense']['total'])->toEqual(800000.0);
    expect($result['net_profit'])->toEqual(3200000.0); // 4M - 800K
    expect($result['net_profit_margin'])->toEqual(32.0); // (3.2M / 10M) * 100
});

test('service detects loss when expenses exceed revenue', function () {
    $salesRevenue = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-1000']);
    $cogs = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '5-1000']);
    $opex = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6-1000']);

    JournalEntry::factory()->create(['coa_id' => $salesRevenue->id, 'date' => now(), 'credit' => 3000000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $cogs->id, 'date' => now(), 'debit' => 2000000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $opex->id, 'date' => now(), 'debit' => 2000000, 'credit' => 0]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['gross_profit'])->toEqual(1000000.0); // 3M - 2M
    expect($result['operating_profit'])->toEqual(-1000000.0); // 1M - 2M (LOSS)
    expect($result['net_profit'])->toEqual(-1000000.0);
    expect($result['is_profit'])->toBeFalse();
});

test('service validates COA classification for complete structure', function () {
    // Create accounts for all 5 levels
    ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-1000']); // Sales Revenue
    ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '5-1000']); // COGS
    ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6-1000']); // Operating Expense
    ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '7-1000']); // Other Income
    ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '8-1000']); // Tax

    $validation = $this->service->validateCOAClassification();

    expect($validation['is_valid'])->toBeTrue();
    expect($validation['classification']['sales_revenue_accounts'])->toBe(1);
    expect($validation['classification']['cogs_accounts'])->toBe(1);
    expect($validation['classification']['operating_expense_accounts'])->toBe(1);
    expect($validation['classification']['other_income_expense_accounts'])->toBe(1);
    expect($validation['classification']['tax_expense_accounts'])->toBe(1);
});

test('service detects missing COGS accounts', function () {
    ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-1000']);
    // No COGS accounts (5-1xxx)

    $validation = $this->service->validateCOAClassification();

    expect($validation['is_valid'])->toBeFalse();
    expect($validation['issues'])->toContain('Tidak ada akun Harga Pokok Penjualan (COGS) dengan prefix 5-1. HPP diperlukan untuk menghitung Laba Kotor.');
});

test('service detects missing operating expense accounts', function () {
    ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-1000']);
    ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '5-1000']);
    // No Operating Expenses (6-xxxx)

    $validation = $this->service->validateCOAClassification();

    expect($validation['is_valid'])->toBeFalse();
    expect($validation['issues'])->toContain('Tidak ada akun Beban Operasional dengan prefix 6-. Beban operasional diperlukan untuk menghitung Laba Operasional.');
});

test('service compares periods for all 5 levels', function () {
    $salesRevenue = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-1000']);
    $cogs = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '5-1000']);
    $opex = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6-1000']);

    // Current period
    JournalEntry::factory()->create(['coa_id' => $salesRevenue->id, 'date' => '2024-02-15', 'credit' => 10000000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $cogs->id, 'date' => '2024-02-15', 'debit' => 4000000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $opex->id, 'date' => '2024-02-15', 'debit' => 2000000, 'credit' => 0]);

    // Previous period
    JournalEntry::factory()->create(['coa_id' => $salesRevenue->id, 'date' => '2024-01-15', 'credit' => 8000000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $cogs->id, 'date' => '2024-01-15', 'debit' => 3000000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $opex->id, 'date' => '2024-01-15', 'debit' => 1500000, 'credit' => 0]);

    $comparison = $this->service->comparePeriods(
        ['start_date' => '2024-02-01', 'end_date' => '2024-02-29'],
        ['start_date' => '2024-01-01', 'end_date' => '2024-01-31']
    );

    // Sales Revenue change: 10M - 8M = +2M (+25%)
    expect($comparison['changes']['sales_revenue']['amount'])->toEqual(2000000.0);
    expect($comparison['changes']['sales_revenue']['percentage'])->toEqual(25.0);

    // Gross Profit change: (10M-4M) - (8M-3M) = 6M - 5M = +1M (+20%)
    expect($comparison['changes']['gross_profit']['amount'])->toEqual(1000000.0);
    expect($comparison['changes']['gross_profit']['percentage'])->toEqual(20.0);

    // Operating Profit change: (6M-2M) - (5M-1.5M) = 4M - 3.5M = +500K (+14.29%)
    expect($comparison['changes']['operating_profit']['amount'])->toEqual(500000.0);
    expect(round($comparison['changes']['operating_profit']['percentage'], 2))->toEqual(14.29);

    // Net Profit change
    expect($comparison['changes']['net_profit']['amount'])->toEqual(500000.0);
});

test('service handles zero revenue gracefully for margin calculations', function () {
    $cogs = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '5-1000']);
    JournalEntry::factory()->create(['coa_id' => $cogs->id, 'date' => now(), 'debit' => 1000000, 'credit' => 0]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['sales_revenue']['total'])->toEqual(0.0);
    expect($result['gross_profit_margin'])->toEqual(0.0); // Should not divide by zero
    expect($result['operating_profit_margin'])->toEqual(0.0);
    expect($result['net_profit_margin'])->toEqual(0.0);
});

test('service filters accounts by code prefix correctly', function () {
    // Create accounts with different prefixes
    $sales1 = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-1000']);
    $sales2 = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '4-2000']);
    $otherIncome = ChartOfAccount::factory()->create(['type' => 'Revenue', 'code' => '7-1000']); // Should not be in sales_revenue
    
    $cogs = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '5-1000']);
    $opex = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6-1000']);
    $otherExpense = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '7-2000']); // Should not be in operating_expenses

    JournalEntry::factory()->create(['coa_id' => $sales1->id, 'date' => now(), 'credit' => 1000000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $sales2->id, 'date' => now(), 'credit' => 2000000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $otherIncome->id, 'date' => now(), 'credit' => 500000, 'debit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $cogs->id, 'date' => now(), 'debit' => 1500000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $opex->id, 'date' => now(), 'debit' => 800000, 'credit' => 0]);
    JournalEntry::factory()->create(['coa_id' => $otherExpense->id, 'date' => now(), 'debit' => 200000, 'credit' => 0]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    expect($result['sales_revenue']['total'])->toEqual(3000000.0); // 4-1000 + 4-2000
    expect($result['sales_revenue']['accounts']->count())->toBe(2);
    expect($result['other_income']['total'])->toEqual(500000.0); // 7-1000
    expect($result['cogs']['total'])->toEqual(1500000.0); // 5-1000
    expect($result['operating_expenses']['total'])->toEqual(800000.0); // 6-1000
    expect($result['other_expense']['total'])->toEqual(200000.0); // 7-2000
});

test('service calculates percentage of revenue for each account', function () {
    // Create Sales Revenue account
    $salesRevenue = ChartOfAccount::factory()->create([
        'type' => 'Revenue',
        'code' => '4-1000',
        'name' => 'Penjualan Produk',
    ]);

    // Create COGS account
    $cogs = ChartOfAccount::factory()->create([
        'type' => 'Expense',
        'code' => '5-1000',
        'name' => 'Harga Pokok Penjualan',
    ]);

    // Create Operating Expense account
    $opex = ChartOfAccount::factory()->create([
        'type' => 'Expense',
        'code' => '6-1000',
        'name' => 'Beban Gaji',
    ]);

    // Create journal entries
    // Revenue = 1,000,000
    JournalEntry::factory()->create([
        'coa_id' => $salesRevenue->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 1000000,
    ]);

    // COGS = 400,000 (40% of revenue)
    JournalEntry::factory()->create([
        'coa_id' => $cogs->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 400000,
        'credit' => 0,
    ]);

    // Operating Expense = 300,000 (30% of revenue)
    JournalEntry::factory()->create([
        'coa_id' => $opex->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 300000,
        'credit' => 0,
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    // Check sales revenue percentage (should be 100%)
    expect($result['sales_revenue']['accounts']->first()['percentage_of_revenue'])->toEqual(100.0);

    // Check COGS percentage (should be 40%)
    expect($result['cogs']['accounts']->first()['percentage_of_revenue'])->toEqual(40.0);

    // Check Operating Expense percentage (should be 30%)
    expect($result['operating_expenses']['accounts']->first()['percentage_of_revenue'])->toEqual(30.0);

    // Check margins
    expect($result['gross_profit_margin'])->toEqual(60.0); // (1,000,000 - 400,000) / 1,000,000 * 100
    expect($result['operating_profit_margin'])->toEqual(30.0); // (600,000 - 300,000) / 1,000,000 * 100
    expect($result['net_profit_margin'])->toEqual(30.0); // 300,000 / 1,000,000 * 100
});

test('service can retrieve journal entries for drill-down', function () {
    $revenueAccount = ChartOfAccount::factory()->create([
        'type' => 'Revenue',
        'code' => '4-1000',
        'name' => 'Pendapatan Penjualan',
    ]);

    $cabang = Cabang::factory()->create();

    // Create multiple journal entries
    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 500000,
        'cabang_id' => $cabang->id,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 0,
        'credit' => 300000,
        'cabang_id' => $cabang->id,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->subDay()->format('Y-m-d'),
        'debit' => 50000, // Sales return
        'credit' => 0,
        'cabang_id' => $cabang->id,
    ]);

    $result = $this->service->getAccountJournalEntries(
        $revenueAccount->id,
        now()->startOfMonth()->format('Y-m-d'),
        now()->endOfMonth()->format('Y-m-d')
    );

    expect($result['account'])->not->toBeNull();
    expect($result['account']['code'])->toEqual('4-1000');
    expect($result['account']['name'])->toEqual('Pendapatan Penjualan');
    expect($result['entries']->count())->toBe(3);
    expect($result['total_debit'])->toEqual(50000.0);
    expect($result['total_credit'])->toEqual(800000.0);
    expect($result['balance'])->toEqual(750000.0); // 800,000 - 50,000
});

test('service can filter drill-down entries by cabang', function () {
    $revenueAccount = ChartOfAccount::factory()->create([
        'type' => 'Revenue',
        'code' => '4-1000',
        'name' => 'Pendapatan Penjualan',
    ]);

    $cabang1 = Cabang::factory()->create(['kode' => 'CAB1']);
    $cabang2 = Cabang::factory()->create(['kode' => 'CAB2']);

    // Create entries for different cabangs
    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'credit' => 500000,
        'debit' => 0,
        'cabang_id' => $cabang1->id,
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $revenueAccount->id,
        'date' => now()->format('Y-m-d'),
        'credit' => 300000,
        'debit' => 0,
        'cabang_id' => $cabang2->id,
    ]);

    // Get entries for cabang1 only
    $result = $this->service->getAccountJournalEntries(
        $revenueAccount->id,
        now()->startOfMonth()->format('Y-m-d'),
        now()->endOfMonth()->format('Y-m-d'),
        $cabang1->id
    );

    expect($result['entries']->count())->toBe(1);
    expect($result['balance'])->toEqual(500000.0);
});

test('service handles non-existent account in drill-down gracefully', function () {
    $result = $this->service->getAccountJournalEntries(
        999999, // Non-existent account ID
        now()->startOfMonth()->format('Y-m-d'),
        now()->endOfMonth()->format('Y-m-d')
    );

    expect($result['account'])->toBeNull();
    expect($result['entries']->count())->toBe(0);
    expect($result['balance'])->toEqual(0);
});

test('service calculates percentage as zero when revenue is zero', function () {
    // Create expense without revenue
    $expenseAccount = ChartOfAccount::factory()->create([
        'type' => 'Expense',
        'code' => '6-1000',
        'name' => 'Beban Gaji',
    ]);

    JournalEntry::factory()->create([
        'coa_id' => $expenseAccount->id,
        'date' => now()->format('Y-m-d'),
        'debit' => 100000,
        'credit' => 0,
    ]);

    $result = $this->service->generate([
        'start_date' => now()->startOfMonth()->format('Y-m-d'),
        'end_date' => now()->endOfMonth()->format('Y-m-d'),
    ]);

    // All percentage_of_revenue should be 0 when total revenue is 0
    if ($result['operating_expenses']['accounts']->count() > 0) {
        expect($result['operating_expenses']['accounts']->first()['percentage_of_revenue'])->toEqual(0.0);
    }
    expect($result['gross_profit_margin'])->toEqual(0.0);
    expect($result['operating_profit_margin'])->toEqual(0.0);
    expect($result['net_profit_margin'])->toEqual(0.0);
});
