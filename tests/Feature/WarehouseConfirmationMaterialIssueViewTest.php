<?php

use App\Filament\Resources\WarehouseConfirmationResource\Pages\ListWarehouseConfirmations;
use App\Filament\Resources\WarehouseConfirmationResource\Pages\ViewWarehouseConfirmation;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionPlan;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Models\WarehouseConfirmationItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantWarehouseConfirmationViewPermission(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    Permission::firstOrCreate([
        'name' => 'view any warehouse confirmation',
        'guard_name' => 'web',
    ]);

    Permission::firstOrCreate([
        'name' => 'view warehouse confirmation',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo([
        'view any warehouse confirmation',
        'view warehouse confirmation',
    ]);
}

test('warehouse confirmation view shows material issue items per bahan', function () {
    $branch = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $branch->id]);
    grantWarehouseConfirmationViewPermission($user);

    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Pcs', 'abbreviation' => 'PCS']);
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
        'name' => 'Finished Product Material View',
        'sku' => 'FG-MI-VIEW',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
    ]);

    $rawMaterialOne = Product::factory()->create([
        'name' => 'Material View A',
        'sku' => 'RM-MI-VIEW-A',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 10000,
    ]);

    $rawMaterialTwo = Product::factory()->create([
        'name' => 'Material View B',
        'sku' => 'RM-MI-VIEW-B',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 12000,
    ]);

    $billOfMaterial = \App\Models\BillOfMaterial::create([
        'cabang_id' => $branch->id,
        'product_id' => $finishedProduct->id,
        'code' => 'BOM-MI-VIEW-001',
        'nama_bom' => 'BOM Material View',
        'uom_id' => $uom->id,
        'quantity' => 1,
        'total_cost' => 22000,
        'work_in_progress_coa_id' => ChartOfAccount::where('code', '1400.04')->value('id'),
    ]);

    ProductionPlan::create([
        'plan_number' => 'PP-MI-VIEW-001',
        'name' => 'Production Plan Material View',
        'source_type' => 'manual',
        'bill_of_material_id' => $billOfMaterial->id,
        'product_id' => $finishedProduct->id,
        'quantity' => 2,
        'uom_id' => $uom->id,
        'start_date' => now(),
        'end_date' => now()->addDay(),
        'status' => 'scheduled',
        'warehouse_id' => $warehouse->id,
        'cabang_id' => $branch->id,
        'created_by' => $user->id,
    ]);

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-MI-VIEW-001',
        'production_plan_id' => ProductionPlan::where('plan_number', 'PP-MI-VIEW-001')->value('id'),
        'warehouse_id' => $warehouse->id,
        'issue_date' => now()->toDateString(),
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
        'total_cost' => 44000,
        'created_by' => $user->id,
    ]);

    $itemOne = MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterialOne->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'quantity' => 10,
        'cost_per_unit' => 10000,
        'total_cost' => 100000,
        'status' => MaterialIssueItem::STATUS_PENDING_APPROVAL,
    ]);

    $itemTwo = MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterialTwo->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'quantity' => 100,
        'cost_per_unit' => 12000,
        'total_cost' => 1200000,
        'status' => MaterialIssueItem::STATUS_PENDING_APPROVAL,
    ]);

    InventoryStock::create([
        'product_id' => $rawMaterialOne->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 100,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    InventoryStock::create([
        'product_id' => $rawMaterialTwo->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 100,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $confirmation = WarehouseConfirmation::create([
        'confirmable_type' => MaterialIssue::class,
        'confirmable_id' => $materialIssue->id,
        'confirmation_type' => 'material_issue',
        'note' => 'Auto-generated from Material Issue',
        'status' => 'request',
    ]);

    WarehouseConfirmationItem::create([
        'warehouse_confirmation_id' => $confirmation->id,
        'material_issue_item_id' => $itemOne->id,
        'product_id' => $rawMaterialOne->id,
        'product_name' => $rawMaterialOne->name,
        'requested_qty' => 10,
        'confirmed_qty' => 10,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'status' => 'confirmed',
    ]);

    WarehouseConfirmationItem::create([
        'warehouse_confirmation_id' => $confirmation->id,
        'material_issue_item_id' => $itemTwo->id,
        'product_id' => $rawMaterialTwo->id,
        'product_name' => $rawMaterialTwo->name,
        'requested_qty' => 100,
        'confirmed_qty' => 100,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'status' => 'confirmed',
    ]);

    $confirmation->refresh()->load(['warehouseConfirmationItems.warehouse', 'warehouseConfirmationItems.materialIssueItem.product', 'warehouseConfirmationItems.product', 'confirmable']);

    Livewire::actingAs($user)
        ->test(ViewWarehouseConfirmation::class, ['record' => $confirmation->getKey()])
        ->assertSuccessful()
        ->assertSee('Material Issue Confirmation')
        ->assertSee('Informasi Material Issue')
        ->assertSee('Rincian Bahan')
        ->assertSee('Request 10')
        ->assertSee('Confirm 10')
        ->assertSee('Request 100')
        ->assertSee('Confirm 100')
        ->assertSee('Material Issue Item #' . $itemOne->id)
        ->assertSee('Material Issue Item #' . $itemTwo->id)
        ->assertSee('2 baris / qty 110');

    Livewire::actingAs($user)
        ->test(ListWarehouseConfirmations::class)
        ->assertSee('Qty Request')
        ->assertSee('2 baris / qty 110');
});