<?php

use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(CashBankService::class);
    
    $this->fromCoa = ChartOfAccount::factory()->create([
        'code' => '1101',
        'name' => 'Kas Bank A',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $this->toCoa = ChartOfAccount::factory()->create([
        'code' => '1102',
        'name' => 'Kas Bank B',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $this->expenseCoa = ChartOfAccount::factory()->create([
        'code' => '6999',
        'name' => 'Biaya Admin Bank',
        'type' => 'Expense',
        'is_active' => true,
    ]);
});

test('postTransfer creates correct journal entries without admin fee', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 500000,
        'other_costs' => 0,
        'other_costs_coa_id' => null,
        'status' => 'draft',
    ]);

    DB::beginTransaction();
    $this->service->postTransfer($transfer);
    DB::commit();

    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    expect($journals)->toHaveCount(2);
    
    // Check credit entry
    $credit = $journals->where('credit', '>', 0)->first();
    expect($credit)
        ->coa_id->toBe($this->fromCoa->id)
        ->credit->toEqual(500000.0)
        ->debit->toEqual(0.0);

    // Check debit entry
    $debit = $journals->where('debit', '>', 0)->first();
    expect($debit)
        ->coa_id->toBe($this->toCoa->id)
        ->debit->toEqual(500000.0)
        ->credit->toEqual(0.0);
});

test('postTransfer creates correct journal entries with admin fee and coa', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 500000,
        'other_costs' => 2500,
        'other_costs_coa_id' => $this->expenseCoa->id,
        'status' => 'draft',
    ]);

    DB::beginTransaction();
    $this->service->postTransfer($transfer);
    DB::commit();

    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    expect($journals)->toHaveCount(3);

    // Credit from account (total)
    $credit = $journals->where('credit', '>', 0)
        ->where('coa_id', $this->fromCoa->id)
        ->first();
    expect($credit->credit)->toEqual(502500.0);
    expect($credit->debit)->toEqual(0.0);

    // Debit to account (amount only)
    $debitTo = $journals->where('debit', '>', 0)
        ->where('coa_id', $this->toCoa->id)
        ->first();
    expect($debitTo->debit)->toEqual(500000.0);
    expect($debitTo->credit)->toEqual(0.0);

    // Debit expense account (admin fee only)
    $debitExpense = $journals->where('debit', '>', 0)
        ->where('coa_id', $this->expenseCoa->id)
        ->first();
    expect($debitExpense->debit)->toEqual(2500.0);
    expect($debitExpense->credit)->toEqual(0.0);
});

test('postTransfer with admin fee but no coa only creates two entries', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 500000,
        'other_costs' => 2500,
        'other_costs_coa_id' => null, // No COA
        'status' => 'draft',
    ]);

    DB::beginTransaction();
    $this->service->postTransfer($transfer);
    DB::commit();

    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    // Should only create 2 entries (no separate admin fee entry)
    expect($journals)->toHaveCount(2);

    // Credit should be total
    $credit = $journals->where('credit', '>', 0)->first();
    expect($credit->credit)->toEqual(502500.0);

    // Debit should be amount only
    $debit = $journals->where('debit', '>', 0)->first();
    expect($debit->debit)->toEqual(500000.0);
});

test('postTransfer maintains double entry balance with admin fee', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 1000000,
        'other_costs' => 15000,
        'other_costs_coa_id' => $this->expenseCoa->id,
        'status' => 'draft',
    ]);

    DB::beginTransaction();
    $this->service->postTransfer($transfer);
    DB::commit();

    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    $totalDebits = $journals->sum('debit');
    $totalCredits = $journals->sum('credit');

    expect($totalDebits)->toBe($totalCredits)
        ->and($totalDebits)->toEqual(1015000.0);
});

test('postTransfer updates transfer status to posted', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 100000,
        'status' => 'draft',
    ]);

    expect($transfer->status)->toBe('draft');

    DB::beginTransaction();
    $this->service->postTransfer($transfer);
    DB::commit();

    expect($transfer->fresh()->status)->toBe('posted');
});

test('postTransfer handles zero admin fee correctly', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 250000,
        'other_costs' => 0,
        'other_costs_coa_id' => $this->expenseCoa->id, // COA set but amount is 0
        'status' => 'draft',
    ]);

    DB::beginTransaction();
    $this->service->postTransfer($transfer);
    DB::commit();

    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    // Should only create 2 entries (no admin fee entry when amount is 0)
    expect($journals)->toHaveCount(2);
});

test('postTransfer uses correct descriptions in journal entries', function () {
    $transfer = CashBankTransfer::factory()->create([
        'number' => 'TRF-TEST-001',
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 100000,
        'other_costs' => 5000,
        'other_costs_coa_id' => $this->expenseCoa->id,
        'description' => 'Test transfer',
        'status' => 'draft',
    ]);

    DB::beginTransaction();
    $this->service->postTransfer($transfer);
    DB::commit();

    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    // All entries should contain transfer number
    $journals->each(function ($journal) {
        expect($journal->reference)->toBe('TRF-TEST-001');
    });
});

test('postTransfer records correct transaction date', function () {
    $transferDate = now()->subDays(5);
    
    $transfer = CashBankTransfer::factory()->create([
        'date' => $transferDate,
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 100000,
        'status' => 'draft',
    ]);

    DB::beginTransaction();
    $this->service->postTransfer($transfer);
    DB::commit();

    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    $journals->each(function ($journal) use ($transferDate) {
        expect(\Carbon\Carbon::parse($journal->date)->format('Y-m-d'))
            ->toBe($transferDate->format('Y-m-d'));
    });
});
