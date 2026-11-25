<?php

/**
 * UAT 25 Nov 2026: tombol Kirim Barang (mutasi) tidak merespons. Penyebab: pengiriman pertama ke suatu cabang membuat
 * Gudang Transit, dan pembuatan gudang dulu membuat baris stok kosong untuk SETIAP produk satu per satu.
 */

use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

function wcpSeedProducts(int $n): void
{
    $base = Product::factory()->create();
    $row = collect($base->getAttributes())->except(['id'])->all();
    $rows = [];
    for ($i = 0; $i < $n; $i++) {
        $rows[] = array_merge($row, ['sku' => 'WCP-'.$i.'-'.uniqid(), 'name' => 'Produk Uji '.$i]);
        if (count($rows) === 500) { DB::table('products')->insert($rows); $rows = []; }
    }
    if ($rows) { DB::table('products')->insert($rows); }
}

it('gudang transit dibuat cepat tanpa baris stok kosong per produk', function () {
    $ctx = stkContext();
    wcpSeedProducts(3000);

    $t = microtime(true);
    $transit = app(\App\Services\StockTransferService::class)->transitWarehouse($ctx['cabang']->id);
    $secs = microtime(true) - $t;

    expect($transit->is_transit)->toBeTrue()
        ->and(InventoryStock::withoutGlobalScopes()->where('warehouse_id', $transit->id)->count())->toBe(0)
        ->and($secs)->toBeLessThan(1.0);
});

it('gudang biasa tetap mendapat baris stok kosong untuk semua produk, dibuat massal', function () {
    $ctx = stkContext();
    wcpSeedProducts(3000);
    $total = Product::query()->count();

    $t = microtime(true);
    $w = Warehouse::factory()->create(['cabang_id' => $ctx['cabang']->id]);
    $secs = microtime(true) - $t;

    expect(InventoryStock::withoutGlobalScopes()->where('warehouse_id', $w->id)->count())->toBe($total)
        ->and($secs)->toBeLessThan(5.0);
});
