<?php

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
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
use App\Services\ManufacturingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
});

function createMaterialIssueConfirmationContext(): array
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

    $rawMaterialA = Product::factory()->create([
        'name' => 'Confirmation Raw Material A',
        'sku' => 'RM-MI-CONF-A-' . fake()->unique()->numerify('###'),
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 10000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    $rawMaterialB = Product::factory()->create([
        'name' => 'Confirmation Raw Material B',
        'sku' => 'RM-MI-CONF-B-' . fake()->unique()->numerify('###'),
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 12000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    $finishedProduct = Product::factory()->create([
        'name' => 'Confirmation Finished Product',
        'sku' => 'FG-MI-CONF-' . fake()->unique()->numerify('###'),
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
        'code' => 'BOM-MI-CONF-' . fake()->unique()->numerify('###'),
        'nama_bom' => 'Confirmation BOM',
        'uom_id' => $uom->id,
        'quantity' => 1,
        'total_cost' => 22000,
        'work_in_progress_coa_id' => $wipCoa->id,
    ]);

    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterialA->id,
        'uom_id' => $uom->id,
        'quantity' => 1,
        'unit_price' => 10000,
        'subtotal' => 10000,
    ]);

    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterialB->id,
        'uom_id' => $uom->id,
        'quantity' => 1,
        'unit_price' => 12000,
        'subtotal' => 12000,
    ]);

    $productionPlan = ProductionPlan::create([
        'plan_number' => 'PP-MI-CONF-' . fake()->unique()->numerify('###'),
        'name' => 'Confirmation Production Plan',
        'source_type' => 'manual',
        'bill_of_material_id' => $bom->id,
        'product_id' => $finishedProduct->id,
        'quantity' => 3,
        'uom_id' => $uom->id,
        'start_date' => now(),
        'end_date' => now()->addDay(),
        'status' => 'scheduled',
        'warehouse_id' => $warehouse->id,
        'cabang_id' => $branch->id,
        'created_by' => $user->id,
    ]);

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-MI-CONF-' . fake()->unique()->numerify('###'),
        'production_plan_id' => $productionPlan->id,
        'manufacturing_order_id' => null,
        'warehouse_id' => $warehouse->id,
        'issue_date' => now()->toDateString(),
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_DRAFT,
        'total_cost' => 0,
        'created_by' => $user->id,
    ]);

    $issueItemA = MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterialA->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'quantity' => 3,
        'cost_per_unit' => 10000,
        'total_cost' => 30000,
        'status' => MaterialIssueItem::STATUS_DRAFT,
    ]);

    $issueItemB = MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterialB->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'quantity' => 3,
        'cost_per_unit' => 12000,
        'total_cost' => 36000,
        'status' => MaterialIssueItem::STATUS_DRAFT,
    ]);

    InventoryStock::create([
        'product_id' => $rawMaterialA->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 100,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    InventoryStock::create([
        'product_id' => $rawMaterialB->id,
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
        'rawMaterialA',
        'rawMaterialB',
        'productionPlan',
        'materialIssue',
        'issueItemA',
        'issueItemB'
    );
}

test('material issue warehouse confirmation is created per material item', function () {
    $context = createMaterialIssueConfirmationContext();

    app(ManufacturingService::class)->createWarehouseConfirmationForMaterialIssue($context['materialIssue']);

    $confirmations = WarehouseConfirmation::query()
        ->where('confirmable_type', MaterialIssue::class)
        ->where('confirmable_id', $context['materialIssue']->id)
        ->with('warehouseConfirmationItems')
        ->orderBy('id')
        ->get();

    expect($confirmations)->toHaveCount(2);

    $confirmations->each(function (WarehouseConfirmation $confirmation) {
        expect($confirmation->confirmation_type)->toBe('material_issue')
            ->and($confirmation->status)->toBe('request')
            ->and($confirmation->warehouseConfirmationItems)->toHaveCount(1);

        $item = $confirmation->warehouseConfirmationItems->first();

        expect($item->material_issue_item_id)->not->toBeNull()
            ->and($item->product_id)->not->toBeNull()
            ->and((float) $item->requested_qty)->toBeGreaterThan(0.0);
    });
});

test('material issue becomes completed when all confirmation items are approved', function () {
    $context = createMaterialIssueConfirmationContext();

    app(ManufacturingService::class)->createWarehouseConfirmationForMaterialIssue($context['materialIssue']);

    $confirmations = WarehouseConfirmation::query()
        ->where('confirmable_type', MaterialIssue::class)
        ->where('confirmable_id', $context['materialIssue']->id)
        ->with('warehouseConfirmationItems')
        ->get();

    $firstConfirmation = $confirmations->first();
    $secondConfirmation = $confirmations->last();

    $this->actingAs($context['user']);

    $firstItem = $firstConfirmation->warehouseConfirmationItems->sole();
    $firstItem->update([
        'status' => 'confirmed',
        'confirmed_qty' => $firstItem->requested_qty,
    ]);

    $firstMaterialIssueItem = $firstItem->fresh()->materialIssueItem;

    expect($firstMaterialIssueItem->fresh()->status)->toBe(MaterialIssueItem::STATUS_APPROVED)
        ->and($firstMaterialIssueItem->fresh()->approved_by)->not->toBeNull()
        ->and($firstMaterialIssueItem->fresh()->approved_at)->not->toBeNull();

    $secondItem = $secondConfirmation->warehouseConfirmationItems->sole();
    $secondItem->update([
        'status' => 'confirmed',
        'confirmed_qty' => $secondItem->requested_qty,
    ]);

    expect($context['materialIssue']->fresh()->status)->toBe(MaterialIssue::STATUS_COMPLETED)
        ->and($context['materialIssue']->fresh()->hasConfirmedWarehouseConfirmation())->toBeTrue();

    expect(JournalEntry::query()
        ->where('source_type', MaterialIssue::class)
        ->where('source_id', $context['materialIssue']->id)
        ->exists())->toBeTrue();

    WarehouseConfirmation::query()
        ->where('confirmable_type', MaterialIssue::class)
        ->where('confirmable_id', $context['materialIssue']->id)
        ->get()
        ->each(fn (WarehouseConfirmation $confirmation) => expect($confirmation->fresh()->status)->toBe('confirmed'));
});

test('approving parent warehouse confirmation also approves linked material issue items', function () {
    $context = createMaterialIssueConfirmationContext();

    app(ManufacturingService::class)->createWarehouseConfirmationForMaterialIssue($context['materialIssue']);

    $confirmation = WarehouseConfirmation::query()
        ->where('confirmable_type', MaterialIssue::class)
        ->where('confirmable_id', $context['materialIssue']->id)
        ->with('warehouseConfirmationItems.materialIssueItem')
        ->orderBy('id')
        ->firstOrFail();

    $this->actingAs($context['user']);

    $confirmation->update([
        'status' => 'confirmed',
        'confirmed_by' => $context['user']->id,
        'confirmed_at' => now(),
    ]);

    $linkedItem = $confirmation->fresh(['warehouseConfirmationItems.materialIssueItem'])
        ->warehouseConfirmationItems
        ->sole()
        ->materialIssueItem;

    expect($linkedItem->fresh()->status)->toBe(MaterialIssueItem::STATUS_APPROVED)
        ->and($linkedItem->fresh()->approved_by)->toBe($context['user']->id)
        ->and($linkedItem->fresh()->approved_at)->not->toBeNull();
});

test('material issue becomes rejected when any confirmation item is rejected', function () {
    $context = createMaterialIssueConfirmationContext();

    app(ManufacturingService::class)->createWarehouseConfirmationForMaterialIssue($context['materialIssue']);

    $confirmation = WarehouseConfirmation::query()
        ->where('confirmable_type', MaterialIssue::class)
        ->where('confirmable_id', $context['materialIssue']->id)
        ->with('warehouseConfirmationItems')
        ->firstOrFail();

    $confirmation->warehouseConfirmationItems->sole()->update([
        'status' => 'rejected',
        'confirmed_qty' => 0,
    ]);

    expect($context['materialIssue']->fresh()->status)->toBe(MaterialIssue::STATUS_REJECTED)
        ->and($confirmation->fresh()->status)->toBe('rejected');
});
