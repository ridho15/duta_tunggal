<?php

use App\Services\BalanceSheetService;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new BalanceSheetService();
    
    // Create test branch
    $this->cabang = Cabang::create([
        'kode' => 'TEST',
        'nama' => 'Test Branch',
        'alamat' => 'Test Address',
        'telepon' => '0123456789',
    ]);
    
    // Create Chart of Accounts for Balance Sheet
    // Current Assets (1-1xxx)
    $this->cashAccount = ChartOfAccount::create([
        'code' => '1-1001',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_current' => true,
    ]);
    
    $this->receivableAccount = ChartOfAccount::create([
        'code' => '1-1002',
        'name' => 'Piutang Dagang',
        'type' => 'Asset',
        'is_current' => true,
    ]);
    
    // Fixed Assets (1-2xxx)
    $this->buildingAccount = ChartOfAccount::create([
        'code' => '1-2001',
        'name' => 'Gedung',
        'type' => 'Asset',
        'is_current' => false,
    ]);
    
    $this->machineAccount = ChartOfAccount::create([
        'code' => '1-2002',
        'name' => 'Mesin',
        'type' => 'Asset',
        'is_current' => false,
    ]);
    
    // Contra Asset (Accumulated Depreciation)
    $this->depreciationAccount = ChartOfAccount::create([
        'code' => '1-2999',
        'name' => 'Akumulasi Penyusutan',
        'type' => 'Contra Asset',
        'is_current' => false,
    ]);
    
    // Current Liabilities (2-1xxx)
    $this->payableAccount = ChartOfAccount::create([
        'code' => '2-1001',
        'name' => 'Hutang Dagang',
        'type' => 'Liability',
        'is_current' => true,
    ]);
    
    // Long-term Liabilities (2-2xxx)
    $this->loanAccount = ChartOfAccount::create([
        'code' => '2-2001',
        'name' => 'Hutang Bank Jangka Panjang',
        'type' => 'Liability',
        'is_current' => false,
    ]);
    
    // Equity (3-xxxx)
    $this->capitalAccount = ChartOfAccount::create([
        'code' => '3-1001',
        'name' => 'Modal Pemilik',
        'type' => 'Equity',
    ]);
    
    // Revenue (for retained earnings calculation) (4-xxxx)
    $this->revenueAccount = ChartOfAccount::create([
        'code' => '4-1001',
        'name' => 'Pendapatan Penjualan',
        'type' => 'Revenue',
    ]);
    
    // Expense (for retained earnings calculation) (6-xxxx)
    $this->expenseAccount = ChartOfAccount::create([
        'code' => '6-1001',
        'name' => 'Beban Gaji',
        'type' => 'Expense',
    ]);
});

describe('Balance Sheet Structure', function () {
    
    it('generates complete balance sheet structure', function () {
        // Create journal entries for all accounts
        $baseDate = now()->startOfMonth();
        
        // Assets
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate,
            'reference' => 'BS-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash balance',
            'debit' => 10000000,
            'credit' => 0,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->buildingAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate,
            'reference' => 'BS-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Building purchase',
            'debit' => 50000000,
            'credit' => 0,
        ]);
        
        // Contra Asset (Depreciation)
        JournalEntry::create([
            'coa_id' => $this->depreciationAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate,
            'reference' => 'BS-003',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Accumulated depreciation',
            'debit' => 0,
            'credit' => 5000000,
        ]);
        
        // Liabilities
        JournalEntry::create([
            'coa_id' => $this->payableAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate,
            'reference' => 'BS-004',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Accounts payable',
            'debit' => 0,
            'credit' => 8000000,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->loanAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate,
            'reference' => 'BS-005',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Long-term loan',
            'debit' => 0,
            'credit' => 20000000,
        ]);
        
        // Equity
        JournalEntry::create([
            'coa_id' => $this->capitalAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate,
            'reference' => 'BS-006',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Capital',
            'debit' => 0,
            'credit' => 20000000, // Adjusted to balance the equation
        ]);
        
        // Revenue and Expense (for retained earnings)
        JournalEntry::create([
            'coa_id' => $this->revenueAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate,
            'reference' => 'BS-007',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Sales revenue',
            'debit' => 0,
            'credit' => 15000000,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->expenseAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate,
            'reference' => 'BS-008',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Salary expense',
            'debit' => 8000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => $baseDate->endOfMonth()->format('Y-m-d'),
            'cabang_id' => $this->cabang->id,
        ]);
        
        // Verify structure
        expect($result)->toHaveKeys([
            'current_assets',
            'fixed_assets',
            'contra_assets',
            'total_assets',
            'current_liabilities',
            'long_term_liabilities',
            'total_liabilities',
            'equity',
            'retained_earnings',
            'total_equity',
            'total_liabilities_and_equity',
            'is_balanced',
            'difference',
        ]);
        
        // Verify current assets
        expect($result['current_assets']['accounts']->count())->toBe(1);
        expect($result['current_assets']['total'])->toBe(10000000.0);
        
        // Verify fixed assets
        expect($result['fixed_assets']['accounts']->count())->toBe(1);
        expect($result['fixed_assets']['total'])->toBe(50000000.0);
        
        // Verify contra assets
        expect($result['contra_assets']['total'])->toBe(5000000.0);
        
        // Verify total assets (current + fixed - contra)
        expect($result['total_assets'])->toBe(55000000.0);
        
        // Verify current liabilities
        expect($result['current_liabilities']['total'])->toBe(8000000.0);
        
        // Verify long-term liabilities
        expect($result['long_term_liabilities']['total'])->toBe(20000000.0);
        
        // Verify total liabilities
        expect($result['total_liabilities'])->toBe(28000000.0);
        
        // Verify equity
        expect($result['equity']['total'])->toBe(20000000.0);
        
        // Verify retained earnings (Revenue 15M - Expense 8M = 7M)
        expect($result['retained_earnings'])->toBe(7000000.0);
        
        // Verify total equity (equity + retained earnings)
        expect($result['total_equity'])->toBe(27000000.0);
        
        // Verify balance (Assets = Liabilities + Equity)
        expect($result['is_balanced'])->toBe(true);
        expect($result['total_liabilities_and_equity'])->toBe(55000000.0);
        expect($result['total_assets'])->toBe($result['total_liabilities_and_equity']);
    });
    
    it('correctly calculates asset balances (debit - credit)', function () {
        // Create asset with multiple transactions
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subDays(5),
            'reference' => 'CASH-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Initial cash',
            'debit' => 20000000,
            'credit' => 0,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subDays(3),
            'reference' => 'CASH-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Payment',
            'debit' => 0,
            'credit' => 5000000,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subDays(1),
            'reference' => 'CASH-003',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Receipt',
            'debit' => 3000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        // Balance = (20M + 3M) - 5M = 18M
        expect($result['current_assets']['total'])->toBe(18000000.0);
    });
    
    it('correctly calculates liability balances (credit - debit)', function () {
        // Create liability with multiple transactions
        JournalEntry::create([
            'coa_id' => $this->payableAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subDays(5),
            'reference' => 'AP-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Purchase on credit',
            'debit' => 0,
            'credit' => 15000000,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->payableAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subDays(2),
            'reference' => 'AP-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Payment to supplier',
            'debit' => 4000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        // Balance = 15M - 4M = 11M
        expect($result['current_liabilities']['total'])->toBe(11000000.0);
    });
});

describe('Current vs Non-Current Classification', function () {
    
    it('separates current and non-current assets correctly', function () {
        // Current asset
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'CA-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash',
            'debit' => 5000000,
            'credit' => 0,
        ]);
        
        // Fixed asset
        JournalEntry::create([
            'coa_id' => $this->buildingAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'FA-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Building',
            'debit' => 40000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        expect($result['current_assets']['total'])->toBe(5000000.0);
        expect($result['fixed_assets']['total'])->toBe(40000000.0);
        expect($result['current_assets']['accounts']->first()->id)->toBe($this->cashAccount->id);
        expect($result['fixed_assets']['accounts']->first()->id)->toBe($this->buildingAccount->id);
    });
    
    it('separates current and long-term liabilities correctly', function () {
        // Current liability
        JournalEntry::create([
            'coa_id' => $this->payableAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'CL-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Accounts payable',
            'debit' => 0,
            'credit' => 6000000,
        ]);
        
        // Long-term liability
        JournalEntry::create([
            'coa_id' => $this->loanAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'LTL-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Long-term loan',
            'debit' => 0,
            'credit' => 25000000,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        expect($result['current_liabilities']['total'])->toBe(6000000.0);
        expect($result['long_term_liabilities']['total'])->toBe(25000000.0);
        expect($result['current_liabilities']['accounts']->first()->id)->toBe($this->payableAccount->id);
        expect($result['long_term_liabilities']['accounts']->first()->id)->toBe($this->loanAccount->id);
    });
});

describe('Retained Earnings Calculation', function () {
    
    it('calculates retained earnings from revenue and expenses', function () {
        // Revenue transactions
        JournalEntry::create([
            'coa_id' => $this->revenueAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subMonths(2),
            'reference' => 'REV-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Sales month 1',
            'debit' => 0,
            'credit' => 50000000,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->revenueAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subMonth(),
            'reference' => 'REV-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Sales month 2',
            'debit' => 0,
            'credit' => 60000000,
        ]);
        
        // Expense transactions
        JournalEntry::create([
            'coa_id' => $this->expenseAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subMonths(2),
            'reference' => 'EXP-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Salary month 1',
            'debit' => 30000000,
            'credit' => 0,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->expenseAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subMonth(),
            'reference' => 'EXP-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Salary month 2',
            'debit' => 35000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        // Retained Earnings = (50M + 60M) - (30M + 35M) = 45M
        expect($result['retained_earnings'])->toBe(45000000.0);
    });
    
    it('handles negative retained earnings (accumulated loss)', function () {
        // Low revenue
        JournalEntry::create([
            'coa_id' => $this->revenueAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'REV-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Low sales',
            'debit' => 0,
            'credit' => 10000000,
        ]);
        
        // High expense
        JournalEntry::create([
            'coa_id' => $this->expenseAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'EXP-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'High costs',
            'debit' => 25000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        // Retained Earnings = 10M - 25M = -15M
        expect($result['retained_earnings'])->toBe(-15000000.0);
    });
});

describe('Date-based Balance Calculation', function () {
    
    it('includes only transactions up to the specified date', function () {
        $baseDate = now()->startOfMonth();
        
        // Transaction before cutoff
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate->copy()->addDays(5),
            'reference' => 'CASH-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Before cutoff',
            'debit' => 10000000,
            'credit' => 0,
        ]);
        
        // Transaction after cutoff (should not be included)
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate->copy()->addDays(20),
            'reference' => 'CASH-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'After cutoff',
            'debit' => 5000000,
            'credit' => 0,
        ]);
        
        $cutoffDate = $baseDate->copy()->addDays(10);
        
        $result = $this->service->generate([
            'as_of_date' => $cutoffDate->format('Y-m-d'),
        ]);
        
        // Should only include first transaction
        expect($result['current_assets']['total'])->toBe(10000000.0);
    });
    
    it('calculates cumulative balance correctly', function () {
        $baseDate = now()->startOfMonth();
        
        // Multiple transactions over time
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate->copy()->addDay(),
            'reference' => 'CASH-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Day 1',
            'debit' => 8000000,
            'credit' => 0,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate->copy()->addDays(5),
            'reference' => 'CASH-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Day 5',
            'debit' => 0,
            'credit' => 2000000,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $baseDate->copy()->addDays(10),
            'reference' => 'CASH-003',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Day 10',
            'debit' => 4000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => $baseDate->copy()->addDays(10)->format('Y-m-d'),
        ]);
        
        // Cumulative: 8M - 2M + 4M = 10M
        expect($result['current_assets']['total'])->toBe(10000000.0);
    });
});

describe('Cabang Filtering', function () {
    
    it('filters by specific cabang when provided', function () {
        $cabang2 = Cabang::create([
            'kode' => 'TEST2',
            'nama' => 'Test Branch 2',
            'alamat' => 'Test Address 2',
            'telepon' => '0987654321',
        ]);
        
        // Transactions for cabang 1
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'C1-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cabang 1',
            'debit' => 5000000,
            'credit' => 0,
        ]);
        
        // Transactions for cabang 2
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $cabang2->id,
            'date' => now(),
            'reference' => 'C2-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cabang 2',
            'debit' => 8000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
            'cabang_id' => $this->cabang->id,
        ]);
        
        // Should only include cabang 1 transactions
        expect($result['current_assets']['total'])->toBe(5000000.0);
    });
    
    it('includes all cabang when cabang_id is null', function () {
        $cabang2 = Cabang::create([
            'kode' => 'TEST2',
            'nama' => 'Test Branch 2',
            'alamat' => 'Test Address 2',
            'telepon' => '0987654321',
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'C1-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cabang 1',
            'debit' => 5000000,
            'credit' => 0,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $cabang2->id,
            'date' => now(),
            'reference' => 'C2-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cabang 2',
            'debit' => 8000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
            'cabang_id' => null,
        ]);
        
        // Should include both cabang
        expect($result['current_assets']['total'])->toBe(13000000.0);
    });
});

describe('Period Comparison', function () {
    
    it('compares two balance sheet dates correctly', function () {
        $date1 = now()->startOfMonth();
        $date2 = now()->subMonth()->startOfMonth();
        
        // Transactions for date 2 (older)
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $date2->copy()->addDays(5),
            'reference' => 'OLD-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Old transaction',
            'debit' => 10000000,
            'credit' => 0,
        ]);
        
        // Transactions for date 1 (newer)
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => $date1->copy()->addDays(5),
            'reference' => 'NEW-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'New transaction',
            'debit' => 5000000,
            'credit' => 0,
        ]);
        
        $comparison = $this->service->comparePeriods(
            ['as_of_date' => $date1->copy()->addDays(10)->format('Y-m-d')],
            ['as_of_date' => $date2->copy()->addDays(10)->format('Y-m-d')],
            null
        );
        
        // Current (date1) should have 15M (cumulative)
        // Previous (date2) should have 10M
        expect($comparison['current_assets']['current'])->toBe(15000000.0);
        expect($comparison['current_assets']['previous'])->toBe(10000000.0);
        expect($comparison['current_assets']['change'])->toBe(5000000.0);
        expect($comparison['current_assets']['percentage'])->toBe(50.0);
    });
});

describe('Drill-down Functionality', function () {
    
    it('retrieves journal entries for specific account', function () {
        // Create multiple entries
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subDays(5),
            'reference' => 'DRILL-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Entry 1',
            'debit' => 5000000,
            'credit' => 0,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now()->subDays(3),
            'reference' => 'DRILL-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Entry 2',
            'debit' => 0,
            'credit' => 2000000,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'DRILL-003',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Entry 3',
            'debit' => 3000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->getAccountJournalEntries(
            $this->cashAccount->id,
            now()->format('Y-m-d'),
            null
        );
        
        expect($result['entries']->count())->toBe(3);
        expect($result['total_debit'])->toBe(8000000.0);
        expect($result['total_credit'])->toBe(2000000.0);
        expect($result['balance'])->toBe(6000000.0);
        expect($result['account']->id)->toBe($this->cashAccount->id);
    });
    
    it('filters drill-down by cabang', function () {
        $cabang2 = Cabang::create([
            'kode' => 'TEST2',
            'nama' => 'Test Branch 2',
            'alamat' => 'Test Address 2',
            'telepon' => '0987654321',
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'C1-DRILL',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cabang 1',
            'debit' => 5000000,
            'credit' => 0,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $cabang2->id,
            'date' => now(),
            'reference' => 'C2-DRILL',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cabang 2',
            'debit' => 3000000,
            'credit' => 0,
        ]);
        
        $result = $this->service->getAccountJournalEntries(
            $this->cashAccount->id,
            now()->format('Y-m-d'),
            $this->cabang->id
        );
        
        expect($result['entries']->count())->toBe(1);
        expect($result['balance'])->toBe(5000000.0);
    });
});

describe('Financial Ratios and Summary', function () {
    
    it('calculates current ratio correctly', function () {
        // Current assets: 12M
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'CR-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash',
            'debit' => 12000000,
            'credit' => 0,
        ]);
        
        // Current liabilities: 6M
        JournalEntry::create([
            'coa_id' => $this->payableAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'CR-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Payable',
            'debit' => 0,
            'credit' => 6000000,
        ]);
        
        $summary = $this->service->getSummary([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        // Current Ratio = 12M / 6M = 2.0
        expect($summary['current_ratio'])->toBe(2.0);
    });
    
    it('calculates debt-to-equity ratio correctly', function () {
        // Total liabilities: 18M
        JournalEntry::create([
            'coa_id' => $this->payableAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'DTE-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Current liability',
            'debit' => 0,
            'credit' => 8000000,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->loanAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'DTE-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Long-term liability',
            'debit' => 0,
            'credit' => 10000000,
        ]);
        
        // Total equity: 9M
        JournalEntry::create([
            'coa_id' => $this->capitalAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'DTE-003',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Capital',
            'debit' => 0,
            'credit' => 9000000,
        ]);
        
        $summary = $this->service->getSummary([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        // Debt-to-Equity = 18M / 9M = 2.0
        expect($summary['debt_to_equity_ratio'])->toBe(2.0);
    });
    
    it('calculates working capital correctly', function () {
        // Current assets: 20M
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'WC-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash',
            'debit' => 20000000,
            'credit' => 0,
        ]);
        
        // Current liabilities: 12M
        JournalEntry::create([
            'coa_id' => $this->payableAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'WC-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Payable',
            'debit' => 0,
            'credit' => 12000000,
        ]);
        
        $summary = $this->service->getSummary([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        // Working Capital = 20M - 12M = 8M
        expect($summary['working_capital'])->toBe(8000000.0);
    });
});

describe('Balance Verification', function () {
    
    it('detects balanced sheet', function () {
        // Assets: 60M (Cash 10M + Building 50M)
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'BAL-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash',
            'debit' => 10000000,
            'credit' => 0,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->buildingAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'BAL-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Building',
            'debit' => 50000000,
            'credit' => 0,
        ]);
        
        // Liabilities: 25M
        JournalEntry::create([
            'coa_id' => $this->loanAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'BAL-003',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Loan',
            'debit' => 0,
            'credit' => 25000000,
        ]);
        
        // Equity: 35M
        JournalEntry::create([
            'coa_id' => $this->capitalAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'BAL-004',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Capital',
            'debit' => 0,
            'credit' => 35000000,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        expect($result['is_balanced'])->toBe(true);
        expect($result['difference'])->toBe(0.0);
    });
    
    it('detects unbalanced sheet', function () {
        // Assets: 50M
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'UNBAL-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash',
            'debit' => 50000000,
            'credit' => 0,
        ]);
        
        // Liabilities + Equity: 40M (unbalanced)
        JournalEntry::create([
            'coa_id' => $this->capitalAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'UNBAL-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Capital',
            'debit' => 0,
            'credit' => 40000000,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        expect($result['is_balanced'])->toBe(false);
        expect($result['difference'])->toBe(10000000.0);
    });
});

describe('Edge Cases', function () {
    
    it('handles zero balance accounts (excludes from list)', function () {
        // Create account with zero balance
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'ZERO-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Debit',
            'debit' => 5000000,
            'credit' => 0,
        ]);
        
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'ZERO-002',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Credit (balances out)',
            'debit' => 0,
            'credit' => 5000000,
        ]);
        
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        // Account with zero balance should not appear
        expect($result['current_assets']['accounts']->count())->toBe(0);
        expect($result['current_assets']['total'])->toBe(0.0);
    });
    
    it('handles empty balance sheet (no transactions)', function () {
        $result = $this->service->generate([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        expect($result['total_assets'])->toBe(0.0);
        expect($result['total_liabilities'])->toBe(0.0);
        expect($result['total_equity'])->toBe(0.0);
        expect($result['is_balanced'])->toBe(true);
    });
    
    it('handles division by zero in ratios', function () {
        // Only assets, no liabilities or equity
        JournalEntry::create([
            'coa_id' => $this->cashAccount->id,
            'cabang_id' => $this->cabang->id,
            'date' => now(),
            'reference' => 'DIV-001',
            'source_type' => 'manual',
            'source_id' => 1,
            'description' => 'Cash only',
            'debit' => 10000000,
            'credit' => 0,
        ]);
        
        $summary = $this->service->getSummary([
            'as_of_date' => now()->format('Y-m-d'),
        ]);
        
        // Should handle division by zero gracefully (no liabilities, so current ratio = 0)
        expect($summary['current_ratio'])->toBe(0.0);
        expect($summary['debt_to_equity_ratio'])->toBe(0.0);
    });
});
