<?php

use App\Filament\Resources\QualityControlPurchaseResource\Pages\CreateQualityControlPurchase;
use App\Filament\Resources\QualityControlPurchaseResource;
use App\Filament\Resources\QualityControlPurchaseResource\Pages\ListQualityControlPurchases;
use App\Filament\Resources\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\PurchaseOrderItemRelationManager;
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

test('quality control purchase create defaults inspector to current user for regular user', function () {
    $context = createQualityControlPurchaseContext();

    Livewire::actingAs($context['user'])
        ->test(CreateQualityControlPurchase::class)
        ->assertFormSet([
            'inspected_by' => $context['user']->id,
        ])
        ->fillForm([
            'from_model_id' => $context['purchaseOrderItem']->id,
            'qc_number' => 'QC-P-INSPECTOR-001',
            'product_id' => $context['product']->id,
            'warehouse_id' => $context['warehouse']->id,
            'quantity_received' => 5,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'inspected_by' => $context['otherUser']->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(QualityControl::where('qc_number', 'QC-P-INSPECTOR-001')->value('inspected_by'))
        ->toBe($context['user']->id);
});

test('quality control purchase warehouse options only show warehouses from order request item cabang', function () {
    $context = createQualityControlPurchaseOrderRequestBranchContext();

    $resolvedCabangId = QualityControlPurchaseResource::resolveQcPurchaseCabangId($context['purchaseOrderItem']->fresh());
    $options = QualityControlPurchaseResource::getQcPurchaseWarehouseOptions($resolvedCabangId);

    expect($resolvedCabangId)->toBe($context['cabangA']->id)
        ->and($options)->toHaveKey($context['warehouseA']->id)
        ->and($options)->not->toHaveKey($context['warehouseB']->id);
});

test('quality control purchase create rejects warehouse from another cabang', function () {
    $context = createQualityControlPurchaseOrderRequestBranchContext();

    Livewire::actingAs($context['user'])
        ->test(CreateQualityControlPurchase::class)
        ->fillForm([
            'from_model_id' => $context['purchaseOrderItem']->id,
            'qc_number' => 'QC-P-WH-CABANG-LOCK-001',
            'product_id' => $context['product']->id,
            'warehouse_id' => $context['warehouseB']->id,
            'quantity_received' => 5,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'inspected_by' => $context['user']->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['warehouse_id']);
});

test('quality control purchase create query preselects one eligible purchase order item and fills remaining quantity', function () {
    $context = createQualityControlPurchaseContext(10);

    Livewire::withQueryParams(['purchase_order_id' => $context['purchaseOrder']->id])
        ->actingAs($context['user'])
        ->test(CreateQualityControlPurchase::class)
        ->assertFormSet([
            'from_model_id' => $context['purchaseOrderItem']->id,
            'from_model_type' => PurchaseOrderItem::class,
            'product_id' => $context['product']->id,
            'product_name' => $context['product']->name,
            'sku' => $context['product']->sku,
            'cabang_id' => $context['purchaseOrder']->cabang_id,
            'quantity_received' => 10.0,
            'passed_quantity' => 10.0,
            'rejected_quantity' => 0,
            'total_inspected' => 10.0,
        ]);
});

test('quality control purchase create query submits default remaining quantity successfully', function () {
    $context = createQualityControlPurchaseContext(10);

    Livewire::withQueryParams(['purchase_order_id' => $context['purchaseOrder']->id])
        ->actingAs($context['user'])
        ->test(CreateQualityControlPurchase::class)
        ->fillForm([
            'qc_number' => 'QC-P-QUERY-PREFILL-001',
            'warehouse_id' => $context['warehouse']->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $qualityControl = QualityControl::where('qc_number', 'QC-P-QUERY-PREFILL-001')->firstOrFail();

    expect($qualityControl->from_model_type)->toBe(PurchaseOrderItem::class)
        ->and($qualityControl->from_model_id)->toBe($context['purchaseOrderItem']->id)
        ->and((float) $qualityControl->quantity_received)->toBe(10.0)
        ->and((float) $qualityControl->passed_quantity)->toBe(10.0)
        ->and((float) $qualityControl->rejected_quantity)->toBe(0.0);
});

test('quality control purchase create query returns form errors when quantity exceeds remaining purchase order item quantity', function () {
    $context = createQualityControlPurchaseContext(10);

    Livewire::withQueryParams(['purchase_order_id' => $context['purchaseOrder']->id])
        ->actingAs($context['user'])
        ->test(CreateQualityControlPurchase::class)
        ->fillForm([
            'qc_number' => 'QC-P-QUERY-OVER-001',
            'warehouse_id' => $context['warehouse']->id,
            'quantity_received' => 100,
            'passed_quantity' => 100,
            'rejected_quantity' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['quantity_received', 'passed_quantity']);

    expect(QualityControl::where('qc_number', 'QC-P-QUERY-OVER-001')->exists())->toBeFalse();
});

test('quality control purchase create query filters multiple eligible items to selected purchase order', function () {
    $context = createQualityControlPurchaseContext(10);

    $secondProduct = Product::factory()->forCabang($context['cabang'])->create([
        'supplier_id' => $context['purchaseOrder']->supplier_id,
    ]);
    $secondItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $context['purchaseOrder']->id,
        'product_id' => $secondProduct->id,
        'quantity' => 7,
        'unit_price' => 1000,
        'currency_id' => $context['currency']->id,
    ]);

    $otherContext = createQualityControlPurchaseContext(3);

    $eligibleItems = QualityControlPurchaseResource::eligiblePurchaseOrderItems($context['purchaseOrder']->id)
        ->pluck('id')
        ->all();

    expect($eligibleItems)->toContain($context['purchaseOrderItem']->id)
        ->and($eligibleItems)->toContain($secondItem->id)
        ->and($eligibleItems)->not->toContain($otherContext['purchaseOrderItem']->id);

    Livewire::withQueryParams(['purchase_order_id' => $context['purchaseOrder']->id])
        ->actingAs($context['user'])
        ->test(CreateQualityControlPurchase::class)
        ->assertFormSet([
            'from_model_id' => null,
        ]);
});

test('quality control purchase create query does not auto select purchase order without remaining qc quantity', function () {
    $context = createQualityControlPurchaseContext(10);

    QualityControl::factory()->create([
        'qc_number' => 'QC-P-EXHAUSTED-001',
        'from_model_type' => PurchaseOrderItem::class,
        'from_model_id' => $context['purchaseOrderItem']->id,
        'product_id' => $context['product']->id,
        'warehouse_id' => $context['warehouse']->id,
        'quantity_received' => 10,
        'passed_quantity' => 10,
        'rejected_quantity' => 0,
        'status' => 1,
        'inspected_by' => $context['user']->id,
        'cabang_id' => $context['purchaseOrder']->cabang_id,
    ]);

    expect(QualityControlPurchaseResource::eligiblePurchaseOrderItems($context['purchaseOrder']->id))->toHaveCount(0);

    Livewire::withQueryParams(['purchase_order_id' => $context['purchaseOrder']->id])
        ->actingAs($context['user'])
        ->test(CreateQualityControlPurchase::class)
        ->assertFormSet([
            'from_model_id' => null,
        ]);
});

test('quality control purchase returns no warehouse options when cabang cannot be resolved', function () {
    $cabang = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $cabang->id,
        'status' => 1,
    ]);
    $resolvedCabangId = QualityControlPurchaseResource::resolveQcPurchaseCabangId();

    expect($resolvedCabangId)->toBeNull()
        ->and(QualityControlPurchaseResource::getQcPurchaseWarehouseOptions($resolvedCabangId))->toBe([])
        ->and(QualityControlPurchaseResource::warehouseMatchesQcPurchaseCabang($warehouse->id, $resolvedCabangId))->toBeFalse();
});

test('quality control purchase batch action rejects warehouse outside selected item cabang', function () {
    $context = createQualityControlPurchaseOrderRequestBranchContext();

    Livewire::actingAs($context['user'])
        ->test(ListQualityControlPurchases::class)
        ->callTableAction('batch_create_qc', data: [
            'purchase_order_id' => $context['purchaseOrder']->id,
            'selected_po_item_ids' => [$context['purchaseOrderItem']->id],
            'warehouse_id' => $context['warehouseB']->id,
            'inspected_by' => $context['user']->id,
            'inspection_date' => now()->toDateString(),
        ])
        ->assertHasTableActionErrors(['warehouse_id']);
});

test('quality control purchase batch action rejects items from different cabang', function () {
    $context = createQualityControlPurchaseOrderRequestBranchContext();
    $productB = Product::factory()->forCabang($context['cabangB'])->create([
        'supplier_id' => $context['supplier']->id,
    ]);
    $orderRequestItemB = OrderRequestItem::factory()->create([
        'order_request_id' => $context['orderRequest']->id,
        'product_id' => $productB->id,
        'supplier_id' => $context['supplier']->id,
        'cabang_id' => $context['cabangB']->id,
        'quantity' => 5,
        'fulfilled_quantity' => 0,
        'unit_price' => 1000,
        'currency_id' => $context['currency']->id,
    ]);
    $purchaseOrderItemB = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $context['purchaseOrder']->id,
        'product_id' => $productB->id,
        'quantity' => 5,
        'unit_price' => 1000,
        'currency_id' => $context['currency']->id,
        'refer_item_model_type' => OrderRequestItem::class,
        'refer_item_model_id' => $orderRequestItemB->id,
    ]);

    expect(QualityControlPurchaseResource::resolveBatchQcPurchaseCabangId(
        $context['purchaseOrder']->id,
        [$context['purchaseOrderItem']->id, $purchaseOrderItemB->id]
    ))->toBeNull();

    Livewire::actingAs($context['user'])
        ->test(ListQualityControlPurchases::class)
        ->callTableAction('batch_create_qc', data: [
            'purchase_order_id' => $context['purchaseOrder']->id,
            'selected_po_item_ids' => [$context['purchaseOrderItem']->id, $purchaseOrderItemB->id],
            'warehouse_id' => $context['warehouseA']->id,
            'inspected_by' => $context['user']->id,
            'inspection_date' => now()->toDateString(),
        ])
        ->assertHasErrors();
});

test('quality control purchase create allows owner to choose inspector', function () {
    $context = createQualityControlPurchaseContext();
    $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
    $context['user']->assignRole($role);

    Livewire::actingAs($context['user'])
        ->test(CreateQualityControlPurchase::class)
        ->fillForm([
            'from_model_id' => $context['purchaseOrderItem']->id,
            'qc_number' => 'QC-P-OWNER-INSPECTOR-001',
            'product_id' => $context['product']->id,
            'warehouse_id' => $context['warehouse']->id,
            'quantity_received' => 5,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'inspected_by' => $context['otherUser']->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(QualityControl::where('qc_number', 'QC-P-OWNER-INSPECTOR-001')->value('inspected_by'))
        ->toBe($context['otherUser']->id);
});

test('quality control purchase create form validates passed quantity against received quantity via auto-correction', function () {
    $context = createQualityControlPurchaseContext(3000);

    Livewire::actingAs($context['user'])
        ->test(CreateQualityControlPurchase::class)
        ->fillForm([
            'from_model_id' => $context['purchaseOrderItem']->id,
            'qc_number' => 'QC-P-RECEIVED-LOCK-001',
            'product_id' => $context['product']->id,
            'warehouse_id' => $context['warehouse']->id,
            'quantity_received' => 1000,
            'passed_quantity' => 2000,
            'rejected_quantity' => 0,
            'inspected_by' => $context['user']->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Expect that the passed_quantity was auto-corrected to 1000 (which is the quantity_received)
    expect((float) QualityControl::where('qc_number', 'QC-P-RECEIVED-LOCK-001')->value('passed_quantity'))
        ->toBe(1000.0);
});

test('purchase order item qc action forces current user as inspector for regular user', function () {
    $context = createQualityControlPurchaseContext();

    Livewire::actingAs($context['user'])
        ->test(PurchaseOrderItemRelationManager::class, [
            'ownerRecord' => $context['purchaseOrder'],
            'pageClass' => EditPurchaseOrder::class,
        ])
        ->callTableAction('kirim_qc', $context['purchaseOrderItem'], data: [
            'warehouse_id' => $context['warehouse']->id,
            'inspected_by' => $context['otherUser']->id,
            'quantity_received' => 5,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'condition' => 'good',
        ])
        ->assertHasNoTableActionErrors();

    expect(QualityControl::where('from_model_id', $context['purchaseOrderItem']->id)
        ->where('from_model_type', PurchaseOrderItem::class)
        ->value('inspected_by'))
        ->toBe($context['user']->id);
});

test('purchase order item qc action validates passed quantity against received quantity via auto-correction', function () {
    $context = createQualityControlPurchaseContext(3000);

    Livewire::actingAs($context['user'])
        ->test(PurchaseOrderItemRelationManager::class, [
            'ownerRecord' => $context['purchaseOrder'],
            'pageClass' => EditPurchaseOrder::class,
        ])
        ->callTableAction('kirim_qc', $context['purchaseOrderItem'], data: [
            'warehouse_id' => $context['warehouse']->id,
            'inspected_by' => $context['user']->id,
            'quantity_received' => 1000,
            'passed_quantity' => 2000,
            'rejected_quantity' => 0,
            'condition' => 'good',
        ])
        ->assertHasNoTableActionErrors();

    expect((float) QualityControl::where('from_model_id', $context['purchaseOrderItem']->id)
        ->where('from_model_type', PurchaseOrderItem::class)
        ->value('passed_quantity'))
        ->toBe(1000.0);
});

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
