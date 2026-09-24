<?php

use App\Models\BankReconciliation;
use App\Models\Cabang;
use App\Models\CashBankTransaction;
use App\Models\CashBankTransactionDetail;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\User;
use App\Models\VoucherRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Fixture context untuk pengujian modul keuangan, kas & bank, kasbon, dan rekonsiliasi (Tahap 5).
 */
function finContext(array $overrides = []): array
{
    $cabang = Cabang::factory()->create([
        'kode' => 'FIN-' . strtoupper(substr(uniqid(), -5)),
        'nama' => 'Cabang Keuangan & Perbendaharaan',
        'status' => 1,
        'lihat_stok_cabang_lain' => false,
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'username' => 'fin_' . uniqid(),
        'email' => 'fin_' . uniqid() . '@example.com',
        'kode_user' => 'U' . strtoupper(substr(uniqid(), -4)),
        'manage_type' => 'all',
    ]);
    Auth::login($user);

    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(
        ['code' => $code],
        ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]
    );

    $kasUtama = $coa('1111.01', 'Kas Utama Operasional', 'Asset');
    $bankBca = $coa('1112.01', 'Bank BCA Operasional', 'Asset');
    $bankMandiri = $coa('1112.02', 'Bank Mandiri Operasional', 'Asset');
    $bebanOperasional = $coa('6100.01', 'Beban Operasional Kantor', 'Expense');
    $bebanAdminBank = $coa('8000.01', 'Beban Administrasi Bank', 'Expense');
    $pendapatanLain = $coa('7100.01', 'Pendapatan Lain-lain & Bunga Bank', 'Revenue');
    $kasbonKaryawan = $coa('1130.05', 'Piutang Kasbon Karyawan', 'Asset');

    return array_merge(compact(
        'cabang', 'user', 'kasUtama', 'bankBca', 'bankMandiri',
        'bebanOperasional', 'bebanAdminBank', 'pendapatanLain', 'kasbonKaryawan'
    ), $overrides);
}

/**
 * Buat CashBankTransaction (Transaksi Kas/Bank Masuk atau Keluar).
 */
function finTransaction(
    array $ctx,
    string $type = 'cash_out',
    float $amount = 100000,
    ?ChartOfAccount $accountCoa = null,
    ?ChartOfAccount $offsetCoa = null,
    array $attributes = []
): CashBankTransaction {
    $account = $accountCoa ?? ($type === 'cash_in' || $type === 'cash_out' ? $ctx['kasUtama'] : $ctx['bankBca']);
    $offset = $offsetCoa ?? ($type === 'cash_in' || $type === 'bank_in' ? $ctx['pendapatanLain'] : $ctx['bebanOperasional']);

    return CashBankTransaction::create(array_merge([
        'number' => 'CBT-' . strtoupper(substr(uniqid(), -6)),
        'date' => now()->toDateString(),
        'type' => $type,
        'account_coa_id' => $account->id,
        'offset_coa_id' => $offset->id,
        'amount' => $amount,
        'counterparty' => 'Vendor / Mitra Finansial',
        'description' => 'Transaksi Finansial ' . $type,
        'cabang_id' => $ctx['cabang']->id,
    ], $attributes));
}

/**
 * Buat CashBankTransfer (Transfer Antar Rekening Kas/Bank).
 */
function finTransfer(
    array $ctx,
    float $amount = 500000,
    float $otherCosts = 6500,
    ?ChartOfAccount $fromCoa = null,
    ?ChartOfAccount $toCoa = null,
    array $attributes = []
): CashBankTransfer {
    $from = $fromCoa ?? $ctx['bankBca'];
    $to = $toCoa ?? $ctx['kasUtama'];

    return CashBankTransfer::create(array_merge([
        'number' => 'TRF-' . strtoupper(substr(uniqid(), -6)),
        'date' => now()->toDateString(),
        'from_coa_id' => $from->id,
        'to_coa_id' => $to->id,
        'amount' => $amount,
        'other_costs' => $otherCosts,
        'other_costs_coa_id' => $otherCosts > 0 ? $ctx['bebanAdminBank']->id : null,
        'description' => 'Transfer Dana Kas/Bank',
        'status' => 'draft',
        'cabang_id' => $ctx['cabang']->id,
    ], $attributes));
}

/**
 * Buat VoucherRequest (Pengajuan Kasbon / Dana Operasional).
 */
function finVoucher(
    array $ctx,
    float $amount = 250000,
    string $status = 'draft',
    array $attributes = []
): VoucherRequest {
    return VoucherRequest::create(array_merge([
        'voucher_number' => 'VR-' . strtoupper(substr(uniqid(), -6)),
        'voucher_date' => now()->toDateString(),
        'amount' => $amount,
        'related_party' => 'Karyawan Operasional',
        'description' => 'Kasbon Perjalanan Dinas',
        'status' => $status,
        'created_by' => $ctx['user']->id,
        'approved_by' => $status === 'approved_by_owner' ? $ctx['user']->id : null,
        'approved_at' => $status === 'approved_by_owner' ? now() : null,
        'cabang_id' => $ctx['cabang']->id,
    ], $attributes));
}

/**
 * Buat BankReconciliation (Rekonsiliasi Bank).
 */
function finReconciliation(
    array $ctx,
    ?ChartOfAccount $bankCoa = null,
    float $statementEndingBalance = 1000000,
    array $attributes = []
): BankReconciliation {
    $coa = $bankCoa ?? $ctx['bankBca'];

    return BankReconciliation::create(array_merge([
        'coa_id' => $coa->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'statement_ending_balance' => $statementEndingBalance,
        'reference' => 'RECON-' . strtoupper(substr(uniqid(), -6)),
        'notes' => 'Rekonsiliasi Bulanan Rekening Koran',
        'status' => 'open',
    ], $attributes));
}
