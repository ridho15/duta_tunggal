<?php

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
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
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
    ]);

    UnitOfMeasure::factory()->create();
    $this->cabang = Cabang::factory()->create();
    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->cabang->id,
    ]);
    $this->supplier = Supplier::factory()->create([
        'tempo_hutang' => 30,
    ]);

    $this->productA = Product::factory()->create([
        'cost_price' => 15000,
        'sell_price' => 25000,
    ]);

    $this->productB = Product::factory()->create([
        'cost_price' => 27500,
        'sell_price' => 35000,
    ]);

    $this->orderRequest = OrderRequest::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
        'status' => 'draft',
        'request_date' => Carbon::now()->toDateString(),
        'note' => 'Pengadaan material uji',
    ]);

    $this->itemA = OrderRequestItem::factory()->create([
        'order_request_id' => $this->orderRequest->id,
        'product_id'       => $this->productA->id,
        'quantity'         => 5,
        'unit_price'       => (float) $this->productA->cost_price,
        'original_price'   => (float) $this->productA->cost_price,
        'discount'         => 10, // percent
        'tax'              => 5,  // percent
        'note'             => 'Untuk batch produksi 01',
    ]);

    $this->itemB = OrderRequestItem::factory()->create([
        'order_request_id' => $this->orderRequest->id,
        'product_id'       => $this->productB->id,
        'quantity'         => 3,
        'unit_price'       => (float) $this->productB->cost_price,
        'original_price'   => (float) $this->productB->cost_price,
        'note'             => 'Safety stock',
    ]);
});

test('order request approval generates purchase order and items', function () {
    $orderDate = Carbon::now()->startOfDay();
    $expectedDate = Carbon::now()->addDays(7)->startOfDay();

    $payload = [
        'po_number' => 'PO-20251031-0001',
        'supplier_id' => $this->supplier->id,
        'order_date' => $orderDate->toDateTimeString(),
        'note' => 'Auto generated from order request',
        'expected_date' => $expectedDate->toDateTimeString(),
    ];

    $orderRequest = $this->orderRequest->fresh(['orderRequestItem.product']);

    $result = $this->service->approve($orderRequest, $payload);

    $fresh = $result->fresh(['purchaseOrder.purchaseOrderItem']);

    expect($fresh->status)->toBe('approved');
    expect(PurchaseOrder::count())->toBe(1);

    $purchaseOrder = $fresh->purchaseOrder;

    expect($purchaseOrder)->not->toBeNull()
        ->and($purchaseOrder->po_number)->toBe($payload['po_number'])
        ->and($purchaseOrder->supplier_id)->toBe($payload['supplier_id'])
        ->and($purchaseOrder->order_date->toDateTimeString())->toBe($payload['order_date'])
        ->and($purchaseOrder->expected_date->toDateTimeString())->toBe($payload['expected_date'])
        ->and($purchaseOrder->note)->toBe($payload['note'])
        ->and($purchaseOrder->refer_model_type)->toBe(OrderRequest::class)
        ->and($purchaseOrder->refer_model_id)->toBe($fresh->id)
        ->and($purchaseOrder->status)->toBe('draft')
        ->and($purchaseOrder->warehouse_id)->toBe($this->warehouse->id)
        ->and($purchaseOrder->tempo_hutang)->toBe(30)
        ->and($purchaseOrder->created_by)->toBe($this->user->id);

    expect($purchaseOrder->purchaseOrderItem)->toHaveCount(2);

    $poItemA = $purchaseOrder->purchaseOrderItem->firstWhere('product_id', $this->productA->id);
    $poItemB = $purchaseOrder->purchaseOrderItem->firstWhere('product_id', $this->productB->id);

    expect($poItemA)->not->toBeNull()
        ->and((float) $poItemA->quantity)->toBe(5.0)
        ->and((float) $poItemA->unit_price)->toBe((float) $this->productA->cost_price)
        ->and((float) $poItemA->discount)->toBe(10.0)
        ->and((float) $poItemA->tax)->toBe(5.0)
        ->and($poItemA->tipe_pajak)->toBe('Eklusif') // tax_type defaults to 'PPN Excluded' → Eklusif
        ->and($poItemA->currency_id)->toBe($this->currency->id)
        ->and($poItemA->refer_item_model_type)->toBe(OrderRequestItem::class)
        ->and($poItemA->refer_item_model_id)->toBe($this->itemA->id);

    expect($poItemB)->not->toBeNull()
        ->and((float) $poItemB->quantity)->toBe(3.0)
        ->and((float) $poItemB->unit_price)->toBe((float) $this->productB->cost_price)
        ->and((float) $poItemB->discount)->toBe(0.0)
        ->and((float) $poItemB->tax)->toBe(0.0)
        ->and($poItemB->tipe_pajak)->toBe('Non Pajak') // tax = 0 → Non Pajak
        ->and($poItemB->currency_id)->toBe($this->currency->id)
        ->and($poItemB->refer_item_model_type)->toBe(OrderRequestItem::class)
        ->and($poItemB->refer_item_model_id)->toBe($this->itemB->id);
});

test('order request rejection updates status without creating purchase order', function () {
    $this->service->reject($this->orderRequest);

    expect($this->orderRequest->fresh()->status)->toBe('rejected');
    expect(PurchaseOrder::count())->toBe(0);
    expect(PurchaseOrderItem::count())->toBe(0);
});

// ─── Feature 1: original_price tracking ──────────────────────────────────────

test('order request item stores original_price separately from unit_price override', function () {
    $masterPrice = 10000.0;
    $overridePrice = 9500.0;

    // Simulate: original_price = master, unit_price = user override
    $this->itemA->update([
        'original_price' => $masterPrice,
        'unit_price'     => $overridePrice,
    ]);

    $fresh = $this->itemA->fresh();
    expect((float) $fresh->original_price)->toBe($masterPrice);
    expect((float) $fresh->unit_price)->toBe($overridePrice);
    expect((float) $fresh->original_price)->not->toBe((float) $fresh->unit_price);
});

test('createPurchaseOrder uses unit_price override not original_price', function () {
    $masterPrice  = 20000.0;
    $overridePrice = 17500.0;

    $this->itemA->update([
        'original_price' => $masterPrice,
        'unit_price'     => $overridePrice,
    ]);
    $this->orderRequest->update(['status' => 'approved']);

    $payload = [
        'po_number'   => 'PO-OVERRIDE-001',
        'supplier_id' => $this->supplier->id,
        'order_date'  => now()->toDateTimeString(),
        'note'        => null,
        'selected_items' => [
            [
                'item_id'    => $this->itemA->id,
                'quantity'   => 5,
                'unit_price' => $overridePrice,
                'include'    => true,
            ],
        ],
    ];

    $po = $this->service->createPurchaseOrder($this->orderRequest->fresh(), $payload);
    $poItem = $po->purchaseOrderItem->first();

    expect((float) $poItem->unit_price)->toBe($overridePrice);
    expect((float) $poItem->unit_price)->not->toBe($masterPrice);
});

// ─── Feature 2: tax_type → tipe_pajak mapping ────────────────────────────────

test('PPN Included tax_type maps to Inklusif on purchase order item', function () {
    $this->orderRequest->update(['tax_type' => 'PPN Included', 'status' => 'approved']);
    $this->itemA->update(['tax' => 11, 'unit_price' => 10000]);

    $payload = [
        'po_number'   => 'PO-INKLUSIF-001',
        'supplier_id' => $this->supplier->id,
        'order_date'  => now()->toDateTimeString(),
        'note'        => null,
        'selected_items' => [
            ['item_id' => $this->itemA->id, 'quantity' => 2, 'unit_price' => 10000, 'include' => true],
        ],
    ];

    $po = $this->service->createPurchaseOrder($this->orderRequest->fresh(), $payload);
    $poItem = $po->purchaseOrderItem->first();

    expect($poItem->tipe_pajak)->toBe('Inklusif');
});

test('PPN Excluded tax_type maps to Eksklusif on purchase order item', function () {
    $this->orderRequest->update(['tax_type' => 'PPN Excluded', 'status' => 'approved']);
    $this->itemA->update(['tax' => 11, 'unit_price' => 10000]);

    $payload = [
        'po_number'   => 'PO-EKSKLUSIF-001',
        'supplier_id' => $this->supplier->id,
        'order_date'  => now()->toDateTimeString(),
        'note'        => null,
        'selected_items' => [
            ['item_id' => $this->itemA->id, 'quantity' => 2, 'unit_price' => 10000, 'include' => true],
        ],
    ];

    $po = $this->service->createPurchaseOrder($this->orderRequest->fresh(), $payload);
    $poItem = $po->purchaseOrderItem->first();

    expect($poItem->tipe_pajak)->toBe('Eklusif');
});

test('item with zero tax always gets Non Pajak regardless of tax_type', function () {
    $this->orderRequest->update(['tax_type' => 'PPN Included', 'status' => 'approved']);
    $this->itemB->update(['tax' => 0, 'unit_price' => 5000]);

    $payload = [
        'po_number'   => 'PO-NONPAJAK-001',
        'supplier_id' => $this->supplier->id,
        'order_date'  => now()->toDateTimeString(),
        'note'        => null,
        'selected_items' => [
            ['item_id' => $this->itemB->id, 'quantity' => 1, 'unit_price' => 5000, 'include' => true],
        ],
    ];

    $po = $this->service->createPurchaseOrder($this->orderRequest->fresh(), $payload);
    $poItem = $po->purchaseOrderItem->first();

    expect($poItem->tipe_pajak)->toBe('Non Pajak');
});

// ─── Feature 3: One Order Request → Multiple Purchase Orders ─────────────────

test('one order request can generate multiple purchase orders for different items', function () {
    $this->orderRequest->update(['status' => 'approved']);
    $this->itemA->update(['unit_price' => 15000, 'fulfilled_quantity' => 0]);
    $this->itemB->update(['unit_price' => 27500, 'fulfilled_quantity' => 0]);

    // PO 1: only itemA
    $payload1 = [
        'po_number'   => 'PO-MULTI-001',
        'supplier_id' => $this->supplier->id,
        'order_date'  => now()->toDateTimeString(),
        'note'        => null,
        'selected_items' => [
            ['item_id' => $this->itemA->id, 'quantity' => 5, 'unit_price' => 15000, 'include' => true],
            ['item_id' => $this->itemB->id, 'quantity' => 3, 'unit_price' => 27500, 'include' => false],
        ],
    ];

    $po1 = $this->service->createPurchaseOrder($this->orderRequest->fresh(), $payload1);

    // PO 2: only itemB (created from same OR)
    $payload2 = [
        'po_number'   => 'PO-MULTI-002',
        'supplier_id' => $this->supplier->id,
        'order_date'  => now()->addDay()->toDateTimeString(),
        'note'        => null,
        'selected_items' => [
            ['item_id' => $this->itemA->id, 'quantity' => 5, 'unit_price' => 15000, 'include' => false],
            ['item_id' => $this->itemB->id, 'quantity' => 3, 'unit_price' => 27500, 'include' => true],
        ],
    ];

    $po2 = $this->service->createPurchaseOrder($this->orderRequest->fresh(), $payload2);

    // Two separate POs exist
    expect(PurchaseOrder::count())->toBe(2);
    expect($po1->id)->not->toBe($po2->id);
    expect($po1->po_number)->toBe('PO-MULTI-001');
    expect($po2->po_number)->toBe('PO-MULTI-002');

    // PO1 has itemA, PO2 has itemB
    expect($po1->purchaseOrderItem)->toHaveCount(1);
    expect($po2->purchaseOrderItem)->toHaveCount(1);
    expect($po1->purchaseOrderItem->first()->product_id)->toBe($this->productA->id);
    expect($po2->purchaseOrderItem->first()->product_id)->toBe($this->productB->id);

    // Both POs reference the same Order Request
    expect($po1->refer_model_id)->toBe($this->orderRequest->id);
    expect($po2->refer_model_id)->toBe($this->orderRequest->id);

    // purchaseOrders() relationship returns both POs
    $allPos = $this->orderRequest->purchaseOrders;
    expect($allPos)->toHaveCount(2);
});

test('second PO can be created for remaining unfulfilled quantity after first PO', function () {
    $this->orderRequest->update(['status' => 'approved']);
    $this->itemA->update(['quantity' => 10, 'unit_price' => 15000, 'fulfilled_quantity' => 0]);

    // PO 1: fulfill 6 units
    $payload1 = [
        'po_number'   => 'PO-PARTIAL-001',
        'supplier_id' => $this->supplier->id,
        'order_date'  => now()->toDateTimeString(),
        'note'        => null,
        'selected_items' => [
            ['item_id' => $this->itemA->id, 'quantity' => 6, 'unit_price' => 15000, 'include' => true],
        ],
    ];

    $this->service->createPurchaseOrder($this->orderRequest->fresh(), $payload1);

    // After PO1, itemA has 6 fulfilled, 4 remaining
    $this->itemA->update(['fulfilled_quantity' => 6]);

    // PO 2: fulfill remaining 4 units
    $payload2 = [
        'po_number'   => 'PO-PARTIAL-002',
        'supplier_id' => $this->supplier->id,
        'order_date'  => now()->addDay()->toDateTimeString(),
        'note'        => null,
        'selected_items' => [
            ['item_id' => $this->itemA->id, 'quantity' => 4, 'unit_price' => 15000, 'include' => true],
        ],
    ];

    $po2 = $this->service->createPurchaseOrder($this->orderRequest->fresh(), $payload2);

    expect(PurchaseOrder::count())->toBe(2);
    expect((float) $po2->purchaseOrderItem->first()->quantity)->toBe(4.0);
});
