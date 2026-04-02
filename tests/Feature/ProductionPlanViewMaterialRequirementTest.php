<?php

use App\Filament\Resources\ProductionPlanResource\Pages\ViewProductionPlan;
use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionPlan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantProductionPlanViewPermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any production plan',
        'view production plan',
        'view any bill of material',
        'view any product',
        'view any cabang',
        'view any unit of measure',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'view any production plan',
        'view production plan',
        'view any bill of material',
        'view any product',
        'view any cabang',
        'view any unit of measure',
    ]);
}

test('production plan view shows material requirements from bom quantity times plan quantity', function () {
    $cabang = Cabang::factory()->create([
        'kode' => 'CBG-PP-VIEW',
        'nama' => 'Cabang View Production Plan',
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
    ]);

    grantProductionPlanViewPermissions($user);

    $uom = UnitOfMeasure::factory()->create([
        'name' => 'Pcs',
        'abbreviation' => 'PCS',
    ]);

    $category = ProductCategory::factory()->create();

    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product View PP',
        'sku' => 'FG-PP-VIEW',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
    ]);

    $rawMaterialOne = Product::factory()->create([
        'name' => 'Raw Material A View PP',
        'sku' => 'RM-PP-A',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 10000,
    ]);

    $rawMaterialTwo = Product::factory()->create([
        'name' => 'Raw Material B View PP',
        'sku' => 'RM-PP-B',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 12000,
    ]);

    $billOfMaterial = BillOfMaterial::query()->create([
        'cabang_id' => $cabang->id,
        'product_id' => $finishedProduct->id,
        'code' => 'BOM-PP-VIEW-001',
        'nama_bom' => 'BOM View PP',
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
        'plan_number' => 'PP-PP-VIEW-001',
        'name' => 'Production Plan View PP',
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

    Livewire::actingAs($user)
        ->test(ViewProductionPlan::class, ['record' => $productionPlan->getKey()])
        ->assertSuccessful()
        ->assertSee('Informasi Kebutuhan Bahan Produksi')
        ->assertSee('Ringkasan Kebutuhan')
        ->assertSee('Total bahan 2 | Available 0 | Partial 0 | Unavailable 2 | Issued 0 | Ready No')
        ->assertSee('Material')
        ->assertSee('Kebutuhan')
        ->assertSee('Stok Tersedia')
        ->assertSee('Terpakai')
        ->assertSee('Status')
        ->assertSee('(RM-PP-A) Raw Material A View PP')
        ->assertSee('(RM-PP-B) Raw Material B View PP')
        ->assertSee('4,00')
        ->assertSee('6,00')
        ->assertSee('Unavailable');
});
