<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->salesOrderService = app(SalesOrderService::class);
    $this->cabang = Cabang::factory()->create();
    $this->user = User::factory()->create(['cabang_id' => $this->cabang->id]);
    Auth::login($this->user);

    $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->category = ProductCategory::factory()->create();
    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->rak = Rak::factory()->create(['warehouse_id' => $this->warehouse->id]);
});

function createSaleOrderWithShortage($customer, $category, $warehouse, $rak, int $createdByUserId): SaleOrder
{
    $product = Product::factory()->create([
        'cabang_id' => $customer->cabang_id,
        'product_category_id' => $category->id,
        'uom_id' => 1,
        'is_active' => true,
    ]);

    InventoryStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 0,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $saleOrder = SaleOrder::create([
        'so_number' => 'SO-STOCK-' . uniqid(),
        'customer_id' => $customer->id,
        'cabang_id' => $customer->cabang_id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(3),
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => $createdByUserId,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 0,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    return $saleOrder->fresh(['saleOrderItem.warehouseAllocations', 'saleOrderItem.product']);
}

function createSaleOrderWithStock($customer, $category, $warehouse, $rak, int $createdByUserId): SaleOrder
{
    $product = Product::factory()->create([
        'cabang_id' => $customer->cabang_id,
        'product_category_id' => $category->id,
        'uom_id' => 1,
        'is_active' => true,
    ]);

    InventoryStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 50,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $saleOrder = SaleOrder::create([
        'so_number' => 'SO-STOCK-OK-' . uniqid(),
        'customer_id' => $customer->id,
        'cabang_id' => $customer->cabang_id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(3),
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => $createdByUserId,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 0,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    return $saleOrder->fresh(['saleOrderItem.warehouseAllocations', 'saleOrderItem.product']);
}

test('sales order request approval still works when stock is insufficient', function () {
    $saleOrder = createSaleOrderWithShortage($this->customer, $this->category, $this->warehouse, $this->rak, $this->user->id);

    expect($saleOrder->hasInsufficientStock())->toBeTrue();

    expect($this->salesOrderService->requestApprove($saleOrder))->toBeTrue();

    expect($saleOrder->fresh()->status)->toBe('request_approve')
        ->and($saleOrder->fresh()->request_approve_by)->not->toBeNull();
});

test('sales order approval still works when stock is insufficient', function () {
    $saleOrder = createSaleOrderWithShortage($this->customer, $this->category, $this->warehouse, $this->rak, $this->user->id);

    $saleOrder->update(['status' => 'request_approve']);

    expect($saleOrder->fresh()->hasInsufficientStock())->toBeTrue();

    expect($this->salesOrderService->approve($saleOrder))->toBeTrue();

    expect($saleOrder->fresh()->status)->toBe('approved')
        ->and($saleOrder->fresh()->approve_by)->not->toBeNull();
});

test('sales order can request approval and approve when stock is sufficient', function () {
    $saleOrder = createSaleOrderWithStock($this->customer, $this->category, $this->warehouse, $this->rak, $this->user->id);

    expect($saleOrder->hasInsufficientStock())->toBeFalse();

    expect($this->salesOrderService->requestApprove($saleOrder))->toBeTrue();
    expect($saleOrder->fresh()->status)->toBe('request_approve');

    expect($this->salesOrderService->approve($saleOrder))->toBeTrue();
    expect($saleOrder->fresh()->status)->toBe('approved');
});