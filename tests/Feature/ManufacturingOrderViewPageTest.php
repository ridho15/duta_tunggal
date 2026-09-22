<?php

use App\Filament\Resources\ManufacturingOrderResource\Pages\ViewManufacturingOrder;
use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\ProductionPlan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantManufacturingOrderViewPermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any manufacturing order',
        'view manufacturing order',
        'view any bill of material',
        'view any product',
        'view any cabang',
        'view any unit of measure',
        'update manufacturing order',
        'delete manufacturing order',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'view any manufacturing order',
        'view manufacturing order',
        'view any bill of material',
        'view any product',
        'view any cabang',
        'view any unit of measure',
        'update manufacturing order',
        'delete manufacturing order',
    ]);
}

test('manufacturing order view shows product and production information', function () {
    $cabang = Cabang::factory()->create([
        'kode' => 'CBG-VIEW',
        'nama' => 'Cabang View MO',
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
    ]);

    grantManufacturingOrderViewPermissions($user);

    $uom = UnitOfMeasure::factory()->create([
        'name' => 'Pcs',
        'abbreviation' => 'PCS',
    ]);

    ChartOfAccount::firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product View MO',
        'sku' => 'FG-MO-VIEW',
        'cabang_id' => $cabang->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'cost_price' => 88000,
    ]);

    $rawMaterialOne = Product::factory()->create([
        'name' => 'Raw Material A View MO',
        'sku' => 'RM-MO-A',
        'cabang_id' => $cabang->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 15000,
    ]);

    $rawMaterialTwo = Product::factory()->create([
        'name' => 'Raw Material B View MO',
        'sku' => 'RM-MO-B',
        'cabang_id' => $cabang->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 17500,
    ]);

    $billOfMaterial = BillOfMaterial::query()->create([
        'cabang_id' => $cabang->id,
        'product_id' => $finishedProduct->id,
        'quantity' => 1,
        'code' => 'BOM-MO-VIEW',
        'nama_bom' => 'BOM View MO',
        'uom_id' => $uom->id,
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
        'unit_price' => 15000,
        'subtotal' => 30000,
    ]);

    BillOfMaterialItem::query()->create([
        'bill_of_material_id' => $billOfMaterial->id,
        'product_id' => $rawMaterialTwo->id,
        'uom_id' => $uom->id,
        'quantity' => 1,
        'unit_price' => 17500,
        'subtotal' => 17500,
    ]);

    $productionPlan = ProductionPlan::query()->create([
        'plan_number' => 'PP-MO-VIEW-001',
        'name' => 'Production Plan View MO',
        'source_type' => 'manual',
        'product_id' => $finishedProduct->id,
        'quantity' => 10,
        'uom_id' => $uom->id,
        'cabang_id' => $cabang->id,
        'bill_of_material_id' => $billOfMaterial->id,
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDays(5)->toDateTimeString(),
        'status' => 'scheduled',
        'created_by' => $user->id,
    ]);

    $manufacturingOrder = ManufacturingOrder::query()->create([
        'mo_number' => 'MO-MO-VIEW-001',
        'production_plan_id' => $productionPlan->id,
        'cabang_id' => $cabang->id,
        'status' => 'draft',
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDays(7)->toDateTimeString(),
    ]);

    Livewire::actingAs($user)
        ->test(ViewManufacturingOrder::class, ['record' => $manufacturingOrder->getKey()])
        ->assertSuccessful()
        ->assertSee('Informasi Produksi')
        ->assertSee('PP-MO-VIEW-001 - Production Plan View MO')
        ->assertSee('(FG-MO-VIEW) Finished Product View MO')
        ->assertSee('Satuan Pcs (PCS) | Cost Price Rp 88.000')
        ->assertSee('(BOM-MO-VIEW) BOM View MO')
        ->assertSee('Item 2 | Available 0 | Partial 0 | Unavailable 2 | Issued 0 | Ready No')
        ->assertSee('Detail Bahan')
        ->assertSee('(RM-MO-A) Raw Material A View MO')
        ->assertSee('(RM-MO-B) Raw Material B View MO');
});
