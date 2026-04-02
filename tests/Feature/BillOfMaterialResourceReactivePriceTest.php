<?php

use App\Filament\Resources\BillOfMaterialResource\Pages\CreateBillOfMaterial;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantBillOfMaterialCreatePermission(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any bill of material',
        'create bill of material',
        'view any product',
        'view any cabang',
        'view any unit of measure',
        'view any chart of account',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'view any bill of material',
        'create bill of material',
        'view any product',
        'view any cabang',
        'view any unit of measure',
        'view any chart of account',
    ]);
}

test('bom item price refreshes when material changes', function () {
    $user = User::factory()->create();
    grantBillOfMaterialCreatePermission($user);

    $cabang = Cabang::factory()->create();
    $uom = UnitOfMeasure::factory()->create();

    ChartOfAccount::firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    $firstMaterial = Product::factory()->create([
        'name' => 'Raw Material A',
        'sku' => 'RM-BOM-A',
        'cabang_id' => $cabang->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 15000,
    ]);

    $secondMaterial = Product::factory()->create([
        'name' => 'Raw Material B',
        'sku' => 'RM-BOM-B',
        'cabang_id' => $cabang->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 27500,
    ]);

    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product BOM',
        'sku' => 'FG-BOM-001',
        'cabang_id' => $cabang->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'cost_price' => 50000,
    ]);

    Livewire::actingAs($user)
        ->test(CreateBillOfMaterial::class)
        ->assertSuccessful()
        ->fillForm([
            'code' => 'BOM-REACTIVE-001',
            'nama_bom' => 'Reactive BOM Test',
            'cabang_id' => $cabang->id,
            'product_id' => $finishedProduct->id,
            'uom_id' => $uom->id,
            'quantity' => 1,
            'items' => [[
            'product_id' => $firstMaterial->id,
            'uom_id' => $uom->id,
            'quantity' => 2,
            'unit_price' => '15.000',
            'subtotal' => '30.000',
            'note' => 'Initial material',
            ]],
        ])
        ->set('data.items.0.product_id', $secondMaterial->id)
        ->assertSet('data.items.0.unit_price', '27.500')
        ->assertSet('data.items.0.subtotal', '55.000')
        ->set('data.items.0.quantity', 3)
        ->assertSet('data.items.0.subtotal', '82.500');
});