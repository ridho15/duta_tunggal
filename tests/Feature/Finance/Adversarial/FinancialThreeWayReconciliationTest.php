<?php

use App\Models\CashBankTransaction;
use App\Models\CashBankTransactionDetail;
use App\Models\CashBankTransfer;
use App\Models\JournalEntry;
use App\Services\CashBankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = finContext();
    $this->service = app(CashBankService::class);
});

test('FIN-RC-01: Three-way reconciliation between CashBank, JournalEntry, and General Ledger integrity across mixed operations', function () {
    // 1. Cash In: Pendapatan Lain ke Kas Utama = 5.000.000
    $trxIn = finTransaction(
        $this->ctx,
        type: 'cash_in',
        amount: 5000000,
        accountCoa: $this->ctx['kasUtama'],
        offsetCoa: $this->ctx['pendapatanLain']
    );
    $this->service->postTransaction($trxIn);

    // 2. Cash Out: Beban Operasional dari Kas Utama = 1.200.000
    $trxOut = finTransaction(
        $this->ctx,
        type: 'cash_out',
        amount: 1200000,
        accountCoa: $this->ctx['kasUtama'],
        offsetCoa: $this->ctx['bebanOperasional']
    );
    $this->service->postTransaction($trxOut);

    // 3. Transfer Dana: Dari Kas Utama ke Bank BCA = 2.000.000 + Biaya Admin 5.000
    $transfer = finTransfer(
        $this->ctx,
        amount: 2000000,
        otherCosts: 5000,
        fromCoa: $this->ctx['kasUtama'],
        toCoa: $this->ctx['bankBca'],
        attributes: [
            'other_costs_coa_id' => $this->ctx['bebanAdminBank']->id,
        ]
    );
    $this->service->postTransfer($transfer);

    // 4. Bank In: Bunga/Pendapatan ke Bank BCA = 150.000
    $bankIn = finTransaction(
        $this->ctx,
        type: 'bank_in',
        amount: 150000,
        accountCoa: $this->ctx['bankBca'],
        offsetCoa: $this->ctx['pendapatanLain']
    );
    $this->service->postTransaction($bankIn);

    // 5. Bank Out dengan rincian multi-detail: Total 300.000 dari Bank BCA
    $bankOut = finTransaction(
        $this->ctx,
        type: 'bank_out',
        amount: 300000,
        accountCoa: $this->ctx['bankBca'],
        offsetCoa: $this->ctx['bebanOperasional']
    );
    CashBankTransactionDetail::create([
        'cash_bank_transaction_id' => $bankOut->id,
        'chart_of_account_id' => $this->ctx['bebanOperasional']->id,
        'amount' => 200000,
        'description' => 'Biaya utilitas listrik & air',
    ]);
    CashBankTransactionDetail::create([
        'cash_bank_transaction_id' => $bankOut->id,
        'chart_of_account_id' => $this->ctx['bebanOperasional']->id,
        'amount' => 100000,
        'description' => 'Biaya internet & telpon',
    ]);
    $bankOut->load('transactionDetails');
    $this->service->postTransaction($bankOut);

    // --- REKONSILIASI TIGA ARAH (Three-Way Integrity) ---
    $allEntries = JournalEntry::all();

    // 1. Total Debit Global == Total Kredit Global (General Ledger Equilibrium)
    $globalDebit = (float)$allEntries->sum('debit');
    $globalCredit = (float)$allEntries->sum('credit');
    expect($globalDebit)->toBeGreaterThan(0);
    expect($globalDebit)->toBe($globalCredit);

    // 2. Rekonsiliasi Akun Kas Utama
    // Debit: 5.000.000
    // Kredit: 1.200.000 (Beban) + 2.005.000 (Transfer + Admin) = 3.205.000
    // Net: +1.795.000
    $kasEntries = $allEntries->where('coa_id', $this->ctx['kasUtama']->id);
    $kasDebit = (float)$kasEntries->sum('debit');
    $kasCredit = (float)$kasEntries->sum('credit');
    expect($kasDebit)->toBe(5000000.0);
    expect($kasCredit)->toBe(3205000.0);
    expect($kasDebit - $kasCredit)->toBe(1795000.0);

    // 3. Rekonsiliasi Akun Bank BCA
    // Debit: 2.000.000 (Transfer Masuk) + 150.000 (Bank In) = 2.150.000
    // Kredit: 300.000 (Bank Out)
    // Net: +1.850.000
    $bcaEntries = $allEntries->where('coa_id', $this->ctx['bankBca']->id);
    $bcaDebit = (float)$bcaEntries->sum('debit');
    $bcaCredit = (float)$bcaEntries->sum('credit');
    expect($bcaDebit)->toBe(2150000.0);
    expect($bcaCredit)->toBe(300000.0);
    expect($bcaDebit - $bcaCredit)->toBe(1850000.0);

    // 4. Rekonsiliasi Beban Administrasi Bank
    $adminEntries = $allEntries->where('coa_id', $this->ctx['bebanAdminBank']->id);
    expect((float)$adminEntries->sum('debit'))->toBe(5000.0);
});

test('FIN-RC-02: Negative detail amounts (discounts / deductions) in cash_in maintain strict GL balancing', function () {
    // Penerimaan uang kas bersih 950.000:
    // Pendapatan kotor: 1.000.000
    // Diskon/potongan: -50.000
    $trxIn = finTransaction(
        $this->ctx,
        type: 'cash_in',
        amount: 950000,
        accountCoa: $this->ctx['kasUtama'],
        offsetCoa: $this->ctx['pendapatanLain']
    );

    CashBankTransactionDetail::create([
        'cash_bank_transaction_id' => $trxIn->id,
        'chart_of_account_id' => $this->ctx['pendapatanLain']->id,
        'amount' => 1000000,
        'description' => 'Pendapatan kotor',
    ]);

    CashBankTransactionDetail::create([
        'cash_bank_transaction_id' => $trxIn->id,
        'chart_of_account_id' => $this->ctx['bebanOperasional']->id,
        'amount' => -50000,
        'description' => 'Diskon promo',
    ]);

    $trxIn->load('transactionDetails');
    $this->service->postTransaction($trxIn);

    $entries = JournalEntry::where('source_type', CashBankTransaction::class)
        ->where('source_id', $trxIn->id)
        ->get();

    expect($entries)->toHaveCount(3);

    $totalDebit = (float)$entries->sum('debit');
    $totalCredit = (float)$entries->sum('credit');

    // Debit: Kas 950.000 + Diskon 50.000 = 1.000.000
    // Kredit: Pendapatan 1.000.000
    expect($totalDebit)->toBe(1000000.0);
    expect($totalCredit)->toBe(1000000.0);
    expect($totalDebit - $totalCredit)->toBe(0.0);
});

test('FIN-RC-03: Transactional integrity and rollback on mid-operation failure prevents orphan journals', function () {
    $trx = finTransaction($this->ctx, 'cash_out', 100000);

    // Rincian tidak seimbang secara sengaja
    CashBankTransactionDetail::create([
        'cash_bank_transaction_id' => $trx->id,
        'chart_of_account_id' => $this->ctx['bebanOperasional']->id,
        'amount' => 80000, // Kurang 20.000
        'description' => 'Parsial gagal',
    ]);

    $trx->load('transactionDetails');

    $initialJournalCount = JournalEntry::count();

    try {
        $this->service->postTransaction($trx);
    } catch (\Throwable $e) {
        // Exception diharapkan
    }

    // Pastikan tidak ada jurnal baru atau parsial yang tersisa akibat rollback
    expect(JournalEntry::count())->toBe($initialJournalCount);
});
