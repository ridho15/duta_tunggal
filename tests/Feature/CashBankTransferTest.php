<?php

use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test COA accounts
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

    $this->adminFeeCoa = ChartOfAccount::factory()->create([
        'code' => '6999',
        'name' => 'Biaya Admin Bank',
        'type' => 'Expense',
        'is_active' => true,
    ]);

    $this->cashBankService = app(CashBankService::class);
});

test('bank transfer without admin fee creates two journal entries', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 1000000,
        'other_costs' => 0,
        'other_costs_coa_id' => null,
        'status' => 'draft',
    ]);

    $this->cashBankService->postTransfer($transfer);

    // Verify transfer status updated to posted
    expect($transfer->fresh()->status)->toBe('posted');

    // Verify exactly 2 journal entries created
    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    expect($journals)->toHaveCount(2);

    // Verify Credit entry (from account)
    $creditEntry = $journals->where('credit', '>', 0)->first();
    expect($creditEntry)
        ->coa_id->toBe($this->fromCoa->id)
        ->credit->toEqual(1000000.0)
        ->debit->toEqual(0.0)
        ->reference->toContain($transfer->number);

    // Verify Debit entry (to account)
    $debitEntry = $journals->where('debit', '>', 0)->first();
    expect($debitEntry)
        ->coa_id->toBe($this->toCoa->id)
        ->debit->toEqual(1000000.0)
        ->credit->toEqual(0.0)
        ->reference->toContain($transfer->number);
});

test('bank transfer with admin fee creates three journal entries', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 1000000,
        'other_costs' => 5000,
        'other_costs_coa_id' => $this->adminFeeCoa->id,
        'status' => 'draft',
    ]);

    $this->cashBankService->postTransfer($transfer);

    // Verify transfer status updated
    expect($transfer->fresh()->status)->toBe('posted');

    // Verify exactly 3 journal entries created
    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    expect($journals)->toHaveCount(3);

    // Verify Credit entry (from account) - total amount
    $creditEntry = $journals->where('credit', '>', 0)
        ->where('coa_id', $this->fromCoa->id)
        ->first();
    
    expect($creditEntry)
        ->coa_id->toBe($this->fromCoa->id)
        ->credit->toEqual(1005000.0) // amount + other_costs
        ->debit->toEqual(0.0);

    // Verify Debit entry (to account) - amount only
    $debitToEntry = $journals->where('debit', '>', 0)
        ->where('coa_id', $this->toCoa->id)
        ->first();
    
    expect($debitToEntry)
        ->coa_id->toBe($this->toCoa->id)
        ->debit->toEqual(1000000.0) // amount only
        ->credit->toEqual(0.0);

    // Verify Debit entry (admin fee account) - other_costs only
    $debitFeeEntry = $journals->where('debit', '>', 0)
        ->where('coa_id', $this->adminFeeCoa->id)
        ->first();
    
    expect($debitFeeEntry)
        ->coa_id->toBe($this->adminFeeCoa->id)
        ->debit->toEqual(5000.0) // other_costs only
        ->credit->toEqual(0.0);

    // Verify balance: total debits = total credits
    $totalDebits = $journals->sum('debit');
    $totalCredits = $journals->sum('credit');
    expect($totalDebits)->toBe($totalCredits);
});

test('bank transfer with admin fee but no coa creates two journal entries', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 1000000,
        'other_costs' => 5000,
        'other_costs_coa_id' => null, // No COA specified
        'status' => 'draft',
    ]);

    $this->cashBankService->postTransfer($transfer);

    // Verify only 2 journal entries created (admin fee not posted separately)
    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    expect($journals)->toHaveCount(2);

    // Credit should be total amount
    $creditEntry = $journals->where('credit', '>', 0)->first();
    expect($creditEntry->credit)->toEqual(1005000.0);

    // Debit to account should be amount only
    $debitEntry = $journals->where('debit', '>', 0)->first();
    expect($debitEntry->debit)->toEqual(1000000.0);
});

test('bank transfer factory with admin fee state creates proper data', function () {
    $transfer = CashBankTransfer::factory()->withAdminFee()->create();

    expect($transfer)
        ->other_costs->toBeGreaterThan(0)
        ->other_costs_coa_id->not->toBeNull();

    $coa = $transfer->otherCostsCoa;
    expect($coa)
        ->code->toBe('6999')
        ->name->toBe('Biaya Admin Bank')
        ->type->toBe('Expense');
});

test('bank transfer posted event is dispatched', function () {
    Event::fake();

    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 1000000,
        'status' => 'draft',
    ]);

    $this->cashBankService->postTransfer($transfer);

    Event::assertDispatched(\App\Events\TransferPosted::class, function ($event) use ($transfer) {
        return $event->transfer->id === $transfer->id;
    });
});

test('bank transfer cannot be posted twice', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 1000000,
        'status' => 'draft',
    ]);

    // First post should succeed
    $this->cashBankService->postTransfer($transfer);
    expect($transfer->fresh()->status)->toBe('posted');

    // Verify 2 journal entries
    $journalCountBefore = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->count();

    // Second post should not create duplicate entries (service clears previous entries)
    $this->cashBankService->postTransfer($transfer);
    
    $journalCountAfter = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->count();

    expect($journalCountBefore)->toBe($journalCountAfter);
});

test('bank transfer with large admin fee calculates correctly', function () {
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $this->fromCoa->id,
        'to_coa_id' => $this->toCoa->id,
        'amount' => 1000000,
        'other_costs' => 50000, // 5% admin fee
        'other_costs_coa_id' => $this->adminFeeCoa->id,
        'status' => 'draft',
    ]);

    $this->cashBankService->postTransfer($transfer);

    $journals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    // Verify amounts
    $creditEntry = $journals->where('credit', '>', 0)->first();
    $debitToEntry = $journals->where('debit', '>', 0)
        ->where('coa_id', $this->toCoa->id)
        ->first();
    $debitFeeEntry = $journals->where('debit', '>', 0)
        ->where('coa_id', $this->adminFeeCoa->id)
        ->first();

    expect($creditEntry->credit)->toEqual(1050000.0);
    expect($debitToEntry->debit)->toEqual(1000000.0);
    expect($debitFeeEntry->debit)->toEqual(50000.0);

    // Verify balance
    expect($debitToEntry->debit + $debitFeeEntry->debit)->toEqual($creditEntry->credit);
});
