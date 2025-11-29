<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionPlan;
use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Rak;

test('material issue approval reserves stock from available to reserved', function () {
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

    // Create raw material product
    $rawMaterial = Product::factory()->create([
        'name' => 'Raw Material Test',
        'sku' => 'RM-TEST-001',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 10000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    // Create initial inventory stock
    $initialStock = InventoryStock::create([
        'product_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 100, // Initial available stock
        'qty_reserved' => 0,    // No reserved stock initially
        'qty_min' => 0,
    ]);

    // Create production plan and BOM
    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product Test',
        'sku' => 'FP-TEST-001',
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
        'code' => 'BOM-TEST-' . time(),
        'nama_bom' => 'BOM for Finished Product Test',
        'uom_id' => $uom->id,
        'quantity' => 1,
        'total_cost' => 10000,
        'work_in_progress_coa_id' => $rawCoa->id,
    ]);

    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'quantity' => 10, // Need 10 units of raw material per finished product
        'unit_price' => 10000,
        'subtotal' => 100000,
    ]);

    $productionPlan = ProductionPlan::create([
        'plan_number' => 'PP-TEST-' . time(),
        'name' => 'Test Production Plan',
        'source_type' => 'manual',
        'bill_of_material_id' => $bom->id,
        'product_id' => $finishedProduct->id,
        'quantity' => 5, // Produce 5 units, so need 50 units of raw material
        'uom_id' => $uom->id,
        'start_date' => now(),
        'end_date' => now()->addDays(7),
        'status' => 'scheduled',
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
    ]);

    // Create Material Issue
    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-TEST-' . time(),
        'production_plan_id' => $productionPlan->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => now()->toDateString(),
        'type' => 'issue',
        'status' => 'draft',
        'total_cost' => 500000, // 50 units * 10000
        'notes' => 'Test Material Issue',
        'created_by' => $user->id,
    ]);

    // Create Material Issue Item
    MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 50, // 5 * 10 units needed
        'cost_per_unit' => 10000,
        'total_cost' => 500000,
        'status' => 'draft',
        'inventory_coa_id' => $rawCoa->id,
    ]);

    // Verify initial stock state
    $initialStock->refresh();
    expect((float) $initialStock->qty_available)->toBe(100.0);
    expect((float) $initialStock->qty_reserved)->toBe(0.0);

    // Approve the Material Issue
    $materialIssue->update([
        'status' => MaterialIssue::STATUS_APPROVED,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);

    // Verify stock after approval - should move from available to reserved
    $initialStock->refresh();
    expect((float) $initialStock->qty_available)->toBe(100.0); // Available should remain the same
    expect((float) $initialStock->qty_reserved)->toBe(50.0);   // Reserved should increase by 50
});

test('material issue completion releases reserved stock and reduces available stock', function () {
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

    // Create COA for work in progress
    $wipCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.02'],
        ['name' => 'Barang Dalam Proses', 'type' => 'Asset', 'is_active' => true]
    );

    // Create raw material product
    $rawMaterial = Product::factory()->create([
        'name' => 'Raw Material Test 2',
        'sku' => 'RM-TEST-002',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 10000,
        'inventory_coa_id' => $rawCoa->id,
    ]);

    // Create initial inventory stock
    $initialStock = InventoryStock::create([
        'product_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 100, // Initial available stock
        'qty_reserved' => 0,    // No reserved stock initially
        'qty_min' => 0,
    ]);

    // Create production plan and BOM
    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product Test 2',
        'sku' => 'FP-TEST-002',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'cost_price' => 50000,
    ]);

    $bom = BillOfMaterial::create([
        'cabang_id' => $branch->id,
        'code' => 'BOM-TEST-2-' . time(),
        'nama_bom' => 'BOM for Finished Product Test 2',
        'product_id' => $finishedProduct->id,
        'uom_id' => $uom->id,
        'quantity' => 1,
        'total_cost' => 10000,
        'work_in_progress_coa_id' => $rawCoa->id,
    ]);

    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'quantity' => 10, // Need 10 units of raw material per finished product
        'unit_price' => 10000,
        'subtotal' => 100000,
    ]);

    $productionPlan = ProductionPlan::create([
        'plan_number' => 'PP-TEST-2-' . time(),
        'name' => 'Test Production Plan 2',
        'source_type' => 'manual',
        'bill_of_material_id' => $bom->id,
        'product_id' => $finishedProduct->id,
        'quantity' => 5, // Produce 5 units, so need 50 units of raw material
        'uom_id' => $uom->id,
        'start_date' => now(),
        'end_date' => now()->addDays(7),
        'status' => 'scheduled',
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
    ]);

    // Create Material Issue
    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-TEST-2-' . time(),
        'production_plan_id' => $productionPlan->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => now()->toDateString(),
        'type' => 'issue',
        'status' => 'draft',
        'total_cost' => 500000, // 50 units * 10000
        'notes' => 'Test Material Issue 2',
        'created_by' => $user->id,
        'wip_coa_id' => $wipCoa->id,
    ]);

    // Create Material Issue Item
    MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 50, // 5 * 10 units needed
        'cost_per_unit' => 10000,
        'total_cost' => 500000,
        'status' => 'draft',
        'inventory_coa_id' => $rawCoa->id,
    ]);

    // First approve the Material Issue
    $materialIssue->update([
        'status' => MaterialIssue::STATUS_APPROVED,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);

    // Verify stock after approval
    $initialStock->refresh();
    expect($initialStock->qty_available)->toBe(100.0);
    expect($initialStock->qty_reserved)->toBe(50.0);

    // Now complete the Material Issue
    $materialIssue->update([
        'status' => MaterialIssue::STATUS_COMPLETED,
    ]);

    // Verify stock after completion - reserved stock should be released and available stock should be reduced
    $initialStock->refresh();
    expect($initialStock->qty_available)->toBe(50.0); // Reduced by 50 (used for production)
    expect($initialStock->qty_reserved)->toBe(0.0);   // Reserved stock released
});