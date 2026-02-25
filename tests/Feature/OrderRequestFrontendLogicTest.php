<?php

namespace Tests\Feature;

use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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

test('order request supplier relationship', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create(['perusahaan' => 'Test Supplier', 'code' => 'SUP001']);

    test()->actingAs($user);

    $orderRequest = OrderRequest::create([
        'request_number' => 'OR-SUPPLIER-TEST',
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'request_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    // Test relationship
    expect($orderRequest->supplier)->not->toBeNull();
    expect($orderRequest->supplier->perusahaan)->toBe('Test Supplier');
    expect($orderRequest->supplier->code)->toBe('SUP001');

    // Test that supplier can be null
    $orderRequestWithoutSupplier = OrderRequest::create([
        'request_number' => 'OR-NO-SUPPLIER-TEST',
        'warehouse_id' => $warehouse->id,
        'supplier_id' => null,
        'request_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    expect($orderRequestWithoutSupplier->supplier)->not->toBeNull(); // withDefault() returns default model
    expect($orderRequestWithoutSupplier->supplier->perusahaan)->toBeNull(); // default model has null name
});

test('order request filters work correctly', function () {
    $user = User::factory()->create();
    $warehouse1 = Warehouse::factory()->create(['name' => 'Warehouse A']);
    $warehouse2 = Warehouse::factory()->create(['name' => 'Warehouse B']);
    $supplier1 = Supplier::factory()->create(['perusahaan' => 'Supplier A']);
    $supplier2 = Supplier::factory()->create(['perusahaan' => 'Supplier B']);

    test()->actingAs($user);

    // Create order requests with different statuses, suppliers, and warehouses
    $draftRequest = OrderRequest::create([
        'request_number' => 'OR-DRAFT-001',
        'warehouse_id' => $warehouse1->id,
        'supplier_id' => $supplier1->id,
        'request_date' => '2025-11-10',
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    $approvedRequest = OrderRequest::create([
        'request_number' => 'OR-APPROVED-001',
        'warehouse_id' => $warehouse2->id,
        'supplier_id' => $supplier2->id,
        'request_date' => '2025-11-12',
        'status' => 'approved',
        'created_by' => $user->id,
    ]);

    $rejectedRequest = OrderRequest::create([
        'request_number' => 'OR-REJECTED-001',
        'warehouse_id' => $warehouse1->id,
        'supplier_id' => $supplier1->id,
        'request_date' => '2025-11-14',
        'status' => 'rejected',
        'created_by' => $user->id,
    ]);

    // Test status filter
    $draftOrders = OrderRequest::where('status', 'draft')->get();
    expect($draftOrders)->toHaveCount(1);
    expect($draftOrders->first()->id)->toBe($draftRequest->id);

    $approvedOrders = OrderRequest::where('status', 'approved')->get();
    expect($approvedOrders)->toHaveCount(1);
    expect($approvedOrders->first()->id)->toBe($approvedRequest->id);

    // Test supplier filter
    $supplier1Orders = OrderRequest::where('supplier_id', $supplier1->id)->get();
    expect($supplier1Orders)->toHaveCount(2); // draft and rejected

    $supplier2Orders = OrderRequest::where('supplier_id', $supplier2->id)->get();
    expect($supplier2Orders)->toHaveCount(1); // approved

    // Test warehouse filter
    $warehouse1Orders = OrderRequest::where('warehouse_id', $warehouse1->id)->get();
    expect($warehouse1Orders)->toHaveCount(2); // draft and rejected

    $warehouse2Orders = OrderRequest::where('warehouse_id', $warehouse2->id)->get();
    expect($warehouse2Orders)->toHaveCount(1); // approved

    // Test date range filter
    $dateRangeOrders = OrderRequest::whereDate('request_date', '>=', '2025-11-11')
                                   ->whereDate('request_date', '<=', '2025-11-13')
                                   ->get();
    expect($dateRangeOrders)->toHaveCount(1); // only approved request
    expect($dateRangeOrders->first()->id)->toBe($approvedRequest->id);
});

test('approve form supplier auto-selected from order request', function () {
    $user = User::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $supplier = Supplier::factory()->create(['perusahaan' => 'Auto Select Supplier', 'code' => 'AUTO001']);
    $product = Product::factory()->create(['supplier_id' => $supplier->id]);

    test()->actingAs($user);

    // Create order request with supplier
    $orderRequest = OrderRequest::create([
        'request_number' => 'OR-AUTO-SELECT-001',
        'warehouse_id' => $warehouse->id,
        'supplier_id' => $supplier->id,
        'request_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    // Add item
    OrderRequestItem::create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $product->id,
        'quantity' => 5,
    ]);

    // Verify that the order request has the supplier
    expect($orderRequest->supplier_id)->toBe($supplier->id);
    expect($orderRequest->supplier->perusahaan)->toBe('Auto Select Supplier');

    // In a real scenario, when opening the approve form, supplier_id should be pre-filled
    // This would be tested in a browser test with Dusk, but here we verify the data integrity
    $freshOrderRequest = $orderRequest->fresh();
    expect($freshOrderRequest->supplier_id)->toBe($supplier->id);
});
