<?php

use App\Filament\Resources\ProductionPlanResource\Pages\CreateProductionPlan;
use App\Models\BillOfMaterial;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\ProductionPlan;
use App\Filament\Resources\ProductionPlanResource\Pages\EditProductionPlan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantProductionPlanCreatePermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any production plan',
        'create production plan',
        'update production plan',
        'view production plan',
        'view any bill of material',
        'view any product',
        'view any cabang',
        'view any unit of measure',
        'view any warehouse',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'view any production plan',
        'create production plan',
        'update production plan',
        'view production plan',
        'view any bill of material',
        'view any product',
        'view any cabang',
        'view any unit of measure',
        'view any warehouse',
    ]);
}

test('production plan keeps the selected bom on create', function () {
    $cabang = Cabang::factory()->create([
        'kode' => 'CBG-PP-BOM',
        'nama' => 'Cabang Production Plan BOM',
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'manage_type' => 'all',
    ]);

    grantProductionPlanCreatePermissions($user);

    $uom = UnitOfMeasure::factory()->create([
        'name' => 'Pcs',
        'abbreviation' => 'PCS',
    ]);

    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $cabang->id,
        'name' => 'Gudang Produksi',
        'status' => true,
    ]);

    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product BOM Persist',
        'sku' => 'FG-PP-BOM-001',
        'cabang_id' => $cabang->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
    ]);

    $selectedBom = BillOfMaterial::query()->create([
        'cabang_id' => $cabang->id,
        'product_id' => $finishedProduct->id,
        'code' => 'BOM-PP-PERSIST-001',
        'nama_bom' => 'BOM Persist A',
        'uom_id' => $uom->id,
        'quantity' => 1,
        'labor_cost' => 1000,
        'overhead_cost' => 500,
        'total_cost' => 0,
        'is_active' => true,
    ]);

    BillOfMaterial::query()->create([
        'cabang_id' => $cabang->id,
        'product_id' => $finishedProduct->id,
        'code' => 'BOM-PP-PERSIST-002',
        'nama_bom' => 'BOM Persist B',
        'uom_id' => $uom->id,
        'quantity' => 1,
        'labor_cost' => 1200,
        'overhead_cost' => 650,
        'total_cost' => 0,
        'is_active' => true,
    ]);

    Livewire::actingAs($user)
        ->test(CreateProductionPlan::class)
        ->fillForm([
            'plan_number' => 'PP-20260404-0001',
            'name' => 'Production Plan BOM Persistence',
            'source_type' => 'manual',
            'cabang_id' => $cabang->id,
            'bill_of_material_id' => $selectedBom->id,
            'product_id' => $finishedProduct->id,
            'quantity' => 10,
            'uom_id' => $uom->id,
            'warehouse_id' => $warehouse->id,
            'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'end_date' => now()->addDays(7)->format('Y-m-d H:i:s'),
            'notes' => 'Testing BOM persistence on create',
            'auto_schedule' => false,
        ])
        ->assertSet('data.bill_of_material_id', $selectedBom->id)
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProductionPlan::query()->where('plan_number', 'PP-20260404-0001')->first())
        ->not->toBeNull()
        ->and(ProductionPlan::query()->where('plan_number', 'PP-20260404-0001')->first()->bill_of_material_id)
        ->toBe($selectedBom->id);
});

test('production plan edit keeps cabang product uom and warehouse synchronized with the selected bom', function () {
    $cabangA = Cabang::factory()->create([
        'kode' => 'CBG-PP-EDIT-A',
        'nama' => 'Cabang Production Plan Edit A',
    ]);

    $cabangB = Cabang::factory()->create([
        'kode' => 'CBG-PP-EDIT-B',
        'nama' => 'Cabang Production Plan Edit B',
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabangA->id,
        'manage_type' => 'all',
    ]);

    grantProductionPlanCreatePermissions($user);

    $uomA = UnitOfMeasure::factory()->create([
        'name' => 'Box',
        'abbreviation' => 'BOX',
    ]);

    $uomB = UnitOfMeasure::factory()->create([
        'name' => 'Pallet',
        'abbreviation' => 'PLT',
    ]);

    $warehouseA = Warehouse::factory()->create([
        'cabang_id' => $cabangA->id,
        'name' => 'Gudang Edit A',
        'status' => true,
    ]);

    $warehouseB = Warehouse::factory()->create([
        'cabang_id' => $cabangB->id,
        'name' => 'Gudang Edit B',
        'status' => true,
    ]);

    $finishedProductA = Product::factory()->create([
        'name' => 'Finished Product Edit A',
        'sku' => 'FG-PP-EDIT-A',
        'cabang_id' => $cabangA->id,
        'uom_id' => $uomA->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
    ]);

    $finishedProductB = Product::factory()->create([
        'name' => 'Finished Product Edit B',
        'sku' => 'FG-PP-EDIT-B',
        'cabang_id' => $cabangB->id,
        'uom_id' => $uomB->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
    ]);

    $bomA = BillOfMaterial::query()->create([
        'cabang_id' => $cabangA->id,
        'product_id' => $finishedProductA->id,
        'code' => 'BOM-PP-EDIT-A',
        'nama_bom' => 'BOM Edit A',
        'uom_id' => $uomA->id,
        'quantity' => 1,
        'labor_cost' => 1000,
        'overhead_cost' => 500,
        'total_cost' => 0,
        'is_active' => true,
    ]);

    $bomB = BillOfMaterial::query()->create([
        'cabang_id' => $cabangB->id,
        'product_id' => $finishedProductB->id,
        'code' => 'BOM-PP-EDIT-B',
        'nama_bom' => 'BOM Edit B',
        'uom_id' => $uomB->id,
        'quantity' => 1,
        'labor_cost' => 2000,
        'overhead_cost' => 700,
        'total_cost' => 0,
        'is_active' => true,
    ]);

    $productionPlan = ProductionPlan::query()->create([
        'plan_number' => 'PP-20260404-EDIT-001',
        'name' => 'Production Plan Edit Sync',
        'source_type' => 'manual',
        'cabang_id' => $cabangA->id,
        'bill_of_material_id' => $bomA->id,
        'product_id' => $finishedProductA->id,
        'quantity' => 10,
        'uom_id' => $uomA->id,
        'warehouse_id' => $warehouseA->id,
        'start_date' => now()->addDay()->format('Y-m-d H:i:s'),
        'end_date' => now()->addDays(7)->format('Y-m-d H:i:s'),
        'status' => 'draft',
        'created_by' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test(EditProductionPlan::class, ['record' => $productionPlan->getKey()])
        ->assertSuccessful()
        ->fillForm([
            'bill_of_material_id' => $bomB->id,
            'warehouse_id' => $warehouseB->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $productionPlan->refresh();

    expect($productionPlan->bill_of_material_id)->toBe($bomB->id)
        ->and($productionPlan->cabang_id)->toBe($cabangB->id)
        ->and($productionPlan->product_id)->toBe($finishedProductB->id)
        ->and($productionPlan->uom_id)->toBe($uomB->id)
        ->and($productionPlan->warehouse_id)->toBeNull();
});
