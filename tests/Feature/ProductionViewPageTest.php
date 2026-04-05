<?php

use App\Filament\Resources\ProductionResource\Pages\ListProductions;
use App\Filament\Resources\ProductionResource\Pages\EditProduction;
use App\Filament\Resources\ProductionResource\Pages\ViewProduction;
use App\Models\ChartOfAccount;
use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ManufacturingOrder;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantProductionViewPermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any production',
        'view production',
        'view any manufacturing order',
        'view manufacturing order',
        'view any bill of material',
        'view any product',
        'view any cabang',
        'view any unit of measure',
        'update production',
        'delete production',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'view any production',
        'view production',
        'view any manufacturing order',
        'view manufacturing order',
        'view any bill of material',
        'view any product',
        'view any cabang',
        'view any unit of measure',
        'update production',
        'delete production',
    ]);
}

test('production list shows a view action and the view page shows material requirements', function () {
    $cabang = Cabang::factory()->create([
        'kode' => 'CBG-PROD-VIEW',
        'nama' => 'Cabang View Production',
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
    ]);

    grantProductionViewPermissions($user);

    $uom = UnitOfMeasure::factory()->create([
        'name' => 'Pcs',
        'abbreviation' => 'PCS',
    ]);

    $category = ProductCategory::factory()->create();

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '1-201'],
        ['name' => 'Persediaan Barang Dalam Proses - WIP Inventory', 'type' => 'Asset', 'is_active' => true]
    );

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '5230'],
        ['name' => 'Biaya Tenaga Kerja Proses Produksi', 'type' => 'Expense', 'is_active' => true]
    );

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '6-202'],
        ['name' => 'Biaya Tenaga Kerja Langsung - Direct Labor 2', 'type' => 'Expense', 'is_active' => true]
    );

    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product View Production',
        'sku' => 'FG-PROD-VIEW',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
    ]);

    $rawMaterialOne = Product::factory()->create([
        'name' => 'Raw Material A View Production',
        'sku' => 'RM-PROD-A',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
    ]);

    $rawMaterialTwo = Product::factory()->create([
        'name' => 'Raw Material B View Production',
        'sku' => 'RM-PROD-B',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
    ]);

    $billOfMaterial = BillOfMaterial::query()->create([
        'cabang_id' => $cabang->id,
        'product_id' => $finishedProduct->id,
        'code' => 'BOM-PROD-VIEW',
        'nama_bom' => 'BOM View Production',
        'uom_id' => $uom->id,
        'quantity' => 1,
        'labor_cost' => 5000,
        'overhead_cost' => 2500,
        'total_cost' => 0,
        'is_active' => true,
    ]);

    BillOfMaterialItem::query()->create([
        'bill_of_material_id' => $billOfMaterial->id,
        'product_id' => $rawMaterialOne->id,
        'uom_id' => $uom->id,
        'quantity' => 2,
        'unit_price' => 10000,
        'subtotal' => 20000,
    ]);

    BillOfMaterialItem::query()->create([
        'bill_of_material_id' => $billOfMaterial->id,
        'product_id' => $rawMaterialTwo->id,
        'uom_id' => $uom->id,
        'quantity' => 3,
        'unit_price' => 12000,
        'subtotal' => 36000,
    ]);

    $productionPlan = ProductionPlan::query()->create([
        'plan_number' => 'PP-PROD-VIEW-001',
        'name' => 'Production Plan View Production',
        'source_type' => 'manual',
        'product_id' => $finishedProduct->id,
        'quantity' => 2,
        'uom_id' => $uom->id,
        'cabang_id' => $cabang->id,
        'bill_of_material_id' => $billOfMaterial->id,
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDays(3)->toDateTimeString(),
        'status' => 'scheduled',
        'created_by' => $user->id,
    ]);

    $manufacturingOrder = ManufacturingOrder::query()->create([
        'mo_number' => 'MO-PROD-VIEW-001',
        'production_plan_id' => $productionPlan->id,
        'cabang_id' => $cabang->id,
        'status' => 'completed',
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDays(2)->toDateTimeString(),
    ]);

    $production = Production::query()->create([
        'production_number' => 'PRD-PROD-VIEW-001',
        'manufacturing_order_id' => $manufacturingOrder->id,
        'production_date' => now()->toDateString(),
        'quantity_produced' => 2,
        'status' => 'finished',
    ]);

    expect(
        JournalEntry::query()
            ->where('source_type', Production::class)
            ->where('source_id', $production->id)
            ->count()
    )->toBe(3);

    Livewire::actingAs($user)
        ->test(ListProductions::class)
        ->assertTableActionExists('view');

    Livewire::actingAs($user)
        ->test(ViewProduction::class, ['record' => $production->getKey()])
        ->assertSuccessful()
        ->assertSee('Informasi Produksi')
        ->assertSee('Informasi Kebutuhan Bahan Produksi')
        ->assertSee('PRD-PROD-VIEW-001')
        ->assertSee('MO-PROD-VIEW-001')
        ->assertSee('PP-PROD-VIEW-001')
        ->assertSee('(FG-PROD-VIEW) Finished Product View Production')
        ->assertSee('(BOM-PROD-VIEW) BOM View Production')
        ->assertSee('Jurnal Produksi In Progress')
        ->assertSee('Baris Jurnal')
        ->assertSee('Total Debit')
        ->assertSee('Total Credit')
        ->assertSee('Selisih')
        ->assertSee('Total bahan 2 | Available 0 | Partial 0 | Unavailable 2 | Issued 0 | Ready No')
        ->assertSee('Material')
        ->assertSee('Kebutuhan')
        ->assertSee('Stok Tersedia')
        ->assertSee('Terpakai')
        ->assertSee('(RM-PROD-A) Raw Material A View Production')
        ->assertSee('(RM-PROD-B) Raw Material B View Production')
        ->assertSee('4,00')
        ->assertSee('6,00')
        ->assertSee('Unavailable');
});

test('production edit page stays editable and shows bom requirements info', function () {
    $cabang = Cabang::factory()->create([
        'kode' => 'CBG-PROD-EDIT',
        'nama' => 'Cabang Edit Production',
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
    ]);

    grantProductionViewPermissions($user);

    $uom = UnitOfMeasure::factory()->create([
        'name' => 'Pcs',
        'abbreviation' => 'PCS',
    ]);

    $category = ProductCategory::factory()->create();

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '1-201'],
        ['name' => 'Persediaan Barang Dalam Proses - WIP Inventory', 'type' => 'Asset', 'is_active' => true]
    );

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '5230'],
        ['name' => 'Biaya Tenaga Kerja Proses Produksi', 'type' => 'Expense', 'is_active' => true]
    );

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '6-202'],
        ['name' => 'Biaya Tenaga Kerja Langsung - Direct Labor 2', 'type' => 'Expense', 'is_active' => true]
    );

    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product Edit Production',
        'sku' => 'FG-PROD-EDIT',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
    ]);

    $rawMaterial = Product::factory()->create([
        'name' => 'Raw Material Edit Production',
        'sku' => 'RM-PROD-EDIT',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
    ]);

    $billOfMaterial = BillOfMaterial::query()->create([
        'cabang_id' => $cabang->id,
        'product_id' => $finishedProduct->id,
        'code' => 'BOM-PROD-EDIT',
        'nama_bom' => 'BOM Edit Production',
        'uom_id' => $uom->id,
        'quantity' => 1,
        'labor_cost' => 5000,
        'overhead_cost' => 2500,
        'total_cost' => 0,
        'is_active' => true,
    ]);

    BillOfMaterialItem::query()->create([
        'bill_of_material_id' => $billOfMaterial->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'quantity' => 2,
        'unit_price' => 10000,
        'subtotal' => 20000,
    ]);

    $productionPlan = ProductionPlan::query()->create([
        'plan_number' => 'PP-PROD-EDIT-001',
        'name' => 'Production Plan Edit Production',
        'source_type' => 'manual',
        'product_id' => $finishedProduct->id,
        'quantity' => 1,
        'uom_id' => $uom->id,
        'cabang_id' => $cabang->id,
        'bill_of_material_id' => $billOfMaterial->id,
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDays(3)->toDateTimeString(),
        'status' => 'scheduled',
        'created_by' => $user->id,
    ]);

    $manufacturingOrder = ManufacturingOrder::query()->create([
        'mo_number' => 'MO-PROD-EDIT-001',
        'production_plan_id' => $productionPlan->id,
        'cabang_id' => $cabang->id,
        'status' => 'completed',
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDays(2)->toDateTimeString(),
    ]);

    $production = Production::query()->create([
        'production_number' => 'PRD-PROD-EDIT-001',
        'manufacturing_order_id' => $manufacturingOrder->id,
        'production_date' => now()->toDateString(),
        'quantity_produced' => 1,
        'status' => 'finished',
    ]);

    Livewire::actingAs($user)
        ->test(EditProduction::class, ['record' => $production->getKey()])
        ->assertSuccessful()
        ->assertFormFieldExists('production_number')
        ->assertFormFieldExists('manufacturing_order_id')
        ->assertFormFieldExists('production_date')
        ->assertSee('Simpan')
        ->assertSee('Informasi BOM dan Kebutuhan Bahan Produksi')
        ->assertSee('(PP-PROD-EDIT-001) Production Plan Edit Production')
        ->assertSee('(BOM-PROD-EDIT) BOM Edit Production')
        ->assertSee('Ringkasan Kebutuhan')
        ->assertSee('(RM-PROD-EDIT) Raw Material Edit Production')
        ->assertSee('2,00');
});

test('production view page exposes finish action and marks draft production as finished', function () {
    $cabang = Cabang::factory()->create([
        'kode' => 'CBG-PROD-FINISH',
        'nama' => 'Cabang Finish Production',
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
    ]);

    grantProductionViewPermissions($user);

    $uom = UnitOfMeasure::factory()->create([
        'name' => 'Pcs',
        'abbreviation' => 'PCS',
    ]);

    $category = ProductCategory::factory()->create();

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '1-201'],
        ['name' => 'Persediaan Barang Dalam Proses - WIP Inventory', 'type' => 'Asset', 'is_active' => true]
    );

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '5230'],
        ['name' => 'Biaya Tenaga Kerja Proses Produksi', 'type' => 'Expense', 'is_active' => true]
    );

    ChartOfAccount::query()->firstOrCreate(
        ['code' => '6-202'],
        ['name' => 'Biaya Tenaga Kerja Langsung - Direct Labor 2', 'type' => 'Expense', 'is_active' => true]
    );

    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product Finish Production',
        'sku' => 'FG-PROD-FINISH',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
    ]);

    $rawMaterial = Product::factory()->create([
        'name' => 'Raw Material Finish Production',
        'sku' => 'RM-PROD-FINISH',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
    ]);

    $billOfMaterial = BillOfMaterial::query()->create([
        'cabang_id' => $cabang->id,
        'product_id' => $finishedProduct->id,
        'code' => 'BOM-PROD-FINISH',
        'nama_bom' => 'BOM Finish Production',
        'uom_id' => $uom->id,
        'quantity' => 1,
        'labor_cost' => 5000,
        'overhead_cost' => 2500,
        'total_cost' => 0,
        'is_active' => true,
    ]);

    BillOfMaterialItem::query()->create([
        'bill_of_material_id' => $billOfMaterial->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'quantity' => 1,
        'unit_price' => 10000,
        'subtotal' => 10000,
    ]);

    $productionPlan = ProductionPlan::query()->create([
        'plan_number' => 'PP-PROD-FINISH-001',
        'name' => 'Production Plan Finish Production',
        'source_type' => 'manual',
        'product_id' => $finishedProduct->id,
        'quantity' => 3,
        'uom_id' => $uom->id,
        'cabang_id' => $cabang->id,
        'bill_of_material_id' => $billOfMaterial->id,
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDays(3)->toDateTimeString(),
        'status' => 'scheduled',
        'created_by' => $user->id,
    ]);

    $manufacturingOrder = ManufacturingOrder::query()->create([
        'mo_number' => 'MO-PROD-FINISH-001',
        'production_plan_id' => $productionPlan->id,
        'cabang_id' => $cabang->id,
        'status' => 'completed',
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDays(2)->toDateTimeString(),
    ]);

    $production = Production::query()->create([
        'production_number' => 'PRD-PROD-FINISH-001',
        'manufacturing_order_id' => $manufacturingOrder->id,
        'production_date' => now()->toDateString(),
        'quantity_produced' => null,
        'status' => 'draft',
    ]);

    Livewire::actingAs($user)
        ->test(ViewProduction::class, ['record' => $production->getKey()])
        ->assertSuccessful()
        ->assertSee('Finished')
        ->callAction('finished');

    expect($production->fresh()->status)->toBe('finished')
        ->and($production->fresh()->quantity_produced)->toBe((string) $productionPlan->quantity);
});