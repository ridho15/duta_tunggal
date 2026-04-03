<?php

/**
 * ============================================================
 * Feature Tests: TrialBalanceService
 *
 * TC-TB-001  Basic computation – debit/credit in period
 * TC-TB-002  Beginning balance includes pre-period entries
 * TC-TB-003  Opening balance on COA included in beginning balance
 * TC-TB-004  Credit-normal accounts (Liability, Revenue, Equity)
 * TC-TB-005  Cabang (branch) filter – only entries for that branch
 * TC-TB-006  Show zero balance flag
 * TC-TB-007  Grand totals match sum of rows
 * TC-TB-008  Negative ending balance rendered with parentheses
 * TC-TB-009  Hierarchical ordering: parent before children
 * ============================================================
 */

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Cabang;
use App\Services\TrialBalanceService;

beforeEach(function () {
    $this->service = new TrialBalanceService();

    // Reference branch
    $this->branch = Cabang::factory()->create([
        'kode' => 'TBR',
        'nama' => 'Trial Balance Branch',
    ]);

    // Create a minimal chart of accounts
    $this->cash = ChartOfAccount::factory()->create([
        'code' => '1-0001',
        'name' => 'Kas',
        'type' => 'Asset',
        'is_active' => true,
        'opening_balance' => 0,
    ]);

    $this->receivable = ChartOfAccount::factory()->create([
        'code' => '1-0002',
        'name' => 'Piutang Usaha',
        'type' => 'Asset',
        'is_active' => true,
        'opening_balance' => 0,
    ]);

    $this->payable = ChartOfAccount::factory()->create([
        'code' => '2-0001',
        'name' => 'Hutang Usaha',
        'type' => 'Liability',
        'is_active' => true,
        'opening_balance' => 0,
    ]);

    $this->revenue = ChartOfAccount::factory()->create([
        'code' => '4-0001',
        'name' => 'Pendapatan Penjualan',
        'type' => 'Revenue',
        'is_active' => true,
        'opening_balance' => 0,
    ]);

    $this->expense = ChartOfAccount::factory()->create([
        'code' => '5-0001',
        'name' => 'HPP',
        'type' => 'Expense',
        'is_active' => true,
        'opening_balance' => 0,
    ]);
});

// ────────────────────────────────────────────────────────────
// TC-TB-001  Basic debit/credit in period
// ────────────────────────────────────────────────────────────
test('TC-TB-001 basic period debit credit are computed correctly', function () {
    $start = '2025-01-01';
    $end   = '2025-01-31';

    // Cash receives 500k debit
    JournalEntry::factory()->create([
        'coa_id' => $this->cash->id,
        'date'   => '2025-01-15',
        'debit'  => 500_000,
        'credit' => 0,
        'journal_type' => 'manual',
    ]);

    // Revenue receives 500k credit
    JournalEntry::factory()->create([
        'coa_id' => $this->revenue->id,
        'date'   => '2025-01-15',
        'debit'  => 0,
        'credit' => 500_000,
        'journal_type' => 'manual',
    ]);

    $result = $this->service->generate(['start_date' => $start, 'end_date' => $end]);
    $rows   = collect($result['rows'])->keyBy('code');

    // Cash (Asset/Debit-normal): ending = 0 + 500k - 0 = 500k
    expect($rows['1-0001']->period_debit)->toBe(500_000.0)
        ->and($rows['1-0001']->period_credit)->toBe(0.0)
        ->and($rows['1-0001']->ending_balance)->toBe(500_000.0);

    // Revenue (Credit-normal): ending = 0 - 0 + 500k = 500k
    expect($rows['4-0001']->period_credit)->toBe(500_000.0)
        ->and($rows['4-0001']->ending_balance)->toBe(500_000.0);
});

// ────────────────────────────────────────────────────────────
// TC-TB-002  Beginning balance includes pre-period entries
// ────────────────────────────────────────────────────────────
test('TC-TB-002 beginning balance includes pre-period journal entries', function () {
    $start = '2025-02-01';
    $end   = '2025-02-28';

    // Pre-period entry (January) – cash in
    JournalEntry::factory()->create([
        'coa_id' => $this->cash->id,
        'date'   => '2025-01-20',
        'debit'  => 1_000_000,
        'credit' => 0,
        'journal_type' => 'manual',
    ]);

    // Period entry (February) – cash out
    JournalEntry::factory()->create([
        'coa_id' => $this->cash->id,
        'date'   => '2025-02-10',
        'debit'  => 0,
        'credit' => 200_000,
        'journal_type' => 'manual',
    ]);

    $result = $this->service->generate(['start_date' => $start, 'end_date' => $end]);
    $rows   = collect($result['rows'])->keyBy('code');

    expect($rows['1-0001']->beginning_balance)->toBe(1_000_000.0)
        ->and($rows['1-0001']->period_debit)->toBe(0.0)
        ->and($rows['1-0001']->period_credit)->toBe(200_000.0)
        ->and($rows['1-0001']->ending_balance)->toBe(800_000.0);
});

// ────────────────────────────────────────────────────────────
// TC-TB-003  Opening balance on COA is part of beginning balance
// ────────────────────────────────────────────────────────────
test('TC-TB-003 opening balance on COA is included in beginning balance', function () {
    $this->cash->update(['opening_balance' => 5_000_000]);

    $start = '2025-01-01';
    $end   = '2025-01-31';

    $result = $this->service->generate(['start_date' => $start, 'end_date' => $end]);
    $rows   = collect($result['rows'])->keyBy('code');

    // No journal entries at all – beginning balance = opening_balance
    expect($rows['1-0001']->beginning_balance)->toBe(5_000_000.0)
        ->and($rows['1-0001']->ending_balance)->toBe(5_000_000.0);
});

// ────────────────────────────────────────────────────────────
// TC-TB-004  Credit-normal accounts (Liability)
// ────────────────────────────────────────────────────────────
test('TC-TB-004 liability credit-normal balance computed correctly', function () {
    $start = '2025-01-01';
    $end   = '2025-01-31';

    // Liability grows with credit
    JournalEntry::factory()->create([
        'coa_id' => $this->payable->id,
        'date'   => '2025-01-10',
        'debit'  => 0,
        'credit' => 750_000,
        'journal_type' => 'manual',
    ]);

    $result = $this->service->generate(['start_date' => $start, 'end_date' => $end]);
    $rows   = collect($result['rows'])->keyBy('code');

    expect($rows['2-0001']->normal_balance)->toBe('C')
        ->and($rows['2-0001']->period_credit)->toBe(750_000.0)
        ->and($rows['2-0001']->ending_balance)->toBe(750_000.0);
});

// ────────────────────────────────────────────────────────────
// TC-TB-005  Branch filter
// ────────────────────────────────────────────────────────────
test('TC-TB-005 branch filter excludes entries from other branches', function () {
    $start = '2025-01-01';
    $end   = '2025-01-31';

    $otherBranch = Cabang::factory()->create(['kode' => 'OTH', 'nama' => 'Other']);

    // Entry for our branch
    JournalEntry::factory()->create([
        'coa_id'    => $this->cash->id,
        'date'      => '2025-01-05',
        'debit'     => 300_000,
        'credit'    => 0,
        'cabang_id' => $this->branch->id,
        'journal_type' => 'manual',
    ]);

    // Entry for other branch – should NOT appear
    JournalEntry::factory()->create([
        'coa_id'    => $this->cash->id,
        'date'      => '2025-01-05',
        'debit'     => 999_999,
        'credit'    => 0,
        'cabang_id' => $otherBranch->id,
        'journal_type' => 'manual',
    ]);

    $result = $this->service->generate([
        'start_date' => $start,
        'end_date'   => $end,
        'cabang_id'  => $this->branch->id,
    ]);
    $rows = collect($result['rows'])->keyBy('code');

    expect($rows['1-0001']->period_debit)->toBe(300_000.0);
});

// ────────────────────────────────────────────────────────────
// TC-TB-006  Show zero balance flag
// ────────────────────────────────────────────────────────────
test('TC-TB-006 show_zero_balance=false hides accounts with all-zero values', function () {
    // No journal entries – all accounts have zero balances
    $result = $this->service->generate([
        'start_date'        => '2025-01-01',
        'end_date'          => '2025-01-31',
        'show_zero_balance' => false,
    ]);

    expect($result['rows'])->toBeEmpty();
});

test('TC-TB-006b show_zero_balance=true includes zero-balance accounts', function () {
    $result = $this->service->generate([
        'start_date'        => '2025-01-01',
        'end_date'          => '2025-01-31',
        'show_zero_balance' => true,
    ]);

    // All 5 test accounts should appear
    expect(count($result['rows']))->toBeGreaterThanOrEqual(5);
});

// ────────────────────────────────────────────────────────────
// TC-TB-007  Grand totals match sum of rows
// ────────────────────────────────────────────────────────────
test('TC-TB-007 grand totals match sum of individual rows', function () {
    $start = '2025-03-01';
    $end   = '2025-03-31';

    JournalEntry::factory()->create([
        'coa_id' => $this->cash->id, 'date' => '2025-03-05',
        'debit' => 100_000, 'credit' => 0, 'journal_type' => 'manual',
    ]);
    JournalEntry::factory()->create([
        'coa_id' => $this->expense->id, 'date' => '2025-03-10',
        'debit' => 50_000, 'credit' => 0, 'journal_type' => 'manual',
    ]);
    JournalEntry::factory()->create([
        'coa_id' => $this->revenue->id, 'date' => '2025-03-15',
        'debit' => 0, 'credit' => 100_000, 'journal_type' => 'manual',
    ]);
    JournalEntry::factory()->create([
        'coa_id' => $this->payable->id, 'date' => '2025-03-15',
        'debit' => 0, 'credit' => 50_000, 'journal_type' => 'manual',
    ]);

    $result = $this->service->generate(['start_date' => $start, 'end_date' => $end]);
    $rows   = $result['rows'];
    $totals = $result['grand_totals'];

    $expectedDebit  = collect($rows)->sum('period_debit');
    $expectedCredit = collect($rows)->sum('period_credit');
    $expectedBegin  = collect($rows)->sum('beginning_balance');
    $expectedEnd    = collect($rows)->sum('ending_balance');

    expect($totals['period_debit'])->toBe($expectedDebit)
        ->and($totals['period_credit'])->toBe($expectedCredit)
        ->and($totals['beginning_balance'])->toBe($expectedBegin)
        ->and($totals['ending_balance'])->toBe($expectedEnd);
});

// ────────────────────────────────────────────────────────────
// TC-TB-008  Normal balance flags
// ────────────────────────────────────────────────────────────
test('TC-TB-008 normal balance flag is D for asset and expense, C for others', function () {
    $result = $this->service->generate([
        'start_date'        => '2025-01-01',
        'end_date'          => '2025-01-31',
        'show_zero_balance' => true,
    ]);
    $rows = collect($result['rows'])->keyBy('code');

    expect($rows['1-0001']->normal_balance)->toBe('D') // Asset
        ->and($rows['5-0001']->normal_balance)->toBe('D') // Expense
        ->and($rows['2-0001']->normal_balance)->toBe('C') // Liability
        ->and($rows['4-0001']->normal_balance)->toBe('C'); // Revenue
});

// ────────────────────────────────────────────────────────────
// TC-TB-009  Hierarchical ordering
// ────────────────────────────────────────────────────────────
test('TC-TB-009 parent account appears before its children in ordered rows', function () {
    $parent = ChartOfAccount::factory()->create([
        'code'          => '1-P000',
        'name'          => 'Kas & Bank',
        'type'          => 'Asset',
        'is_active'     => true,
        'parent_id'     => null,
        'opening_balance' => 0,
    ]);

    $child = ChartOfAccount::factory()->create([
        'code'          => '1-C001',
        'name'          => 'Kas Besar',
        'type'          => 'Asset',
        'is_active'     => true,
        'parent_id'     => $parent->id,
        'opening_balance' => 0,
    ]);

    // Give child a non-zero balance so it appears
    JournalEntry::factory()->create([
        'coa_id' => $child->id, 'date' => '2025-04-10',
        'debit' => 1_000, 'credit' => 0, 'journal_type' => 'manual',
    ]);
    JournalEntry::factory()->create([
        'coa_id' => $parent->id, 'date' => '2025-04-10',
        'debit' => 1_000, 'credit' => 0, 'journal_type' => 'manual',
    ]);

    $result = $this->service->generate([
        'start_date' => '2025-04-01',
        'end_date'   => '2025-04-30',
    ]);

    $codes  = collect($result['rows'])->pluck('code')->toArray();
    $pIdx   = array_search('1-P000', $codes);
    $cIdx   = array_search('1-C001', $codes);

    expect($pIdx)->not->toBeFalse()
        ->and($cIdx)->not->toBeFalse()
        ->and($pIdx)->toBeLessThan($cIdx);
});
