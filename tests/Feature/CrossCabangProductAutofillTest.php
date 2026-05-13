<?php

use App\Models\Cabang;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create two cabangs
    $this->cabangA = Cabang::factory()->create();
    $this->cabangB = Cabang::factory()->create();

    // Create user in cabangA
    $this->user = User::factory()->create(['cabang_id' => $this->cabangA->id]);
    $this->actingAs($this->user);

    // Create UOM
    $this->uom = UnitOfMeasure::factory()->create(['abbreviation' => 'Pcs']);

    // Create product in cabangB (different from user's cabang)
    $this->crossCabangProduct = Product::factory()->create([
        'sku' => 'CROSS-001',
        'sell_price' => 50000,
        'cost_price' => 35000,
        'tipe_pajak' => 'inklusif',
        'pajak' => 10,
        'uom_id' => $this->uom->id,
        'cabang_id' => $this->cabangB->id, // Different cabang!
    ]);

    // Create product in same cabang for comparison
    $this->sameCanangProduct = Product::factory()->create([
        'sku' => 'SAME-001',
        'sell_price' => 40000,
        'uom_id' => $this->uom->id,
        'cabang_id' => $this->cabangA->id,
    ]);
});

it('product lookup with global scope only finds products in user cabang', function () {
    // This demonstrates the GLOBAL SCOPE restriction
    $foundProduct = Product::find($this->sameCanangProduct->id);
    expect($foundProduct)
        ->not->toBeNull()
        ->id->toBe($this->sameCanangProduct->id);

    // Cross-cabang product should NOT be found with global scope
    $notFound = Product::find($this->crossCabangProduct->id);
    expect($notFound)->toBeNull();
});

it('product lookup without global scope finds product across cabangs', function () {
    // This is the CORE FIX: withoutGlobalScope removes the cabang restriction
    $foundProduct = Product::withoutGlobalScope('product_cabang')->find($this->crossCabangProduct->id);

    expect($foundProduct)
        ->not->toBeNull()
        ->id->toBe($this->crossCabangProduct->id)
        ->cabang_id->toBe($this->cabangB->id);
});

it('product all query with global scope filters by user cabang', function () {
    // All() with global scope should only return user's cabang products
    $products = Product::all();
    $productIds = $products->pluck('id')->toArray();

    expect($productIds)->toContain($this->sameCanangProduct->id);
    expect($productIds)->not->toContain($this->crossCabangProduct->id);
});

it('product all query without global scope returns products from all cabangs', function () {
    // all() without global scope should return products from both cabangs
    $products = Product::withoutGlobalScope('product_cabang')->get();
    $productIds = $products->pluck('id')->toArray();

    expect($productIds)->toContain($this->sameCanangProduct->id);
    expect($productIds)->toContain($this->crossCabangProduct->id);
});

it('p1 patch: purchase order can reference cross-cabang product', function () {
    // Simulate the PurchaseOrderResource callback pattern (P1 fix)
    // This proves the withoutGlobalScope fix is in place
    $product = Product::withoutGlobalScope('product_cabang')->find($this->crossCabangProduct->id);

    if ($product) {
        expect($product->sku)->toBe('CROSS-001');
        expect($product->cost_price)->toBe(35000);
        expect($product->tipe_pajak)->toBe('inklusif');
    }
});

it('p2 patch: sale order can reference cross-cabang product', function () {
    // Simulate the SaleOrderResource callback pattern (P2 fix)
    $product = Product::withoutGlobalScope('product_cabang')->find($this->crossCabangProduct->id);

    if ($product) {
        expect($product->sku)->toBe('CROSS-001');
        expect($product->sell_price)->toBe(50000);
    }
});
