<?php

/**
 * Regresi perbaikan audit siklus penjualan (SJ batal setelah berangkat, pembulatan penerimaan, cadangan saldo titip COD,
 * command perbaikan data deposit). Pelengkap tests/Audit/Sales/SalesCycleAuditTest.php.
 */

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\SuratJalan;
use App\Services\SaleOrderCashierVerification;
use App\Services\SuratJalanService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->travelTo(now()->startOfMonth()->addDays(9)->setTime(10, 0)));

function sfxGl(string $code): float
{
    return round((float) JournalEntry::withoutGlobalScopes()->join('chart_of_accounts as c', 'c.id', '=', 'journal_entries.coa_id')
        ->whereNull('journal_entries.deleted_at')->where('c.code', $code)->sum(DB::raw('journal_entries.debit - journal_entries.credit')), 2);
}

function sfxSetup(array $so = []): array
{
    $ctx = stkContext();
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');
    $ctx['kas'] = ChartOfAccount::firstOrCreate(['code' => '1111.01'], ['name' => 'Kas Kecil', 'type' => 'Asset', 'is_active' => true, 'is_cash_bank' => true]);
    $ctx['dep'] = ChartOfAccount::firstOrCreate(['code' => '2160.04'], ['name' => 'Deposit Pelanggan', 'type' => 'Liability', 'is_active' => true]);
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$saleOrder, $soItem] = stkSaleOrder($ctx, 10, $so);
    $ctx['so'] = $saleOrder;
    $ctx['do'] = stkDeliveryOrder($ctx, $saleOrder, $soItem, 10, 'approved');

    return $ctx;
}

function sfxSj($test, array $ctx): SuratJalan
{
    $test->actingAs($ctx['user'])->post(route('penjualan.sj.store'), [
        'delivery_order_ids' => [$ctx['do']->id], 'issued_at' => now()->toDateString(),
        'delivery_method' => 'ekspedisi', 'expedition_name' => 'JNE Uji', 'cabang_id' => $ctx['cabang']->id,
    ])->assertSessionHasNoErrors();

    return SuratJalan::latest('id')->firstOrFail();
}

function sfxInvoice($test, array $ctx, SuratJalan $sj): Invoice
{
    $test->actingAs($ctx['user'])->post(route('penjualan.invoices.store'), [
        'surat_jalan_ids' => [$sj->id], 'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
        'cabang_id' => $ctx['cabang']->id,
    ])->assertSessionHasNoErrors();

    return Invoice::latest('id')->firstOrFail();
}

function sfxTitip($test, array $ctx, float $amount)
{
    return $test->actingAs($ctx['user'])->post(route('penjualan.receipts.store'), [
        'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Cash',
        'coa_id' => $ctx['kas']->id, 'deposit_amount' => $amount,
    ]);
}

it('SJ terbit hari ini lalu dibatalkan: stok fisik kembali, jurnal barang terkirim dibalik, DO siap kirim terkunci; terbit ulang menjurnal sekali', function () {
    $ctx = sfxSetup();
    $sj = sfxSj($this, $ctx);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(20.0)->and(sfxGl('1140.20'))->toBe(50000.0);

    foreach ($sj->deliverySchedules as $schedule) {
        $schedule->update(['status' => 'cancelled']);
    }
    $service = app(SuratJalanService::class);
    $service->cancel($sj->fresh(), 'Salah alamat kirim', $ctx['user']->id);

    $stock = stkStock($ctx['product'], $ctx['warehouse']);
    expect($stock['available'])->toBe(30.0)
        ->and($stock['reserved'])->toBe(10.0)
        ->and(sfxGl('1140.20'))->toBe(0.0)
        ->and(sfxGl('1140.10'))->toBe(0.0)
        ->and($ctx['do']->fresh()->status)->toBe('confirmed');

    $service->reissue($sj->fresh());
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(20.0)
        ->and(sfxGl('1140.20'))->toBe(50000.0)
        ->and(sfxGl('1140.10'))->toBe(-50000.0);
});

it('Kurang bayar <= Rp1 dengan akun Pembulatan: piutang lunas, kas = uang diterima, selisih ke Pembulatan', function () {
    $ctx = sfxSetup();
    ChartOfAccount::firstOrCreate(['code' => '9000.02'], ['name' => 'PEMBULATAN', 'type' => 'Expense', 'is_active' => true]);
    $inv = sfxInvoice($this, $ctx, sfxSj($this, $ctx));

    $this->post(route('penjualan.receipts.store'), [
        'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Cash', 'coa_id' => $ctx['kas']->id,
        'selected_invoices' => [$inv->id], 'invoice_receipts' => [$inv->id => 99999.50],
    ])->assertSessionHasNoErrors();

    expect(sfxGl('1111.01'))->toBe(99999.5)
        ->and(sfxGl('9000.02'))->toBe(0.5)
        ->and(sfxGl('1120'))->toBe(0.0)
        ->and((float) AccountReceivable::where('invoice_id', $inv->id)->value('remaining'))->toBe(0.0)
        ->and($inv->fresh()->status)->toBe(Invoice::STATUS_PAID);
});

it('Verifikasi kasir COD hanya mencadangkan saldo (tanpa jurnal); batal SO melepas cadangan; cadangan tidak bisa dipakai pelunasan Deposit lain', function () {
    $ctx = sfxSetup(['total_amount' => 100000, 'shipping_cost' => 0]);
    $ctx['customer']->forceFill(['tipe_pembayaran' => 'COD (Bayar Lunas)'])->save();
    sfxTitip($this, $ctx, 100000)->assertSessionHasNoErrors();
    $journalsBefore = JournalEntry::count();

    $result = app(SaleOrderCashierVerification::class)->verify($ctx['so']->fresh(), null, null, $ctx['user']);
    $deposit = Deposit::where('from_model_type', Customer::class)->where('from_model_id', $ctx['customer']->id)->firstOrFail();

    expect($result['used'])->toBe(100000.0)
        ->and((float) $deposit->fresh()->remaining_amount)->toBe(100000.0)
        ->and(Deposit::availableForCustomer($ctx['customer']->id))->toBe(0.0)
        ->and(JournalEntry::count())->toBe($journalsBefore)
        ->and(-sfxGl('2160.04'))->toBe(100000.0);

    expect(app(SaleOrderCashierVerification::class)->refund($ctx['so']->fresh(), $ctx['user']))->toBe(100000.0)
        ->and(Deposit::availableForCustomer($ctx['customer']->id))->toBe(100000.0)
        ->and(app(SaleOrderCashierVerification::class)->refund($ctx['so']->fresh(), $ctx['user']))->toBe(0.0);
});

it('Pelunasan metode Deposit melebihi saldo bebas ditolak dengan pesan jelas', function () {
    $ctx = sfxSetup();
    sfxTitip($this, $ctx, 30000)->assertSessionHasNoErrors();
    $inv = sfxInvoice($this, $ctx, sfxSj($this, $ctx));

    $this->post(route('penjualan.receipts.store'), [
        'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Deposit', 'coa_id' => $ctx['dep']->id,
        'selected_invoices' => [$inv->id], 'invoice_receipts' => [$inv->id => 50000],
    ])->assertSessionHasErrors('payment_method');

    expect((float) Deposit::where('from_model_id', $ctx['customer']->id)->sum('remaining_amount'))->toBe(30000.0)
        ->and(sfxGl('1120'))->toBe(100000.0);
});

it('Command perbaikan deposit: dry-run tidak mengubah; --apply memindah akun kas ke payment_coa_id & mengoreksi jurnal pemakaian', function () {
    $ctx = sfxSetup();
    $legacy = Deposit::create([
        'deposit_number' => 'DEP-LAMA-1', 'from_model_type' => Customer::class, 'from_model_id' => $ctx['customer']->id,
        'amount' => 50000, 'used_amount' => 0, 'remaining_amount' => 50000, 'coa_id' => $ctx['kas']->id, 'status' => 'active', 'created_by' => $ctx['user']->id,
    ]);
    $receipt = \App\Models\CustomerReceipt::create([
        'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Deposit', 'coa_id' => $ctx['dep']->id,
        'total_payment' => 20000, 'status' => 'Draft', 'created_by' => $ctx['user']->id,
    ]);
    JournalEntry::create(['coa_id' => $ctx['kas']->id, 'date' => now()->toDateString(), 'reference' => 'REC-'.$receipt->id, 'debit' => 20000, 'credit' => 0,
        'description' => 'Deposit / Uang Muka usage for receipt id '.$receipt->id, 'journal_type' => 'receipt', 'source_type' => \App\Models\CustomerReceipt::class, 'source_id' => $receipt->id]);

    $this->artisan('sales:fix-customer-deposit-accounts')->assertSuccessful();
    expect((int) $legacy->fresh()->coa_id)->toBe($ctx['kas']->id)->and(sfxGl('2160.04'))->toBe(0.0);

    $this->artisan('sales:fix-customer-deposit-accounts', ['--apply' => true])->assertSuccessful();
    expect((int) $legacy->fresh()->coa_id)->toBe($ctx['dep']->id)
        ->and((int) $legacy->fresh()->payment_coa_id)->toBe($ctx['kas']->id)
        ->and(sfxGl('2160.04'))->toBe(20000.0)
        ->and(sfxGl('1111.01'))->toBe(0.0);

    $this->artisan('sales:fix-customer-deposit-accounts', ['--apply' => true])->assertSuccessful();   // idempoten
    expect(sfxGl('2160.04'))->toBe(20000.0);
});
