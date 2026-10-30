<?php

use App\Models\AccountingPeriod;
use App\Models\Cabang;
use App\Models\CashBankAccount;
use App\Models\CashBankTransaction;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
    $this->ctx = finContext();

    // Buat akun CashBankAccount untuk memetakan COA ke rekening kas/bank
    CashBankAccount::firstOrCreate(
        ['coa_id' => $this->ctx['kasUtama']->id],
        ['name' => 'Kas Utama', 'account_number' => 'KAS-01', 'is_active' => true]
    );

    CashBankAccount::firstOrCreate(
        ['coa_id' => $this->ctx['bankBca']->id],
        ['name' => 'Bank BCA', 'account_number' => 'BCA-8899', 'is_active' => true]
    );

    CashBankAccount::firstOrCreate(
        ['coa_id' => $this->ctx['bankMandiri']->id],
        ['name' => 'Bank Mandiri', 'account_number' => 'MDR-5544', 'is_active' => true]
    );

    // Role 1: Kasir / Petugas Treasury
    $this->kasir = User::factory()->create([
        'name' => 'Andi Kasir Treasury',
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'cabang',
    ]);
    $roleKasir = Role::firstOrCreate(['name' => 'Kasir Treasury', 'guard_name' => 'web']);
    $this->kasir->assignRole($roleKasir);

    // Role 2: Supervisor Keuangan
    $this->supervisorFinance = User::factory()->create([
        'name' => 'Dewi Spv Keuangan',
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'cabang',
    ]);
    $roleSpv = Role::firstOrCreate(['name' => 'Supervisor Keuangan', 'guard_name' => 'web']);
    $this->supervisorFinance->assignRole($roleSpv);

    // Role 3: Senior Accountant
    $this->akuntan = User::factory()->create([
        'name' => 'Bambang Senior Akuntan',
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'cabang',
    ]);
    $roleAkuntan = Role::firstOrCreate(['name' => 'Senior Akuntan', 'guard_name' => 'web']);
    $this->akuntan->assignRole($roleAkuntan);
});

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

/**
 * ADV-FIN-01: Kasir dilarang membukukan transaksi dengan rincian tidak sama dengan header atau akun lawan identik
 */
it('ADV-FIN-01: Validasi transaksi kas/bank menolak mismatch nominal rincian dan menolak akun lawan yang sama dengan akun kas', function () {
    $this->actingAs($this->kasir);

    // Kasus A: Akun lawan sama persis dengan akun kas/bank (self-referencing transfer dilarang via transaksi biasa)
    $selfPayload = [
        'number' => 'CB-ERR-01',
        'date' => now()->toDateString(),
        'type' => 'cash_out',
        'account_coa_id' => $this->ctx['kasUtama']->id,
        'offset_coa_id' => $this->ctx['kasUtama']->id, // COA sama!
        'amount' => 150000,
        'description' => 'Uji akun sama',
    ];

    $responseA = $this->post(route('keuangan.cashbank.store'), $selfPayload);
    $responseA->assertSessionHasErrors('offset_coa_id');

    // Kasus B: Total rincian rincian (Rp 80.000 + Rp 40.000 = Rp 120.000) != header (Rp 150.000)
    $mismatchPayload = [
        'number' => 'CB-ERR-02',
        'date' => now()->toDateString(),
        'type' => 'cash_out',
        'account_coa_id' => $this->ctx['kasUtama']->id,
        'amount' => 150000,
        'description' => 'Uji mismatch breakdown',
        'details' => [
            [
                'chart_of_account_id' => $this->ctx['bebanOperasional']->id,
                'amount' => 80000,
                'description' => 'Biaya A',
            ],
            [
                'chart_of_account_id' => $this->ctx['bebanAdminBank']->id,
                'amount' => 40000,
                'description' => 'Biaya B',
            ],
        ],
    ];

    $responseB = $this->post(route('keuangan.cashbank.store'), $mismatchPayload);
    $responseB->assertSessionHas('error');
    expect(session('error'))->toContain('tidak sama dengan jumlah transaksi');

    // Tidak ada transaksi fiktif yang tersimpan
    expect(CashBankTransaction::where('number', 'CB-ERR-02')->count())->toEqual(0);
});

/**
 * ADV-FIN-02: Transfer antar rekening kas/bank menolak rekening sumber dan tujuan yang sama atau nominal non-positif
 */
it('ADV-FIN-02: Transfer dana menolak akun sumber dan tujuan identik serta menolak nominal non-positif', function () {
    $this->actingAs($this->supervisorFinance);

    // Kasus A: Akun asal = akun tujuan (Bank BCA ke Bank BCA)
    $sameAccountPayload = [
        'number' => 'TRF-ERR-01',
        'date' => now()->toDateString(),
        'from_coa_id' => $this->ctx['bankBca']->id,
        'to_coa_id' => $this->ctx['bankBca']->id,
        'amount' => 1000000,
        'description' => 'Uji transfer akun identik',
    ];

    $responseA = $this->post(route('keuangan.cashbank.transfer.store'), $sameAccountPayload);
    $responseA->assertSessionHasErrors('to_coa_id');

    // Kasus B: Nominal transfer bernilai 0 atau negatif
    $zeroPayload = [
        'number' => 'TRF-ERR-02',
        'date' => now()->toDateString(),
        'from_coa_id' => $this->ctx['bankBca']->id,
        'to_coa_id' => $this->ctx['kasUtama']->id,
        'amount' => 0,
        'description' => 'Uji nominal nol',
    ];

    $responseB = $this->post(route('keuangan.cashbank.transfer.store'), $zeroPayload);
    $responseB->assertSessionHasErrors('amount');

    expect(CashBankTransfer::where('number', 'TRF-ERR-01')->count())->toEqual(0);
    expect(CashBankTransfer::where('number', 'TRF-ERR-02')->count())->toEqual(0);
});

/**
 * ADV-FIN-03: Jurnal Penyesuaian Manual menolak keras ketidakseimbangan debit-kredit
 */
it('ADV-FIN-03: Jurnal Penyesuaian Manual menolak entri yang tidak seimbang (debit != kredit) dan entri kurang dari 2 baris', function () {
    $this->actingAs($this->akuntan);

    // Kasus A: Baris jurnal tidak seimbang (Debit Rp 500.000 vs Kredit Rp 450.000 -> Deviasi Rp 50.000)
    $unbalancedPayload = [
        'reference' => 'JV-UNBALANCED-' . rand(1000, 9999),
        'date' => now()->toDateString(),
        'journal_type' => 'manual',
        'description' => 'Jurnal uji tidak balance',
        'entries' => [
            [
                'coa_id' => $this->ctx['bebanOperasional']->id,
                'debit' => 500000,
                'credit' => 0,
                'description' => 'Beban',
            ],
            [
                'coa_id' => $this->ctx['kasUtama']->id,
                'debit' => 0,
                'credit' => 450000,
                'description' => 'Kas',
            ],
        ],
    ];

    $responseA = $this->post(route('keuangan.journal.store'), $unbalancedPayload);
    $responseA->assertSessionHas('error');
    expect(session('error'))->toContain('Jurnal tidak seimbang');

    // Kasus B: Kurang dari 2 baris akun
    $singleLinePayload = [
        'reference' => 'JV-SINGLE-' . rand(1000, 9999),
        'date' => now()->toDateString(),
        'journal_type' => 'manual',
        'description' => 'Jurnal satu baris',
        'entries' => [
            [
                'coa_id' => $this->ctx['bebanOperasional']->id,
                'debit' => 500000,
                'credit' => 0,
            ],
        ],
    ];

    $responseB = $this->post(route('keuangan.journal.store'), $singleLinePayload);
    $responseB->assertSessionHasErrors('entries');

    // Tidak ada jurnal fiktif yang dibukukan
    expect(JournalEntry::where('reference', $unbalancedPayload['reference'])->count())->toEqual(0);
});

/**
 * ADV-FIN-04: Periode Akuntansi tertutup mengunci posting jurnal keuangan ke masa lampau
 */
it('ADV-FIN-04: Sistem menolak keras pembukuan jurnal pada periode akuntansi yang telah ditutup (closed period)', function () {
    $this->actingAs($this->akuntan);

    // Kunci / tutup periode bulan lalu
    $targetDate = now()->subMonths(2)->startOfMonth()->toDateString();
    AccountingPeriod::create([
        'period_start' => now()->subMonths(2)->startOfMonth()->toDateString(),
        'period_end' => now()->subMonths(2)->endOfMonth()->toDateString(),
        'status' => 'closed',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);

    // Coba posting jurnal manual ke dalam tanggal periode yang sudah ditutup
    $closedPayload = [
        'reference' => 'JV-CLOSED-' . rand(1000, 9999),
        'date' => $targetDate,
        'journal_type' => 'manual',
        'description' => 'Uji posting ke periode tertutup',
        'entries' => [
            [
                'coa_id' => $this->ctx['bebanOperasional']->id,
                'debit' => 200000,
                'credit' => 0,
            ],
            [
                'coa_id' => $this->ctx['kasUtama']->id,
                'debit' => 0,
                'credit' => 200000,
            ],
        ],
    ];

    $response = $this->post(route('keuangan.journal.store'), $closedPayload);
    $response->assertSessionHas('error');
    expect(session('error'))->toContain('sudah ditutup');

    expect(JournalEntry::where('reference', $closedPayload['reference'])->count())->toEqual(0);
});

/**
 * UAT-FIN-01: Alur Lengkap Treasury, Transfer Kas/Bank, dan Jurnal Akuntansi dengan Pembuktian 4 Lapis
 */
it('UAT-FIN-01: End-to-End Perbendaharaan (Kas Masuk -> Transfer Antar-Bank -> Jurnal Penyesuaian) dengan Pembuktian 4 Lapis', function () {
    // === TAHAP 1: Kasir Menerima Kas Masuk Operasional ===
    $this->actingAs($this->kasir);

    $cashInPayload = [
        'number' => 'BKM-UAT-' . rand(1000, 9999),
        'date' => now()->toDateString(),
        'type' => 'cash_in',
        'account_coa_id' => $this->ctx['kasUtama']->id,
        'offset_coa_id' => $this->ctx['pendapatanLain']->id,
        'amount' => 5000000,
        'counterparty' => 'Pelanggan Tunai',
        'description' => 'Penerimaan Pendapatan Lain-lain Tunai',
        'cabang_id' => $this->ctx['cabang']->id,
    ];

    $responseIn = $this->post(route('keuangan.cashbank.store'), $cashInPayload);
    $responseIn->assertSessionHas('success');

    $trxIn = CashBankTransaction::where('number', $cashInPayload['number'])->first();
    expect($trxIn)->not->toBeNull();

    // Verifikasi Jurnal Kas Masuk otomatis terbit seimbang
    $inJournals = JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trxIn->id)
        ->get();
    expect($inJournals->count())->toEqual(2);
    expect((float) $inJournals->sum('debit'))->toEqualWithDelta(5000000.0, 0.01);
    expect((float) $inJournals->sum('credit'))->toEqualWithDelta(5000000.0, 0.01);


    // === TAHAP 2: Supervisor Melakukan Transfer Dana Kas ke Rekening Bank BCA ===
    $this->actingAs($this->supervisorFinance);

    // Transfer Rp 3.000.000 dari Kas Utama ke Bank BCA, biaya admin bank Rp 6.500
    $transferPayload = [
        'number' => 'TRF-UAT-' . rand(1000, 9999),
        'date' => now()->toDateString(),
        'from_coa_id' => $this->ctx['kasUtama']->id,
        'to_coa_id' => $this->ctx['bankBca']->id,
        'amount' => 3000000,
        'other_costs' => 6500,
        'other_costs_coa_id' => $this->ctx['bebanAdminBank']->id,
        'description' => 'Penyetoran Kas Toko ke Bank BCA via Setor Tunai',
        'cabang_id' => $this->ctx['cabang']->id,
    ];

    $responseTrf = $this->post(route('keuangan.cashbank.transfer.store'), $transferPayload);
    $responseTrf->assertSessionHas('success');

    $transfer = CashBankTransfer::where('number', $transferPayload['number'])->first();
    expect($transfer)->not->toBeNull();

    // Jurnal Transfer Dana Otomatis:
    // Debit Bank BCA (Rp 3.000.000) + Debit Beban Admin (Rp 6.500) vs Kredit Kas Utama (Rp 3.006.500)
    $trfJournals = JournalEntry::where('source_type', CashBankTransfer::class)
        ->where('source_id', $transfer->id)
        ->get();
    expect($trfJournals->count())->toEqual(3);

    $totalDebitTrf = (float) $trfJournals->sum('debit');
    $totalCreditTrf = (float) $trfJournals->sum('credit');
    expect($totalDebitTrf)->toEqualWithDelta(3006500.0, 0.01);
    expect($totalCreditTrf)->toEqualWithDelta(3006500.0, 0.01);
    expect(abs($totalDebitTrf - $totalCreditTrf))->toBeLessThan(0.01);


    // === TAHAP 3: Senior Akuntan Membukukan Jurnal Penyesuaian Manual ===
    $this->actingAs($this->akuntan);

    // Akuntan mencatat beban operasional kantor Rp 450.000 dibayar dari sisa Kas Utama
    $manualJvPayload = [
        'reference' => 'JV-UAT-' . rand(1000, 9999),
        'date' => now()->toDateString(),
        'journal_type' => 'manual',
        'description' => 'Penyesuaian Biaya Listrik & Kebersihan Kantor',
        'cabang_id' => $this->ctx['cabang']->id,
        'entries' => [
            [
                'coa_id' => $this->ctx['bebanOperasional']->id,
                'debit' => 450000,
                'credit' => 0,
                'description' => 'Beban Operasional Kantor',
            ],
            [
                'coa_id' => $this->ctx['kasUtama']->id,
                'debit' => 0,
                'credit' => 450000,
                'description' => 'Pengeluaran Kas Utama',
            ],
        ],
    ];

    $responseJv = $this->post(route('keuangan.journal.store'), $manualJvPayload);
    $responseJv->assertSessionHas('success');


    // === TAHAP 4: Verifikasi Integritas 4 Lapis Data ===
    // [LAPIS 1: Status Dokumen Transaksi]
    expect($trxIn->exists)->toBeTrue();
    expect($transfer->exists)->toBeTrue();
    expect(JournalEntry::where('reference', $manualJvPayload['reference'])->count())->toEqual(2);

    // [LAPIS 2: Saldo Rekening Likuiditas (Kas Utama & Bank BCA)]
    // Kas Utama Mutasi:
    // + Rp 5.000.000 (Kas Masuk)
    // - Rp 3.006.500 (Transfer Keluar + Admin)
    // - Rp 450.000 (Jurnal Penyesuaian Manual)
    // Saldo Akhir Kas Utama = Rp 1.543.500,00
    $kasDebit = (float) JournalEntry::where('coa_id', $this->ctx['kasUtama']->id)->sum('debit');
    $kasCredit = (float) JournalEntry::where('coa_id', $this->ctx['kasUtama']->id)->sum('credit');
    $kasEndingBalance = $kasDebit - $kasCredit;
    expect($kasEndingBalance)->toEqualWithDelta(1543500.0, 0.01);

    // Bank BCA Mutasi:
    // + Rp 3.000.000 (Transfer Masuk)
    // Saldo Akhir Bank BCA = Rp 3.000.000,00
    $bcaDebit = (float) JournalEntry::where('coa_id', $this->ctx['bankBca']->id)->sum('debit');
    $bcaCredit = (float) JournalEntry::where('coa_id', $this->ctx['bankBca']->id)->sum('credit');
    $bcaEndingBalance = $bcaDebit - $bcaCredit;
    expect($bcaEndingBalance)->toEqualWithDelta(3000000.0, 0.01);

    // [LAPIS 3: Keseimbangan Buku Besar & Zero Drift Keseluruhan]
    $allJournals = JournalEntry::all();
    $totalAllDebit = (float) $allJournals->sum('debit');
    $totalAllCredit = (float) $allJournals->sum('credit');
    expect($totalAllDebit)->toBeGreaterThan(0);
    expect($totalAllCredit)->toBeGreaterThan(0);
    expect(abs($totalAllDebit - $totalAllCredit))->toBeLessThan(0.01);

    // [LAPIS 4: Sub-ledger Biaya & Cabang Scope]
    $adminExpense = (float) JournalEntry::where('coa_id', $this->ctx['bebanAdminBank']->id)->sum('debit');
    expect($adminExpense)->toEqualWithDelta(6500.0, 0.01);

    $opExpense = (float) JournalEntry::where('coa_id', $this->ctx['bebanOperasional']->id)->sum('debit');
    expect($opExpense)->toEqualWithDelta(450000.0, 0.01);
});
