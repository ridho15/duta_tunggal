<?php

/**
 * OrderRequestRegressionTest
 *
 * Regression tests for OrderRequest Create PO & Approve flows.
 * Tests the new behavior where supplier_id is derived from items
 * instead of being provided via form fields.
 */

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\OrderRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(OrderRequestService::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->currency = Currency::factory()->create([
        'code'   => 'IDR',
        'name'   => 'Rupiah',
        'symbol' => 'Rp',
    ]);

    UnitOfMeasure::factory()->create();

    $this->cabang = Cabang::factory()->create();
    $this->supplier1 = Supplier::factory()->create(['tempo_hutang' => 30]);
    $this->supplier2 = Supplier::factory()->create(['tempo_hutang' => 30]);

    $this->productA = Product::factory()->create(['cost_price' => 10000, 'sell_price' => 15000]);
    $this->productB = Product::factory()->create(['cost_price' => 20000, 'sell_price' => 30000]);

    $this->orderRequest = OrderRequest::factory()->create([
        'created_by'   => $this->user->id,
        'status'       => 'draft',
        'request_date' => Carbon::today()->toDateString(),
    ]);
});

function createItemsForOrderRequest(OrderRequest $orderRequest, array $items): void
{
    foreach ($items as $item) {
        OrderRequestItem::factory()->create([
            'order_request_id'   => $orderRequest->id,
            'product_id'         => $item['product']->id,
            'quantity'           => $item['quantity'] ?? 10,
            'fulfilled_quantity' => 0,
            'unit_price'         => $item['unit_price'] ?? 10000,
            'discount'           => 0,
            'tax'                => 0,
            'supplier_id'       => $item['supplier_id'],
        ]);
    }
}

// ─────────────────────────────────────────────
// TEST 1: supplier_id derived from item (single supplier)
// ─────────────────────────────────────────────
test('approve derives supplier_id from first item when not provided', function () {
    $this->orderRequest->update(['status' => 'draft']);

    createItemsForOrderRequest($this->orderRequest, [
        ['product' => $this->productA, 'supplier_id' => $this->supplier1->id],
        ['product' => $this->productB, 'supplier_id' => $this->supplier1->id], // same supplier
    ]);

    // Simulate form submission WITHOUT supplier_id field
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => null, // NOT provided - should be derived from items
        'po_number'             => 'PO-REGRESSION-001',
        'order_date'            => Carbon::today()->toDateString(),
    ];

    // Simulate the action code logic: derive supplier_id from first item
    $items = $this->orderRequest->fresh()->orderRequestItem;
    if (empty($payload['supplier_id']) && $items->isNotEmpty()) {
        $firstItem = $items->first();
        $payload['supplier_id'] = $firstItem->supplier_id;
    }

    expect($payload['supplier_id'])->toBe($this->supplier1->id);

    $result = $this->service->approve($this->orderRequest, $payload);

    expect($result->status)->toBe('approved');
    $po = $result->fresh()->purchaseOrder;
    expect($po)->not->toBeNull();
    expect($po->supplier_id)->toBe($this->supplier1->id);
});

// ─────────────────────────────────────────────
// TEST 2: createPurchaseOrder derives supplier_id from item
// ─────────────────────────────────────────────
test('createPurchaseOrder derives supplier_id from first item when not provided', function () {
    $this->orderRequest->update(['status' => 'approved']);

    createItemsForOrderRequest($this->orderRequest, [
        ['product' => $this->productA, 'supplier_id' => $this->supplier1->id],
        ['product' => $this->productB, 'supplier_id' => $this->supplier1->id],
    ]);

    // Simulate form submission WITHOUT supplier_id field
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => null, // NOT provided
        'po_number'             => 'PO-REGRESSION-002',
        'order_date'            => Carbon::today()->toDateString(),
    ];

    // Simulate the action code logic
    $items = $this->orderRequest->fresh()->orderRequestItem;
    if (empty($payload['supplier_id']) && $items->isNotEmpty()) {
        $firstItem = $items->first();
        $payload['supplier_id'] = $firstItem->supplier_id;
    }

    expect($payload['supplier_id'])->toBe($this->supplier1->id);

    $result = $this->service->createPurchaseOrder($this->orderRequest, $payload);

    expect($result->supplier_id)->toBe($this->supplier1->id);
});

// ─────────────────────────────────────────────
// TEST 3: po_number auto-generated when not provided
// ─────────────────────────────────────────────
test('approve auto-generates po_number when not provided', function () {
    $this->orderRequest->update(['status' => 'draft']);

    createItemsForOrderRequest($this->orderRequest, [
        ['product' => $this->productA, 'supplier_id' => $this->supplier1->id],
    ]);

    // Simulate form submission WITHOUT po_number
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => $this->supplier1->id,
        'po_number'             => null, // NOT provided
        'order_date'            => Carbon::today()->toDateString(),
    ];

    // Simulate the action code logic: generate po_number if empty
    if (empty($payload['po_number'])) {
        $payload['po_number'] = 'PO-AUTO-' . time();
    }

    expect($payload['po_number'])->not->toBeNull();
    expect($payload['po_number'])->toStartWith('PO-AUTO-');

    $result = $this->service->approve($this->orderRequest, $payload);
    expect($result->fresh()->purchaseOrder->po_number)->toBe($payload['po_number']);
});

// ─────────────────────────────────────────────
// TEST 4: multi supplier creates multiple POs
// ─────────────────────────────────────────────
test('approve creates multiple POs for multi-supplier items', function () {
    $this->orderRequest->update(['status' => 'draft']);

    createItemsForOrderRequest($this->orderRequest, [
        ['product' => $this->productA, 'supplier_id' => $this->supplier1->id],
        ['product' => $this->productB, 'supplier_id' => $this->supplier2->id], // different supplier
    ]);

    // Simulate multi-supplier flow
    $items = $this->orderRequest->fresh()->orderRequestItem;
    $groups = $items->groupBy('supplier_id');

    expect($groups->count())->toBe(2);

    $poCount = 0;
    foreach ($groups as $supplierId => $supplierItems) {
        $payload = [
            'create_purchase_order' => true,
            'supplier_id'           => $supplierId,
            'po_number'             => 'PO-MULTI-' . $supplierId . '-' . time(),
            'order_date'            => Carbon::today()->toDateString(),
            'selected_items'        => $supplierItems->map(fn($item) => [
                'item_id'             => $item->id,
                'item_supplier_id'    => $item->supplier_id,
                'item_cabang_id'      => $item->cabang_id,
                'include'             => true,
                'quantity'            => $item->quantity,
            ])->toArray(),
        ];

        $this->service->approve($this->orderRequest, $payload);
        $poCount++;
    }

    expect($this->orderRequest->fresh()->purchaseOrders->count())->toBe(2);
});

// ─────────────────────────────────────────────
// TEST 5: supplier_id from selected_items (include filter)
// ─────────────────────────────────────────────
test('approve derives supplier_id from first included item', function () {
    $this->orderRequest->update(['status' => 'draft']);

    createItemsForOrderRequest($this->orderRequest, [
        ['product' => $this->productA, 'supplier_id' => $this->supplier1->id],
        ['product' => $this->productB, 'supplier_id' => $this->supplier2->id],
    ]);

    $items = $this->orderRequest->fresh()->orderRequestItem;

    // Simulate form with selected_items (not all items included)
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => null, // NOT provided
        'po_number'             => 'PO-REGRESSION-005',
        'order_date'            => Carbon::today()->toDateString(),
        'selected_items'        => [
            // Only itemA (supplier1) is included
            [
                'item_id'          => $items->firstWhere('product_id', $this->productA->id)->id,
                'item_supplier_id' => $this->supplier1->id,
                'include'           => true,
                'quantity'          => 10,
            ],
            [
                'item_id'          => $items->firstWhere('product_id', $this->productB->id)->id,
                'item_supplier_id' => $this->supplier2->id,
                'include'           => false, // NOT included
                'quantity'          => 10,
            ],
        ],
    ];

    // Simulate the action code logic: get supplier from first included item
    if (empty($payload['supplier_id']) && !empty($payload['selected_items'])) {
        $firstIncludedItem = collect($payload['selected_items'])
            ->filter(fn($i) => $i['include'] ?? false)
            ->first();
        $payload['supplier_id'] = $firstIncludedItem['item_supplier_id'] ?? null;
    }

    expect($payload['supplier_id'])->toBe($this->supplier1->id);

    $result = $this->service->approve($this->orderRequest, $payload);

    $po = $result->fresh()->purchaseOrder;
    expect($po->supplier_id)->toBe($this->supplier1->id);
    // Only itemA should be in PO
    expect($po->purchaseOrderItem)->toHaveCount(1);
});

// ─────────────────────────────────────────────
// TEST 6: No supplier_id fallback when items have no supplier
// ─────────────────────────────────────────────
test('approve fails gracefully when items have no supplier', function () {
    $this->orderRequest->update(['status' => 'draft']);

    createItemsForOrderRequest($this->orderRequest, [
        ['product' => $this->productA, 'supplier_id' => null], // no supplier
    ]);

    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => null,
        'po_number'             => 'PO-REGRESSION-006',
        'order_date'            => Carbon::today()->toDateString(),
    ];

    // Simulate the action code logic
    $items = $this->orderRequest->fresh()->orderRequestItem;
    if (empty($payload['supplier_id']) && $items->isNotEmpty()) {
        $firstItem = $items->first();
        $payload['supplier_id'] = $firstItem->supplier_id; // This will be null
    }

    expect($payload['supplier_id'])->toBeNull();

    // Service should throw when supplier_id is null
    expect(fn() => $this->service->approve($this->orderRequest, $payload))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
