<?php

/**
 * AUDIT AKUNTANSI — Siklus Penjualan & Penerimaan Kas (SO → DO → SJ → Invoice → Terima Pembayaran / Titip Dana / Nota Kredit).
 * Setiap tes menegaskan perlakuan yang SEHARUSNYA (PSAK 14/72, PPN). Tes yang gagal = temuan audit; angka aktual tampil di pesan gagal
 * dan di baris "AUDIT>" (STDERR).
 *
 * Jalankan: DB_DATABASE=duta_tunggal_test_4 php artisan test tests/Audit/Sales/SalesCycleAuditTest.php
 */

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\SuratJalan;
use App\Services\CreditNoteService;
use App\Services\SaleOrderCashierVerification;
use App\Services\SuratJalanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(fn () => $this->travelTo(now()->startOfMonth()->addDays(9)->setTime(10, 0)));

// ---------------------------------------------------------------- helpers

function audLog(string $label, $value): void
{
    fwrite(STDERR, 'AUDIT> '.$label.' = '.(is_scalar($value) ? $value : json_encode($value))."\n");
}

/** Saldo GL (debit − kredit) akun berkode $code. */
function audGl(string $code, ?string $sourceType = null, ?int $sourceId = null): float
{
    $q = JournalEntry::withoutGlobalScopes()->join('chart_of_accounts as c', 'c.id', '=', 'journal_entries.coa_id')
        ->whereNull('journal_entries.deleted_at')->where('c.code', $code);
    if ($sourceType) {
        $q->where('journal_entries.source_type', $sourceType);
    }
    if ($sourceId) {
        $q->where('journal_entries.source_id', $sourceId);
    }

    return round((float) $q->sum(DB::raw('journal_entries.debit - journal_entries.credit')), 2);
}

/** Jumlah debit dan kredit seluruh jurnal (harus sama). */
function audTrialBalance(): array
{
    $q = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at');

    return [round((float) (clone $q)->sum('debit'), 2), round((float) (clone $q)->sum('credit'), 2)];
}

function audSetup(array $item = [], array $so = []): array
{
    $ctx = stkContext();
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');
    $ctx['kas'] = ChartOfAccount::firstOrCreate(['code' => '1111.01'], ['name' => 'Kas Kecil', 'type' => 'Asset', 'is_active' => true, 'is_cash_bank' => true]);
    $ctx['dep'] = ChartOfAccount::firstOrCreate(['code' => '2160.04'], ['name' => 'Deposit Pelanggan', 'type' => 'Liability', 'is_active' => true]);
    ChartOfAccount::firstOrCreate(['code' => '4100.01'], ['name' => 'Diskon Penjualan', 'type' => 'Expense', 'is_active' => true]);
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$saleOrder, $soItem] = stkSaleOrder($ctx, 10, $so, $item);
    $ctx['so'] = $saleOrder;
    $ctx['soItem'] = $soItem;
    $ctx['do'] = stkDeliveryOrder($ctx, $saleOrder, $soItem, 10, 'approved');

    return $ctx;
}

function audSj($test, array $ctx, array $extra = []): SuratJalan
{
    $test->actingAs($ctx['user'])->post(route('penjualan.sj.store'), array_merge([
        'delivery_order_ids' => [$ctx['do']->id], 'issued_at' => now()->toDateString(),
        'delivery_method' => 'ekspedisi', 'expedition_name' => 'JNE Uji', 'cabang_id' => $ctx['cabang']->id,
    ], $extra))->assertSessionHasNoErrors();

    return SuratJalan::latest('id')->firstOrFail();
}

function audInvoice($test, array $ctx, SuratJalan $sj, array $extra = []): Invoice
{
    $test->actingAs($ctx['user'])->post(route('penjualan.invoices.store'), array_merge([
        'surat_jalan_ids' => [$sj->id], 'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
        'cabang_id' => $ctx['cabang']->id,
    ], $extra))->assertSessionHasNoErrors();

    return Invoice::latest('id')->firstOrFail();
}

function audPay($test, array $ctx, Invoice $inv, float $amount, array $extra = [])
{
    return $test->actingAs($ctx['user'])->post(route('penjualan.receipts.store'), array_merge([
        'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Cash', 'coa_id' => $ctx['kas']->id,
        'selected_invoices' => [$inv->id], 'invoice_receipts' => [$inv->id => $amount],
    ], $extra));
}

function audTitip($test, array $ctx, float $amount, array $extra = [])
{
    return $test->actingAs($ctx['user'])->post(route('penjualan.receipts.store'), array_merge([
        'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Cash',
        'coa_id' => $ctx['kas']->id, 'deposit_amount' => $amount,
    ], $extra));
}

function audDepositSub(array $ctx): float
{
    return round((float) Deposit::where('from_model_type', Customer::class)->where('from_model_id', $ctx['customer']->id)->sum('remaining_amount'), 2);
}

function audArSub(): float
{
    return round((float) AccountReceivable::withoutGlobalScopes()->sum('remaining'), 2);
}

// ---------------------------------------------------------------- 1–2. Surat Jalan: barang terkirim / persediaan / HPP

it('A1 SJ: Dr Barang Terkirim / Cr Persediaan = qty × HPP rata-rata, bertanggal SJ, tanpa HPP; stok terpotong', function () {
    $ctx = audSetup();
    $sj = audSj($this, $ctx);

    $je = JournalEntry::withoutGlobalScopes()->where('source_type', \App\Models\DeliveryOrder::class)->where('source_id', $ctx['do']->id)->get();
    audLog('A1 jurnal SJ', $je->map(fn ($e) => [$e->coa->code, (float) $e->debit, (float) $e->credit, substr((string) $e->date, 0, 10)])->all());

    expect(audGl('1140.20'))->toBe(50000.0)
        ->and(audGl('1140.10'))->toBe(-50000.0)
        ->and(audGl('5100.10'))->toBe(0.0)
        ->and($je->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->unique()->all())->toBe([now()->toDateString()])
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(20.0)
        ->and(audTrialBalance()[0])->toBe(audTrialBalance()[1]);
});

it('A2 SJ dibatalkan: stok kembali & jurnal barang terkirim dibalik (tidak meninggalkan saldo)', function () {
    $ctx = audSetup();
    $sj = audSj($this, $ctx, ['delivery_date' => now()->addDay()->toDateString()]);
    // jadwal pending memblokir pembatalan → batalkan jadwal dulu (prosedur resmi)
    foreach ($sj->deliverySchedules as $s) {
        $s->update(['status' => 'cancelled']);
    }
    app(SuratJalanService::class)->cancel($sj->fresh(), 'Salah alamat kirim', $ctx['user']->id);

    audLog('A2 setelah batal SJ: stok fisik', stkStock($ctx['product'], $ctx['warehouse']));
    audLog('A2 setelah batal SJ: GL 1140.20 / 1140.10', [audGl('1140.20'), audGl('1140.10')]);
    audLog('A2 status DO', $ctx['do']->fresh()->status);

    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(30.0)
        ->and(audGl('1140.20'))->toBe(0.0);
});

it('A3 SJ dibatalkan lalu diterbitkan ulang: tidak ada potong stok / jurnal ganda', function () {
    $ctx = audSetup();
    $sj = audSj($this, $ctx, ['delivery_date' => now()->addDay()->toDateString()]);
    foreach ($sj->deliverySchedules as $s) {
        $s->update(['status' => 'cancelled']);
    }
    $svc = app(SuratJalanService::class);
    $svc->cancel($sj->fresh(), 'Salah alamat kirim', $ctx['user']->id);
    $svc->reissue($sj->fresh());

    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(20.0)
        ->and(audGl('1140.20'))->toBe(50000.0)
        ->and(audGl('1140.10'))->toBe(-50000.0);
});

// ---------------------------------------------------------------- 3. Invoice

it('B1 Invoice Eksklusif 11% + diskon 10% + ongkir: AR=total, Pendapatan=DPP, PPN 11% efektif, HPP melepas barang terkirim', function () {
    $ctx = audSetup(['tax' => 11, 'tipe_pajak' => 'Eksklusif', 'discount' => 10], ['shipping_cost' => 50000]);
    $sj = audSj($this, $ctx);
    $inv = audInvoice($this, $ctx, $sj);

    $je = JournalEntry::withoutGlobalScopes()->where('source_type', Invoice::class)->where('source_id', $inv->id)->get();
    audLog('B1 invoice', ['subtotal' => (float) $inv->subtotal, 'tax' => (float) $inv->tax, 'other_fee' => $inv->other_fee, 'total' => (float) $inv->total]);
    audLog('B1 jurnal', $je->map(fn ($e) => [$e->coa->code, (float) $e->debit, (float) $e->credit])->all());

    // gross 100.000; diskon 10.000; DPP 90.000; PPN = 12% × (11/12 × 90.000) = 9.900; ongkir 50.000; total 149.900
    expect((float) $inv->total)->toBe(149900.0)
        ->and(audGl('1120'))->toBe(149900.0)
        ->and(audGl('4000'))->toBe(-90000.0)
        ->and(audGl('2120.06'))->toBe(-9900.0)
        ->and(audGl('6100.02'))->toBe(-50000.0)
        ->and(audGl('5100.10'))->toBe(50000.0)
        ->and(audGl('1140.20'))->toBe(0.0)
        ->and(round((float) $je->sum('debit'), 2))->toBe(round((float) $je->sum('credit'), 2))
        ->and(audArSub())->toBe(audGl('1120'))
        ->and(substr((string) $je->first()->date, 0, 10))->toBe(now()->toDateString());
});

it('B2 Invoice Inklusif 11%: DPP = total × 100/111, PPN = sisa; Tanpa Pajak: PPN 0', function () {
    $ctx = audSetup(['tax' => 11, 'tipe_pajak' => 'Inklusif', 'discount' => 0]);
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    audLog('B2 inklusif', ['subtotal' => (float) $inv->subtotal, 'tax' => (float) $inv->tax, 'total' => (float) $inv->total, 'GL4000' => audGl('4000'), 'GL2120.06' => audGl('2120.06')]);
    expect((float) $inv->total)->toBe(100000.0)
        ->and(audGl('4000'))->toBe(-90090.09)
        ->and(audGl('2120.06'))->toBe(-9909.91)
        ->and(audGl('1120'))->toBe(100000.0);
});

it('B3 Invoice Tanpa Pajak: tidak ada PPN keluaran', function () {
    $ctx = audSetup(['tax' => 0, 'tipe_pajak' => 'none']);
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    expect((float) $inv->total)->toBe(100000.0)->and(audGl('2120.06'))->toBe(0.0)->and(audGl('4000'))->toBe(-100000.0);
});

it('B4 Tarif SO 12% (tarif UU HPP 2025): PPN harus tetap 11% efektif (12% × DPP Nilai Lain 11/12)', function () {
    $ctx = audSetup(['tax' => 12, 'tipe_pajak' => 'Eksklusif']);
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    audLog('B4 tarif 12', ['dpp' => (float) $inv->dpp, 'tax' => (float) $inv->tax, 'total' => (float) $inv->total, 'cols' => array_keys($inv->getAttributes())]);
    expect(audGl('2120.06'))->toBe(-11000.0);   // 12% × (11/12 × 100.000)
});

it('B5 Invoice ganda untuk SJ yang sama ditolak & posting ulang tidak menggandakan jurnal', function () {
    $ctx = audSetup();
    $sj = audSj($this, $ctx);
    $inv = audInvoice($this, $ctx, $sj);
    $this->post(route('penjualan.invoices.store'), [
        'surat_jalan_ids' => [$sj->id], 'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(),
    ])->assertSessionHasErrors('surat_jalan_ids');
    (new \App\Observers\InvoiceObserver())->postSalesInvoice($inv->fresh());
    $inv->fresh()->update(['status' => Invoice::STATUS_SENT, 'notes' => 'x']);
    expect(Invoice::count())->toBe(1)->and(audGl('1120'))->toBe(100000.0)->and(audGl('5100.10'))->toBe(50000.0);
});

it('B6 HPP saat invoice = nilai barang terkirim saat SJ (HPP rata-rata berubah di antaranya tidak boleh meninggalkan saldo 1140.20)', function () {
    $ctx = audSetup();
    $sj = audSj($this, $ctx);
    // pembelian baru menaikkan HPP rata-rata sebelum invoice dibuat
    $ctx['product']->forceFill(['cost_price' => 6000])->saveQuietly();
    audInvoice($this, $ctx, $sj);
    audLog('B6 GL 5100.10 / 1140.20', [audGl('5100.10'), audGl('1140.20')]);
    expect(audGl('5100.10'))->toBe(50000.0)->and(audGl('1140.20'))->toBe(0.0);
});

it('B7 Pembulatan PPN: header invoice.tax harus sama dengan PPN yang dijurnal (contoh UAT 20 × 8.687, diskon 5%)', function () {
    $ctx = audSetup(['tax' => 11, 'tipe_pajak' => 'Eksklusif', 'discount' => 5, 'unit_price' => 8687, 'quantity' => 20]);
    $ctx['soItem']->update(['quantity' => 20]);
    $ctx['do']->deliveryOrderItem()->first()->update(['quantity' => 10]);
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    $itemTax = (float) $inv->invoiceItem()->sum('tax_amount');
    audLog('B7', ['header_tax' => $inv->tax, 'item_tax' => $itemTax, 'GLPPN' => audGl('2120.06'), 'total' => (float) $inv->total]);
    expect((float) $inv->tax)->toBe(-audGl('2120.06'));
});

// ---------------------------------------------------------------- 4. Penerimaan

it('C1 Parsial lalu lunas dengan kelebihan → deposit; subledger AR = GL 1120; deposit subledger = GL 2160.04', function () {
    $ctx = audSetup(['tax' => 11, 'tipe_pajak' => 'Eksklusif', 'discount' => 10], ['shipping_cost' => 50000]);
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));

    audPay($this, $ctx, $inv, 50000)->assertSessionHasNoErrors();
    expect(audGl('1120'))->toBe(99900.0)->and(audArSub())->toBe(99900.0)->and($inv->fresh()->status)->toBe(Invoice::STATUS_PARTIALLY_PAID);

    audPay($this, $ctx, $inv, 100000, ['overpayment_as_deposit' => 1])->assertSessionHasNoErrors();
    audLog('C1 akhir', ['GL1120' => audGl('1120'), 'AR' => audArSub(), 'GLkas' => audGl('1111.01'), 'GL2160' => audGl('2160.04'), 'depSub' => audDepositSub($ctx), 'inv' => $inv->fresh()->status]);
    expect(audGl('1120'))->toBe(0.0)->and(audArSub())->toBe(0.0)
        ->and(audGl('1111.01'))->toBe(150000.0)
        ->and(audGl('2160.04'))->toBe(-100.0)->and(audDepositSub($ctx))->toBe(100.0)
        ->and($inv->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and(audTrialBalance()[0])->toBe(audTrialBalance()[1]);
});

it('C2 Bayar TUNAI saat customer punya saldo titip: saldo titip TIDAK boleh berkurang', function () {
    $ctx = audSetup();
    audTitip($this, $ctx, 500000)->assertSessionHasNoErrors();
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    audPay($this, $ctx, $inv, 100000)->assertSessionHasNoErrors();
    audLog('C2', ['depSub' => audDepositSub($ctx), 'GL2160' => audGl('2160.04'), 'GLkas' => audGl('1111.01')]);
    expect(audDepositSub($ctx))->toBe(500000.0)->and(-audGl('2160.04'))->toBe(audDepositSub($ctx));
});

it('C3 Pelunasan memakai Deposit: Dr Deposit Pelanggan 2160.04 / Cr Piutang; kas tidak bertambah; saldo deposit turun sekali', function () {
    $ctx = audSetup();
    audTitip($this, $ctx, 500000)->assertSessionHasNoErrors();
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    audPay($this, $ctx, $inv, 100000, ['payment_method' => 'Deposit', 'coa_id' => $ctx['dep']->id])->assertSessionHasNoErrors();
    $rc = CustomerReceipt::latest('id')->first();
    audLog('C3 jurnal receipt', JournalEntry::withoutGlobalScopes()->where('source_type', CustomerReceipt::class)->where('source_id', $rc->id)->get()->map(fn ($e) => [$e->coa->code, (float) $e->debit, (float) $e->credit])->all());
    audLog('C3 deposit logs', \App\Models\DepositLog::all()->map(fn ($l) => [$l->type, (float) $l->amount, $l->deposit_loggable_type])->all());
    audLog('C3', ['depSub' => audDepositSub($ctx), 'GL2160' => audGl('2160.04'), 'GLkas' => audGl('1111.01'), 'GL1120' => audGl('1120'), 'AR' => audArSub()]);
    expect(audGl('1120'))->toBe(0.0)
        ->and(audGl('1111.01'))->toBe(500000.0)
        ->and(audGl('2160.04'))->toBe(-400000.0)
        ->and(audDepositSub($ctx))->toBe(400000.0);
});

it('C4 Titip dana COD dipakai verifikasi kasir: deposit subledger = GL 2160.04, dan piutang invoice SO itu terlunasi', function () {
    $ctx = audSetup([], ['total_amount' => 100000, 'shipping_cost' => 0]);
    $ctx['customer']->forceFill(['tipe_pembayaran' => 'COD (Bayar Lunas)'])->save();
    audTitip($this, $ctx, 100000)->assertSessionHasNoErrors();
    $res = app(SaleOrderCashierVerification::class)->verify($ctx['so']->fresh(), null, null, $ctx['user']);
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    audLog('C4', ['verify' => $res, 'depSub' => audDepositSub($ctx), 'GL2160' => audGl('2160.04'), 'GL1120' => audGl('1120'), 'AR' => audArSub(), 'inv' => $inv->fresh()->status]);
    expect(-audGl('2160.04'))->toBe(audDepositSub($ctx))
        ->and(audGl('1120'))->toBe(audArSub())
        ->and(audArSub())->toBe(0.0);
});

it('C5 Tanggal jurnal titip dana = tanggal bayar (payment_date), bukan tanggal input', function () {
    $ctx = audSetup();
    $paid = now()->subDays(3)->toDateString();
    audTitip($this, $ctx, 250000, ['payment_date' => $paid])->assertSessionHasNoErrors();
    $dates = JournalEntry::withoutGlobalScopes()->where('source_type', Deposit::class)->pluck('date')->map(fn ($d) => substr((string) $d, 0, 10))->unique()->values()->all();
    audLog('C5 tanggal jurnal deposit', $dates);
    expect($dates)->toBe([$paid]);
});

it('C6 Bayar kurang Rp 0,50 dari sisa: kas didebit sebesar uang yang benar-benar diterima', function () {
    $ctx = audSetup();
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    audPay($this, $ctx, $inv, 99999.50)->assertSessionHasNoErrors();
    audLog('C6', ['GLkas' => audGl('1111.01'), 'GL1120' => audGl('1120'), 'AR' => audArSub()]);
    expect(audGl('1111.01'))->toBe(99999.5);
});

// ---------------------------------------------------------------- 5. Pembatalan

it('D1 Batal penerimaan: jurnal pembalik; piutang & status invoice kembali; deposit kelebihan hilang', function () {
    $ctx = audSetup();
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    audPay($this, $ctx, $inv, 120000, ['overpayment_as_deposit' => 1])->assertSessionHasNoErrors();
    $receipt = CustomerReceipt::latest('id')->first();
    $this->post(route('penjualan.receipts.cancel', $receipt), ['reason' => 'Salah input nominal transfer'])->assertSessionHasNoErrors();
    audLog('D1', ['GL1120' => audGl('1120'), 'AR' => audArSub(), 'GLkas' => audGl('1111.01'), 'GL2160' => audGl('2160.04'), 'dep' => audDepositSub($ctx), 'inv' => $inv->fresh()->status, 'rc' => $receipt->fresh()->status]);
    expect($receipt->fresh()->status)->toBe('Cancelled')
        ->and(audGl('1120'))->toBe(100000.0)->and(audArSub())->toBe(100000.0)
        ->and(audGl('1111.01'))->toBe(0.0)->and(audGl('2160.04'))->toBe(0.0)->and(audDepositSub($ctx))->toBe(0.0)
        ->and($inv->fresh()->status)->not->toBe(Invoice::STATUS_PAID);
});

function audCancelInvoice(array $ctx, Invoice $inv): CreditNote
{
    config(['sales.controls.credit_notes' => true]);
    ChartOfAccount::firstOrCreate(['code' => '4120.10'], ['name' => 'Retur Penjualan', 'type' => 'Revenue', 'is_active' => true]);
    Permission::firstOrCreate(['name' => 'approve credit note', 'guard_name' => 'web']);
    $ctx['user']->givePermissionTo('approve credit note');
    $svc = app(CreditNoteService::class);
    $cn = $svc->draft($inv->fresh(), CreditNote::TYPE_CANCELLATION, [], 'Pembatalan faktur salah harga', [], $ctx['user']);

    return $svc->issue($cn, $ctx['user']->fresh());
}

it('D2 Batal invoice (Nota Kredit pembatalan): piutang, PPN, pendapatan bersih nol', function () {
    $ctx = audSetup(['tax' => 11, 'tipe_pajak' => 'Eksklusif']);
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    audCancelInvoice($ctx, $inv);
    audLog('D2', ['inv' => $inv->fresh()->status, 'GL1120' => audGl('1120'), 'AR' => audArSub(), 'PPN' => audGl('2120.06'), 'net_sales' => audGl('4000') + audGl('4120.10'), 'HPP' => audGl('5100.10'), '1140.20' => audGl('1140.20')]);
    expect($inv->fresh()->status)->toBe('cancelled')
        ->and(audGl('1120'))->toBe(0.0)->and(audArSub())->toBe(0.0)
        ->and(audGl('2120.06'))->toBe(0.0)
        ->and(round(audGl('4000') + audGl('4120.10'), 2))->toBe(0.0);
});

it('D3 Invoice dibatalkan lalu SJ yang sama difaktur ulang: HPP tidak boleh tercatat dua kali', function () {
    $ctx = audSetup();
    $sj = audSj($this, $ctx);
    $inv = audInvoice($this, $ctx, $sj);
    audCancelInvoice($ctx, $inv);
    expect($sj->fresh()->isInvoiced())->toBeFalse();   // SJ kembali tersedia untuk difaktur
    audInvoice($this, $ctx, $sj->fresh());
    audLog('D3', ['HPP' => audGl('5100.10'), '1140.20' => audGl('1140.20'), 'GL1120' => audGl('1120'), 'stok' => stkStock($ctx['product'], $ctx['warehouse'])['available']]);
    expect(audGl('5100.10'))->toBe(50000.0)->and(audGl('1140.20'))->toBe(0.0);
});

// ---------------------------------------------------------------- 6. Rekonsiliasi akhir (multi transaksi)

it('E1 Rekonsiliasi: GL piutang = subledger; GL PPN = Σ PPN invoice; GL penjualan = Σ DPP; persediaan turun = Σ qty×HPP', function () {
    $ctx = audSetup(['tax' => 11, 'tipe_pajak' => 'Eksklusif', 'discount' => 5], ['shipping_cost' => 25000]);
    $inv = audInvoice($this, $ctx, audSj($this, $ctx));
    audPay($this, $ctx, $inv, 40000)->assertSessionHasNoErrors();

    $invoices = Invoice::all();
    audLog('E1', ['GL1120' => audGl('1120'), 'AR' => audArSub(), 'GLPPN' => audGl('2120.06'), 'sumTax' => (float) $invoices->sum('tax'), 'GL4000' => audGl('4000'), 'sumDpp' => (float) $invoices->sum('dpp'), 'GL1140.10' => audGl('1140.10'), 'TB' => audTrialBalance()]);
    expect(audGl('1120'))->toBe(audArSub())
        ->and(-audGl('2120.06'))->toBe(round((float) $invoices->sum('tax'), 2))
        ->and(-audGl('4000'))->toBe(round((float) $invoices->sum('dpp'), 2))
        ->and(audGl('1140.10'))->toBe(-50000.0)
        ->and(audTrialBalance()[0])->toBe(audTrialBalance()[1]);
});
