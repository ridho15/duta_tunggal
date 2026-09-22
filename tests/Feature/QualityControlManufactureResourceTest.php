<?php

use App\Filament\Resources\QualityControlManufactureResource\Pages\CreateQualityControlManufacture;
use App\Filament\Resources\QualityControlManufactureResource\Pages\EditQualityControlManufacture;
use App\Filament\Resources\QualityControlManufactureResource\Pages\ViewQualityControlManufacture;
use App\Filament\Resources\QualityControlManufactureResource;
use App\Models\Cabang;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantQualityControlManufacturePermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any quality control',
        'view quality control',
        'create quality control',
        'update quality control',
        'delete quality control',
        'complete quality control',
        'view any production',
        'view production',
        'view any manufacturing order',
        'view manufacturing order',
        'view any product',
        'view any warehouse',
        'view any rak',
        'view any cabang',
        'view any unit of measure',
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
        'complete quality control',
        'view any production',
        'view production',
        'view any manufacturing order',
        'view manufacturing order',
        'view any product',
        'view any warehouse',
        'view any rak',
        'view any cabang',
        'view any unit of measure',
    ]);
}

function createQualityControlManufactureContext(): array
{
    $cabang = Cabang::factory()->create([
        'kode' => 'CBG-QCM',
        'nama' => 'Cabang QC Manufacture',
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
    ]);

    grantQualityControlManufacturePermissions($user);

    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $cabang->id,
        'kode' => 'WH-QCM',
        'name' => 'Gudang QC Manufacture',
    ]);

    $rak = Rak::factory()->create([
        'warehouse_id' => $warehouse->id,
        'code' => 'RAK-QCM',
        'name' => 'Rak QC Manufacture',
    ]);

    $uom = UnitOfMeasure::factory()->create([
        'name' => 'Pcs',
        'abbreviation' => 'PCS',
    ]);

    $category = ProductCategory::factory()->create();

    $product = Product::factory()->create([
        'name' => 'Produk QC Manufacture',
        'sku' => 'FG-QCM-001',
        'cabang_id' => $cabang->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
    ]);

    $productionPlan = ProductionPlan::query()->create([
        'plan_number' => 'PP-QCM-001',
        'name' => 'Production Plan QC Manufacture',
        'source_type' => 'manual',
        'product_id' => $product->id,
        'quantity' => 12,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'cabang_id' => $cabang->id,
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDays(2)->toDateTimeString(),
        'status' => 'scheduled',
        'created_by' => $user->id,
    ]);

    $manufacturingOrder = ManufacturingOrder::query()->create([
        'mo_number' => 'MO-QCM-001',
        'production_plan_id' => $productionPlan->id,
        'cabang_id' => $cabang->id,
        'status' => 'completed',
        'start_date' => now()->toDateTimeString(),
        'end_date' => now()->addDay()->toDateTimeString(),
    ]);

    $production = Production::query()->create([
        'production_number' => 'PRD-QCM-001',
        'manufacturing_order_id' => $manufacturingOrder->id,
        'production_date' => now()->toDateString(),
        'quantity_produced' => 10,
        'status' => 'finished',
    ]);

    $qualityControl = QualityControl::query()->create([
        'qc_number' => 'QC-M-20260403-0001',
        'inspected_by' => $user->id,
        'passed_quantity' => 8,
        'rejected_quantity' => 2,
        'status' => 0,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'reason_reject' => 'Minor defect',
        'product_id' => $product->id,
        'from_model_id' => $production->id,
        'from_model_type' => Production::class,
        'cabang_id' => $cabang->id,
    ]);

    $eligibleProduction = Production::query()->create([
        'production_number' => 'PRD-QCM-002',
        'manufacturing_order_id' => $manufacturingOrder->id,
        'production_date' => now()->subDay()->toDateString(),
        'quantity_produced' => 5,
        'status' => 'finished',
    ]);

    return compact('user', 'warehouse', 'rak', 'product', 'production', 'qualityControl', 'eligibleProduction');
}

test('quality control manufacture view page shows from production information clearly', function () {
    $context = createQualityControlManufactureContext();

    Livewire::actingAs($context['user'])
        ->test(ViewQualityControlManufacture::class, ['record' => $context['qualityControl']->getKey()])
        ->assertSuccessful()
        ->assertSee('From Production')
        ->assertSee('PRD-QCM-001 / MO-QCM-001')
        ->assertSee('(FG-QCM-001) Produk QC Manufacture')
        ->assertSee('Passed Quantity')
        ->assertSee('Rejected Quantity')
        ->assertSee('Minor defect');
});

test('quality control manufacture edit page keeps the linked production visible', function () {
    $context = createQualityControlManufactureContext();

    Livewire::actingAs($context['user'])
        ->test(EditQualityControlManufacture::class, ['record' => $context['qualityControl']->getKey()])
        ->assertSuccessful()
        ->assertFormFieldExists('from_model_id')
        ->assertFormSet([
            'from_model_id' => $context['production']->getKey(),
            'qc_number' => 'QC-M-20260403-0001',
            'reason_reject' => 'Minor defect',
        ])
        ->assertSee('From Production')
        ->assertSee('MO: MO-QCM-001 - Produk QC Manufacture (Qty: 10)');
});

test('quality control manufacture create page lists finished productions without active quality control', function () {
    $context = createQualityControlManufactureContext();

    $context['eligibleProduction']->qualityControl()->delete();

    Livewire::actingAs($context['user'])
        ->test(CreateQualityControlManufacture::class)
        ->assertSuccessful()
        ->assertFormFieldExists('from_model_id');

    $options = QualityControlManufactureResource::getProductionSelectOptions();
    $eligibleLabel = QualityControlManufactureResource::getProductionOptionLabel($context['eligibleProduction']->fresh());
    $existingLinkedLabel = QualityControlManufactureResource::getProductionOptionLabel($context['production']->fresh());

    expect(array_key_exists($context['eligibleProduction']->getKey(), $options))->toBeTrue();
    expect(array_key_exists($context['production']->getKey(), $options))->toBeFalse();
    expect(array_values($options))
        ->toContain($eligibleLabel)
        ->not->toContain($existingLinkedLabel);
});

test('quality control manufacture create defaults inspector to current user for regular user', function () {
    $context = createQualityControlManufactureContext();
    $otherUser = User::factory()->create(['cabang_id' => $context['user']->cabang_id]);

    Livewire::actingAs($context['user'])
        ->test(CreateQualityControlManufacture::class)
        ->assertFormSet([
            'inspected_by' => $context['user']->id,
        ])
        ->fillForm([
            'from_model_id' => $context['eligibleProduction']->id,
            'qc_number' => 'QC-M-INSPECTOR-001',
            'warehouse_id' => $context['warehouse']->id,
            'rak_id' => $context['rak']->id,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'inspected_by' => $otherUser->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(QualityControl::where('qc_number', 'QC-M-INSPECTOR-001')->value('inspected_by'))
        ->toBe($context['user']->id);
});

test('quality control manufacture create allows super admin to choose inspector', function () {
    $context = createQualityControlManufactureContext();
    $otherUser = User::factory()->create(['cabang_id' => $context['user']->cabang_id]);
    $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $context['user']->assignRole($role);

    Livewire::actingAs($context['user'])
        ->test(CreateQualityControlManufacture::class)
        ->fillForm([
            'from_model_id' => $context['eligibleProduction']->id,
            'qc_number' => 'QC-M-SUPER-INSPECTOR-001',
            'warehouse_id' => $context['warehouse']->id,
            'rak_id' => $context['rak']->id,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'inspected_by' => $otherUser->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(QualityControl::where('qc_number', 'QC-M-SUPER-INSPECTOR-001')->value('inspected_by'))
        ->toBe($otherUser->id);
});

test('quality control manufacture edit keeps existing inspector for regular user', function () {
    $context = createQualityControlManufactureContext();
    $otherUser = User::factory()->create(['cabang_id' => $context['user']->cabang_id]);

    Livewire::actingAs($context['user'])
        ->test(EditQualityControlManufacture::class, ['record' => $context['qualityControl']->getKey()])
        ->fillForm([
            'inspected_by' => $otherUser->id,
            'notes' => 'Updated notes',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($context['qualityControl']->fresh()->inspected_by)
        ->toBe($context['user']->id);
});
