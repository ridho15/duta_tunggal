<?php

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\QualityControl;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantQualityControlPurchasePermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any quality control',
        'view quality control',
        'create quality control',
        'create quality control purchase',
        'update quality control',
        'delete quality control',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'view any quality control',
        'view quality control',
        'create quality control',
        'create quality control purchase',
        'update quality control',
        'delete quality control',
    ]);
}

function createQualityControlPurchaseContext(float $quantity = 5): array
{
    $cabang = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $cabang->id]);
    $otherUser = User::factory()->create(['cabang_id' => $cabang->id]);
    grantQualityControlPurchasePermissions($user);
    grantQualityControlPurchasePermissions($otherUser);

    UnitOfMeasure::factory()->create();
    $currency = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
    $supplier = Supplier::factory()->create(['cabang_id' => $cabang->id]);
    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $cabang->id,
        'status' => 1,
    ]);
    $product = Product::factory()->forCabang($cabang)->create([
        'supplier_id' => $supplier->id,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'cabang_id' => $cabang->id,
        'status' => 'approved',
        'created_by' => $user->id,
    ]);

    $purchaseOrderItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'unit_price' => 1000,
        'currency_id' => $currency->id,
    ]);

    return compact('cabang', 'user', 'otherUser', 'warehouse', 'product', 'currency', 'purchaseOrder', 'purchaseOrderItem');
}

function createQualityControlPurchaseOrderRequestBranchContext(): array
{
    $cabangA = Cabang::factory()->create(['nama' => 'Cabang QC A']);
    $cabangB = Cabang::factory()->create(['nama' => 'Cabang QC B']);
    $user = User::factory()->create([
        'cabang_id' => $cabangA->id,
        'manage_type' => 'all',
    ]);
    grantQualityControlPurchasePermissions($user);

    UnitOfMeasure::factory()->create();
    $currency = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
    $supplier = Supplier::factory()->create(['cabang_id' => $cabangB->id]);
    $warehouseA = Warehouse::factory()->create([
        'cabang_id' => $cabangA->id,
        'status' => 1,
        'kode' => 'WH-QC-A',
        'name' => 'Gudang QC Cabang A',
    ]);
    $warehouseB = Warehouse::factory()->create([
        'cabang_id' => $cabangB->id,
        'status' => 1,
        'kode' => 'WH-QC-B',
        'name' => 'Gudang QC Cabang B',
    ]);
    $product = Product::factory()->forCabang($cabangA)->create([
        'supplier_id' => $supplier->id,
    ]);

    $orderRequest = OrderRequest::factory()->create([
        'status' => 'approved',
        'cabang_id' => $cabangB->id,
        'currency_id' => $currency->id,
        'created_by' => $user->id,
    ]);

    $orderRequestItem = OrderRequestItem::factory()->create([
        'order_request_id' => $orderRequest->id,
        'product_id' => $product->id,
        'supplier_id' => $supplier->id,
        'cabang_id' => $cabangA->id,
        'quantity' => 10,
        'fulfilled_quantity' => 0,
        'unit_price' => 1000,
        'currency_id' => $currency->id,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'cabang_id' => null,
        'status' => 'approved',
        'refer_model_type' => OrderRequest::class,
        'refer_model_id' => $orderRequest->id,
        'created_by' => $user->id,
    ]);

    $purchaseOrderItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price' => 1000,
        'currency_id' => $currency->id,
        'refer_item_model_type' => OrderRequestItem::class,
        'refer_item_model_id' => $orderRequestItem->id,
    ]);

    return compact(
        'cabangA',
        'cabangB',
        'user',
        'currency',
        'supplier',
        'warehouseA',
        'warehouseB',
        'product',
        'orderRequest',
        'orderRequestItem',
        'purchaseOrder',
        'purchaseOrderItem',
    );
}

test('quality control purchase rejects passed quantity greater than received quantity', function () {
    $context = createQualityControlPurchaseContext(3000);

    $service = app(QualityControlService::class);

    expect(fn () => $service->createQCFromPurchaseOrderItem($context['purchaseOrderItem'], [
        'inspected_by' => $context['user']->id,
        'quantity_received' => 1000,
        'passed_quantity' => 2000,
        'rejected_quantity' => 0,
        'warehouse_id' => $context['warehouse']->id,
    ]))->toThrow(Exception::class, 'Qty Received');
});

test('quality control purchase rejects total inspected greater than received quantity', function () {
    $context = createQualityControlPurchaseContext(3000);

    $service = app(QualityControlService::class);

    expect(fn () => $service->createQCFromPurchaseOrderItem($context['purchaseOrderItem'], [
        'inspected_by' => $context['user']->id,
        'quantity_received' => 1000,
        'passed_quantity' => 800,
        'rejected_quantity' => 300,
        'warehouse_id' => $context['warehouse']->id,
    ]))->toThrow(Exception::class, 'Qty Received');
});

test('quality control purchase accepts total inspected equal to received quantity', function () {
    $context = createQualityControlPurchaseContext(3000);

    $qc = app(QualityControlService::class)->createQCFromPurchaseOrderItem($context['purchaseOrderItem'], [
        'inspected_by' => $context['user']->id,
        'quantity_received' => 1000,
        'passed_quantity' => 800,
        'rejected_quantity' => 200,
        'warehouse_id' => $context['warehouse']->id,
    ]);

    expect((float) $qc->quantity_received)->toBe(1000.0)
        ->and((float) $qc->passed_quantity)->toBe(800.0)
        ->and((float) $qc->rejected_quantity)->toBe(200.0);
});

test('quality control complete rejects updates that exceed received quantity', function () {
    $context = createQualityControlPurchaseContext(3000);
    $service = app(QualityControlService::class);

    $qc = $service->createQCFromPurchaseOrderItem($context['purchaseOrderItem'], [
        'inspected_by' => $context['user']->id,
        'quantity_received' => 1000,
        'passed_quantity' => 800,
        'rejected_quantity' => 200,
        'warehouse_id' => $context['warehouse']->id,
    ]);

    expect(fn () => $service->completeQualityControl($qc->fresh(), [
        'passed_quantity' => 1200,
        'rejected_quantity' => 0,
    ]))->toThrow(Exception::class, 'Qty Received');

    expect((float) $qc->fresh()->passed_quantity)->toBe(800.0);
});
