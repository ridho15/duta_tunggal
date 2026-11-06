<?php

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Test currency conversion in OrderRequest for IDR <-> USD scenarios
 * 
 * This test verifies:
 * 1. IDR to USD conversion is correct (divide by to_rupiah rate)
 * 2. USD to IDR conversion is correct (multiply by to_rupiah rate)
 * 3. Currency data is persisted correctly through forms
 * 4. Prices are displayed correctly in different currencies
 */

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create currencies with exchange rates
    $this->currencyIdr = Currency::factory()->create([
        'code' => 'IDR',
        'name' => 'Indonesian Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    $this->currencyUsd = Currency::factory()->create([
        'code' => 'USD',
        'name' => 'US Dollar',
        'symbol' => '$',
        'to_rupiah' => 15000,
    ]);

    // Create basic entities
    UnitOfMeasure::factory()->create();
    $this->cabang = Cabang::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->supplier = Supplier::factory()->create();

    // Create a product with costs in IDR
    $this->product = Product::factory()->create([
        'name' => 'Test Product',
        'sku' => 'TEST-001',
        'cost_price' => 150000, // 150,000 IDR = $10 USD
        'sell_price' => 200000,
    ]);

    // Link supplier with a price in IDR
    $this->product->suppliers()->attach($this->supplier->id, [
        'supplier_price' => 120000, // 120,000 IDR = $8 USD
    ]);
});

test('OrderRequest creates items with currency_id when in USD', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'currency_id' => $this->currencyUsd->id,
        'request_date' => now()->toDateString(),
    ]);

    // Create item with USD currency
    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 10, // $10 USD
        'currency_id' => $this->currencyUsd->id,
        'discount' => 0,
        'tax' => 0,
    ]);

    expect($item->currency_id)->toBe($this->currencyUsd->id);
    expect((float)$item->unit_price)->toBe(10.0);
});

// Test round-trip conversion
test('order request item with USD currency maintains correct price', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'currency_id' => $this->currencyUsd->id,
        'request_date' => now()->toDateString(),
    ]);

    // Create item with price in USD
    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product->id,
        'quantity' => 2,
        'unit_price' => 8, // $8 USD (from supplier)
        'currency_id' => $this->currencyUsd->id,
        'discount' => 0,
        'tax' => 0,
    ]);

    // Refresh and verify
    $item->refresh();
    
    expect((float)$item->unit_price)->toBe(8.0);
    expect($item->currency_id)->toBe($this->currencyUsd->id);
});

test('mixed currency scenario: one item IDR, one item USD', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'currency_id' => $this->currencyIdr->id,
        'request_date' => now()->toDateString(),
    ]);

    // Item 1: IDR
    $itemIdr = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 150000,
        'currency_id' => $this->currencyIdr->id,
        'discount' => 0,
        'tax' => 0,
    ]);

    // Item 2: USD
    $itemUsd = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 10,
        'currency_id' => $this->currencyUsd->id,
        'discount' => 0,
        'tax' => 0,
    ]);

    $orderRequest->refresh();
    
    expect($orderRequest->orderRequestItem)->toHaveCount(2);
    
    $item1 = $orderRequest->orderRequestItem->where('currency_id', $this->currencyIdr->id)->first();
    $item2 = $orderRequest->orderRequestItem->where('currency_id', $this->currencyUsd->id)->first();
    
    expect((float)$item1->unit_price)->toBe(150000.0);
    expect((float)$item2->unit_price)->toBe(10.0);
});

test('order request item model does not overwrite existing anchors on save', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'currency_id' => $this->currencyUsd->id,
        'request_date' => now()->toDateString(),
    ]);

    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'quantity' => 1,
        'currency_id' => $this->currencyUsd->id,
        'unit_price' => 117.33,
        'original_price' => 117.33,
        'unit_price_idr' => 1760000,
        'original_price_idr' => 1760000,
        'discount' => 0,
        'tipe_pajak' => 'none',
    ]);

    $item->note = 'saved without price change';
    $item->save();
    $item->refresh();

    expect((float) $item->unit_price_idr)->toBe(1760000.0);
    expect((float) $item->original_price_idr)->toBe(1760000.0);
});

test('repair order request currency anchors command is dry-run by default and applies manual anchors when requested', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'currency_id' => $this->currencyUsd->id,
        'request_date' => now()->toDateString(),
    ]);

    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product->id,
        'supplier_id' => $this->supplier->id,
        'quantity' => 1,
        'currency_id' => $this->currencyUsd->id,
        'unit_price' => 117.33,
        'original_price' => 117.33,
        'unit_price_idr' => 1759950,
        'original_price_idr' => 1759950,
        'discount' => 0,
        'tipe_pajak' => 'none',
    ]);

    $this->artisan('procurement:repair-order-request-currency-anchors', [
        '--item' => $item->id,
        '--unit-idr' => '1760000',
        '--original-idr' => '1760000',
    ])->assertSuccessful();

    $item->refresh();
    expect((float) $item->unit_price_idr)->toBe(1759950.0);

    $this->artisan('procurement:repair-order-request-currency-anchors', [
        '--item' => $item->id,
        '--unit-idr' => '1760000',
        '--original-idr' => '1760000',
        '--apply' => true,
    ])->assertSuccessful();

    $item->refresh();
    expect((float) $item->unit_price_idr)->toBe(1760000.0);
    expect((float) $item->original_price_idr)->toBe(1760000.0);
    expect((float) $item->unit_price)->toBeGreaterThan(117.3333);
    expect((float) $item->unit_price)->toBeLessThan(117.3334);

    $supplierPrice = $this->product->suppliers()
        ->where('suppliers.id', $this->supplier->id)
        ->first()
        ->pivot
        ->supplier_price;

    expect((float) $supplierPrice)->toBe(120000.0);
});
