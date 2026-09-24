<?php

use App\Models\BankReconciliation;
use App\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = finContext();
});

test('REC-BV-01: Book balance calculation dynamically updates based on linked bank_recon_id journals', function () {
    $recon = finReconciliation($this->ctx, $this->ctx['bankBca'], statementEndingBalance: 1300000);

    // Buat jurnal debit (uang masuk) & kredit (uang keluar) pada akun BCA
    $entryIn1 = JournalEntry::create([
        'coa_id' => $this->ctx['bankBca']->id,
        'date' => now()->toDateString(),
        'reference' => 'IN-01',
        'debit' => 1000000,
        'credit' => 0,
        'journal_type' => 'cashbank',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    $entryIn2 = JournalEntry::create([
        'coa_id' => $this->ctx['bankBca']->id,
        'date' => now()->toDateString(),
        'reference' => 'IN-02',
        'debit' => 500000,
        'credit' => 0,
        'journal_type' => 'cashbank',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    $entryOut = JournalEntry::create([
        'coa_id' => $this->ctx['bankBca']->id,
        'date' => now()->toDateString(),
        'reference' => 'OUT-01',
        'debit' => 0,
        'credit' => 200000,
        'journal_type' => 'cashbank',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    // Sebelum ditautkan ke recon, saldo rekonsiliasi adalah 0
    expect((float)$recon->book_balance)->toBe(0.0);

    // Tautkan entryIn1 dan entryOut
    $entryIn1->update(['bank_recon_id' => $recon->id]);
    $entryOut->update(['bank_recon_id' => $recon->id]);

    // Saldo buku yang terekonsiliasi: 1.000.000 - 200.000 = 800.000
    expect((float)$recon->fresh()->book_balance)->toBe(800000.0);

    // Tautkan sisa entryIn2
    $entryIn2->update(['bank_recon_id' => $recon->id]);

    // Saldo buku sekarang: 1.500.000 - 200.000 = 1.300.000
    expect((float)$recon->fresh()->book_balance)->toBe(1300000.0);
});

test('REC-BV-02: Unreconciled entries query excludes reconciled ones and out-of-period entries', function () {
    $recon = finReconciliation($this->ctx, $this->ctx['bankBca']);

    // Entry 1: Dalam periode, BCA, belum rekonsiliasi -> Harap masuk unreconciled
    $validEntry = JournalEntry::create([
        'coa_id' => $this->ctx['bankBca']->id,
        'date' => now()->toDateString(),
        'reference' => 'VALID-01',
        'debit' => 100000,
        'credit' => 0,
        'journal_type' => 'cashbank',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    // Entry 2: Dalam periode, tapi akun berbeda (Mandiri) -> Harus dieksklusikan
    $wrongAccountEntry = JournalEntry::create([
        'coa_id' => $this->ctx['bankMandiri']->id,
        'date' => now()->toDateString(),
        'reference' => 'MANDIRI-01',
        'debit' => 200000,
        'credit' => 0,
        'journal_type' => 'cashbank',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    // Entry 3: BCA, tapi di luar periode (bulan lalu) -> Harus dieksklusikan
    $outOfPeriodEntry = JournalEntry::create([
        'coa_id' => $this->ctx['bankBca']->id,
        'date' => now()->subMonths(2)->toDateString(),
        'reference' => 'OLD-01',
        'debit' => 300000,
        'credit' => 0,
        'journal_type' => 'cashbank',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    // Entry 4: Dalam periode, BCA, tapi sudah terekonsiliasi -> Harus dieksklusikan
    $alreadyReconciledEntry = JournalEntry::create([
        'coa_id' => $this->ctx['bankBca']->id,
        'date' => now()->toDateString(),
        'reference' => 'RECONCILED-01',
        'debit' => 400000,
        'credit' => 0,
        'bank_recon_id' => $recon->id,
        'journal_type' => 'cashbank',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    $unreconciled = $recon->unreconciledEntries()->get();

    expect($unreconciled->pluck('id')->all())->toContain($validEntry->id);
    expect($unreconciled->pluck('id')->all())->not->toContain($wrongAccountEntry->id);
    expect($unreconciled->pluck('id')->all())->not->toContain($outOfPeriodEntry->id);
    expect($unreconciled->pluck('id')->all())->not->toContain($alreadyReconciledEntry->id);
    expect($unreconciled)->toHaveCount(1);
});

test('REC-BV-03: Difference calculation handles exact match, positive, and negative variance', function () {
    $recon = finReconciliation($this->ctx, $this->ctx['bankBca'], statementEndingBalance: 1000000);

    $entry = JournalEntry::create([
        'coa_id' => $this->ctx['bankBca']->id,
        'date' => now()->toDateString(),
        'reference' => 'RECON-ENTRY',
        'debit' => 1000000,
        'credit' => 0,
        'bank_recon_id' => $recon->id,
        'journal_type' => 'cashbank',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    // 1. Exact match (statement 1.000.000 == book 1.000.000)
    expect((float)$recon->difference)->toBe(0.0);

    // 2. Bank statement lebih besar (over-statement, misal bunga bank belum dicatat di buku)
    $recon->statement_ending_balance = 1150000;
    expect((float)$recon->difference)->toBe(150000.0);

    // 3. Bank statement lebih kecil (under-statement, misal biaya admin bank belum dicatat)
    $recon->statement_ending_balance = 920000;
    expect((float)$recon->difference)->toBe(-80000.0);
});
