<?php

/**
 * Keputusan Ko Handy 21 Nov 2026: HPP menganut AVERAGING (rata-rata bergerak); "harga beli terakhir" = referensi terpisah.
 */

use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function purchaseIn(array $ctx, float $qty, float $unitPrice): StockMovement
{
    return StockMovement::create([
        'product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => $qty,
        'value' => round($qty * $unitPrice, 2), 'type' => 'purchase_in', 'date' => now()->toDateString(),
        'notes' => 'Uji pembelian', 'meta' => ['po_unit_price_idr' => $unitPrice],
    ]);
}

it('HPP dihitung rata-rata bergerak dan harga beli terakhir disimpan terpisah', function () {
    $ctx = stkContext();                                   // HPP awal 5000
    stkSetStock($ctx['product'], $ctx['warehouse'], 10);   // 10 unit @5000

    purchaseIn($ctx, 10, 7000);                            // (10×5000 + 10×7000) / 20 = 6000
    $p = $ctx['product']->fresh();
    expect((float) $p->cost_price)->toBe(6000.0)
        ->and((float) $p->last_purchase_price)->toBe(7000.0)
        ->and($p->last_purchase_at?->toDateString())->toBe(now()->toDateString());

    purchaseIn($ctx, 20, 4500);                            // (20×6000 + 20×4500) / 40 = 5250
    $p = $ctx['product']->fresh();
    expect((float) $p->cost_price)->toBe(5250.0)
        ->and((float) $p->last_purchase_price)->toBe(4500.0)
        ->and((float) \App\Models\InventoryStock::where('product_id', $p->id)->sum('qty_available'))->toBe(40.0);
});

it('stok kosong: HPP = harga beli pertama', function () {
    $ctx = stkContext();
    $ctx['product']->forceFill(['cost_price' => 0])->save();

    purchaseIn($ctx, 5, 8000);
    expect((float) $ctx['product']->fresh()->cost_price)->toBe(8000.0);
});

it('barang keluar tidak mengubah HPP rata-rata', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 10);
    purchaseIn($ctx, 10, 7000);   // 6000

    StockMovement::create([
        'product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 5, 'value' => 30000,
        'type' => 'sales', 'date' => now()->toDateString(), 'notes' => 'Uji jual',
    ]);
    expect((float) $ctx['product']->fresh()->cost_price)->toBe(6000.0);
});
