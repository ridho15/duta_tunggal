<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\MaterialFulfillment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionPlan;
use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Rak;

test('production plan mo creation checks material fulfillment', function () {
    $branch = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $branch->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    $category = ProductCategory::factory()->create(['cabang_id' => $branch->id]);

    // Create COA for raw materials
    $rawCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.01'],
        ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]
    );

    // Create products
    $rawMaterial1 = Product::factory()->create([
        'name' => 'Raw Material 1',
        'sku' => 'RM-001',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 10000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    $rawMaterial2 = Product::factory()->create([
        'name' => 'Raw Material 2',
        'sku' => 'RM-002',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 15000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    $finishedGood = Product::factory()->create([
        'name' => 'Finished Product',
        'sku' => 'FG-001',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'inventory_coa_id' => null,
    ]);

    // Create BOM
    $bom = BillOfMaterial::create([
        'code' => 'BOM-TEST-001',
        'nama_bom' => 'BOM for Test Product',
        'name' => 'BOM for Test Product',
        'product_id' => $finishedGood->id,
        'uom_id' => $uom->id,
        'version' => '1.0',
        'is_active' => true,
        'cabang_id' => $branch->id,
    ]);

    // Add BOM items
    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial1->id,
        'quantity' => 2,
        'uom_id' => $uom->id,
        'sequence' => 1,
    ]);

    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial2->id,
        'quantity' => 3,
        'uom_id' => $uom->id,
        'sequence' => 2,
    ]);

    // Create Production Plan
    $productionPlan = ProductionPlan::create([
        'plan_number' => 'PP-TEST-001',
        'name' => 'Test Production Plan',
        'source_type' => 'manual',
        'bill_of_material_id' => $bom->id,
        'product_id' => $finishedGood->id,
        'quantity' => 5,
        'uom_id' => $uom->id,
        'start_date' => now(),
        'end_date' => now()->addDays(7),
        'status' => 'draft',
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
    ]);

    // Set to scheduled status
    $productionPlan->update(['status' => 'scheduled']);

    // Update material fulfillment first
    app(\App\Services\ManufacturingService::class)->updateMaterialFulfillment($productionPlan);

    // Initially, no stock - should not be able to start production
    $canStart = MaterialFulfillment::canStartProduction($productionPlan);
    expect($canStart)->toBeFalse();

    $summary = MaterialFulfillment::getFulfillmentSummary($productionPlan);
    expect($summary['total_materials'])->toBe(2);
    expect($summary['not_available'])->toBe(2);

    // Add sufficient stock for first material (need 10, have 5 - insufficient)
    InventoryStock::create([
        'product_id' => $rawMaterial1->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 5, // Need 10 (2 * 5), so insufficient
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    // Update fulfillment and check again
    app(\App\Services\ManufacturingService::class)->updateMaterialFulfillment($productionPlan);
    $canStart = MaterialFulfillment::canStartProduction($productionPlan);
    expect($canStart)->toBeFalse();

    $summary = MaterialFulfillment::getFulfillmentSummary($productionPlan);
    expect($summary['partially_available'])->toBe(1);
    expect($summary['not_available'])->toBe(1);

    // Add sufficient stock for second material (need 15, have 10 - insufficient)
    InventoryStock::create([
        'product_id' => $rawMaterial2->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 10, // Need 15 (3 * 5), so insufficient
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    // Update fulfillment and check again
    app(\App\Services\ManufacturingService::class)->updateMaterialFulfillment($productionPlan);
    $canStart = MaterialFulfillment::canStartProduction($productionPlan);
    expect($canStart)->toBeFalse();

    $summary = MaterialFulfillment::getFulfillmentSummary($productionPlan);
    expect($summary['partially_available'])->toBe(2);
    expect($summary['not_available'])->toBe(0);

    // Add sufficient stock for both materials
    $stock1 = InventoryStock::where('product_id', $rawMaterial1->id)->first();
    $stock1->update(['qty_available' => 15]); // Now sufficient (15 >= 10)

    $stock2 = InventoryStock::where('product_id', $rawMaterial2->id)->first();
    $stock2->update(['qty_available' => 20]); // Now sufficient (20 >= 15)

    // Update fulfillment and check again
    app(\App\Services\ManufacturingService::class)->updateMaterialFulfillment($productionPlan);
    $canStart = MaterialFulfillment::canStartProduction($productionPlan);
    expect($canStart)->toBeTrue();

    $summary = MaterialFulfillment::getFulfillmentSummary($productionPlan);
    expect($summary['fully_available'])->toBe(2);
    expect($summary['partially_available'])->toBe(0);
    expect($summary['not_available'])->toBe(0);
});