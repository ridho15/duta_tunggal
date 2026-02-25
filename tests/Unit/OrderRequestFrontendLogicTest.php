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

test('supplier product filtering logic', function () {
    // Create test data
    $supplier1 = Supplier::factory()->create(['perusahaan' => 'Supplier A']);
    $supplier2 = Supplier::factory()->create(['perusahaan' => 'Supplier B']);

    $product1 = Product::factory()->create(['supplier_id' => $supplier1->id, 'name' => 'Product 1']);
    $product2 = Product::factory()->create(['supplier_id' => $supplier1->id, 'name' => 'Product 2']);
    $product3 = Product::factory()->create(['supplier_id' => $supplier2->id, 'name' => 'Product 3']);

    // Test: When supplier1 is selected, only products from supplier1 should be available
    $availableProductsForSupplier1 = Product::where('supplier_id', $supplier1->id)->get();
    expect($availableProductsForSupplier1)->toHaveCount(2);
    expect($availableProductsForSupplier1->contains('id', $product1->id))->toBeTrue();
    expect($availableProductsForSupplier1->contains('id', $product2->id))->toBeTrue();
    expect($availableProductsForSupplier1->contains('id', $product3->id))->toBeFalse();

    // Test: When supplier2 is selected, only products from supplier2 should be available
    $availableProductsForSupplier2 = Product::where('supplier_id', $supplier2->id)->get();
    expect($availableProductsForSupplier2)->toHaveCount(1);
    expect($availableProductsForSupplier2->contains('id', $product3->id))->toBeTrue();
    expect($availableProductsForSupplier2->contains('id', $product1->id))->toBeFalse();
    expect($availableProductsForSupplier2->contains('id', $product2->id))->toBeFalse();
});

test('order request creation with supplier and products', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();
    $product1 = Product::factory()->create(['supplier_id' => $supplier->id]);
    $product2 = Product::factory()->create(['supplier_id' => $supplier->id]);

    test()->actingAs($user);

    // Create order request with supplier
    $orderRequest = OrderRequest::create([
        'request_number' => 'OR-TEST-001',
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'request_date' => now()->toDateString(),
        'status' => 'draft',
        'note' => 'Test order request',
        'created_by' => $user->id,
    ]);

    expect($orderRequest->supplier_id)->toBe($supplier->id);
    expect($orderRequest->warehouse_id)->toBe($warehouse->id);
    expect($orderRequest->status)->toBe('draft');

    // Add items with products from the same supplier
    $item1 = OrderRequestItem::create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $product1->id,
        'quantity' => 10,
    ]);

    $item2 = OrderRequestItem::create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $product2->id,
        'quantity' => 5,
    ]);

    expect($orderRequest->fresh()->orderRequestItem)->toHaveCount(2);
});

test('supplier change clears invalid items', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier1 = Supplier::factory()->create();
    $supplier2 = Supplier::factory()->create();

    $productFromSupplier1 = Product::factory()->create(['supplier_id' => $supplier1->id]);
    $productFromSupplier2 = Product::factory()->create(['supplier_id' => $supplier2->id]);

    test()->actingAs($user);

    // Create order request with supplier1
    $orderRequest = OrderRequest::create([
        'request_number' => 'OR-TEST-002',
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier1->id,
        'request_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    // Add item from supplier1
    $item = OrderRequestItem::create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $productFromSupplier1->id,
        'quantity' => 10,
    ]);

    expect($orderRequest->fresh()->orderRequestItem)->toHaveCount(1);

    // Simulate frontend logic: when supplier changes to supplier2, invalid items should be cleared
    $orderRequest->update(['supplier_id' => $supplier2->id]);

    // Items from old supplier should be considered invalid
    $invalidItems = OrderRequestItem::where('order_request_id', $orderRequest->id)
        ->whereHas('product', function ($query) use ($supplier2) {
            $query->where('supplier_id', '!=', $supplier2->id);
        })
        ->get();

    // In frontend logic, these would be deleted
    foreach ($invalidItems as $invalidItem) {
        $invalidItem->delete();
    }

    // Verify no items remain
    expect($orderRequest->fresh()->orderRequestItem)->toHaveCount(0);
});

test('order request fillable attributes', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create();

    $data = [
        'request_number' => 'OR-FILLABLE-TEST',
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'request_date' => '2025-11-13',
        'status' => 'draft',
        'note' => 'Testing fillable attributes',
        'created_by' => $user->id,
    ];

    $orderRequest = OrderRequest::create($data);

    expect($orderRequest->request_number)->toBe($data['request_number']);
    expect($orderRequest->warehouse_id)->toBe($data['warehouse_id']);
    expect($orderRequest->supplier_id)->toBe($data['supplier_id']);
    expect($orderRequest->request_date)->toBe($data['request_date']);
    expect($orderRequest->status)->toBe($data['status']);
    expect($orderRequest->note)->toBe($data['note']);
    expect($orderRequest->created_by)->toBe($data['created_by']);
});
