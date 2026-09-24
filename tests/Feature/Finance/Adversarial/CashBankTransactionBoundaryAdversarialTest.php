<?php

use App\Models\CashBankTransaction;
use App\Models\CashBankTransactionDetail;
use App\Models\JournalEntry;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = finContext();
    $this->service = app(CashBankService::class);
});

test('CBT-BV-01: Detail breakdown amount mismatch (sum details != header amount) throws exception', function () {
    // Header menyatakan pengeluaran 100.000
    $trx = finTransaction($this->ctx, 'cash_out', 100000);

    // Detail hanya berjumlah 95.000 (selisih 5.000)
    CashBankTransactionDetail::create([
        'cash_bank_transaction_id' => $trx->id,
        'chart_of_account_id' => $this->ctx['bebanOperasional']->id,
        'amount' => 95000,
        'description' => 'Biaya operasional parsial',
    ]);

    $trx->load('transactionDetails');

    // Harap melempar Exception karena selisih > 0.01
    expect(fn () => $this->service->postTransaction($trx))
        ->toThrow(\Exception::class);

    // Pastikan tidak ada jurnal yang menggantung/terbuat akibat kegagalan ini
    expect(JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trx->id)->count())->toBe(0);
});

test('CBT-BV-02: Transaction with withholding tax negative detail balances correctly', function () {
    // Skenario adversarial: Jasa senilai 100.000 dipotong PPh 23 (-2.000), kas keluar net 98.000
    $trx = finTransaction($this->ctx, 'cash_out', 98000);

    CashBankTransactionDetail::create([
        'cash_bank_transaction_id' => $trx->id,
        'chart_of_account_id' => $this->ctx['bebanOperasional']->id,
        'amount' => 100000,
        'description' => 'Gross jasa konsultan',
    ]);

    CashBankTransactionDetail::create([
        'cash_bank_transaction_id' => $trx->id,
        'chart_of_account_id' => $this->ctx['pendapatanLain']->id, // Akun lawan potongan
        'amount' => -2000,
        'description' => 'Potongan PPh pasal 23',
    ]);

    $trx->load('transactionDetails');
    $this->service->postTransaction($trx);

    $entries = JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trx->id)
        ->get();

    expect($entries)->toHaveCount(3);
    $totalDebit = $entries->sum('debit');
    $totalCredit = $entries->sum('credit');

    // Total debit dan kredit harus seimbang pada 100.000
    expect((float)$totalDebit)->toBe(100000.0);
    expect((float)$totalCredit)->toBe(100000.0);
});

test('CBT-BV-03: Repeated posting idempotency maintains clean, non-duplicate balanced journals', function () {
    $trx = finTransaction($this->ctx, 'bank_in', 500000);

    // Posting pertama kali
    $this->service->postTransaction($trx);
    $countFirst = JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trx->id)->count();
    expect($countFirst)->toBe(2);

    // Posting ulang kedua kali secara berturut-turut (simulasi retry / idempotency)
    $this->service->postTransaction($trx);
    $countSecond = JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trx->id)->count();
    expect($countSecond)->toBe(2);

    // Posting ketiga kali
    $this->service->postTransaction($trx);
    $entries = JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trx->id)->get();

    expect($entries)->toHaveCount(2);
    expect((float)$entries->sum('debit'))->toBe(500000.0);
    expect((float)$entries->sum('credit'))->toBe(500000.0);
});

test('CBT-BV-04: Mutation of transaction header amount auto-regenerates journals via observer', function () {
    $trx = finTransaction($this->ctx, 'cash_out', 150000);
    $this->service->postTransaction($trx);

    $initialEntries = JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trx->id)->get();
    expect((float)$initialEntries->sum('debit'))->toBe(150000.0);

    // Mutasi amount pada header transaksi
    $trx->update([
        'amount' => 275000,
    ]);

    // Observer CashBankTransaction::updated() harus mendeteksi perubahan amount
    // dan meregenerasi jurnal secara otomatis
    $updatedEntries = JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trx->id)->get();

    expect($updatedEntries)->toHaveCount(2);
    expect((float)$updatedEntries->sum('debit'))->toBe(275000.0);
    expect((float)$updatedEntries->sum('credit'))->toBe(275000.0);

    // Hapus transaksi (soft delete) -> jurnal harus ikut terhapus via deleting hook
    $trx->delete();
    expect(JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trx->id)->count())->toBe(0);
});
