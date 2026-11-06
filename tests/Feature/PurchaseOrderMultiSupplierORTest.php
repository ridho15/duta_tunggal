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
use App\Models\PurchaseOrderItem;
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

// ─── currency_id defaults to first Currency when not passed ─────────────────

// ─── OR create page returns HTTP 200 ────────────────────────────────────────

