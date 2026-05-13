<?php

use App\Filament\Resources\OrderRequestResource;
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

test('convertIdrToCurrency correctly converts IDR 150000 to USD (150000 / 15000 = 10)', function () {
    $amountInIdr = 150000;
    $convertedAmount = OrderRequestResource::convertIdrToCurrency($amountInIdr, $this->currencyUsd->id);
    
    expect($convertedAmount)->toBe(10.0);
});

test('convertIdrToCurrency handles zero currency rate gracefully', function () {
    $badCurrency = Currency::factory()->create([
        'code' => 'BAD',
        'to_rupiah' => 0,
    ]);

    $amountInIdr = 100000;
    $convertedAmount = OrderRequestResource::convertIdrToCurrency($amountInIdr, $badCurrency->id);
    
    // Should default to 1.0 rate
    expect($convertedAmount)->toBe((float)$amountInIdr);
});

test('convertIdrToCurrency with null currency_id returns full amount (assumes IDR)', function () {
    $amountInIdr = 150000;
    $convertedAmount = OrderRequestResource::convertIdrToCurrency($amountInIdr, null);
    
    // Should assume rate of 1 (IDR)
    expect($convertedAmount)->toBe((float)$amountInIdr);
});

test('resolveCurrencyRateToRupiah returns correct exchange rate for USD', function () {
    $rate = OrderRequestResource::resolveCurrencyRateToRupiah($this->currencyUsd->id);
    
    expect($rate)->toBe(15000.0);
});

test('resolveCurrencyRateToRupiah returns 1 for IDR', function () {
    $rate = OrderRequestResource::resolveCurrencyRateToRupiah($this->currencyIdr->id);
    
    expect($rate)->toBe(1.0);
});

test('formatMoneyByCurrency formats USD with dollar symbol', function () {
    $formatted = OrderRequestResource::formatMoneyByCurrency($this->currencyUsd->id, 10.5);
    
    expect($formatted)->toContain('$');
    expect($formatted)->toContain('11'); // number_format rounds 10.5 to 11
});

test('formatMoneyByCurrency formats IDR with Rp symbol', function () {
    $formatted = OrderRequestResource::formatMoneyByCurrency($this->currencyIdr->id, 150000);
    
    expect($formatted)->toContain('Rp');
    expect($formatted)->toContain('150');
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

test('resolveCurrencySymbol returns correct symbol for USD', function () {
    $symbol = OrderRequestResource::resolveCurrencySymbol($this->currencyUsd->id);
    
    expect($symbol)->toBe('$');
});

test('resolveCurrencySymbol returns correct symbol for IDR', function () {
    $symbol = OrderRequestResource::resolveCurrencySymbol($this->currencyIdr->id);
    
    expect($symbol)->toBe('Rp');
});

test('resolveCurrencySymbol defaults to Rp for null currency_id', function () {
    $symbol = OrderRequestResource::resolveCurrencySymbol(null);
    
    expect($symbol)->toBe('Rp');
});

test('supplier options display converted prices in USD', function () {
    // Get supplier options with USD currency
    $options = OrderRequestResource::resolveSupplierOptions(
        productId: $this->product->id,
        currencyId: $this->currencyUsd->id
    );

    $supplierOption = $options[$this->supplier->id];

    // The supplier price should be converted from 120,000 IDR to $8 USD
    // (120,000 / 15,000 = 8)
    expect($supplierOption)->toContain($this->supplier->perusahaan);
    expect($supplierOption)->toContain('$'); // Should have USD symbol
    expect($supplierOption)->toContain('8'); // $8 USD
});

test('supplier options display prices in IDR when currency is IDR', function () {
    $options = OrderRequestResource::resolveSupplierOptions(
        productId: $this->product->id,
        currencyId: $this->currencyIdr->id
    );

    $supplierOption = $options[$this->supplier->id];

    expect($supplierOption)->toContain('120'); // 120,000 IDR
    expect($supplierOption)->toContain('Rp'); // Rp symbol
});

test('supplier label reflects correct converted price in USD', function () {
    $label = OrderRequestResource::resolveSupplierLabel(
        supplierId: $this->supplier->id,
        productId: $this->product->id,
        currencyId: $this->currencyUsd->id
    );

    // 120,000 IDR / 15,000 = $8
    expect($label)->toContain('$');
    expect($label)->toContain('8');
});

test('supplier label reflects price in IDR', function () {
    $label = OrderRequestResource::resolveSupplierLabel(
        supplierId: $this->supplier->id,
        productId: $this->product->id,
        currencyId: $this->currencyIdr->id
    );

    expect($label)->toContain('Rp');
    expect($label)->toContain('120');
});

// Test round-trip conversion
test('round trip conversion IDR to USD and back should be consistent', function () {
    $originalPriceIdr = 150000;

    // Convert IDR to USD
    $priceUsd = OrderRequestResource::convertIdrToCurrency($originalPriceIdr, $this->currencyUsd->id);
    expect($priceUsd)->toBe(10.0);

    // Convert back: USD to IDR (multiply by rate)
    $rateToRupiah = OrderRequestResource::resolveCurrencyRateToRupiah($this->currencyUsd->id);
    $backToIdr = $priceUsd * $rateToRupiah;
    
    expect($backToIdr)->toBe((float)$originalPriceIdr);
});

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
