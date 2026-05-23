<?php

use App\Filament\Resources\QualityControlPurchaseResource\Pages\CreateQualityControlPurchase;
use App\Filament\Resources\PurchaseOrderResource\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers\PurchaseOrderItemRelationManager;
use App\Models\Cabang;
use App\Models\Currency;
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

    return compact('user', 'otherUser', 'warehouse', 'product', 'purchaseOrder', 'purchaseOrderItem');
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

test('quality control purchase create form validates passed quantity against received quantity', function () {
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
        ->assertHasFormErrors(['passed_quantity']);
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

test('purchase order item qc action validates passed quantity against received quantity', function () {
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
        ->assertHasTableActionErrors(['passed_quantity']);
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
