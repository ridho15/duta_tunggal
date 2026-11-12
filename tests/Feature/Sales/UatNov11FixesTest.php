<?php

/**
 * Temuan UAT 11 Nov 2026:
 *  B1 SO yang ditolak kasir/dibatalkan melepaskan Penawaran agar bisa dipakai lagi; SO tolak bisa disalin ke SO baru.
 *  B2 Referensi penawaran terbawa ke form SO.
 *  B3 Tipe pajak SO tersimpan beserta tarifnya.
 *  B4 Titip dana (deposit) customer COD tanpa invoice; verifikasi kasir memakai semua baris deposit aktif.
 *  A2 Form penawaran tidak lagi memuat seluruh master customer/produk.
 */

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Services\SaleOrderCashierVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function uatSoPayload(array $ctx, array $extra = [], array $item = []): array
{
    return array_merge([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'order_date' => now()->toDateString(),
        'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $ctx['idr']->id, 'action' => 'draft',
        'items' => [array_merge(['product_id' => $ctx['product']->id, 'quantity' => 2, 'unit_price' => 10000, 'discount' => 0], $item)],
    ], $extra);
}

function uatApprovedQuotation(array $ctx): Quotation
{
    $q = Quotation::factory()->create(['customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'status' => Quotation::STATUS_APPROVE, 'valid_until' => now()->addDays(10)]);
    QuotationItem::factory()->create(['quotation_id' => $q->id, 'product_id' => $ctx['product']->id, 'quantity' => 2, 'unit_price' => 10000, 'discount' => 0]);

    return $q;
}

it('B1: penawaran bisa dipakai lagi setelah SO-nya ditolak atau dibatalkan', function (string $status) {
    $ctx = stkContext();
    $quotation = uatApprovedQuotation($ctx);
    $so = SaleOrder::factory()->create(['customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'quotation_id' => $quotation->id, 'status' => 'approved']);

    expect($quotation->fresh()->unusableReasonForSaleOrder())->not->toBeNull()
        ->and(Quotation::usable()->whereKey($quotation->id)->exists())->toBeFalse();

    $so->forceFill(['status' => $status])->save();

    expect($quotation->fresh()->unusableReasonForSaleOrder())->toBeNull()
        ->and(Quotation::usable()->whereKey($quotation->id)->exists())->toBeTrue();
})->with(['reject', 'canceled']);

it('B1/B2: form SO dapat menyalin SO yang ditolak dan membawa item penawaran', function () {
    $ctx = stkContext();
    $quotation = uatApprovedQuotation($ctx);
    $rejected = SaleOrder::factory()->create(['customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'status' => 'reject', 'tipe_pengiriman' => 'Ambil Sendiri']);
    SaleOrderItem::factory()->create(['sale_order_id' => $rejected->id, 'product_id' => $ctx['product']->id, 'quantity' => 7, 'unit_price' => 12000, 'discount' => 0, 'tax' => 0, 'tipe_pajak' => null]);

    $this->actingAs($ctx['user'])->get(route('penjualan.so.create', ['copy_from' => $rejected->id]))
        ->assertOk()
        ->assertViewHas('copyFrom', fn ($c) => $c?->id === $rejected->id)
        ->assertViewHas('prefillItems', fn ($items) => count($items) === 1 && (float) $items[0]['quantity'] === 7.0);

    $this->get(route('penjualan.so.create', ['quotation_id' => $quotation->id]))
        ->assertOk()
        ->assertSee($quotation->quotation_number)
        ->assertViewHas('prefillItems', fn ($items) => count($items) === 1 && (float) $items[0]['unit_price'] === 10000.0);
});

it('B3: tipe pajak SO tersimpan beserta tarifnya dan masuk ke total', function () {
    $ctx = stkContext();

    $this->actingAs($ctx['user'])->post(route('penjualan.so.store'), uatSoPayload($ctx, ['tax_type' => 'Eksklusif']))->assertSessionHasNoErrors();
    $item = SaleOrder::latest('id')->first()->saleOrderItem()->first();
    expect($item->tipe_pajak)->toBe(\App\Support\TaxTypeHelper::EKLUSIF)->and((float) $item->tax)->toBeGreaterThan(0.0);

    $this->post(route('penjualan.so.store'), uatSoPayload($ctx, ['tax_type' => 'None']))->assertSessionHasNoErrors();
    $item = SaleOrder::latest('id')->first()->saleOrderItem()->first();
    expect($item->tipe_pajak)->toBe('none')->and((float) $item->tax)->toBe(0.0);

    $this->post(route('penjualan.so.store'), uatSoPayload($ctx, ['tax_type' => 'Ngawur']))->assertSessionHasErrors('tax_type');
});

it('B4: titip dana tanpa invoice tercatat sebagai deposit aktif dan jurnalnya seimbang', function () {
    $ctx = stkContext();
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');
    $kas = ChartOfAccount::firstOrCreate(['code' => '1110.01'], ['name' => 'Kas Operasional', 'type' => 'Asset', 'is_active' => true, 'is_cash_bank' => true]);
    ChartOfAccount::firstOrCreate(['code' => config('coa.customer_deposit')], ['name' => 'Deposit Pelanggan', 'type' => 'Liability', 'is_active' => true]);

    $this->actingAs($ctx['user'])->post(route('penjualan.receipts.store'), [
        'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Cash',
        'coa_id' => $kas->id, 'deposit_amount' => 750000, 'notes' => 'COD bayar di muka',
    ])->assertSessionHasNoErrors()->assertRedirect(route('penjualan.receipts.index'));

    $deposit = Deposit::where('from_model_type', Customer::class)->where('from_model_id', $ctx['customer']->id)->sole();
    expect((float) $deposit->remaining_amount)->toBe(750000.0)->and($deposit->status)->toBe('active')
        ->and($ctx['customer']->fresh()->depositBalance())->toBe(750000.0);

    $entries = \App\Models\JournalEntry::where('source_type', Deposit::class)->where('source_id', $deposit->id)->get();
    expect($entries)->not->toBeEmpty()
        ->and(round((float) $entries->sum('debit'), 2))->toBe(round((float) $entries->sum('credit'), 2));

    // Tanpa invoice dan tanpa nominal titip → ditolak
    $this->post(route('penjualan.receipts.store'), [
        'customer_id' => $ctx['customer']->id, 'payment_date' => now()->toDateString(), 'payment_method' => 'Cash', 'coa_id' => $kas->id,
    ])->assertSessionHasErrors('selected_invoices');
});

it('B4: verifikasi kasir memakai beberapa baris deposit aktif (FIFO) dan refund mengembalikannya', function () {
    $ctx = stkContext();
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');
    $ctx['customer']->forceFill(['tipe_pembayaran' => 'COD (Bayar Lunas)'])->save();
    $coa = ChartOfAccount::factory()->create();
    $mk = fn ($amt) => Deposit::create(['deposit_number' => 'DEP-'.uniqid(), 'from_model_type' => Customer::class, 'from_model_id' => $ctx['customer']->id,
        'amount' => $amt, 'used_amount' => 0, 'remaining_amount' => $amt, 'coa_id' => $coa->id, 'payment_coa_id' => $coa->id, 'status' => 'active', 'created_by' => $ctx['user']->id]);
    $d1 = $mk(150000);
    $d2 = $mk(100000);

    $so = SaleOrder::factory()->create(['customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'status' => 'request_approve', 'tipe_pengiriman' => 'Ambil Sendiri', 'shipping_cost' => 0, 'total_amount' => 200000]);
    SaleOrderItem::factory()->create(['sale_order_id' => $so->id, 'product_id' => $ctx['product']->id, 'quantity' => 2, 'unit_price' => 100000, 'discount' => 0, 'tax' => 0, 'tipe_pajak' => null]);

    $service = app(SaleOrderCashierVerification::class);
    $service->verify($so->fresh(), null, null, $ctx['user']);

    expect((float) $d1->fresh()->remaining_amount)->toBe(0.0)
        ->and((float) $d2->fresh()->remaining_amount)->toBe(50000.0)
        ->and($ctx['customer']->fresh()->depositBalance())->toBe(50000.0);

    expect($service->refund($so->fresh(), $ctx['user']))->toBe(200000.0);
    expect((float) $d1->fresh()->remaining_amount)->toBe(150000.0)
        ->and((float) $d2->fresh()->remaining_amount)->toBe(100000.0);
});

it('A2: form penawaran tidak memuat seluruh master customer/produk', function () {
    $ctx = stkContext();
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');
    Customer::factory()->count(3)->create(['cabang_id' => $ctx['cabang']->id]);

    $this->actingAs($ctx['user'])->get(route('penjualan.quotations.create'))
        ->assertOk()
        ->assertViewHas('customers', fn ($c) => count($c) === 0)
        ->assertViewHas('products', fn ($p) => count($p) === 0)
        ->assertSee('so\\/products\\/search', false);
});

it('UAT 12 Nov: tombol Setujui penawaran berada dalam scope Alpine (layout <main x-data>) dan modal punya kolom alasan untuk pembuat', function () {
    $ctx = stkContext();
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');
    $quotation = Quotation::factory()->create(['customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id,
        'status' => Quotation::STATUS_REQUEST_APPROVE, 'created_by' => $ctx['user']->id, 'valid_until' => now()->addDays(10)]);

    $this->actingAs($ctx['user'])->get(route('penjualan.quotations.show', $quotation))
        ->assertOk()
        ->assertSee('<main x-data', false)
        ->assertSee("open-modal-approve-quotation", false)
        ->assertSee('name="override_reason"', false);
});
