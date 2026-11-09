<?php

/**
 * Prioritas 03 Nov 2026 butir 2: halaman Buat SO tidak lagi menempelkan seluruh master produk; produk dicari lewat AJAX.
 */

use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('halaman Buat SO tidak memuat master produk di payload', function () {
    $ctx = stkContext();
    foreach (range(1, 40) as $i) {
        stkProduct($ctx, ['name' => "Barang Massal Uji {$i}", 'sku' => sprintf('MASS-%04d', $i)]);
    }

    $html = $this->actingAs($ctx['user'])->get(route('penjualan.so.create'))->assertOk()->getContent();

    expect($html)->not->toContain('Barang Massal Uji')
        ->and($html)->not->toContain('MASS-0001');
});

it('produk dari quotation tetap ter-prefill, produk lain tidak ikut dikirim', function () {
    $ctx = stkContext();
    $other = stkProduct($ctx, ['name' => 'Produk Tak Terkait', 'sku' => 'NOPE-1']);
    $quotation = Quotation::factory()->create(['customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id]);
    QuotationItem::factory()->create(['quotation_id' => $quotation->id, 'product_id' => $ctx['product']->id, 'quantity' => 2, 'unit_price' => 10000]);

    $html = $this->actingAs($ctx['user'])->get(route('penjualan.so.create', ['quotation_id' => $quotation->id]))->assertOk()->getContent();

    expect($html)->toContain($ctx['product']->sku)->and($html)->not->toContain('NOPE-1');
});

it('pencarian produk: cocok nama/SKU, SKU persis didahulukan, berisi harga/stok/satuan, maksimal 25', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 15);
    $ctx['product']->forceFill(['name' => 'Kabel Serbaguna', 'sku' => 'KBL-001', 'sell_price' => 12500])->save();
    stkProduct($ctx, ['name' => 'Kabel Cadangan', 'sku' => 'ZZ-KBL-001-X']);
    foreach (range(1, 30) as $i) {
        stkProduct($ctx, ['name' => "Kabel Seri {$i}", 'sku' => "SER-{$i}"]);
    }

    $json = $this->actingAs($ctx['user'])->getJson(route('penjualan.so.products.search', ['q' => 'KBL-001']))->assertOk()->json();

    expect($json['success'])->toBeTrue()
        ->and($json['products'][0]['sku'])->toBe('KBL-001')           // persis di atas yang hanya mengandung
        ->and($json['products'][0]['sell_price'])->toEqual(12500)
        ->and($json['products'][0]['bottom_price'])->toEqual(\App\Support\ProductPriceLimitation::getMinimumAllowedPrice($ctx['product']->fresh()))   // batas harga master produk (dulu selalu 0)
        ->and($json['products'][0]['free_stock'])->toEqual(15)
        ->and($json['products'][0]['uom'])->toBe('pcs');

    $many = $this->getJson(route('penjualan.so.products.search', ['q' => 'kabel']))->assertOk()->json('products');
    expect($many)->toHaveCount(25);

    // semua kata harus cocok
    $both = $this->getJson(route('penjualan.so.products.search', ['q' => 'kabel cadangan']))->json('products');
    expect(collect($both)->pluck('name')->all())->toBe(['Kabel Cadangan']);
});

it('pencarian produk: kata kosong ditolak, produk nonaktif tidak muncul, karakter LIKE tidak jadi wildcard', function () {
    $ctx = stkContext();
    stkProduct($ctx, ['name' => 'Produk Mati', 'sku' => 'OFF-1', 'is_active' => false]);
    stkProduct($ctx, ['name' => 'Diskon 50% Spesial', 'sku' => 'PCT-1']);
    stkProduct($ctx, ['name' => 'Diskon 5000 Spesial', 'sku' => 'PCT-2']);

    $this->actingAs($ctx['user'])->getJson(route('penjualan.so.products.search'))->assertStatus(422);
    $this->getJson(route('penjualan.so.products.search', ['q' => 'OFF-1']))->assertOk()->assertJsonCount(0, 'products');

    $names = collect($this->getJson(route('penjualan.so.products.search', ['q' => '50%']))->json('products'))->pluck('name')->all();
    expect($names)->toBe(['Diskon 50% Spesial']);
});

it('pencarian produk membutuhkan login', function () {
    $this->getJson(route('penjualan.so.products.search', ['q' => 'a']))->assertUnauthorized();
});
