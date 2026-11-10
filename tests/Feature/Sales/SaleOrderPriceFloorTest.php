<?php

/**
 * Keputusan Ko Handy 09 Nov 2026 butir 5: harga di bawah batas DITOLAK saat SO disimpan. Harga di bawah batas hanya sah lewat
 * Penawaran (Quotation) yang sudah di-approve.
 */

use App\Models\Currency;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function floorPayload(array $ctx, float $price, array $extra = []): array
{
    $idr = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);

    return array_merge([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'order_date' => now()->toDateString(),
        'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $idr->id, 'action' => 'draft',
        'items' => [['product_id' => $ctx['product']->id, 'quantity' => 2, 'unit_price' => $price, 'discount' => 0, 'tax_type' => 'None']],
    ], $extra);
}

function floorContext(): array
{
    $ctx = stkContext();
    $ctx['product']->forceFill(['sell_price' => 10000, 'cost_price' => 6000, 'harga_batas' => 10])->save();   // batas 10% → 9.000
    $ctx['customer']->forceFill(['tipe_pembayaran' => 'Bebas'])->save();

    return $ctx;
}

it('harga di bawah batas ditolak saat simpan; tepat di batas lolos', function () {
    $ctx = floorContext();

    $this->actingAs($ctx['user'])->post(route('penjualan.so.store'), floorPayload($ctx, 8500))
        ->assertSessionHasErrors('items.0.unit_price');
    expect(SaleOrder::count())->toBe(0);

    $this->post(route('penjualan.so.store'), floorPayload($ctx, 9000))->assertSessionHasNoErrors();
    expect(SaleOrder::count())->toBe(1);
});

it('diskon yang membuat harga efektif di bawah batas juga ditolak', function () {
    $ctx = floorContext();
    $payload = floorPayload($ctx, 10000);
    $payload['items'][0]['discount'] = 15;   // efektif 8.500

    $this->actingAs($ctx['user'])->post(route('penjualan.so.store'), $payload)->assertSessionHasErrors('items.0.unit_price');
});

it('harga di bawah batas dari Penawaran yang sudah di-approve diterima; dari Penawaran belum di-approve ditolak', function () {
    $ctx = floorContext();
    $quotation = Quotation::factory()->create(['customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'status' => Quotation::STATUS_REQUEST_APPROVE]);
    QuotationItem::factory()->create(['quotation_id' => $quotation->id, 'product_id' => $ctx['product']->id, 'quantity' => 2, 'unit_price' => 8500, 'discount' => 0]);

    $this->actingAs($ctx['user'])->post(route('penjualan.so.store'), floorPayload($ctx, 8500, ['quotation_id' => $quotation->id]))
        ->assertSessionHasErrors('items.0.unit_price');

    $quotation->update(['status' => Quotation::STATUS_APPROVE]);

    // Lebih rendah dari harga penawaran yang disetujui tetap ditolak
    $this->post(route('penjualan.so.store'), floorPayload($ctx, 8000, ['quotation_id' => $quotation->id]))
        ->assertSessionHasErrors('items.0.unit_price');

    $this->post(route('penjualan.so.store'), floorPayload($ctx, 8500, ['quotation_id' => $quotation->id]))->assertSessionHasNoErrors();
    expect(SaleOrder::where('quotation_id', $quotation->id)->count())->toBe(1);
});

it('SO yang masih punya DO aktif tidak bisa dibatalkan; setelah DO ditutup bisa', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 10);
    [$so, $item] = stkSaleOrder($ctx, 5);
    $do = stkDeliveryOrder($ctx, $so, $item, 5, 'approved');
    $service = app(\App\Services\SalesOrderService::class);

    expect(fn () => $service->cancel($so->fresh()))->toThrow(\Illuminate\Validation\ValidationException::class, $do->do_number);
    expect($so->fresh()->status)->not->toBe('canceled');

    $do->update(['status' => 'closed']);
    $service->cancel($so->fresh());
    expect($so->fresh()->status)->toBe('canceled');
});
