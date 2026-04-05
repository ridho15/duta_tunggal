<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\StockReservation;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionPlan;
use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Rak;
use App\Filament\Resources\MaterialIssueResource;

function createMaterialIssueWorkflowContext(string $suffix): array
{
    $branch = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $branch->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    $category = ProductCategory::factory()->create();

    // Create COA for raw materials
    $rawCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.01'],
        ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]
    );

    $temporaryProductionCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]
    );

    // Create raw material product
    $rawMaterial = Product::factory()->create([
        'name' => 'Raw Material Test ' . $suffix,
        'sku' => 'RM-TEST-' . $suffix,
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
        'qty_available' => 100,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    // Create production plan and BOM
    $finishedProduct = Product::factory()->create([
        'name' => 'Finished Product Test ' . $suffix,
        'sku' => 'FP-TEST-' . $suffix,
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
        'code' => 'BOM-TEST-' . $suffix,
        'nama_bom' => 'BOM for Finished Product Test ' . $suffix,
        'uom_id' => $uom->id,
        'quantity' => 1,
        'total_cost' => 10000,
        'work_in_progress_coa_id' => $temporaryProductionCoa->id,
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
        'plan_number' => 'PP-TEST-' . $suffix,
        'name' => 'Test Production Plan ' . $suffix,
        'source_type' => 'manual',
        'bill_of_material_id' => $bom->id,
        'product_id' => $finishedProduct->id,
        'quantity' => 5,
        'uom_id' => $uom->id,
        'start_date' => now(),
        'end_date' => now()->addDays(7),
        'status' => 'scheduled',
        'warehouse_id' => $warehouse->id,
        'created_by' => $user->id,
    ]);

    $materialIssue = MaterialIssue::create([
        'issue_number' => 'MI-TEST-' . $suffix,
        'production_plan_id' => $productionPlan->id,
        'warehouse_id' => $warehouse->id,
        'issue_date' => now()->toDateString(),
        'type' => 'issue',
        'status' => 'draft',
        'total_cost' => 500000,
        'notes' => 'Test Material Issue',
        'created_by' => $user->id,
        'wip_coa_id' => $temporaryProductionCoa->id,
    ]);

    MaterialIssueItem::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'quantity' => 50, // 5 * 10 units needed
        'cost_per_unit' => 10000,
        'total_cost' => 500000,
        'status' => 'draft',
        'inventory_coa_id' => $rawCoa->id,
    ]);

    return compact(
        'branch',
        'user',
        'warehouse',
        'rak',
        'uom',
        'category',
        'rawCoa',
        'temporaryProductionCoa',
        'rawMaterial',
        'initialStock',
        'finishedProduct',
        'bom',
        'productionPlan',
        'materialIssue'
    );
}

test('requesting warehouse confirmation for manufacturing material issue keeps inventory quantity unchanged', function () {
    $context = createMaterialIssueWorkflowContext((string) now()->timestamp);
    $materialIssue = $context['materialIssue'];
    $initialStock = $context['initialStock'];

    $initialStock->refresh();
    expect((float) $initialStock->qty_available)->toBe(100.0);
    expect((float) $initialStock->qty_reserved)->toBe(0.0);

    $materialIssue->update([
        'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
    ]);

    $warehouseConfirmation = $materialIssue->ensureWarehouseConfirmationRequest();

    $initialStock->refresh();
    expect($warehouseConfirmation)->not->toBeNull();
    expect($materialIssue->fresh()->status)->toBe(MaterialIssue::STATUS_PENDING_APPROVAL);
    expect((float) $initialStock->qty_available)->toBe(100.0);
    expect((float) $initialStock->qty_reserved)->toBe(0.0);
});

test('confirmed manufacturing warehouse confirmation consumes stock and resolves stock movement source', function () {
    $context = createMaterialIssueWorkflowContext((string) (now()->timestamp + 1));
    $materialIssue = $context['materialIssue'];
    $initialStock = $context['initialStock'];
    $user = $context['user'];

    $materialIssue->update([
        'status' => MaterialIssue::STATUS_PENDING_APPROVAL,
    ]);

    $warehouseConfirmation = $materialIssue->ensureWarehouseConfirmationRequest();
    expect($warehouseConfirmation)->not->toBeNull();

    $warehouseConfirmation->update([
        'status' => 'confirmed',
        'confirmed_by' => $user->id,
        'confirmed_at' => now(),
    ]);

    $movement = StockMovement::query()
        ->where('from_model_type', MaterialIssue::class)
        ->where('from_model_id', $materialIssue->id)
        ->first();

    $initialStock->refresh();
    expect($materialIssue->fresh()->status)->toBe(MaterialIssue::STATUS_COMPLETED);
    expect((float) $initialStock->qty_available)->toBe(50.0);
    expect((float) $initialStock->qty_reserved)->toBe(0.0);
    expect($movement)->not->toBeNull();
    expect($movement->source_type_label)->toBe('Material Issue');
    expect($movement->source_number)->toBe($materialIssue->issue_number);
    expect($movement->source_display)->toBe('Material Issue - ' . $materialIssue->issue_number);
    expect($movement->source_resource_url)->toContain('/material-issues/' . $materialIssue->id);
});

test('material issue stock metrics include own reservation in effective stock', function () {
    $context = createMaterialIssueWorkflowContext((string) (now()->timestamp + 2));
    $materialIssue = $context['materialIssue'];
    $rawMaterial = $context['rawMaterial'];
    $warehouse = $context['warehouse'];

    StockReservation::create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => null,
        'quantity' => 50,
    ]);

    $otherIssue = MaterialIssue::factory()->create([
        'warehouse_id' => $warehouse->id,
        'type' => 'issue',
        'status' => MaterialIssue::STATUS_APPROVED,
    ]);

    StockReservation::create([
        'material_issue_id' => $otherIssue->id,
        'product_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => null,
        'quantity' => 40,
    ]);

    $metrics = MaterialIssueResource::getStockMetrics($rawMaterial->id, $warehouse->id, $materialIssue);

    expect($metrics['physical'])->toBe(100.0);
    expect($metrics['reserved'])->toBe(90.0);
    expect($metrics['free'])->toBe(10.0);
    expect($metrics['own_reserved'])->toBe(50.0);
    expect($metrics['effective'])->toBe(60.0);
});