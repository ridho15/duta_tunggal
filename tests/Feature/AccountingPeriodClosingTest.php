<?php

use App\Exceptions\ClosedPeriodException;
use App\Models\AccountingPeriod;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AccountingPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\ChartOfAccountSeeder::class);
    $this->seed(\Database\Seeders\CabangSeeder::class);
});

test('cannot create journal entry in closed accounting period', function () {
    $cabang = Cabang::first();
    $coa = ChartOfAccount::first();

    AccountingPeriod::create([
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-31',
        'status' => 'closed',
        'cabang_id' => $cabang->id,
        'closed_at' => now(),
    ]);

    expect(function () use ($coa, $cabang) {
        JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => '2026-03-15',
            'reference' => 'LOCK-CRT-001',
            'description' => 'should be blocked',
            'debit' => 10000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $cabang->id,
        ]);
    })->toThrow(ClosedPeriodException::class);
});

test('cannot update journal entry in closed accounting period', function () {
    $cabang = Cabang::first();
    $coa = ChartOfAccount::first();

    AccountingPeriod::create([
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-31',
        'status' => 'open',
        'cabang_id' => $cabang->id,
    ]);

    $entry = JournalEntry::create([
        'coa_id' => $coa->id,
        'date' => '2026-03-15',
        'reference' => 'LOCK-UPD-001',
        'description' => 'created while open',
        'debit' => 5000,
        'credit' => 0,
        'journal_type' => 'manual',
        'cabang_id' => $cabang->id,
    ]);

    AccountingPeriod::where('cabang_id', $cabang->id)
        ->whereDate('period_start', '2026-03-01')
        ->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

    expect(function () use ($entry) {
        $entry->update(['description' => 'blocked update']);
    })->toThrow(ClosedPeriodException::class);
});

test('cannot delete journal entry in closed accounting period', function () {
    $cabang = Cabang::first();
    $coa = ChartOfAccount::first();

    AccountingPeriod::create([
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-31',
        'status' => 'open',
        'cabang_id' => $cabang->id,
    ]);

    $entry = JournalEntry::create([
        'coa_id' => $coa->id,
        'date' => '2026-03-15',
        'reference' => 'LOCK-DEL-001',
        'description' => 'created while open',
        'debit' => 3000,
        'credit' => 0,
        'journal_type' => 'manual',
        'cabang_id' => $cabang->id,
    ]);

    AccountingPeriod::where('cabang_id', $cabang->id)
        ->whereDate('period_start', '2026-03-01')
        ->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

    expect(function () use ($entry) {
        $entry->delete();
    })->toThrow(ClosedPeriodException::class);
});

test('close period posts closing entries and marks period closed', function () {
    $cabang = Cabang::first();

    $revenue = ChartOfAccount::where('type', 'Revenue')->firstOrFail();
    $expense = ChartOfAccount::where('type', 'Expense')->firstOrFail();
    $cash = ChartOfAccount::where('type', 'Asset')->firstOrFail();

    $period = AccountingPeriod::create([
        'period_start' => '2026-03-01',
        'period_end' => '2026-03-31',
        'status' => 'open',
        'cabang_id' => $cabang->id,
    ]);

    JournalEntry::create([
        'coa_id' => $cash->id,
        'date' => '2026-03-10',
        'reference' => 'TXN-REV-001',
        'description' => 'kas dari penjualan',
        'debit' => 100000,
        'credit' => 0,
        'journal_type' => 'manual',
        'cabang_id' => $cabang->id,
    ]);
    JournalEntry::create([
        'coa_id' => $revenue->id,
        'date' => '2026-03-10',
        'reference' => 'TXN-REV-001',
        'description' => 'pendapatan',
        'debit' => 0,
        'credit' => 100000,
        'journal_type' => 'manual',
        'cabang_id' => $cabang->id,
    ]);

    JournalEntry::create([
        'coa_id' => $expense->id,
        'date' => '2026-03-20',
        'reference' => 'TXN-EXP-001',
        'description' => 'beban operasional',
        'debit' => 30000,
        'credit' => 0,
        'journal_type' => 'manual',
        'cabang_id' => $cabang->id,
    ]);
    JournalEntry::create([
        'coa_id' => $cash->id,
        'date' => '2026-03-20',
        'reference' => 'TXN-EXP-001',
        'description' => 'kas keluar',
        'debit' => 0,
        'credit' => 30000,
        'journal_type' => 'manual',
        'cabang_id' => $cabang->id,
    ]);

    $service = app(AccountingPeriodService::class);
    $closedPeriod = $service->closePeriod($period, null, true);

    expect($closedPeriod->status)->toBe('closed')
        ->and($closedPeriod->closed_at)->not->toBeNull();

    $closingEntriesCount = JournalEntry::where('journal_type', 'closing')
        ->where('source_type', AccountingPeriod::class)
        ->where('source_id', $period->id)
        ->count();

    expect($closingEntriesCount)->toBeGreaterThan(0);
});
