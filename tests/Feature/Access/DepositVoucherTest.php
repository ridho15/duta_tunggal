<?php

/**
 * Titip dana customer (Ko Handy 27 Nov 2026): dicatat kasir, voucher penerimaan dicetak kasir pencatat
 * (Finance Manager / Owner semua), jurnal Dr Kas / Cr Deposit Pelanggan; kelebihan bayar ikut voucher penerimaannya.
 */

use App\Models\ChartOfAccount;
use App\Models\CustomerReceipt;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\JournalEntry;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = stkContext();
    $this->kas = ChartOfAccount::firstOrCreate(['code' => '1110.01'], ['name' => 'Kas Operasional', 'type' => 'Asset', 'is_active' => true, 'is_cash_bank' => true]);
    $this->depAcc = ChartOfAccount::firstOrCreate(['code' => config('coa.customer_deposit')], ['name' => 'Deposit Pelanggan', 'type' => 'Liability', 'is_active' => true]);
    $make = function (string $role) {
        $u = User::factory()->create(['cabang_id' => $this->ctx['user']->cabang_id, 'manage_type' => ['all']]);
        $u->assignRole(Role::findByName($role));

        return $u;
    };
    $this->kasir = $make('Kasir');
    $this->kasirLain = $make('Kasir');
    $this->finance = $make('Finance Manager');
    Access::flush();
});

it('kasir mencatat titip dana: jurnal Kas/Deposit Pelanggan, tampil di tab Titip Dana, voucher bisa dicetak', function () {
    $this->actingAs($this->kasir)->post(route('penjualan.receipts.store'), [
        'customer_id' => $this->ctx['customer']->id, 'payment_date' => '2026-11-26', 'payment_method' => 'Cash',
        'payment_reference' => 'TRF-889', 'coa_id' => $this->kas->id, 'deposit_amount' => 500000, 'notes' => 'Titip untuk SO berikutnya',
    ])->assertSessionHasNoErrors()->assertRedirect(route('penjualan.receipts.index', ['tab' => 'titip']));

    $deposit = Deposit::where('from_model_type', Customer::class)->sole();
    expect($deposit->payment_date)->toStartWith('2026-11-26')
        ->and($deposit->payment_method)->toBe('Cash')
        ->and($deposit->payment_reference)->toBe('TRF-889')
        ->and((int) $deposit->created_by)->toBe($this->kasir->id);

    $lines = JournalEntry::where('source_type', Deposit::class)->where('source_id', $deposit->id)->get();
    expect((float) $lines->firstWhere('coa_id', $this->kas->id)?->debit)->toBe(500000.0)
        ->and((float) $lines->firstWhere('coa_id', $this->depAcc->id)?->credit)->toBe(500000.0)
        ->and($lines->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->unique()->all())->toBe(['2026-11-26']);

    $this->get(route('penjualan.receipts.index', ['tab' => 'titip']))->assertOk()
        ->assertSee($deposit->deposit_number)->assertSee('Cetak Voucher');
    $this->get(route('penjualan.receipts.deposit-voucher', $deposit))->assertOk()->assertHeader('content-type', 'application/pdf');

    $html = view('penjualan.receipts.voucher', [
        'receipt' => (object) ['status' => 'paid', 'customer' => $this->ctx['customer'], 'bank_name' => null, 'payment_reference' => 'TRF-889',
            'payment_date' => '2026-11-26', 'payment_method' => 'Cash', 'coa' => $this->kas, 'cabang' => null,
            'customerReceiptItem' => collect(), 'overpayment_amount' => 0, 'total_payment' => 500000, 'notes' => null, 'createdBy' => $this->kasir],
        'voucherNumber' => 'BKM-' . $deposit->deposit_number, 'kwitansiNumber' => $deposit->deposit_number,
        'company' => app(\App\Services\DocumentPrintBuilder::class)->company(null, false), 'deposit' => $deposit->fresh(['coa']),
    ])->render();
    expect($html)->toContain('BUKTI PENERIMAAN KAS / BANK')->toContain('Titip Dana Customer')->toContain($this->depAcc->code);
});

it('kasir lain tidak bisa cetak; Finance Manager bisa', function () {
    $deposit = Deposit::create([
        'deposit_number' => 'DEP-T-1', 'from_model_type' => Customer::class, 'from_model_id' => $this->ctx['customer']->id,
        'amount' => 100000, 'used_amount' => 0, 'remaining_amount' => 100000, 'coa_id' => $this->depAcc->id,
        'payment_coa_id' => $this->kas->id, 'status' => 'active', 'created_by' => $this->kasir->id, 'payment_date' => now()->toDateString(),
    ]);

    $this->actingAs($this->kasirLain)->get(route('penjualan.receipts.deposit-voucher', $deposit))->assertForbidden();
    $this->actingAs($this->finance)->get(route('penjualan.receipts.deposit-voucher', $deposit))->assertOk();
    $this->get(route('penjualan.receipts.index', ['tab' => 'titip']))->assertOk()->assertSee('DEP-T-1');
});

it('kelebihan bayar tidak punya voucher terpisah (sudah di voucher penerimaannya)', function () {
    $deposit = Deposit::create([
        'deposit_number' => 'DEP-T-2', 'from_model_type' => Customer::class, 'from_model_id' => $this->ctx['customer']->id,
        'amount' => 5000, 'used_amount' => 0, 'remaining_amount' => 5000, 'coa_id' => $this->depAcc->id,
        'payment_coa_id' => $this->kas->id, 'status' => 'active', 'created_by' => $this->kasir->id,
    ]);
    $receipt = CustomerReceipt::factory()->create(['cabang_id' => $this->ctx['user']->cabang_id, 'created_by' => $this->kasir->id, 'deposit_id' => $deposit->id]);

    expect($deposit->canPrintVoucher($this->kasir))->toBeFalse();
    $this->actingAs($this->kasir)->get(route('penjualan.receipts.deposit-voucher', $deposit))->assertStatus(422);
    $this->get(route('penjualan.receipts.index', ['tab' => 'titip']))->assertOk()
        ->assertSee('Ikut voucher KW-' . str_pad((string) $receipt->id, 6, '0', STR_PAD_LEFT));
});

it('pelunasan dari saldo titip dicetak sebagai pemakaian saldo, bukan kas masuk', function () {
    $receipt = CustomerReceipt::factory()->create(['cabang_id' => $this->ctx['user']->cabang_id, 'payment_method' => 'Deposit', 'created_by' => $this->kasir->id]);
    $receipt->load(['customer', 'cabang', 'coa', 'customerReceiptItem.invoice', 'createdBy']);
    $html = view('penjualan.receipts.voucher', [
        'receipt' => $receipt, 'voucherNumber' => 'BKM-1', 'kwitansiNumber' => 'KW-1',
        'company' => app(\App\Services\DocumentPrintBuilder::class)->company($receipt->cabang, false),
    ])->render();
    expect($html)->toContain('BUKTI PEMAKAIAN SALDO TITIP')->toContain('Tidak ada uang kas / bank masuk')
        ->not->toContain('BUKTI PENERIMAAN KAS / BANK');
});
