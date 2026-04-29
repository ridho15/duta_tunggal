<?php

/**
 * PurchaseOrderMultiSupplierORTest
 *
 * Tests the UX logic added to PurchaseOrderResource for multisupplier Order Requests:
 *
 * 1. buildOrderRequestItems() filters items by supplier when a filter is given.
 * 2. buildOrderRequestItems() returns all items when no filter is given.
 * 3. buildOrderRequestItems() skips items with remaining quantity = 0.
 * 4. buildOrderRequestItems() handles items without a unit_price (fallback to cost_price).
 * 5. buildOrderRequestItems() handles items with no supplier_id and no filter (includes them).
 * 6. buildOrderRequestItems() skips items with mismatched supplier when filter is active.
 * 7. Detecting unique supplier count drives the multisupplier flag correctly.
 * 8. Single-supplier OR: all items qualify for that one supplier group.
 * 9. Multisupplier OR: groupBy simulation produces N groups.
 */

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

// ─── Shared fixture ─────────────────────────────────────────────────────────

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->currency = Currency::factory()->create([
        'code'   => 'IDR',
        'name'   => 'Rupiah',
        'symbol' => 'Rp',
    ]);

    UnitOfMeasure::factory()->create();

    $this->cabang    = Cabang::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

    $this->supplierA = Supplier::factory()->create(['perusahaan' => 'PT Alpha', 'tempo_hutang' => 30]);
    $this->supplierB = Supplier::factory()->create(['perusahaan' => 'CV Beta',  'tempo_hutang' => 14]);

    $this->productA = Product::factory()->create(['cost_price' => 10000, 'sell_price' => 15000]);
    $this->productB = Product::factory()->create(['cost_price' => 5000,  'sell_price' => 8000]);

    $this->orderRequest = OrderRequest::factory()->create([
        'warehouse_id' => $this->warehouse->id,
        'cabang_id'    => $this->cabang->id,
        'created_by'   => $this->user->id,
        'status'       => 'approved',
        'tax_type'     => 'PPN Excluded',
        'request_date' => Carbon::today()->toDateString(),
    ]);

    $this->itemA = OrderRequestItem::factory()->create([
        'order_request_id'   => $this->orderRequest->id,
        'product_id'         => $this->productA->id,
        'supplier_id'        => $this->supplierA->id,
        'quantity'           => 10,
        'fulfilled_quantity' => 0,
        'unit_price'         => 10000,
        'discount'           => 0,
        'tax'                => 0,
    ]);

    $this->itemB = OrderRequestItem::factory()->create([
        'order_request_id'   => $this->orderRequest->id,
        'product_id'         => $this->productB->id,
        'supplier_id'        => $this->supplierB->id,
        'quantity'           => 8,
        'fulfilled_quantity' => 0,
        'unit_price'         => 5000,
        'discount'           => 0,
        'tax'                => 0,
    ]);

    $this->orderRequest->load('orderRequestItem.product.uom', 'orderRequestItem.product.suppliers');
});

// ─── buildOrderRequestItems — unit-level tests ──────────────────────────────

test('buildOrderRequestItems returns all items when no supplier filter is given', function () {
    $items = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        null,
        $this->currency->id
    );

    expect($items)->toHaveCount(2);
});

test('buildOrderRequestItems returns only items for supplier A when filtered', function () {
    $items = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        (int) $this->supplierA->id,
        $this->currency->id
    );

    expect($items)->toHaveCount(1);
    expect($items[0]['product_id'])->toBe($this->productA->id);
    expect($items[0]['refer_item_model_id'])->toBe($this->itemA->id);
});

test('buildOrderRequestItems returns only items for supplier B when filtered', function () {
    $items = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        (int) $this->supplierB->id,
        $this->currency->id
    );

    expect($items)->toHaveCount(1);
    expect($items[0]['product_id'])->toBe($this->productB->id);
    expect($items[0]['refer_item_model_id'])->toBe($this->itemB->id);
});

test('buildOrderRequestItems skips items where remaining quantity is zero or negative', function () {
    // Fully fulfil itemA
    $this->itemA->update(['fulfilled_quantity' => 10]);
    $this->orderRequest->load('orderRequestItem.product.uom', 'orderRequestItem.product.suppliers');

    $items = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        null,
        $this->currency->id
    );

    // Only itemB should remain
    expect($items)->toHaveCount(1);
    expect($items[0]['product_id'])->toBe($this->productB->id);
});

test('buildOrderRequestItems falls back to cost_price when unit_price is zero', function () {
    $this->itemA->update([
        'supplier_id' => null,
        'unit_price' => 0,
    ]);
    $this->orderRequest->load('orderRequestItem.product.uom', 'orderRequestItem.product.suppliers');

    $items = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        (int) $this->supplierA->id,
        $this->currency->id
    );

    expect($items)->toHaveCount(1);
    expect((float) $items[0]['unit_price'])->toBe((float) $this->productA->cost_price);
});

test('buildOrderRequestItems sets correct refer_item_model_type and refer_item_model_id', function () {
    $items = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        null,
        $this->currency->id
    );

    foreach ($items as $item) {
        expect($item['refer_item_model_type'])->toBe(\App\Models\OrderRequestItem::class);
        expect($item['refer_item_model_id'])->toBeInt();
    }

    $itemIds = collect($items)->pluck('refer_item_model_id')->sort()->values();
    expect($itemIds->toArray())->toBe(
        collect([$this->itemA->id, $this->itemB->id])->sort()->values()->toArray()
    );
});

test('buildOrderRequestItems maps tax_type PPN Included to Inklusif', function () {
    $this->orderRequest->update(['tax_type' => 'PPN Included']);
    $this->orderRequest->load('orderRequestItem.product.uom', 'orderRequestItem.product.suppliers');

    $items = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        null,
        $this->currency->id
    );

    foreach ($items as $item) {
        expect($item['tipe_pajak'])->toBe('Inklusif');
    }
});

test('buildOrderRequestItems maps tax_type None to Non Pajak', function () {
    $this->orderRequest->update(['tax_type' => 'None']);
    $this->orderRequest->load('orderRequestItem.product.uom', 'orderRequestItem.product.suppliers');

    $items = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        null,
        $this->currency->id
    );

    foreach ($items as $item) {
        expect($item['tipe_pajak'])->toBe('Non Pajak');
    }
});

// ─── Multisupplier detection logic ──────────────────────────────────────────

test('unique supplier count is 2 for a multisupplier Order Request', function () {
    $this->orderRequest->load('orderRequestItem');

    $uniqueCount = $this->orderRequest->orderRequestItem
        ->pluck('supplier_id')->filter()->unique()->count();

    expect($uniqueCount)->toBe(2);
});

test('unique supplier count is 1 for a single-supplier Order Request', function () {
    $this->itemB->update(['supplier_id' => $this->supplierA->id]);
    $this->orderRequest->load('orderRequestItem');

    $uniqueCount = $this->orderRequest->orderRequestItem
        ->pluck('supplier_id')->filter()->unique()->count();

    expect($uniqueCount)->toBe(1);
});

test('groupBy supplier_id on multisupplier OR produces correct groups', function () {
    $this->orderRequest->load('orderRequestItem');

    $groups = $this->orderRequest->orderRequestItem->groupBy('supplier_id');

    expect($groups)->toHaveCount(2);
    expect($groups[$this->supplierA->id])->toHaveCount(1);
    expect($groups[$this->supplierB->id])->toHaveCount(1);
});

test('available order request supplier ids exclude suppliers that already have a purchase order', function () {
    PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplierA->id,
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $this->orderRequest->id,
        'cabang_id' => $this->cabang->id,
    ]);

    $this->orderRequest->load('orderRequestItem');

    $availableSupplierIds = PurchaseOrderResource::getAvailableOrderRequestSupplierIds($this->orderRequest);

    expect($availableSupplierIds)
        ->toBeArray()
        ->toContain($this->supplierB->id)
        ->not->toContain($this->supplierA->id)
        ->and($availableSupplierIds)->toHaveCount(1);
});

test('available order request supplier ids are empty when all suppliers already have purchase orders', function () {
    PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplierA->id,
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $this->orderRequest->id,
        'cabang_id' => $this->cabang->id,
    ]);

    PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplierB->id,
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $this->orderRequest->id,
        'cabang_id' => $this->cabang->id,
    ]);

    $this->orderRequest->load('orderRequestItem');

    $availableSupplierIds = PurchaseOrderResource::getAvailableOrderRequestSupplierIds($this->orderRequest);

    expect($availableSupplierIds)->toBe([]);
});

test('partial order request with remaining suppliers still appears in refer options', function () {
    PurchaseOrder::factory()->create([
        'supplier_id' => $this->supplierA->id,
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $this->orderRequest->id,
        'cabang_id' => $this->cabang->id,
    ]);

    $this->orderRequest->update(['status' => 'partial']);

    $options = PurchaseOrderResource::getOrderRequestOptions();

    expect($options)
        ->toHaveKey($this->orderRequest->id)
        ->and($options[$this->orderRequest->id])->toBe($this->orderRequest->request_number);
});

test('buildOrderRequestItems for each supplier group produces non-overlapping product sets', function () {
    $itemsA = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        (int) $this->supplierA->id,
        $this->currency->id
    );
    $itemsB = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        (int) $this->supplierB->id,
        $this->currency->id
    );

    $productIdsA = collect($itemsA)->pluck('product_id');
    $productIdsB = collect($itemsB)->pluck('product_id');

    // No overlap between supplier A items and supplier B items
    expect($productIdsA->intersect($productIdsB)->count())->toBe(0);

    // Together they cover all items
    expect($productIdsA->merge($productIdsB)->count())->toBe(2);
});

// ─── currency_id defaults to first Currency when not passed ─────────────────

test('buildOrderRequestItems auto-resolves default currency_id when null', function () {
    $items = PurchaseOrderResource::buildOrderRequestItems(
        $this->orderRequest,
        null,
        null  // let helper resolve it
    );

    foreach ($items as $item) {
        expect($item['currency_id'])->toBe($this->currency->id);
    }
});

// ─── OR create page returns HTTP 200 ────────────────────────────────────────

test('purchase order create page is accessible', function () {
    // Give user the required permission
    \Spatie\Permission\Models\Permission::firstOrCreate([
        'name'       => 'create purchase order',
        'guard_name' => 'web',
    ]);
    $this->user->givePermissionTo('create purchase order');

    // Also grant any-view so the page resolves
    \Spatie\Permission\Models\Permission::firstOrCreate([
        'name'       => 'view any purchase order',
        'guard_name' => 'web',
    ]);
    $this->user->givePermissionTo('view any purchase order');

    $response = $this->get(PurchaseOrderResource::getUrl('create'));

    $response->assertOk();
});
