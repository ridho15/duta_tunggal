<?php

use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Ensure a UnitOfMeasure exists because ProductFactory depends on it.
    \App\Models\UnitOfMeasure::factory()->create();
});

test('order request items can have different suppliers (multi-supplier)', function () {
    $supplier1 = Supplier::factory()->create(['perusahaan' => 'Supplier A']);
    $supplier2 = Supplier::factory()->create(['perusahaan' => 'Supplier B']);

    $product1 = Product::factory()->create(['name' => 'Product 1']);
    $product2 = Product::factory()->create(['name' => 'Product 2']);
    $product3 = Product::factory()->create(['name' => 'Product 3']);

    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $orderRequest = OrderRequest::factory()->create([
        'warehouse_id' => $warehouse->id,
        'created_by'   => $user->id,
    ]);

    OrderRequestItem::create(['order_request_id' => $orderRequest->id, 'product_id' => $product1->id, 'quantity' => 5, 'supplier_id' => $supplier1->id]);
    OrderRequestItem::create(['order_request_id' => $orderRequest->id, 'product_id' => $product2->id, 'quantity' => 3, 'supplier_id' => $supplier1->id]);
    OrderRequestItem::create(['order_request_id' => $orderRequest->id, 'product_id' => $product3->id, 'quantity' => 7, 'supplier_id' => $supplier2->id]);

    $items = $orderRequest->fresh()->orderRequestItem;
    expect($items)->toHaveCount(3);

    $uniqueSuppliers = $items->pluck('supplier_id')->filter()->unique();
    expect($uniqueSuppliers)->toHaveCount(2);
    expect($uniqueSuppliers->contains($supplier1->id))->toBeTrue();
    expect($uniqueSuppliers->contains($supplier2->id))->toBeTrue();
});

test('order request creation without supplier_id', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $product1 = Product::factory()->create();
    $product2 = Product::factory()->create();
    $supplier = Supplier::factory()->create();

    test()->actingAs($user);

    // OrderRequest no longer has a supplier at the header level
    $orderRequest = OrderRequest::create([
        'request_number' => 'OR-TEST-001',
        'warehouse_id' => $warehouse->id,
        'request_date' => now()->toDateString(),
        'status' => 'draft',
        'note' => 'Test order request',
        'created_by' => $user->id,
    ]);

    expect($orderRequest->warehouse_id)->toBe($warehouse->id);
    expect($orderRequest->status)->toBe('draft');

    // Items carry the supplier_id per-item
    OrderRequestItem::create(['order_request_id' => $orderRequest->id, 'product_id' => $product1->id, 'quantity' => 10, 'supplier_id' => $supplier->id]);
    OrderRequestItem::create(['order_request_id' => $orderRequest->id, 'product_id' => $product2->id, 'quantity' => 5, 'supplier_id' => $supplier->id]);

    expect($orderRequest->fresh()->orderRequestItem)->toHaveCount(2);
    expect($orderRequest->fresh()->orderRequestItem->every(fn($i) => $i->supplier_id === $supplier->id))->toBeTrue();
});

test('items can be grouped by supplier_id for multi-PO creation', function () {
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $productA = Product::factory()->create();
    $productB = Product::factory()->create();
    $productC = Product::factory()->create();

    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $orderRequest = OrderRequest::factory()->create([
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
    ]);

    $item1 = OrderRequestItem::create(['order_request_id' => $orderRequest->id, 'product_id' => $productA->id, 'quantity' => 5, 'supplier_id' => $supplier1->id]);
    $item2 = OrderRequestItem::create(['order_request_id' => $orderRequest->id, 'product_id' => $productB->id, 'quantity' => 3, 'supplier_id' => $supplier2->id]);
    $item3 = OrderRequestItem::create(['order_request_id' => $orderRequest->id, 'product_id' => $productC->id, 'quantity' => 2, 'supplier_id' => $supplier2->id]);

    // Simulate the group-by logic used in the approve action
    $groups = $orderRequest->fresh()->orderRequestItem->groupBy('supplier_id');

    expect($groups)->toHaveCount(2);
    expect($groups[$supplier1->id])->toHaveCount(1);
    expect($groups[$supplier2->id])->toHaveCount(2);
});

test('order request fillable attributes (no supplier_id on OR level)', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $data = [
        'request_number' => 'OR-FILLABLE-TEST',
        'warehouse_id' => $warehouse->id,
        'request_date' => '2025-11-13',
        'status' => 'draft',
        'note' => 'Testing fillable attributes',
        'created_by' => $user->id,
    ];

    $orderRequest = OrderRequest::create($data);

    expect($orderRequest->request_number)->toBe($data['request_number']);
    expect($orderRequest->warehouse_id)->toBe($data['warehouse_id']);
    expect($orderRequest->request_date)->toBe($data['request_date']);
    expect($orderRequest->status)->toBe($data['status']);
    expect($orderRequest->note)->toBe($data['note']);
    expect($orderRequest->created_by)->toBe($data['created_by']);
    // supplier_id no longer exists on OrderRequest — it lives on items
    expect(array_key_exists('supplier_id', $orderRequest->getAttributes()))->toBeFalse();
});
