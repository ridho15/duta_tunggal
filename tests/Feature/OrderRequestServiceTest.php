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
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(OrderRequestService::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->currency = Currency::factory()->create([
        'code' => 'IDR',
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
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
        'tipe_pajak'       => 'Eklusif',
        'note'             => 'Untuk batch produksi 01',
    ]);

    $this->itemB = OrderRequestItem::factory()->create([
        'order_request_id' => $this->orderRequest->id,
        'product_id'       => $this->productB->id,
        'quantity'         => 3,
        'unit_price'       => (float) $this->productB->cost_price,
        'original_price'   => (float) $this->productB->cost_price,
        'tax'              => 0,
        'tipe_pajak'       => 'Non Pajak',
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
        ->and($purchaseOrder->status)->toBe('approved')
        ->and($purchaseOrder->tempo_hutang)->toBe(30)
        ->and($purchaseOrder->created_by)->toBe($this->user->id);

    expect($purchaseOrder->purchaseOrderItem)->toHaveCount(2);

    $poItemA = $purchaseOrder->purchaseOrderItem->firstWhere('product_id', $this->productA->id);
    $poItemB = $purchaseOrder->purchaseOrderItem->firstWhere('product_id', $this->productB->id);
    $itemA = $this->itemA->fresh();
    $itemB = $this->itemB->fresh();

    expect($poItemA)->not->toBeNull()
        ->and((float) $poItemA->quantity)->toBe(5.0)
        ->and((float) $poItemA->unit_price)->toBe((float) $this->productA->cost_price)
        ->and((float) $poItemA->discount)->toBe(10.0)
        ->and((float) $poItemA->tax)->toBe((float) ($itemA->tax ?? 0))
        ->and($poItemA->tipe_pajak)->toBe('eklusif') // tax_type defaults to lowercase eklusif
        ->and($poItemA->currency_id)->toBe($this->currency->id)
        ->and($poItemA->refer_item_model_type)->toBe(OrderRequestItem::class)
        ->and($poItemA->refer_item_model_id)->toBe($this->itemA->id);

    expect($poItemB)->not->toBeNull()
        ->and((float) $poItemB->quantity)->toBe(3.0)
        ->and((float) $poItemB->unit_price)->toBe((float) $this->productB->cost_price)
        ->and((float) $poItemB->discount)->toBe(0.0)
        ->and((float) $poItemB->tax)->toBe((float) ($itemB->tax ?? 0))
        ->and($poItemB->tipe_pajak)->toBe('none') // tax = 0 → none
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

test('create purchase order from order request auto approves purchase order', function () {
    $this->orderRequest->update(['status' => 'approved']);

    $payload = [
        'po_number'   => 'PO-OR-AUTO-APPROVE-001',
        'supplier_id' => $this->supplier->id,
        'order_date'  => now()->toDateTimeString(),
        'selected_items' => [
            [
                'item_id'    => $this->itemA->id,
                'quantity'   => 5,
                'unit_price' => 15000,
                'include'    => true,
            ],
        ],
    ];

    $po = $this->service->createPurchaseOrder($this->orderRequest->fresh(), $payload)->fresh();

    expect($po->status)->toBe('approved')
        ->and($po->date_approved)->not->toBeNull()
        ->and($po->approved_by)->toBe($this->user->id);
});

// ─── Feature 2: tax_type → tipe_pajak mapping ────────────────────────────────

test('item tax type Inklusif maps to Inklusif on purchase order item', function () {
    $this->orderRequest->update(['status' => 'approved']);
    $this->itemA->update(['tipe_pajak' => 'Inklusif', 'tax' => 11, 'unit_price' => 10000]);

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

    expect($poItem->tipe_pajak)->toBe('inklusif');
});

test('item tax type Eklusif maps to Eklusif on purchase order item', function () {
    $this->orderRequest->update(['status' => 'approved']);
    $this->itemA->update(['tipe_pajak' => 'Eklusif', 'tax' => 11, 'unit_price' => 10000]);

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

    expect($poItem->tipe_pajak)->toBe('eklusif');
});

test('item tax type Non Pajak always maps to Non Pajak', function () {
    $this->orderRequest->update(['status' => 'approved']);
    $this->itemB->update(['tipe_pajak' => 'Non Pajak', 'tax' => 0, 'unit_price' => 5000]);

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

    expect($poItem->tipe_pajak)->toBe('none');
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

test('order request approve creates product_supplier for non-linked supplier with OR item price', function () {
    $this->itemA->update([
        'supplier_id' => $this->supplier->id,
        'unit_price' => 32100,
    ]);

    DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->delete();

    $payload = [
        'po_number' => 'PO-SYNC-OR-001',
        'supplier_id' => $this->supplier->id,
        'order_date' => now()->toDateTimeString(),
        'selected_items' => [
            [
                'item_id' => $this->itemA->id,
                'quantity' => 5,
                'unit_price' => 32100,
                'include' => true,
            ],
        ],
    ];

    $this->service->approve($this->orderRequest->fresh(['orderRequestItem.product']), $payload);

    $pivot = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and((float) $pivot->supplier_price)->toBe(32100.0)
        ->and(
            DB::table('product_supplier')
                ->where('product_id', $this->productA->id)
                ->where('supplier_id', $this->supplier->id)
                ->count()
        )->toBe(1);
});

// ─── DIAGNOSTIC TESTS FOR PIVOT SYNC ──────────────────────────────────────────

test('DIAGNOSTIC: product_supplier NOT updated on item save (removed saved hook)', function () {
    DB::table('product_supplier')->insert([
        'product_id' => $this->productA->id,
        'supplier_id' => $this->supplier->id,
        'supplier_price' => 10000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // This was the old behavior - pivot was synced on item save
    // But we removed the saved hook, so it should NOT update pivot
    $originalUpdatedAt = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->value('updated_at');

    sleep(1);  // Wait a bit so updated_at would differ

    $this->itemA->update([
        'supplier_id' => $this->supplier->id,
        'unit_price' => 25000,  // Different price
    ]);

    $newUpdatedAt = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->value('updated_at');

    $pivot = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->first();

    // Pivot should NOT be updated from item save (old saved hook removed)
    // This test confirms the removed behavior
    expect((float) $pivot->supplier_price)->toBe(10000.0)
        ->and($originalUpdatedAt)->toBe($newUpdatedAt); // NOT updated (timestamps should be same)
});

test('DIAGNOSTIC: product_supplier created/updated ONLY at approve(), not at save()', function () {
    // Setup: Product has NO pivot for this supplier initially
    DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->delete();

    // Create OR item WITHOUT supplier_id
    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $this->orderRequest->id,
        'product_id' => $this->productA->id,
        'quantity' => 2,
        'unit_price' => 21500,
        'original_price' => 21500,
        'supplier_id' => null,  // ← KEY: no supplier set
    ]);

    // Save the item - should NOT create pivot (saved hook removed)
    $item->save();

    $pivotAfterSave = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->first();

    expect($pivotAfterSave)->toBeNull();  // Pivot NOT created on save

    // Now approve the OR with this supplier
    $payload = [
        'po_number' => 'PO-DIAGNOSTIC-001',
        'supplier_id' => $this->supplier->id,
        'order_date' => now()->toDateTimeString(),
        'selected_items' => [
            [
                'item_id' => $item->id,
                'quantity' => 2,
                'unit_price' => 21500,
                'include' => true,
            ],
        ],
    ];

    $this->service->approve($this->orderRequest->fresh(['orderRequestItem.product']), $payload);

    // After approve, pivot SHOULD be created with the OR item's price
    $pivotAfterApprove = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->first();

    expect($pivotAfterApprove)->not->toBeNull()
        ->and((float) $pivotAfterApprove->supplier_price)->toBe(21500.0);
});

test('DIAGNOSTIC: product_supplier with unlinked supplier (price=0) creates pivot at approve', function () {
    // Setup: Create a second supplier not linked to productA
    $unlinkedSupplier = Supplier::factory()->create(['tempo_hutang' => 30]);

    DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $unlinkedSupplier->id)
        ->delete();

    // Create OR item with unlinked supplier
    $item = OrderRequestItem::factory()->create([
        'order_request_id' => $this->orderRequest->id,
        'product_id' => $this->productA->id,
        'quantity' => 1,
        'unit_price' => 0,  // Price is 0 because supplier not linked
        'original_price' => 0,
        'supplier_id' => $unlinkedSupplier->id,
    ]);

    // Approve with that unlinked supplier
    $payload = [
        'po_number' => 'PO-UNLINKED-001',
        'supplier_id' => $unlinkedSupplier->id,
        'order_date' => now()->toDateTimeString(),
        'selected_items' => [
            [
                'item_id' => $item->id,
                'quantity' => 1,
                'unit_price' => 0,
                'include' => true,
            ],
        ],
    ];

    $this->service->approve($this->orderRequest->fresh(['orderRequestItem.product']), $payload);

    // Pivot should be created with price = 0
    $pivot = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $unlinkedSupplier->id)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and((float) $pivot->supplier_price)->toBe(0.0);
});

test('DIAGNOSTIC: OR item supplier_id is used if set, else fallback to payload supplier_id', function () {
    // Setup: Two suppliers
    $supplier2 = Supplier::factory()->create(['tempo_hutang' => 15]);

    DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->whereIn('supplier_id', [$this->supplier->id, $supplier2->id])
        ->delete();

    // Create a NEW order request to avoid existing itemA and itemB interference
    $newOr = OrderRequest::factory()->create([
        'created_by' => $this->user->id,
        'status' => 'draft',
        'request_date' => now()->toDateString(),
    ]);

    // Create ONE item with supplier2
    $itemWithSupplier = OrderRequestItem::factory()->create([
        'order_request_id' => $newOr->id,
        'product_id' => $this->productA->id,
        'quantity' => 3,
        'unit_price' => 18800,
        'original_price' => 18800,
        'supplier_id' => $supplier2->id,  // ← Explicit supplier on item
    ]);

    // Approve with DIFFERENT supplier ($this->supplier in payload)
    $payload = [
        'po_number' => 'PO-PRIORITY-001',
        'supplier_id' => $this->supplier->id,  // Different from item
        'order_date' => now()->toDateTimeString(),
    ];

    $this->service->approve($newOr->fresh(['orderRequestItem.product']), $payload);

    // When item HAS supplier_id, that supplier should be used for pivot
    $pivotForItemSupplier = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $supplier2->id)
        ->first();

    expect($pivotForItemSupplier)->not->toBeNull()
        ->and((float) $pivotForItemSupplier->supplier_price)->toBe(18800.0);

    // Payload supplier should NOT get a pivot for this item (item has explicit supplier)
    $pivotForPayloadSupplier = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->first();

    expect($pivotForPayloadSupplier)->toBeNull();  // ← Item's supplier takes precedence
});

test('DIAGNOSTIC: creating purchase order does not update product_supplier pivot', function () {
    DB::table('product_supplier')->insert([
        'product_id' => $this->productA->id,
        'supplier_id' => $this->supplier->id,
        'supplier_price' => 12000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->itemA->update([
        'supplier_id' => $this->supplier->id,
        'unit_price' => 16500,
    ]);

    $this->service->createPurchaseOrder($this->orderRequest->fresh(['orderRequestItem.product']), [
        'po_number' => 'PO-NO-PIVOT-SYNC-001',
        'supplier_id' => $this->supplier->id,
        'order_date' => now()->toDateTimeString(),
        'selected_items' => [
            [
                'item_id' => $this->itemA->id,
                'quantity' => 5,
                'unit_price' => 16500,
                'include' => true,
            ],
        ],
    ]);

    $pivot = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and((float) $pivot->supplier_price)->toBe(12000.0)
        ->and(
            DB::table('product_supplier')
                ->where('product_id', $this->productA->id)
                ->where('supplier_id', $this->supplier->id)
                ->count()
        )->toBe(1);
});

test('DIAGNOSTIC: updating purchase order item does not update product_supplier pivot', function () {
    DB::table('product_supplier')->insert([
        'product_id' => $this->productA->id,
        'supplier_id' => $this->supplier->id,
        'supplier_price' => 12000,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $po = $this->service->createPurchaseOrder($this->orderRequest->fresh(['orderRequestItem.product']), [
        'po_number' => 'PO-NO-PIVOT-SYNC-002',
        'supplier_id' => $this->supplier->id,
        'order_date' => now()->toDateTimeString(),
        'selected_items' => [
            [
                'item_id' => $this->itemA->id,
                'quantity' => 5,
                'unit_price' => 15000,
                'include' => true,
            ],
        ],
    ]);

    $poItem = $po->purchaseOrderItem->firstWhere('product_id', $this->productA->id);
    expect($poItem)->not->toBeNull();

    $poItem->update(['unit_price' => 17800]);

    $pivot = DB::table('product_supplier')
        ->where('product_id', $this->productA->id)
        ->where('supplier_id', $this->supplier->id)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and((float) $pivot->supplier_price)->toBe(12000.0)
        ->and(
            DB::table('product_supplier')
                ->where('product_id', $this->productA->id)
                ->where('supplier_id', $this->supplier->id)
                ->count()
        )->toBe(1);
});
