<?php

/**
 * OrderRequestMultiSupplierTest
 *
 * Verifies that when an Order Request contains items from multiple suppliers,
 * the approve and createPurchaseOrder flows produce ONE PO per supplier.
 *
 * Business rule: Even if an OR has items from N different suppliers,
 * each resulting Purchase Order must belong to exactly ONE supplier.
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

    $this->supplierA = Supplier::factory()->create(['perusahaan' => 'PT Supplier Alpha', 'tempo_hutang' => 30]);
    $this->supplierB = Supplier::factory()->create(['perusahaan' => 'CV Supplier Beta',  'tempo_hutang' => 14]);

    $this->productA1 = Product::factory()->create(['cost_price' => 10000, 'sell_price' => 15000]);
    $this->productA2 = Product::factory()->create(['cost_price' => 20000, 'sell_price' => 30000]);
    $this->productB1 = Product::factory()->create(['cost_price' => 5000,  'sell_price' => 8000]);

    // A single OR with items from two different suppliers
    $this->orderRequest = OrderRequest::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'cabang_id'    => $this->cabang->id,
        'created_by'   => $this->user->id,
        'status'       => 'draft',
        'request_date' => Carbon::today()->toDateString(),
    ]);

    // Items for Supplier A
    $this->itemA1 = OrderRequestItem::factory()->create([
        'order_request_id'   => $this->orderRequest->id,
        'product_id'         => $this->productA1->id,
        'supplier_id'        => $this->supplierA->id,
        'quantity'           => 10,
        'fulfilled_quantity' => 0,
        'unit_price'         => 10000,
        'discount'           => 0,
        'tax'                => 0,
    ]);

    $this->itemA2 = OrderRequestItem::factory()->create([
        'order_request_id'   => $this->orderRequest->id,
        'product_id'         => $this->productA2->id,
        'supplier_id'        => $this->supplierA->id,
        'quantity'           => 5,
        'fulfilled_quantity' => 0,
        'unit_price'         => 20000,
        'discount'           => 0,
        'tax'                => 0,
    ]);

    // Item for Supplier B
    $this->itemB1 = OrderRequestItem::factory()->create([
        'order_request_id'   => $this->orderRequest->id,
        'product_id'         => $this->productB1->id,
        'supplier_id'        => $this->supplierB->id,
        'quantity'           => 8,
        'fulfilled_quantity' => 0,
        'unit_price'         => 5000,
        'discount'           => 0,
        'tax'                => 0,
    ]);
});

// ─────────────────────────────────────────────
// SCENARIO 1 — Multi-supplier: groupBy logic produces N suppliers
// ─────────────────────────────────────────────
test('order request items are correctly grouped by supplier_id', function () {
    $items = $this->orderRequest->fresh()->orderRequestItem;

    $groups = $items->groupBy('supplier_id');

    expect($groups)->toHaveCount(2);
    expect($groups[$this->supplierA->id])->toHaveCount(2);
    expect($groups[$this->supplierB->id])->toHaveCount(1);
});

// ─────────────────────────────────────────────
// SCENARIO 2 — createPurchaseOrder for Supplier A items only
//   → 1 PO with supplier_id = supplierA, 2 items
// ─────────────────────────────────────────────
test('createPurchaseOrder for supplier A items creates single-supplier PO', function () {
    $this->orderRequest->update(['status' => 'approved']);

    $payload = [
        'supplier_id'    => $this->supplierA->id,
        'po_number'      => 'PO-A-001',
        'order_date'     => Carbon::today()->toDateString(),
        'selected_items' => [
            ['item_id' => $this->itemA1->id, 'quantity' => 10, 'unit_price' => 10000, 'include' => true],
            ['item_id' => $this->itemA2->id, 'quantity' => 5,  'unit_price' => 20000, 'include' => true],
            ['item_id' => $this->itemB1->id, 'quantity' => 8,  'unit_price' => 5000,  'include' => false], // excluded
        ],
    ];

    $po = $this->service->createPurchaseOrder($this->orderRequest, $payload);

    expect($po->supplier_id)->toBe($this->supplierA->id);
    expect($po->purchaseOrderItem)->toHaveCount(2);

    $poItemA1 = $po->purchaseOrderItem->firstWhere('product_id', $this->productA1->id);
    $poItemA2 = $po->purchaseOrderItem->firstWhere('product_id', $this->productA2->id);
    $poItemB1 = $po->purchaseOrderItem->firstWhere('product_id', $this->productB1->id);

    expect($poItemA1)->not->toBeNull();
    expect($poItemA2)->not->toBeNull();
    expect($poItemB1)->toBeNull(); // excluded
});

// ─────────────────────────────────────────────
// SCENARIO 3 — Two separate PO calls simulate the multi-supplier groupBy flow
//   → 2 POs, each with correct supplier and items
// ─────────────────────────────────────────────
test('two separate createPurchaseOrder calls produce two single-supplier POs', function () {
    $this->orderRequest->update(['status' => 'approved']);

    // PO for Supplier A
    $poA = $this->service->createPurchaseOrder($this->orderRequest, [
        'supplier_id'    => $this->supplierA->id,
        'po_number'      => 'PO-MULTI-A',
        'order_date'     => Carbon::today()->toDateString(),
        'selected_items' => [
            ['item_id' => $this->itemA1->id, 'quantity' => 10, 'unit_price' => 10000, 'include' => true],
            ['item_id' => $this->itemA2->id, 'quantity' => 5,  'unit_price' => 20000, 'include' => true],
        ],
    ]);

    // PO for Supplier B
    $poB = $this->service->createPurchaseOrder($this->orderRequest, [
        'supplier_id'    => $this->supplierB->id,
        'po_number'      => 'PO-MULTI-B',
        'order_date'     => Carbon::today()->toDateString(),
        'selected_items' => [
            ['item_id' => $this->itemB1->id, 'quantity' => 8, 'unit_price' => 5000, 'include' => true],
        ],
    ]);

    // Two POs total
    expect(PurchaseOrder::count())->toBe(2);

    // Each PO belongs to exactly one supplier
    expect($poA->supplier_id)->toBe($this->supplierA->id);
    expect($poB->supplier_id)->toBe($this->supplierB->id);

    // POs have correct items
    expect($poA->purchaseOrderItem)->toHaveCount(2);
    expect($poB->purchaseOrderItem)->toHaveCount(1);

    // No item is shared across POs
    $poAProductIds = $poA->purchaseOrderItem->pluck('product_id');
    $poBProductIds = $poB->purchaseOrderItem->pluck('product_id');
    expect($poAProductIds->intersect($poBProductIds))->toHaveCount(0);
});

// ─────────────────────────────────────────────
// SCENARIO 4 — Approve with multi_supplier=true via service builds correct POs
//   Simulates what the Filament action does: group by item_supplier_id → call service per group
// ─────────────────────────────────────────────
test('multi-supplier approve creates one PO per supplier', function () {
    // Build the selected_items the way the Filament action builds it
    $items = $this->orderRequest->orderRequestItem->map(fn($item) => [
        'item_id'          => $item->id,
        'item_supplier_id' => $item->supplier_id,
        'product_id'       => $item->product_id,
        'quantity'         => $item->quantity,
        'unit_price'       => $item->unit_price,
        'include'          => true,
    ])->values()->toArray();

    $uniqueSupplierIds = collect($items)->pluck('item_supplier_id')->filter()->unique();
    expect($uniqueSupplierIds)->toHaveCount(2); // confirm multi-supplier

    // Simulate the groupBy + createPurchaseOrder loop from the action handler
    $groups = collect($items)->groupBy('item_supplier_id');
    $created = 0;
    foreach ($groups as $supplierId => $groupItems) {
        if (empty($supplierId)) continue;
        $poNumber = 'PO-AUTO-' . $supplierId;
        $this->service->createPurchaseOrder($this->orderRequest, [
            'supplier_id'    => $supplierId,
            'po_number'      => $poNumber,
            'order_date'     => Carbon::today()->toDateString(),
            'selected_items' => $groupItems->values()->toArray(),
            'multi_supplier' => false,
        ]);
        $created++;
    }
    $this->orderRequest->update(['status' => 'approved']);

    expect($created)->toBe(2);
    expect(PurchaseOrder::count())->toBe(2);
    expect($this->orderRequest->fresh()->status)->toBe('approved');

    // Each PO must belong to exactly one supplier
    $allPOs = PurchaseOrder::with('purchaseOrderItem')->get();
    foreach ($allPOs as $po) {
        $poSupplierIds = $po->purchaseOrderItem->map(function ($poi) {
            $orItem = OrderRequestItem::find($poi->refer_item_model_id);
            return $orItem?->supplier_id;
        })->filter()->unique();

        // All items in a PO come from the same supplier
        expect($poSupplierIds)->toHaveCount(1);
        expect($poSupplierIds->first())->toBe($po->supplier_id);
    }
});

// ─────────────────────────────────────────────
// SCENARIO 5 — Single-supplier OR: approve creates one PO normally
// ─────────────────────────────────────────────
test('single-supplier OR approve creates exactly one PO', function () {
    // Override: make all items use supplierA
    $this->itemB1->update(['supplier_id' => $this->supplierA->id]);

    $payload = [
        'create_purchase_order' => true,
        'supplier_id'           => $this->supplierA->id,
        'po_number'             => 'PO-SINGLE-001',
        'order_date'            => Carbon::today()->toDateString(),
    ];

    $result = $this->service->approve($this->orderRequest, $payload);

    expect($result->status)->toBe('approved');
    expect(PurchaseOrder::count())->toBe(1);

    $po = $result->fresh()->purchaseOrder;
    expect($po->supplier_id)->toBe($this->supplierA->id);
    expect($po->purchaseOrderItem)->toHaveCount(3);
});

// ─────────────────────────────────────────────
// SCENARIO 6 — PO items traceability with multi-supplier
// ─────────────────────────────────────────────
test('PO items from multi-supplier flow all have correct refer_item_model traceability', function () {
    $this->orderRequest->update(['status' => 'approved']);

    $poA = $this->service->createPurchaseOrder($this->orderRequest, [
        'supplier_id'    => $this->supplierA->id,
        'po_number'      => 'PO-TRACE-A',
        'order_date'     => Carbon::today()->toDateString(),
        'selected_items' => [
            ['item_id' => $this->itemA1->id, 'quantity' => 10, 'unit_price' => 10000, 'include' => true],
            ['item_id' => $this->itemA2->id, 'quantity' => 5,  'unit_price' => 20000, 'include' => true],
        ],
    ]);

    $poB = $this->service->createPurchaseOrder($this->orderRequest, [
        'supplier_id'    => $this->supplierB->id,
        'po_number'      => 'PO-TRACE-B',
        'order_date'     => Carbon::today()->toDateString(),
        'selected_items' => [
            ['item_id' => $this->itemB1->id, 'quantity' => 8, 'unit_price' => 5000, 'include' => true],
        ],
    ]);

    foreach ([$poA, $poB] as $po) {
        foreach ($po->purchaseOrderItem as $poItem) {
            expect($poItem->refer_item_model_type)->toBe(OrderRequestItem::class);
            $orItem = OrderRequestItem::find($poItem->refer_item_model_id);
            expect($orItem)->not->toBeNull();
            expect($orItem->order_request_id)->toBe($this->orderRequest->id);
        }
    }
});
