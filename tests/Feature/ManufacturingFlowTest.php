<?php

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\ManufacturingOrderMaterial;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Models\User;
use App\Services\BalanceSheetService;
use App\Services\ManufacturingJournalService;
use App\Services\QualityControlService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

\Tests\TestCase::disableBaseSeeding();

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

it('runs the manufacturing to finance flow and balances ledgers and stock', function () {
    $branch = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $branch->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    $category = ProductCategory::factory()->create();

    $rawCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1-101'],
        ['name' => 'PERSEDIAAN BAHAN BAKU - RAW MATERIAL INVENTORY', 'type' => 'Asset', 'is_active' => true]
    );
    $wipCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1400.04'],
        ['name' => 'POS SEMENTARA PRODUKSI', 'type' => 'Asset', 'is_active' => true]
    );
    $wipInventoryCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1-201'],
        ['name' => 'PERSEDIAAN BARANG DALAM PROSES - WIP INVENTORY', 'type' => 'Asset', 'is_active' => true]
    );
    $laborCoa = ChartOfAccount::firstOrCreate(
        ['code' => '5230'],
        ['name' => 'BIAYA TENAGA KERJA PROSES PRODUKSI', 'type' => 'Expense', 'is_active' => true]
    );
    $fgCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.02'],
        ['name' => 'Persediaan Barang Produksi', 'type' => 'Asset', 'is_active' => true]
    );
    $bdpCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1150'],
        ['name' => 'Barang Dalam Proses', 'type' => 'Asset', 'is_active' => true]
    );

    $rawCost = 20.0;
    $rawMaterial = Product::factory()->create([
        'name' => 'Raw Material A',
        'sku' => 'RM-001',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => $rawCost,
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
        'inventory_coa_id' => $fgCoa->id,
    ]);

    $materialPerUnit = 2;
    $bomUnitCost = $materialPerUnit * $rawCost;

    $bom = Model::withoutEvents(function () use ($branch, $finishedGood, $uom, $bomUnitCost) {
        return BillOfMaterial::create([
            'cabang_id' => $branch->id,
            'product_id' => $finishedGood->id,
            'quantity' => 1,
            'code' => 'BOM-TEST',
            'nama_bom' => 'Test BOM',
            'note' => null,
            'is_active' => true,
            'uom_id' => $uom->id,
            'labor_cost' => 0,
            'overhead_cost' => 0,
            'total_cost' => $bomUnitCost,
        ]);
    });

    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial->id,
        'quantity' => $materialPerUnit,
        'uom_id' => $uom->id,
        'unit_price' => $rawCost,
        'subtotal' => $bomUnitCost,
        'note' => null,
    ]);

    $planQuantity = 5;
    $plan = ProductionPlan::create([
        'plan_number' => 'PLAN-TEST',
        'name' => 'Manufacturing Plan',
        'source_type' => 'manual',
        'bill_of_material_id' => $bom->id,
        'product_id' => $finishedGood->id,
        'quantity' => $planQuantity,
        'uom_id' => $uom->id,
        'start_date' => Carbon::now(),
        'end_date' => Carbon::now()->addDay(),
        'status' => 'scheduled',
        'notes' => null,
        'created_by' => $user->id,
    ]);

    $mo = ManufacturingOrder::create([
        'mo_number' => 'MO-TEST',
        'production_plan_id' => $plan->id,
        'status' => 'in_progress',
        'start_date' => Carbon::now(),
        'end_date' => null,
    ]);

    $totalMaterialQty = $materialPerUnit * $planQuantity;

    $initialRawQty = 100;
    $rawStock = InventoryStock::create([
        'product_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => $initialRawQty,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $rawStock->decrement('qty_available', $totalMaterialQty);

    $fgStock = InventoryStock::create([
        'product_id' => $finishedGood->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 0,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $issue = MaterialIssue::withoutEvents(function () use ($plan, $mo, $warehouse) {
        return MaterialIssue::create([
            'issue_number' => 'MI-TEST',
            'production_plan_id' => $plan->id,
            'manufacturing_order_id' => $mo->id,
            'warehouse_id' => $warehouse->id,
            'issue_date' => Carbon::now(),
            'type' => 'issue',
            'status' => 'draft',
            'total_cost' => 0,
            'created_by' => null,
        ]);
    });

    MaterialIssueItem::create([
        'material_issue_id' => $issue->id,
        'product_id' => $rawMaterial->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'quantity' => $totalMaterialQty,
        'cost_per_unit' => $rawCost,
        'total_cost' => $totalMaterialQty * $rawCost,
        'inventory_coa_id' => $rawCoa->id,
        'notes' => null,
    ]);

    MaterialIssue::withoutEvents(function () use ($issue, $totalMaterialQty, $rawCost) {
        $issue->update([
            'status' => 'completed',
            'total_cost' => $totalMaterialQty * $rawCost,
        ]);
    });

    app(ManufacturingJournalService::class)->generateJournalForMaterialIssue($issue->fresh());

    $issueEntries = JournalEntry::where('source_type', MaterialIssue::class)
        ->where('source_id', $issue->id)
        ->get();
    expect($issueEntries)->toHaveCount(2);
    $issueValue = (float) $issue->total_cost;
    expect((float) $issueEntries->sum('debit'))->toBe($issueValue);
    expect((float) $issueEntries->sum('credit'))->toBe($issueValue);

    $production = Production::withoutEvents(function () use ($mo) {
        return Production::create([
            'production_number' => 'PROD-TEST',
            'manufacturing_order_id' => $mo->id,
            'production_date' => Carbon::now(),
            'status' => 'finished',
        ]);
    });

    // Post Produksi In Progress journal (Dr 1-201, Cr 1400.04 + Cr 5230)
    // BOM has labor_cost=0 and overhead_cost=0, so only the material portion is posted.
    app(ManufacturingJournalService::class)->generateJournalForProductionInProgress($production->fresh());

    // Create QC Manufacture to complete the production
    $qc = QualityControl::withoutEvents(function () use ($production, $finishedGood, $warehouse, $rak, $planQuantity, $user) {
        return QualityControl::create([
            'qc_number' => 'QC-M-TEST',
            'passed_quantity' => $planQuantity,
            'rejected_quantity' => 0,
            'status' => 0, // Not processed yet
            'inspected_by' => $user->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'product_id' => $finishedGood->id,
            'from_model_type' => Production::class,
            'from_model_id' => $production->id,
            'date_send_stock' => Carbon::now(),
        ]);
    });

    // Complete the QC to trigger journal entries and stock movement
    $qcService = app(QualityControlService::class);
    $qcService->completeQualityControl($qc, [
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    $qc->refresh();

    $this->assertEquals($initialRawQty - $totalMaterialQty, (float) $rawStock->fresh()->qty_available);
    $this->assertEquals($planQuantity, (float) $fgStock->fresh()->qty_available);

    $productionValue = $issueValue;

    // 1-101 raw material: credited by material issue, never debited
    $rawMovement = JournalEntry::where('coa_id', $rawCoa->id);
    // 1400.04 pos sementara: debited by material issue, credited by in-progress WIP journal → net 0
    $wipMovement = JournalEntry::where('coa_id', $wipCoa->id);
    // 1-201 WIP inventory: debited by in-progress journal, credited by QC completion → net 0
    $wipInventoryMovement = JournalEntry::where('coa_id', $wipInventoryCoa->id);
    // 1140.02 finished goods: debited by QC completion
    $fgMovement = JournalEntry::where('coa_id', $fgCoa->id);

    expect((float) $rawMovement->sum('debit'))->toBe(0.0);
    expect((float) $rawMovement->sum('credit'))->toBe($issueValue);
    // Pos sementara nets to 0: D from material issue = K from in-progress
    expect((float) ($wipMovement->sum('debit') - $wipMovement->sum('credit')))->toBe(0.0);
    // WIP inventory nets to 0: D from in-progress = K from QC completion
    expect((float) ($wipInventoryMovement->sum('debit') - $wipInventoryMovement->sum('credit')))->toBe(0.0);
    expect((float) $fgMovement->sum('debit'))->toBe($productionValue);
    expect((float) $fgMovement->sum('credit'))->toBe(0.0);
});


    $balanceSheet = app(BalanceSheetService::class)->generate([
        'as_of_date' => now()->format('Y-m-d'),
        'cabang_id' => $branch->id,
    ]);

    expect($balanceSheet['is_balanced'])->toBeTrue();
    expect((float) $balanceSheet['difference'])->toBe(0.0);
test('create manufacturing order from production plan', function () {
    $branch = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create();
    $finishedProduct = Product::factory()->create([
        'is_raw_material' => false,
        'uom_id' => $uom->id,
    ]);

    $productionPlan = ProductionPlan::factory()->create([
        'product_id' => $finishedProduct->id,
        'quantity' => 50,
        'status' => 'scheduled',
    ]);

    $mo = ManufacturingOrder::factory()->create([
        'production_plan_id' => $productionPlan->id,
        'status' => 'draft',
    ]);

    expect($mo)->toBeInstanceOf(ManufacturingOrder::class)
        ->and($mo->productionPlan)->toBeInstanceOf(ProductionPlan::class)
        ->and($mo->productionPlan->product)->toBeInstanceOf(Product::class)
        ->and($mo->status)->toBe('draft');
});

test('calculate material requirements from BOM', function () {
    $branch = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create();

    $finishedProduct = Product::factory()->create([
        'is_raw_material' => false,
        'uom_id' => $uom->id,
    ]);

    $rawMaterial1 = Product::factory()->create([
        'is_raw_material' => true,
        'uom_id' => $uom->id,
    ]);

    $rawMaterial2 = Product::factory()->create([
        'is_raw_material' => true,
        'uom_id' => $uom->id,
    ]);

    $bom = BillOfMaterial::factory()->create([
        'product_id' => $finishedProduct->id,
        'quantity' => 1,
        'code' => 'BOM-TEST-001',
    ]);

    BillOfMaterialItem::factory()->create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial1->id,
        'quantity' => 2,
        'uom_id' => $uom->id,
    ]);

    BillOfMaterialItem::factory()->create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial2->id,
        'quantity' => 3,
        'uom_id' => $uom->id,
    ]);

    $productionPlan = ProductionPlan::factory()->create([
        'product_id' => $finishedProduct->id,
        'quantity' => 10,
        'bill_of_material_id' => $bom->id,
        'status' => 'scheduled',
    ]);

    $mo = ManufacturingOrder::factory()->create([
        'production_plan_id' => $productionPlan->id,
    ]);

    // Calculate material requirements based on BOM
    $materialRequirements = [];
    foreach ($bom->items as $item) {
        $materialRequirements[$item->product_id] = [
            'quantity' => $item->quantity * $productionPlan->quantity,
            'uom_id' => $item->uom_id,
        ];
    }

    expect($materialRequirements)->toHaveCount(2);
    expect($materialRequirements[$rawMaterial1->id]['quantity'])->toBe(20.0); // 2 * 10
    expect($materialRequirements[$rawMaterial2->id]['quantity'])->toBe(30.0); // 3 * 10
});

test('manufacturing order status workflow', function () {
    $branch = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create();
    $finishedProduct = Product::factory()->create([
        'is_raw_material' => false,
        'uom_id' => $uom->id,
    ]);

    $productionPlan = ProductionPlan::factory()->create([
        'product_id' => $finishedProduct->id,
        'quantity' => 10,
        'status' => 'scheduled',
    ]);

    $mo = ManufacturingOrder::factory()->create([
        'production_plan_id' => $productionPlan->id,
        'status' => 'draft',
    ]);

    $mo->update(['status' => 'in_progress']);
    expect($mo->fresh()->status)->toBe('in_progress');

    $mo->update(['status' => 'completed']);
    expect($mo->fresh()->status)->toBe('completed');
});

test('validate stock availability for manufacturing', function () {
    $branch = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create();

    $finishedProduct = Product::factory()->create([
        'is_raw_material' => false,
        'uom_id' => $uom->id,
    ]);

    $rawMaterial = Product::factory()->create([
        'is_raw_material' => true,
        'uom_id' => $uom->id,
    ]);

    $productionPlan = ProductionPlan::factory()->create([
        'product_id' => $finishedProduct->id,
        'quantity' => 10,
        'status' => 'scheduled',
    ]);

    $mo = ManufacturingOrder::factory()->create([
        'production_plan_id' => $productionPlan->id,
    ]);

    // Create stock for raw material
    $stock = InventoryStock::factory()->create([
        'product_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 50,
        'qty_reserved' => 0,
    ]);

    // Check if stock is sufficient for manufacturing (this would be done in service layer)
    $requiredQty = 20; // This would come from BOM calculation
    $availableQty = $stock->qty_available - $stock->qty_reserved;

    expect($availableQty)->toBeGreaterThanOrEqual($requiredQty);
    expect($stock->warehouse)->toBeInstanceOf(Warehouse::class);
    expect($stock->rak)->toBeInstanceOf(Rak::class);
});

test('material issue creates journal entries', function () {
    $branch = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);

    $materialIssue = MaterialIssue::factory()->create([
        'manufacturing_order_id' => null,
        'warehouse_id' => $warehouse->id,
        'status' => 'draft',
        'total_cost' => 100000,
    ]);

    $journal = JournalEntry::factory()->create([
        'source_type' => MaterialIssue::class,
        'source_id' => $materialIssue->id,
        'debit' => 100000,
        'credit' => 0,
        'description' => 'Material Issue for MI ' . $materialIssue->issue_number,
    ]);

    expect($journal)->toBeInstanceOf(JournalEntry::class);
    expect($journal->source_type)->toBe(MaterialIssue::class);
    expect($journal->source_id)->toBe($materialIssue->id);
    expect($journal->debit)->toBe('100000.00');
    expect($journal->credit)->toBe('0.00');
});

test('manufacturing order relationships', function () {
    $branch = Cabang::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create();

    $finishedProduct = Product::factory()->create([
        'is_raw_material' => false,
        'uom_id' => $uom->id,
    ]);

    $rawMaterial = Product::factory()->create([
        'is_raw_material' => true,
        'uom_id' => $uom->id,
    ]);

    $productionPlan = ProductionPlan::factory()->create([
        'product_id' => $finishedProduct->id,
    ]);

    $mo = ManufacturingOrder::factory()->create([
        'production_plan_id' => $productionPlan->id,
    ]);

    WarehouseConfirmation::create([
        'confirmable_type' => ManufacturingOrder::class,
        'confirmable_id' => $mo->id,
        'confirmation_type' => 'manufacturing_order',
        'status' => 'confirmed',
        'confirmed_by' => null,
        'confirmed_at' => now(),
    ]);

    // Create a material issue for this production plan
    $materialIssue = MaterialIssue::factory()->create([
        'production_plan_id' => $productionPlan->id,
        'manufacturing_order_id' => $mo->id,
        'warehouse_id' => $warehouse->id,
        'status' => 'approved',
    ]);

    MaterialIssueItem::factory()->create([
        'material_issue_id' => $materialIssue->id,
        'product_id' => $rawMaterial->id,
        'quantity' => 10,
        'warehouse_id' => $warehouse->id,
        'uom_id' => $uom->id,
        'rak_id' => $rak->id,
    ]);

    expect($mo->productionPlan)->toBeInstanceOf(ProductionPlan::class);
    expect($mo->materialIssues)->toHaveCount(1);

    $issue = $mo->materialIssues->first();
    expect($issue)->toBeInstanceOf(MaterialIssue::class);
    expect($issue->warehouse)->toBeInstanceOf(Warehouse::class);
    expect($issue->items)->toHaveCount(1);

    $item = $issue->items->first();
    expect($item->product)->toBeInstanceOf(Product::class);
    expect($item->warehouse)->toBeInstanceOf(Warehouse::class);
    expect($item->uom)->toBeInstanceOf(UnitOfMeasure::class);
    expect($item->rak)->toBeInstanceOf(Rak::class);
});
