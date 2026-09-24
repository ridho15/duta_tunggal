<?php

use App\Models\CashBankTransfer;
use App\Models\JournalEntry;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = finContext();
    $this->service = app(CashBankService::class);
});

test('TRF-BV-01: Transfer with non-zero admin fee debits admin COA and credits source COA for (amount + other_costs), balanced', function () {
    $amount = 1000000.0;
    $adminFee = 6500.0;
    $totalSourceOutflow = $amount + $adminFee;

    $transfer = finTransfer(
        $this->ctx,
        amount: $amount,
        otherCosts: $adminFee,
        fromCoa: $this->ctx['bankBca'],
        toCoa: $this->ctx['bankMandiri'],
        attributes: [
            'other_costs_coa_id' => $this->ctx['bebanAdminBank']->id,
        ]
    );

    $this->service->postTransfer($transfer);
    $transfer->refresh();

    expect($transfer->status)->toBe('posted');

    $entries = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    expect($entries)->toHaveCount(3);

    // 1. Kredit ke Rekening Asal (BCA) sebesar total (amount + other_costs)
    $sourceEntry = $entries->firstWhere('coa_id', $this->ctx['bankBca']->id);
    expect($sourceEntry)->not->toBeNull();
    expect((float)$sourceEntry->credit)->toBe($totalSourceOutflow);
    expect((float)$sourceEntry->debit)->toBe(0.0);

    // 2. Debit ke Rekening Tujuan (Mandiri) sebesar net amount
    $destEntry = $entries->firstWhere('coa_id', $this->ctx['bankMandiri']->id);
    expect($destEntry)->not->toBeNull();
    expect((float)$destEntry->debit)->toBe($amount);
    expect((float)$destEntry->credit)->toBe(0.0);

    // 3. Debit ke Akun Beban Administrasi Bank sebesar other_costs
    $feeEntry = $entries->firstWhere('coa_id', $this->ctx['bebanAdminBank']->id);
    expect($feeEntry)->not->toBeNull();
    expect((float)$feeEntry->debit)->toBe($adminFee);
    expect((float)$feeEntry->credit)->toBe(0.0);

    // Verifikasi total seimbang
    expect((float)$entries->sum('debit'))->toBe($totalSourceOutflow);
    expect((float)$entries->sum('credit'))->toBe($totalSourceOutflow);
});

test('TRF-BV-02: Transfer to same source & destination COA maintains net zero balance impact and balanced entries', function () {
    // Skenario anomali: Akun asal dan tujuan sama (Kas Utama)
    $amount = 250000.0;
    $transfer = finTransfer(
        $this->ctx,
        amount: $amount,
        otherCosts: 0,
        fromCoa: $this->ctx['kasUtama'],
        toCoa: $this->ctx['kasUtama']
    );

    $this->service->postTransfer($transfer);

    $entries = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();

    expect($entries)->toHaveCount(2);

    $debitTotal = (float)$entries->sum('debit');
    $creditTotal = (float)$entries->sum('credit');

    // Keduanya bernilai 250.000 sehingga delta net buku kas = 0
    expect($debitTotal)->toBe($amount);
    expect($creditTotal)->toBe($amount);
    expect($debitTotal - $creditTotal)->toBe(0.0);
});

test('TRF-BV-03: Soft delete of CashBankTransfer cascade-deletes its journal entries and restoration reposts them', function () {
    $transfer = finTransfer(
        $this->ctx,
        amount: 750000.0,
        otherCosts: 2500.0,
        fromCoa: $this->ctx['bankBca'],
        toCoa: $this->ctx['kasUtama'],
        attributes: [
            'other_costs_coa_id' => $this->ctx['bebanAdminBank']->id,
        ]
    );

    $this->service->postTransfer($transfer);

    // Pastikan 3 jurnal terbit
    expect(JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)->count())->toBe(3);

    // Lakukan soft-delete
    $transfer->delete();

    // Observer CashBankTransferObserver::deleted() harus membersihkan semua jurnal terkait
    expect(JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)->count())->toBe(0);

    // Lakukan pemulihan (restore)
    $transfer->restore();

    // Observer CashBankTransferObserver::restored() harus mem-posting ulang jurnal
    $restoredEntries = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)->get();

    expect($restoredEntries)->toHaveCount(3);
    expect((float)$restoredEntries->sum('debit'))->toBe(752500.0);
    expect((float)$restoredEntries->sum('credit'))->toBe(752500.0);
});
