<?php

/**
 * OrderRequestToPurchaseOrderTest
 *
 * Tests the full integration flow:
 *   OrderRequest → (approve / create PO) → PurchaseOrder + PurchaseOrderItems
 *
 * Covers:
 *  - Approving with all items selected (default path)
 *  - Approving with partial item selection (new feature)
 *  - Approving with custom quantity / price overrides
 *  - Excluding items via include=false
 *  - createPurchaseOrder() with partial selection
 *  - refer_item_model traceability on PurchaseOrderItem
 *  - fulfilled_quantity updated correctly
 *  - Items without price fall back to cost_price
 */

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────
// Setup shared fixture
// ─────────────────────────────────────────────
beforeEach(function () {
    $this->service = app(OrderRequestService::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->currency = Currency::factory()->create([
        'code'   => 'IDR',
        'name'   => 'Rupiah',
        'symbol' => 'Rp',
    ]);

    \App\Models\UnitOfMeasure::factory()->create();

    $this->cabang    = Cabang::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->supplier  = Supplier::factory()->create(['tempo_hutang' => 30]);

    $this->productA = Product::factory()->create(['cost_price' => 10000, 'sell_price' => 15000]);
    $this->productB = Product::factory()->create(['cost_price' => 20000, 'sell_price' => 30000]);

    $this->orderRequest = OrderRequest::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'cabang_id'    => $this->cabang->id,
        'supplier_id'  => $this->supplier->id,
        'created_by'   => $this->user->id,
        'status'       => 'draft',
        'request_date' => Carbon::today()->toDateString(),
    ]);

    $this->itemA = OrderRequestItem::factory()->create([
        'order_request_id'   => $this->orderRequest->id,
        'product_id'         => $this->productA->id,
        'quantity'           => 10,
        'fulfilled_quantity' => 0,
        'unit_price'         => 10000,
        'discount'           => 0,
        'tax'                => 0,
    ]);

    $this->itemB = OrderRequestItem::factory()->create([
        'order_request_id'   => $this->orderRequest->id,
        'product_id'         => $this->productB->id,
        'quantity'           => 5,
        'fulfilled_quantity' => 0,
        'unit_price'         => 0, // no price — should fallback to cost_price
        'discount'           => 0,
        'tax'                => 0,
    ]);
});

// ─────────────────────────────────────────────
// SCENARIO 1 — Approve without selected_items: all items included
// ─────────────────────────────────────────────
test('approve without selected_items creates PO with all order request items', function () {
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => $this->supplier->id,
        'po_number'             => 'PO-TEST-001',
        'order_date'            => Carbon::today()->toDateString(),
    ];

    $result = $this->service->approve($this->orderRequest, $payload);

    expect($result->status)->toBe('approved');

    $po = $result->fresh()->purchaseOrder;
    expect($po)->not->toBeNull();
    expect($po->purchaseOrderItem)->toHaveCount(2);

    $poItemA = $po->purchaseOrderItem->firstWhere('product_id', $this->productA->id);
    $poItemB = $po->purchaseOrderItem->firstWhere('product_id', $this->productB->id);

    expect($poItemA)->not->toBeNull();
    expect((float) $poItemA->quantity)->toBe(10.0);
    expect((float) $poItemA->unit_price)->toBe(10000.0);

    expect($poItemB)->not->toBeNull();
    expect((float) $poItemB->quantity)->toBe(5.0);
    // itemB has unit_price=0 so should fallback to cost_price = 20000
    expect((float) $poItemB->unit_price)->toBe(20000.0);
});

// ─────────────────────────────────────────────
// SCENARIO 2 — Approve with partial item selection (only itemA)
// ─────────────────────────────────────────────
test('approve with selected_items creates PO with only included items', function () {
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => $this->supplier->id,
        'po_number'             => 'PO-TEST-002',
        'order_date'            => Carbon::today()->toDateString(),
        'selected_items'        => [
            [
                'item_id'    => $this->itemA->id,
                'product_name' => 'Product A',
                'quantity'   => 10,
                'unit_price' => 10000,
                'include'    => true,   // ← included
            ],
            [
                'item_id'    => $this->itemB->id,
                'product_name' => 'Product B',
                'quantity'   => 5,
                'unit_price' => 20000,
                'include'    => false,  // ← excluded
            ],
        ],
    ];

    $result = $this->service->approve($this->orderRequest, $payload);

    $po = $result->fresh()->purchaseOrder;
    expect($po->purchaseOrderItem)->toHaveCount(1);

    $poItemA = $po->purchaseOrderItem->firstWhere('product_id', $this->productA->id);
    $poItemB = $po->purchaseOrderItem->firstWhere('product_id', $this->productB->id);

    expect($poItemA)->not->toBeNull();
    expect($poItemB)->toBeNull();
});

// ─────────────────────────────────────────────
// SCENARIO 3 — Approve with quantity override in selected_items
// ─────────────────────────────────────────────
test('approve with selected_items respects user-edited quantity', function () {
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => $this->supplier->id,
        'po_number'             => 'PO-TEST-003',
        'order_date'            => Carbon::today()->toDateString(),
        'selected_items'        => [
            [
                'item_id'    => $this->itemA->id,
                'product_name' => 'Product A',
                'quantity'   => 4, // ← partial: only 4 out of 10
                'unit_price' => 10000,
                'include'    => true,
            ],
            [
                'item_id'    => $this->itemB->id,
                'product_name' => 'Product B',
                'quantity'   => 5,
                'unit_price' => 20000,
                'include'    => true,
            ],
        ],
    ];

    $result = $this->service->approve($this->orderRequest, $payload);

    $po = $result->fresh()->purchaseOrder;
    $poItemA = $po->purchaseOrderItem->firstWhere('product_id', $this->productA->id);

    expect((float) $poItemA->quantity)->toBe(4.0);

    // fulfilled_quantity on OR item should reflect the 4 units fulfilled
    $this->itemA->refresh();
    expect((float) $this->itemA->fulfilled_quantity)->toBe(4.0);
});

// ─────────────────────────────────────────────
// SCENARIO 4 — All items excluded → PO created but no items
// ─────────────────────────────────────────────
test('approve with all selected_items excluded creates empty PO', function () {
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => $this->supplier->id,
        'po_number'             => 'PO-TEST-004',
        'order_date'            => Carbon::today()->toDateString(),
        'selected_items'        => [
            [
                'item_id'  => $this->itemA->id,
                'quantity' => 10,
                'include'  => false,
            ],
            [
                'item_id'  => $this->itemB->id,
                'quantity' => 5,
                'include'  => false,
            ],
        ],
    ];

    $result = $this->service->approve($this->orderRequest, $payload);

    $po = $result->fresh()->purchaseOrder;
    expect($po)->not->toBeNull();
    expect($po->purchaseOrderItem)->toHaveCount(0);
    expect($result->status)->toBe('approved');
});

// ─────────────────────────────────────────────
// SCENARIO 5 — Approve without creating PO
// ─────────────────────────────────────────────
test('approve with create_purchase_order=false only changes status', function () {
    $payload = [
        'create_purchase_order' => false,
    ];

    $result = $this->service->approve($this->orderRequest, $payload);

    expect($result->status)->toBe('approved');
    expect(PurchaseOrder::count())->toBe(0);
});

// ─────────────────────────────────────────────
// SCENARIO 6 — createPurchaseOrder() with all items (no selection)
// ─────────────────────────────────────────────
test('createPurchaseOrder without selected_items includes all items', function () {
    // Mark the OR as approved first (no PO)
    $this->orderRequest->update(['status' => 'approved']);

    $payload = [
        'supplier_id'  => $this->supplier->id,
        'po_number'    => 'PO-TEST-006',
        'order_date'   => Carbon::today()->toDateString(),
    ];

    $po = $this->service->createPurchaseOrder($this->orderRequest, $payload);

    expect($po->purchaseOrderItem)->toHaveCount(2);

    $poItemA = $po->purchaseOrderItem->firstWhere('product_id', $this->productA->id);
    $poItemB = $po->purchaseOrderItem->firstWhere('product_id', $this->productB->id);

    expect((float) $poItemA->quantity)->toBe(10.0);
    expect((float) $poItemB->quantity)->toBe(5.0);
    // Traceability: refer_item_model must point back to OrderRequestItem
    expect($poItemA->refer_item_model_type)->toBe(OrderRequestItem::class);
    expect($poItemA->refer_item_model_id)->toBe($this->itemA->id);
    expect($poItemB->refer_item_model_type)->toBe(OrderRequestItem::class);
    expect($poItemB->refer_item_model_id)->toBe($this->itemB->id);
});

// ─────────────────────────────────────────────
// SCENARIO 7 — createPurchaseOrder() with partial selection
// ─────────────────────────────────────────────
test('createPurchaseOrder with selected_items includes only checked items', function () {
    $this->orderRequest->update(['status' => 'approved']);

    $payload = [
        'supplier_id'    => $this->supplier->id,
        'po_number'      => 'PO-TEST-007',
        'order_date'     => Carbon::today()->toDateString(),
        'selected_items' => [
            [
                'item_id'  => $this->itemA->id,
                'quantity' => 10,
                'include'  => true,
            ],
            [
                'item_id'  => $this->itemB->id,
                'quantity' => 5,
                'include'  => false, // ← excluded
            ],
        ],
    ];

    $po = $this->service->createPurchaseOrder($this->orderRequest, $payload);

    expect($po->purchaseOrderItem)->toHaveCount(1);

    $poItemA = $po->purchaseOrderItem->firstWhere('product_id', $this->productA->id);
    $poItemB = $po->purchaseOrderItem->firstWhere('product_id', $this->productB->id);

    expect($poItemA)->not->toBeNull();
    expect($poItemB)->toBeNull();
});

// ─────────────────────────────────────────────
// SCENARIO 8 — refer_item_model traceability preserved
// ─────────────────────────────────────────────
test('PO items have correct refer_item_model traceability after approve', function () {
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => $this->supplier->id,
        'po_number'             => 'PO-TEST-008',
        'order_date'            => Carbon::today()->toDateString(),
    ];

    $result = $this->service->approve($this->orderRequest, $payload);
    $po = $result->fresh()->purchaseOrder;

    foreach ($po->purchaseOrderItem as $poItem) {
        expect($poItem->refer_item_model_type)->toBe(OrderRequestItem::class);
        expect($poItem->refer_item_model_id)->not->toBeNull();

        // The referenced OrderRequestItem must belong to this OrderRequest
        $orItem = OrderRequestItem::find($poItem->refer_item_model_id);
        expect($orItem)->not->toBeNull();
        expect($orItem->order_request_id)->toBe($this->orderRequest->id);
    }
});

// ─────────────────────────────────────────────
// SCENARIO 9 — fulfilled_quantity updated correctly on approve
// ─────────────────────────────────────────────
test('fulfilled_quantity on OrderRequestItems is updated after approve', function () {
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => $this->supplier->id,
        'po_number'             => 'PO-TEST-009',
        'order_date'            => Carbon::today()->toDateString(),
    ];

    $this->service->approve($this->orderRequest, $payload);

    $this->itemA->refresh();
    $this->itemB->refresh();

    expect((float) $this->itemA->fulfilled_quantity)->toBe(10.0);
    expect((float) $this->itemB->fulfilled_quantity)->toBe(5.0);
});

// ─────────────────────────────────────────────
// SCENARIO 10 — currency_id uses first available Currency
// ─────────────────────────────────────────────
test('PO items receive the correct currency_id from the first available currency', function () {
    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => $this->supplier->id,
        'po_number'             => 'PO-TEST-010',
        'order_date'            => Carbon::today()->toDateString(),
    ];

    $result = $this->service->approve($this->orderRequest, $payload);
    $po = $result->fresh()->purchaseOrder;

    foreach ($po->purchaseOrderItem as $poItem) {
        expect($poItem->currency_id)->toBe($this->currency->id);
    }
});
