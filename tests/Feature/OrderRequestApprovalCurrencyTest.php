<?php

use App\Filament\Resources\OrderRequestResource;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\UnitOfMeasure;
use App\Services\OrderRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Test currency conversion during Order Request approval and PO creation
 * 
 * Verifies:
 * 1. Currency is properly transferred to Purchase Order items
 * 2. Prices remain consistent between OR and PO
 * 3. Multiple currencies in same OR are handled correctly
 * 4. Exchange rates are properly stored in PurchaseOrderCurrency
 */

beforeEach(function () {
    $this->service = app(OrderRequestService::class);
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create currencies
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
    $this->supplier = Supplier::factory()->create(['tempo_hutang' => 30]);

    // Create products
    $this->product1 = Product::factory()->create([
        'name' => 'Product 1',
        'sku' => 'P-001',
        'cost_price' => 150000, // $10 USD
    ]);

    $this->product2 = Product::factory()->create([
        'name' => 'Product 2',
        'sku' => 'P-002',
        'cost_price' => 300000, // $20 USD
    ]);
});

test('approval with IDR currency preserves currency and prices in PO', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'currency_id' => $this->currencyIdr->id,
        'request_date' => now()->toDateString(),
    ]);

    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product1->id,
        'quantity' => 5,
        'unit_price' => 150000,
        'currency_id' => $this->currencyIdr->id,
        'discount' => 0,
        'tax' => 0,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    // Approve with PO creation
    $result = $this->service->approve($orderRequest, [
        'create_purchase_order' => true,
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-001',
        'order_date' => now()->toDateString(),
    ]);

    expect($result->status)->toBe('approved');

    $po = $result->fresh()->purchaseOrders()->first();
    expect($po)->not->toBeNull();

    $poItem = $po->purchaseOrderItem()->first();
    expect($poItem)->not->toBeNull();
    expect($poItem->currency_id)->toBe($this->currencyIdr->id);
    expect((float)$poItem->unit_price)->toBe(150000.0);
});

test('approval with USD currency preserves currency and prices in PO', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'currency_id' => $this->currencyUsd->id,
        'request_date' => now()->toDateString(),
    ]);

    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product1->id,
        'quantity' => 5,
        'unit_price' => 10, // $10 USD
        'currency_id' => $this->currencyUsd->id,
        'discount' => 0,
        'tax' => 0,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    // Approve with PO creation
    $result = $this->service->approve($orderRequest, [
        'create_purchase_order' => true,
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-002',
        'order_date' => now()->toDateString(),
    ]);

    expect($result->status)->toBe('approved');

    $po = $result->fresh()->purchaseOrders()->first();
    expect($po)->not->toBeNull();

    $poItem = $po->purchaseOrderItem()->first();
    expect($poItem)->not->toBeNull();
    expect($poItem->currency_id)->toBe($this->currencyUsd->id);
    expect((float)$poItem->unit_price)->toBe(10.0);
});

test('approval creates PurchaseOrderCurrency entry for each unique currency', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'currency_id' => $this->currencyIdr->id,
        'request_date' => now()->toDateString(),
    ]);

    // Item in IDR
    OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product1->id,
        'quantity' => 5,
        'unit_price' => 150000,
        'currency_id' => $this->currencyIdr->id,
        'discount' => 0,
        'tax' => 0,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    // Item in USD
    OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product2->id,
        'quantity' => 3,
        'unit_price' => 20, // $20 USD
        'currency_id' => $this->currencyUsd->id,
        'discount' => 0,
        'tax' => 0,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    // Approve with PO creation
    $result = $this->service->approve($orderRequest, [
        'create_purchase_order' => true,
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-003',
        'order_date' => now()->toDateString(),
    ]);

    $po = $result->fresh()->purchaseOrders()->first();

    // Should have 2 currency entries
    expect($po->purchaseOrderCurrency)->toHaveCount(2);

    $currencyIdrEntry = $po->purchaseOrderCurrency()->where('currency_id', $this->currencyIdr->id)->first();
    $currencyUsdEntry = $po->purchaseOrderCurrency()->where('currency_id', $this->currencyUsd->id)->first();

    expect($currencyIdrEntry)->not->toBeNull();
    expect((float)$currencyIdrEntry->nominal)->toBe(1.0); // IDR rate

    expect($currencyUsdEntry)->not->toBeNull();
    expect((float)$currencyUsdEntry->nominal)->toBe(15000.0); // USD rate
});

test('multiple OR items with same currency use single currency entry in PO', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'currency_id' => $this->currencyUsd->id,
        'request_date' => now()->toDateString(),
    ]);

    // Two items in USD
    OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product1->id,
        'quantity' => 5,
        'unit_price' => 10,
        'currency_id' => $this->currencyUsd->id,
        'discount' => 0,
        'tax' => 0,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product2->id,
        'quantity' => 3,
        'unit_price' => 20,
        'currency_id' => $this->currencyUsd->id,
        'discount' => 0,
        'tax' => 0,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    $result = $this->service->approve($orderRequest, [
        'create_purchase_order' => true,
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-005',
        'order_date' => now()->toDateString(),
    ]);

    $po = $result->fresh()->purchaseOrders()->first();

    // Should have only 1 currency entry (USD)
    expect($po->purchaseOrderCurrency)->toHaveCount(1);

    $currencyEntry = $po->purchaseOrderCurrency()->first();
    expect($currencyEntry->currency_id)->toBe($this->currencyUsd->id);
    expect((float)$currencyEntry->nominal)->toBe(15000.0);
});

test('PO items maintain currency consistency with OR items', function () {
    $orderRequest = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'currency_id' => $this->currencyIdr->id,
        'request_date' => now()->toDateString(),
    ]);

    // Create items with different currencies
    $itemIdr = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product1->id,
        'quantity' => 5,
        'unit_price' => 150000,
        'currency_id' => $this->currencyIdr->id,
        'discount' => 0,
        'tax' => 0,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    $itemUsd = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $this->product2->id,
        'quantity' => 3,
        'unit_price' => 20,
        'currency_id' => $this->currencyUsd->id,
        'discount' => 0,
        'tax' => 0,
        'supplier_id' => $this->supplier->id,
        'cabang_id' => $this->cabang->id,
    ]);

    $result = $this->service->approve($orderRequest, [
        'create_purchase_order' => true,
        'supplier_id' => $this->supplier->id,
        'po_number' => 'PO-006',
        'order_date' => now()->toDateString(),
    ]);

    $po = $result->fresh()->purchaseOrders()->first();

    // Check that PO items match OR items
    $poItemIdr = $po->purchaseOrderItem()->where('product_id', $this->product1->id)->first();
    $poItemUsd = $po->purchaseOrderItem()->where('product_id', $this->product2->id)->first();

    expect($poItemIdr->currency_id)->toBe($this->currencyIdr->id);
    expect((float)$poItemIdr->unit_price)->toBe(150000.0);

    expect($poItemUsd->currency_id)->toBe($this->currencyUsd->id);
    expect((float)$poItemUsd->unit_price)->toBe(20.0);
});
