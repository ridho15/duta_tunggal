<?php

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionPlan;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use Database\Seeders\MaterialIssueSeeder;

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
});

function createMaterialIssueSeederContext(): array
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
        'name' => 'Seeder Raw Material A',
        'sku' => 'RM-SEED-A-' . fake()->unique()->numerify('###'),
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 10000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    $rawMaterialB = Product::factory()->create([
        'name' => 'Seeder Raw Material B',
        'sku' => 'RM-SEED-B-' . fake()->unique()->numerify('###'),
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 12000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    $finishedProduct = Product::factory()->create([
        'name' => 'Seeder Finished Product',
        'sku' => 'FG-SEED-' . fake()->unique()->numerify('###'),
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
        'code' => 'BOM-SEED-' . fake()->unique()->numerify('###'),
        'nama_bom' => 'Seeder BOM',
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
        'plan_number' => 'PP-SEED-' . fake()->unique()->numerify('###'),
        'name' => 'Seeder Production Plan',
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

    ManufacturingOrder::create([
        'mo_number' => 'MO-SEED-' . fake()->unique()->numerify('###'),
        'production_plan_id' => $productionPlan->id,
        'status' => 'in_progress',
        'start_date' => now(),
        'end_date' => now()->addDay(),
        'cabang_id' => $branch->id,
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
        'category',
        'rawCoa',
        'wipCoa',
        'rawMaterialA',
        'rawMaterialB',
        'finishedProduct',
        'bom',
        'productionPlan'
    );
}

test('material issue seeder creates and confirms one warehouse confirmation per material item', function () {
    createMaterialIssueSeederContext();

    $this->seed(MaterialIssueSeeder::class);

    $issues = MaterialIssue::query()
        ->with(['items', 'warehouseConfirmations.warehouseConfirmationItems'])
        ->orderBy('id')
        ->get();

    expect($issues)->toHaveCount(1);

    $issue = $issues->first();

    expect($issue->status)->toBe(MaterialIssue::STATUS_COMPLETED)
        ->and($issue->items)->toHaveCount(2)
        ->and($issue->warehouseConfirmations)->toHaveCount(2);

    $issue->warehouseConfirmations->each(function (WarehouseConfirmation $confirmation) {
        expect($confirmation->confirmation_type)->toBe('material_issue')
            ->and($confirmation->status)->toBe('confirmed')
            ->and($confirmation->warehouseConfirmationItems)->toHaveCount(1);

        $item = $confirmation->warehouseConfirmationItems->sole();

        expect($item->material_issue_item_id)->not->toBeNull()
            ->and($item->status)->toBe('confirmed')
            ->and((float) $item->confirmed_qty)->toBe((float) $item->requested_qty);
    });
});