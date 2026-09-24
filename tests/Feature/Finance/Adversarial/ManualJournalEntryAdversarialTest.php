<?php

use App\Exceptions\ClosedPeriodException;
use App\Models\AccountingPeriod;
use App\Models\Cabang;
use App\Models\CashBankTransaction;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\CashBankService;
use App\Traits\JournalValidationTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = finContext();
});

test('JRN-BV-01: Unbalanced manual entry validation throws Exception via JournalValidationTrait', function () {
    $validator = new class {
        use JournalValidationTrait;

        public function testValidate(array $entries): void
        {
            $this->validateJournalEntries($entries);
        }
    };

    // 1. Array entries tidak seimbang: Debit 100.000 vs Kredit 85.000 (Selisih 15.000)
    $unbalancedArrays = [
        ['debit' => 100000, 'credit' => 0],
        ['debit' => 0, 'credit' => 85000],
    ];

    expect(fn () => $validator->testValidate($unbalancedArrays))
        ->toThrow(\Exception::class, 'Journal entries are not balanced');

    // 2. Entries seimbang: Debit 100.000 vs Kredit 100.000
    $balancedArrays = [
        ['debit' => 100000, 'credit' => 0],
        ['debit' => 0, 'credit' => 100000],
    ];

    expect(fn () => $validator->testValidate($balancedArrays))
        ->not->toThrow(\Exception::class);

    // 3. Entries dalam batas toleransi pembulatan (< 0.01)
    $roundedArrays = [
        ['debit' => 100000.004, 'credit' => 0],
        ['debit' => 0, 'credit' => 100000.001],
    ];

    expect(fn () => $validator->testValidate($roundedArrays))
        ->not->toThrow(\Exception::class);
});

test('JRN-BV-02: Posting journal entry into closed accounting period throws ClosedPeriodException', function () {
    // Tutup periode akuntansi bulan berjalan untuk cabang ini
    AccountingPeriod::create([
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'status' => 'closed',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    // Percobaan membuat jurnal manual pada tanggal di dalam periode tertutup harus dicegah
    expect(function () {
        JournalEntry::create([
            'coa_id' => $this->ctx['kasUtama']->id,
            'date' => now()->toDateString(),
            'reference' => 'JRN-MANUAL-FAIL',
            'debit' => 50000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->ctx['cabang']->id,
        ]);
    })->toThrow(ClosedPeriodException::class, 'sudah ditutup');

    // Pastikan tidak ada jurnal yang tersimpan
    expect(JournalEntry::where('reference', 'JRN-MANUAL-FAIL')->count())->toBe(0);
});

test('JRN-BV-03: Cross-branch isolation: JournalEntry adheres to CabangScope preventing leakage across branches', function () {
    // Cabang B terpisah
    $cabangB = Cabang::factory()->create([
        'kode' => 'FIN-B-' . strtoupper(substr(uniqid(), -4)),
        'nama' => 'Cabang Keuangan B',
        'status' => 1,
    ]);

    $userA = User::factory()->create([
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'cabang', // Bukan 'all'
    ]);

    $userB = User::factory()->create([
        'cabang_id' => $cabangB->id,
        'manage_type' => 'cabang', // Bukan 'all'
    ]);

    // Buat jurnal di Cabang A
    $entryA = JournalEntry::create([
        'coa_id' => $this->ctx['kasUtama']->id,
        'date' => now()->toDateString(),
        'reference' => 'JRN-CABANG-A',
        'debit' => 100000,
        'credit' => 0,
        'journal_type' => 'manual',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    // Buat jurnal di Cabang B
    $entryB = JournalEntry::create([
        'coa_id' => $this->ctx['kasUtama']->id,
        'date' => now()->toDateString(),
        'reference' => 'JRN-CABANG-B',
        'debit' => 200000,
        'credit' => 0,
        'journal_type' => 'manual',
        'cabang_id' => $cabangB->id,
    ]);

    // Login sebagai User B -> Hanya boleh melihat jurnal Cabang B
    Auth::login($userB);
    $entriesViewedByB = JournalEntry::all();
    expect($entriesViewedByB->pluck('id')->all())->toContain($entryB->id);
    expect($entriesViewedByB->pluck('id')->all())->not->toContain($entryA->id);

    // Login sebagai User A -> Hanya boleh melihat jurnal Cabang A
    Auth::login($userA);
    $entriesViewedByA = JournalEntry::all();
    expect($entriesViewedByA->pluck('id')->all())->toContain($entryA->id);
    expect($entriesViewedByA->pluck('id')->all())->not->toContain($entryB->id);
});

test('JRN-BV-04: System-generated journals cascade-deleted when source transaction is removed', function () {
    $service = app(CashBankService::class);
    $trx = finTransaction($this->ctx, 'cash_in', 650000);
    $service->postTransaction($trx);

    $journalQuery = JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trx->id);

    expect($journalQuery->count())->toBe(2);

    // Hapus transaksi kas
    $trx->delete();

    // Jurnal otomatis terhapus (cascading)
    expect($journalQuery->count())->toBe(0);
});
