<?php

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\ManufacturingOrder;
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
use App\Services\ManufacturingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
});

function createManufacturingApprovalContext(): array
{
    $branch = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $branch->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    $category = ProductCategory::factory()->create();

    $rawCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.01'],
        ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]
    );

    $wipCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    $rawMaterial = Product::factory()->create([
        'name' => 'Workflow Raw Material',
        'sku' => 'RM-WC-WORKFLOW-' . fake()->unique()->numerify('###'),
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 10000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    $finishedProduct = Product::factory()->create([
        'name' => 'Workflow Finished Product',
        'sku' => 'FG-WC-WORKFLOW-' . fake()->unique()->numerify('###'),
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'cost_price' => 50000,
    ]);

    $bom = BillOfMaterial::create([
        'cabang_id' => $branch->id,
        'product_id' => $finishedProduct->id,
        'code' => 'BOM-WC-WORKFLOW-' . fake()->unique()->numerify('###'),
        'nama_bom' => 'Workflow BOM',
        'uom_id' => $uom->id,
        'quantity' => 1,
        'total_cost' => 10000,
        'work_in_progress_coa_id' => $wipCoa->id,
    ]);

    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'quantity' => 1,
        'unit_price' => 10000,
        'subtotal' => 10000,
    ]);

    $productionPlan = ProductionPlan::create([
        'plan_number' => 'PP-WC-WORKFLOW-' . fake()->unique()->numerify('###'),
        'name' => 'Workflow Production Plan',
        'source_type' => 'manual',
        'bill_of_material_id' => $bom->id,
        'product_id' => $finishedProduct->id,
        'quantity' => 5,
        'uom_id' => $uom->id,
        'start_date' => now(),
        'end_date' => now()->addDay(),
        'status' => 'scheduled',
        'warehouse_id' => $warehouse->id,
        'cabang_id' => $branch->id,
        'created_by' => $user->id,
    ]);

    $manufacturingOrder = ManufacturingOrder::create([
        'mo_number' => 'MO-WC-WORKFLOW-' . fake()->unique()->numerify('###'),
        'production_plan_id' => $productionPlan->id,
        'status' => 'draft',
        'start_date' => now(),
        'end_date' => now()->addDay(),
        'items' => [],
        'cabang_id' => $branch->id,
    ]);

    InventoryStock::create([
        'product_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 100,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    return compact(
        'branch',
        'user',
        'warehouse',
        'rak',
        'uom',
        'rawMaterial',
        'productionPlan',
        'manufacturingOrder'
    );
}

function createManufacturingObserverContext(): array
{
    $branch = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $branch->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    $category = ProductCategory::factory()->create();

    $rawCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.01'],
        ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]
    );

    $wipCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    $rawMaterial = Product::factory()->create([
        'name' => 'Observer Raw Material',
        'sku' => 'RM-OBSERVER-' . fake()->unique()->numerify('###'),
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 10000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    $finishedProduct = Product::factory()->create([
        'name' => 'Observer Finished Product',
        'sku' => 'FG-OBSERVER-' . fake()->unique()->numerify('###'),
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'cost_price' => 50000,
    ]);

    $bom = BillOfMaterial::create([
        'cabang_id' => $branch->id,
        'product_id' => $finishedProduct->id,
        'code' => 'BOM-OBSERVER-' . fake()->unique()->numerify('###'),
        'nama_bom' => 'Observer BOM',
        'uom_id' => $uom->id,
        'quantity' => 1,
        'total_cost' => 10000,
        'work_in_progress_coa_id' => $wipCoa->id,
    ]);

    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'quantity' => 1,
        'unit_price' => 10000,
        'subtotal' => 10000,
    ]);

    $productionPlan = ProductionPlan::create([
        'plan_number' => 'PP-OBSERVER-' . fake()->unique()->numerify('###'),
        'name' => 'Observer Production Plan',
        'source_type' => 'manual',
        'bill_of_material_id' => $bom->id,
        'product_id' => $finishedProduct->id,
        'quantity' => 5,
        'uom_id' => $uom->id,
        'start_date' => now(),
        'end_date' => now()->addDay(),
        'status' => 'scheduled',
        'warehouse_id' => $warehouse->id,
        'cabang_id' => $branch->id,
        'created_by' => $user->id,
    ]);

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-OBSERVER-' . fake()->unique()->numerify('###'),
        'production_plan_id' => $productionPlan->id,
        'manufacturing_order_id' => null,
        'warehouse_id' => $warehouse->id,
        'issue_date' => now()->toDateString(),
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_COMPLETED,
        'total_cost' => 10000,
        'created_by' => $user->id,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);

    MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'quantity' => 1,
        'cost_per_unit' => 10000,
        'total_cost' => 10000,
        'status' => MaterialIssueItem::STATUS_COMPLETED,
        'inventory_coa_id' => $rawMaterial->inventory_coa_id,
    ]);

    return compact(
        'branch',
        'user',
        'warehouse',
        'rak',
        'uom',
        'rawMaterial',
        'productionPlan',
        'materialIssue'
    );
}

test('manufacturing warehouse confirmation is stored with polymorphic manufacturing data', function () {
    $context = createManufacturingApprovalContext();

    $confirmation = app(ManufacturingService::class)->createWarehouseConfirmation($context['manufacturingOrder']);
    $duplicateCall = app(ManufacturingService::class)->createWarehouseConfirmation($context['manufacturingOrder']);

    expect($confirmation->confirmable_type)->toBe(ManufacturingOrder::class)
        ->and($confirmation->confirmable_id)->toBe($context['manufacturingOrder']->id)
        ->and($confirmation->confirmation_type)->toBe('manufacturing_order')
        ->and($confirmation->status)->toBe('request')
        ->and($duplicateCall->id)->toBe($confirmation->id);
});

test('manufacturing material issue can move to pending approval before warehouse confirmation is confirmed', function () {
    $context = createManufacturingApprovalContext();

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-WC-WORKFLOW-' . fake()->unique()->numerify('###'),
        'production_plan_id' => $context['productionPlan']->id,
        'manufacturing_order_id' => $context['manufacturingOrder']->id,
        'warehouse_id' => $context['warehouse']->id,
        'issue_date' => now()->toDateString(),
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_DRAFT,
        'total_cost' => 50000,
        'created_by' => $context['user']->id,
    ]);

    MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $context['rawMaterial']->id,
        'uom_id' => $context['uom']->id,
        'warehouse_id' => $context['warehouse']->id,
        'rak_id' => $context['rak']->id,
        'quantity' => 5,
        'cost_per_unit' => 10000,
        'total_cost' => 50000,
        'status' => MaterialIssueItem::STATUS_DRAFT,
        'inventory_coa_id' => $context['rawMaterial']->inventory_coa_id,
    ]);

    $materialIssue->update([
        'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
    ]);

    expect($materialIssue->fresh()->status)->toBe(MaterialIssue::STATUS_PENDING_APPROVAL)
        ->and($materialIssue->fresh()->approved_by)->toBeNull()
        ->and($materialIssue->fresh()->approved_at)->toBeNull();
});

test('confirmed manufacturing warehouse confirmation keeps material issue pending without item confirmation', function () {
    $context = createManufacturingApprovalContext();

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-WC-AUTO-' . fake()->unique()->numerify('###'),
        'production_plan_id' => $context['productionPlan']->id,
        'manufacturing_order_id' => $context['manufacturingOrder']->id,
        'warehouse_id' => $context['warehouse']->id,
        'issue_date' => now()->toDateString(),
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_DRAFT,
        'total_cost' => 50000,
        'created_by' => $context['user']->id,
    ]);

    MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $context['rawMaterial']->id,
        'uom_id' => $context['uom']->id,
        'warehouse_id' => $context['warehouse']->id,
        'rak_id' => $context['rak']->id,
        'quantity' => 5,
        'cost_per_unit' => 10000,
        'total_cost' => 50000,
        'status' => MaterialIssueItem::STATUS_DRAFT,
        'inventory_coa_id' => $context['rawMaterial']->inventory_coa_id,
    ]);

    $materialIssue->update([
        'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
    ]);

    $warehouseConfirmation = app(ManufacturingService::class)->createWarehouseConfirmation($context['manufacturingOrder']);
    $warehouseConfirmation->update([
        'status' => 'confirmed',
        'confirmed_by' => $context['user']->id,
        'confirmed_at' => now(),
    ]);

    expect($materialIssue->fresh()->status)->toBe(MaterialIssue::STATUS_PENDING_APPROVAL)
        ->and($materialIssue->fresh()->approved_by)->toBeNull()
        ->and($materialIssue->fresh()->approved_at)->toBeNull();
});

test('material issue observer creates manufacturing order without auto warehouse confirmation', function () {
    $context = createManufacturingObserverContext();

    $observer = new \App\Observers\MaterialIssueObserver();
    $method = new \ReflectionMethod(\App\Observers\MaterialIssueObserver::class, 'createManufacturingOrder');
    $method->setAccessible(true);
    $method->invoke($observer, $context['materialIssue']);

    $manufacturingOrder = ManufacturingOrder::query()
        ->where('production_plan_id', $context['productionPlan']->id)
        ->firstOrFail();

    expect($manufacturingOrder->status)->toBe('draft')
        ->and(WarehouseConfirmation::query()
            ->where('confirmable_type', ManufacturingOrder::class)
            ->where('confirmable_id', $manufacturingOrder->id)
            ->exists())->toBeFalse();
});