<?php

use App\Filament\Resources\BillOfMaterialResource;
use App\Filament\Resources\BillOfMaterialResource\Pages\CreateBillOfMaterial;
use App\Filament\Resources\BillOfMaterialResource\Pages\EditBillOfMaterial;
use App\Filament\Resources\BillOfMaterialResource\Pages\ViewBillOfMaterial;
use App\Models\BillOfMaterial;
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

test('bom conversion rows load from selected product', function () {
    $cabang = Cabang::factory()->create();
    $uom = UnitOfMeasure::factory()->create();
    $altUom1 = UnitOfMeasure::factory()->create(['name' => 'Box', 'abbreviation' => 'BOX']);
    $altUom2 = UnitOfMeasure::factory()->create(['name' => 'Pack', 'abbreviation' => 'PCK']);

    ChartOfAccount::firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product Conversion Test',
        'sku' => 'FG-BOM-CONV-001',
        'cabang_id' => $cabang->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'cost_price' => 50000,
    ]);

    $finishedProduct->unitConversions()->delete();

    $finishedProduct->unitConversions()->create([
        'uom_id' => $altUom1->id,
        'nilai_konversi' => 12,
    ]);

    $finishedProduct->unitConversions()->create([
        'uom_id' => $altUom2->id,
        'nilai_konversi' => 24,
    ]);

    $method = new ReflectionMethod(BillOfMaterialResource::class, 'findProductForForm');
    $method->setAccessible(true);

    $resolvedProduct = $method->invoke(null, $finishedProduct->id);

    expect($resolvedProduct)->not->toBeNull();
    expect($resolvedProduct->unitConversions)
        ->toHaveCount(2)
        ->and($resolvedProduct->unitConversions->first()->uom_id)->toBe($altUom1->id)
        ->and($resolvedProduct->unitConversions->first()->nilai_konversi)->toBe('12.00')
        ->and($resolvedProduct->unitConversions->last()->uom_id)->toBe($altUom2->id)
        ->and($resolvedProduct->unitConversions->last()->nilai_konversi)->toBe('24.00');
});

test('bom conversion rows preload on edit and view', function () {
    $cabang = Cabang::factory()->create();
    $uom = UnitOfMeasure::factory()->create();
    $altUom = UnitOfMeasure::factory()->create(['name' => 'Box', 'abbreviation' => 'BOX']);

    ChartOfAccount::firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    $product = Product::factory()->create([
        'name' => 'Finished Product Edit Test',
        'sku' => 'FG-BOM-EDIT-001',
        'cabang_id' => $cabang->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'cost_price' => 50000,
    ]);

    $product->unitConversions()->delete();

    $product->unitConversions()->create([
        'uom_id' => $altUom->id,
        'nilai_konversi' => 18,
    ]);

    $bom = BillOfMaterial::query()->create([
        'cabang_id' => $cabang->id,
        'product_id' => $product->id,
        'uom_id' => $uom->id,
        'code' => 'BOM-EDIT-001',
        'nama_bom' => 'Edit Conversion BOM Test',
        'quantity' => 1,
        'is_active' => true,
        'labor_cost' => 0,
        'overhead_cost' => 0,
    ]);

    $editPage = app(EditBillOfMaterial::class);
    $editRecord = new ReflectionProperty($editPage, 'record');
    $editRecord->setAccessible(true);
    $editRecord->setValue($editPage, $bom);

    $editMethod = new ReflectionMethod(EditBillOfMaterial::class, 'mutateFormDataBeforeFill');
    $editMethod->setAccessible(true);
    $editData = $editMethod->invoke($editPage, [
        'product_id' => $product->id,
    ]);

    expect($editData['satuan_konversi'])
        ->toBeArray()
        ->toHaveCount(1)
        ->and($editData['satuan_konversi'][0]['uom_id'])->toBe($altUom->id)
        ->and($editData['satuan_konversi'][0]['nilai_konversi'])->toBe('18.00');

    $viewPage = app(ViewBillOfMaterial::class);
    $viewRecord = new ReflectionProperty($viewPage, 'record');
    $viewRecord->setAccessible(true);
    $viewRecord->setValue($viewPage, $bom);

    $viewMethod = new ReflectionMethod(ViewBillOfMaterial::class, 'mutateFormDataBeforeFill');
    $viewMethod->setAccessible(true);
    $viewData = $viewMethod->invoke($viewPage, [
        'product_id' => $product->id,
    ]);

    expect($viewData['satuan_konversi'])
        ->toBeArray()
        ->toHaveCount(1)
        ->and($viewData['satuan_konversi'][0]['uom_id'])->toBe($altUom->id)
        ->and($viewData['satuan_konversi'][0]['nilai_konversi'])->toBe('18.00');
});