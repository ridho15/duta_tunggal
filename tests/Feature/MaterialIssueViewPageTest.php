<?php

use App\Filament\Resources\MaterialIssueResource\Pages\ViewMaterialIssue;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionPlan;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantMaterialIssueViewPermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any material issue',
        'view material issue',
        'view any production plan',
        'view any product',
        'view any warehouse',
        'view any unit of measure',
        'view any cabang',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'view any material issue',
        'view material issue',
        'view any production plan',
        'view any product',
        'view any warehouse',
        'view any unit of measure',
        'view any cabang',
    ]);
}

test('material issue view shows selected warehouse product requirement and stock availability', function () {
    $branch = Cabang::factory()->create([
        'kode' => 'CBG-MI-VIEW',
        'nama' => 'Cabang View Material Issue',
    ]);

    $user = User::factory()->create(['cabang_id' => $branch->id]);
    grantMaterialIssueViewPermissions($user);

    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $branch->id,
        'kode' => 'WH-MI-VIEW',
        'name' => 'Warehouse Material Issue View',
    ]);
    $rak = Rak::factory()->create([
        'warehouse_id' => $warehouse->id,
        'code' => 'RK-MI-VIEW',
        'name' => 'Rak View Material Issue',
    ]);
    $uom = UnitOfMeasure::factory()->create([
        'name' => 'Pcs',
        'abbreviation' => 'PCS',
    ]);
    $category = ProductCategory::factory()->create();

    ChartOfAccount::firstOrCreate(
        ['code' => '1140.01'],
        ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]
    );

    ChartOfAccount::firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product View MI',
        'sku' => 'FG-MI-VIEW-001',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
    ]);

    $rawMaterial = Product::factory()->create([
        'name' => 'Raw Material View MI',
        'sku' => 'RM-MI-VIEW-001',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 15000,
    ]);

    $productionPlan = ProductionPlan::create([
        'plan_number' => 'PP-MI-VIEW-001',
        'name' => 'Production Plan Material Issue View',
        'source_type' => 'manual',
        'product_id' => $finishedProduct->id,
        'quantity' => 2,
        'uom_id' => $uom->id,
        'bill_of_material_id' => null,
        'start_date' => now(),
        'end_date' => now()->addDay(),
        'status' => 'scheduled',
        'warehouse_id' => $warehouse->id,
        'cabang_id' => $branch->id,
        'created_by' => $user->id,
    ]);

    ManufacturingOrder::create([
        'mo_number' => 'MO-MI-VIEW-001',
        'production_plan_id' => $productionPlan->id,
        'status' => 'in_progress',
        'start_date' => now(),
        'end_date' => now()->addDay(),
        'cabang_id' => $branch->id,
    ]);

    $manufacturingOrder = ManufacturingOrder::where('mo_number', 'MO-MI-VIEW-001')->firstOrFail();

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-MI-VIEW-001',
        'production_plan_id' => $productionPlan->id,
        'manufacturing_order_id' => $manufacturingOrder->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => now()->toDateString(),
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
        'total_cost' => 30000,
        'created_by' => $user->id,
    ]);

    MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'quantity' => 2,
        'cost_per_unit' => 15000,
        'total_cost' => 30000,
        'status' => MaterialIssueItem::STATUS_PENDING_APPROVAL,
    ]);

    InventoryStock::create([
        'product_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 30,
        'qty_reserved' => 5,
        'qty_min' => 0,
    ]);

    Livewire::actingAs($user)
        ->test(ViewMaterialIssue::class, ['record' => $materialIssue->getKey()])
        ->assertSuccessful()
        ->assertSee('Informasi Material Issue')
        ->assertSee('Rincian Bahan')
        ->assertSee('MI-MI-VIEW-001')
        ->assertSee('PP-MI-VIEW-001 - Production Plan Material Issue View')
        ->assertSee('(WH-MI-VIEW) Warehouse Material Issue View')
        ->assertSee('(RM-MI-VIEW-001) Raw Material View MI')
        ->assertSee('Stok Tersedia')
        ->assertSee('25,00')
        ->assertSee('Stok Reservasi')
        ->assertSee('5,00');
});
