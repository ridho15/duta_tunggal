<?php

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => ChartOfAccountSeeder::class]);
    
    // Create test journal entry for the temporary procurement COA
    $coa = ChartOfAccount::where('code', '1400.01')->first();
    if ($coa) {
        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => '2025-11-22',
            'reference' => 'TEST-RN-20251122-0001',
            'description' => 'Test Temporary Procurement Entry',
            'debit' => 415740.00,
            'credit' => 0.00,
            'journal_type' => 'procurement',
            'source_type' => 'App\\Models\\PurchaseReceiptItem',
            'source_id' => 1,
            'transaction_id' => 'test-transaction-123',
        ]);
    }
});

test('chart of account ledger displays correct data for specific account', function () {
    // Get the ChartOfAccount by code instead of ID since test DB has different IDs
    $coa = ChartOfAccount::where('code', '1400.01')->first();

    expect($coa)->not->toBeNull();
    expect($coa->code)->toBe('1400.01');
    expect($coa->name)->toBe('POS SEMENTARA PENGADAAN');
    expect($coa->type)->toBe('Asset');
});

test('chart of account ledger filters journal entries by date range correctly', function () {
    $coa = ChartOfAccount::where('code', '1400.01')->first();

    // Test date range filtering
    $startDate = '2025-01-01';
    $endDate = '2025-11-22';

    $journalEntries = $coa->journalEntries()
        ->whereBetween('date', [$startDate, $endDate])
        ->orderBy('date', 'asc')
        ->orderBy('id', 'asc')
        ->get();

    expect($journalEntries)->toHaveCount(1);

    $entry = $journalEntries->first();
    expect($entry->date->format('Y-m-d'))->toBe('2025-11-22');
    expect(floatval($entry->debit))->toBe(415740.0);
    expect(floatval($entry->credit))->toBe(0.0);
});

test('chart of account ledger calculates opening balance correctly', function () {
    $coa = ChartOfAccount::where('code', '1400.01')->first();

    $startDate = '2025-01-01';

    // Calculate opening balance (transactions before start date)
    $openingDebit = $coa->journalEntries()
        ->where('date', '<', $startDate)
        ->sum('debit');

    $openingCredit = $coa->journalEntries()
        ->where('date', '<', $startDate)
        ->sum('credit');

    // For Asset accounts: Opening Balance = opening_balance + debit - credit
    $openingBalance = $coa->opening_balance + $openingDebit - $openingCredit;

    expect(floatval($openingDebit))->toBe(0.0);
    expect(floatval($openingCredit))->toBe(0.0);
    expect(floatval($openingBalance))->toBe(0.0);
});

test('chart of account ledger calculates running balance correctly for asset account', function () {
    $coa = ChartOfAccount::where('code', '1400.01')->first();

    $startDate = '2025-01-01';
    $endDate = '2025-11-22';

    // Calculate opening balance
    $openingDebit = $coa->journalEntries()->where('date', '<', $startDate)->sum('debit');
    $openingCredit = $coa->journalEntries()->where('date', '<', $startDate)->sum('credit');
    $openingBalance = $coa->opening_balance + $openingDebit - $openingCredit;

    // Get journal entries in date range
    $journalEntries = $coa->journalEntries()
        ->whereBetween('date', [$startDate, $endDate])
        ->orderBy('date', 'asc')
        ->orderBy('id', 'asc')
        ->get();

    $runningBalance = $openingBalance;

    foreach ($journalEntries as $entry) {
        // For Asset accounts: running balance = previous balance + debit - credit
        $runningBalance = $runningBalance + $entry->debit - $entry->credit;
    }

    expect($runningBalance)->toBe(415740.00);
});

test('chart of account ledger handles different date ranges correctly', function () {
    $coa = ChartOfAccount::where('code', '1400.01')->first();

    // Test with date range that excludes the transaction
    $startDate = '2025-01-01';
    $endDate = '2025-11-21'; // Before the transaction date

    $journalEntries = $coa->journalEntries()
        ->whereBetween('date', [$startDate, $endDate])
        ->get();

    expect($journalEntries)->toHaveCount(0);

    // Test with date range that includes the transaction
    $endDate = '2025-11-22';

    $journalEntries = $coa->journalEntries()
        ->whereBetween('date', [$startDate, $endDate])
        ->get();

    expect($journalEntries)->toHaveCount(1);
});

test('chart of account ledger handles liability account balance calculation correctly', function () {
    // Create a test liability account
    $liabilityCoa = ChartOfAccount::factory()->create([
        'type' => 'Liability',
        'opening_balance' => 100000.00,
    ]);

    $startDate = '2025-01-01';

    // Calculate opening balance for liability account
    $openingDebit = $liabilityCoa->journalEntries()->where('date', '<', $startDate)->sum('debit');
    $openingCredit = $liabilityCoa->journalEntries()->where('date', '<', $startDate)->sum('credit');

    // For Liability accounts: Opening Balance = opening_balance - debit + credit
    $openingBalance = $liabilityCoa->opening_balance - $openingDebit + $openingCredit;

    expect($openingBalance)->toBe(100000.00); // No transactions, so should equal opening balance
});