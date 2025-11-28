<?php

use App\Events\TransferPosted;
use App\Models\BankReconciliation;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto creates bank reconciliation when transfer is posted', function () {
    // Create COAs
    $fromCoa = ChartOfAccount::factory()->create(['code' => '1111001', 'name' => 'Bank A']);
    $toCoa = ChartOfAccount::factory()->create(['code' => '1112001', 'name' => 'Bank B']);

    // Create transfer
    $transfer = CashBankTransfer::factory()->create([
        'from_coa_id' => $fromCoa->id,
        'to_coa_id' => $toCoa->id,
        'amount' => 100000,
        'date' => now(),
        'status' => 'draft',
    ]);

    // Post transfer
    app(CashBankService::class)->postTransfer($transfer);

    // Refresh transfer
    $transfer->refresh();

    // Assert status posted
    expect($transfer->status)->toBe('posted');

    // Assert reconciliations created
    $reconFrom = BankReconciliation::where('coa_id', $fromCoa->id)->first();
    $reconTo = BankReconciliation::where('coa_id', $toCoa->id)->first();

    expect($reconFrom)->not->toBeNull();
    expect($reconTo)->not->toBeNull();

    expect($reconFrom->status)->toBe('open');
    expect($reconTo->status)->toBe('open');

    // Assert journal entries reconciled
    $entriesFrom = \App\Models\JournalEntry::where('source_type', \App\Models\CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->where('coa_id', $fromCoa->id)
        ->get();
    $entriesTo = \App\Models\JournalEntry::where('source_type', \App\Models\CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->where('coa_id', $toCoa->id)
        ->get();

    expect($entriesFrom->every(fn($e) => $e->bank_recon_id === $reconFrom->id))->toBeTrue();
    expect($entriesTo->every(fn($e) => $e->bank_recon_id === $reconTo->id))->toBeTrue();
});