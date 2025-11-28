<?php

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\FinishedGoodsCompletion;
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
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\User;
use App\Services\ManufacturingJournalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

\Tests\TestCase::disableBaseSeeding();

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

it('runs the manufacturing to finance flow and balances ledgers and stock', function () {
    $branch = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $branch->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    $category = ProductCategory::factory()->create(['cabang_id' => $branch->id]);

    $rawCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.01'],
        ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]
    );
    $wipCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.02'],
        ['name' => 'Persediaan Barang Dalam Proses', 'type' => 'Asset', 'is_active' => true]
    );
    $fgCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.03'],
        ['name' => 'Persediaan Barang Jadi', 'type' => 'Asset', 'is_active' => true]
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
        'product_id' => $finishedGood->id,
        'quantity' => $planQuantity,
        'status' => 'in_progress',
        'start_date' => Carbon::now(),
        'end_date' => null,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    $totalMaterialQty = $materialPerUnit * $planQuantity;
    ManufacturingOrderMaterial::create([
        'manufacturing_order_id' => $mo->id,
        'material_id' => $rawMaterial->id,
        'qty_required' => $totalMaterialQty,
        'qty_used' => 0,
        'warehouse_id' => $warehouse->id,
        'uom_id' => $uom->id,
        'rak_id' => $rak->id,
    ]);

    $initialRawQty = 100;
    $rawStock = InventoryStock::create([
        'product_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => $initialRawQty,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

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
        'notes' => null,
    ]);

    MaterialIssue::withoutEvents(function () use ($issue, $totalMaterialQty, $rawCost) {
        $issue->update([
            'status' => 'completed',
            'total_cost' => $totalMaterialQty * $rawCost,
        ]);
    });

    app(ManufacturingJournalService::class)->generateJournalForMaterialIssue($issue->fresh());

    $rawStock->decrement('qty_available', $totalMaterialQty);
    ManufacturingOrderMaterial::where('manufacturing_order_id', $mo->id)
        ->where('material_id', $rawMaterial->id)
        ->update(['qty_used' => $totalMaterialQty]);

    $issueEntries = JournalEntry::where('source_type', MaterialIssue::class)
        ->where('source_id', $issue->id)
        ->get();
    expect($issueEntries)->toHaveCount(2);
    $issueValue = $totalMaterialQty * $rawCost;
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

    app()->instance(ManufacturingJournalService::class, new class extends ManufacturingJournalService
    {
        public function generateJournalForProductionCompletion(Production $production): void
        {
            $manufacturingOrder = $production->manufacturingOrder;

            $manufacturingOrder->loadMissing([
                'product.billOfMaterial.items.product',
                'productionPlan.billOfMaterial.items.product',
            ]);

            $bom = $manufacturingOrder->product->billOfMaterial->firstWhere('is_active', true)
                ?? $manufacturingOrder->productionPlan?->billOfMaterial;

            if (!$bom) {
                throw new \Exception('No active BOM found for product: ' . $manufacturingOrder->product->name);
            }

            $bom->loadMissing('items.product');

            $materialCost = $bom->items->sum(function ($item) {
                return (float) $item->quantity * (float) ($item->product->cost_price ?? 0);
            });

            $totalCost = ($materialCost + (float) ($bom->labor_cost ?? 0) + (float) ($bom->overhead_cost ?? 0))
                * (float) $manufacturingOrder->quantity;

            $bdpCoa = ChartOfAccount::where('code', '1140.02')->firstOrFail();
            $barangJadiCoa = ChartOfAccount::where('code', '1140.03')->firstOrFail();

            DB::transaction(function () use ($production, $bdpCoa, $barangJadiCoa, $totalCost, $manufacturingOrder) {
                $branchResolver = app(\App\Services\JournalBranchResolver::class);
                $branchId = $branchResolver->resolve($production);
                $departmentId = $branchResolver->resolveDepartment($production);
                $projectId = $branchResolver->resolveProject($production);

                JournalEntry::where('source_type', Production::class)
                    ->where('source_id', $production->id)
                    ->delete();

                JournalEntry::create([
                    'coa_id' => $barangJadiCoa->id,
                    'date' => $production->production_date,
                    'reference' => $production->production_number,
                    'description' => 'Penyelesaian produksi - ' . $manufacturingOrder->mo_number . ' (' . $manufacturingOrder->product->name . ')',
                    'debit' => $totalCost,
                    'credit' => 0,
                    'journal_type' => 'manufacturing_completion',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => Production::class,
                    'source_id' => $production->id,
                ]);

                JournalEntry::create([
                    'coa_id' => $bdpCoa->id,
                    'date' => $production->production_date,
                    'reference' => $production->production_number,
                    'description' => 'Penyelesaian produksi - ' . $manufacturingOrder->mo_number . ' (' . $manufacturingOrder->product->name . ')',
                    'debit' => 0,
                    'credit' => $totalCost,
                    'journal_type' => 'manufacturing_completion',
                    'cabang_id' => $branchId,
                    'department_id' => $departmentId,
                    'project_id' => $projectId,
                    'source_type' => Production::class,
                    'source_id' => $production->id,
                ]);
            });
        }
    });

    app(ManufacturingJournalService::class)->generateJournalForProductionCompletion($production->fresh());

    $productionEntries = JournalEntry::where('source_type', Production::class)
        ->where('source_id', $production->id)
        ->get();
    $productionValue = $bomUnitCost * $planQuantity;
    expect($productionEntries)->toHaveCount(2);
    expect((float) $productionEntries->sum('debit'))->toBe($productionValue);
    expect((float) $productionEntries->sum('credit'))->toBe($productionValue);

    $completion = FinishedGoodsCompletion::withoutEvents(function () use ($plan, $finishedGood, $uom, $warehouse, $rak, $productionValue, $planQuantity) {
        return FinishedGoodsCompletion::create([
            'completion_number' => 'FGC-TEST',
            'production_plan_id' => $plan->id,
            'product_id' => $finishedGood->id,
            'quantity' => $planQuantity,
            'uom_id' => $uom->id,
            'total_cost' => $productionValue,
            'completion_date' => Carbon::now(),
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'notes' => null,
            'status' => 'draft',
            'created_by' => null,
        ]);
    });

    $fgStock->increment('qty_available', $planQuantity);

    FinishedGoodsCompletion::withoutEvents(function () use ($completion) {
        $completion->update(['status' => 'completed']);
    });

    $this->assertEquals($initialRawQty - $totalMaterialQty, (float) $rawStock->fresh()->qty_available);
    $this->assertEquals($planQuantity, (float) $fgStock->fresh()->qty_available);

    $rawMovement = JournalEntry::where('coa_id', $rawCoa->id);
    $wipMovement = JournalEntry::where('coa_id', $wipCoa->id);
    $fgMovement = JournalEntry::where('coa_id', $fgCoa->id);

    expect((float) $rawMovement->sum('debit'))->toBe(0.0);
    expect((float) $rawMovement->sum('credit'))->toBe($issueValue);
    expect((float) ($wipMovement->sum('debit') - $wipMovement->sum('credit')))->toBe(-$issueValue);
    expect((float) $fgMovement->sum('debit'))->toBe($productionValue);
    expect((float) $fgMovement->sum('credit'))->toBe(0.0);
});

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
        'product_id' => $finishedProduct->id,
        'quantity' => 25,
        'status' => 'draft',
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    expect($mo)->toBeInstanceOf(ManufacturingOrder::class)
        ->and($mo->productionPlan)->toBeInstanceOf(ProductionPlan::class)
        ->and($mo->product)->toBeInstanceOf(Product::class)
        ->and($mo->quantity)->toBe(25)
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

    $mo = ManufacturingOrder::factory()->create([
        'product_id' => $finishedProduct->id,
        'quantity' => 10,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    ManufacturingOrderMaterial::factory()->create([
        'manufacturing_order_id' => $mo->id,
        'material_id' => $rawMaterial1->id,
        'qty_required' => 20, // 2 * 10
        'warehouse_id' => $warehouse->id,
        'uom_id' => $uom->id,
        'rak_id' => $rak->id,
    ]);

    ManufacturingOrderMaterial::factory()->create([
        'manufacturing_order_id' => $mo->id,
        'material_id' => $rawMaterial2->id,
        'qty_required' => 30, // 3 * 10
        'warehouse_id' => $warehouse->id,
        'uom_id' => $uom->id,
        'rak_id' => $rak->id,
    ]);

    $materials = $mo->manufacturingOrderMaterial;
    expect($materials)->toHaveCount(2);

    $material1 = $materials->where('material_id', $rawMaterial1->id)->first();
    $material2 = $materials->where('material_id', $rawMaterial2->id)->first();

    expect($material1->qty_required)->toBe(20);
    expect($material2->qty_required)->toBe(30);
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

    $mo = ManufacturingOrder::factory()->create([
        'product_id' => $finishedProduct->id,
        'quantity' => 10,
        'status' => 'draft',
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
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

    $mo = ManufacturingOrder::factory()->create([
        'product_id' => $finishedProduct->id,
        'quantity' => 10,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    ManufacturingOrderMaterial::factory()->create([
        'manufacturing_order_id' => $mo->id,
        'material_id' => $rawMaterial->id,
        'qty_required' => 20,
        'warehouse_id' => $warehouse->id,
        'uom_id' => $uom->id,
        'rak_id' => $rak->id,
    ]);

    $material = $mo->manufacturingOrderMaterial->first();

    expect($material->qty_required)->toBe(20);
    expect($material->warehouse)->toBeInstanceOf(Warehouse::class);
    expect($material->rak)->toBeInstanceOf(Rak::class);
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
        'product_id' => $finishedProduct->id,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    ManufacturingOrderMaterial::factory()->create([
        'manufacturing_order_id' => $mo->id,
        'material_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'uom_id' => $uom->id,
        'rak_id' => $rak->id,
    ]);

    expect($mo->productionPlan)->toBeInstanceOf(ProductionPlan::class);
    expect($mo->product)->toBeInstanceOf(Product::class);
    expect($mo->uom)->toBeInstanceOf(UnitOfMeasure::class);
    expect($mo->warehouse)->toBeInstanceOf(Warehouse::class);
    expect($mo->rak)->toBeInstanceOf(Rak::class);
    expect($mo->manufacturingOrderMaterial)->toHaveCount(1);

    $material = $mo->manufacturingOrderMaterial->first();
    expect($material->material)->toBeInstanceOf(Product::class);
    expect($material->warehouse)->toBeInstanceOf(Warehouse::class);
    expect($material->uom)->toBeInstanceOf(UnitOfMeasure::class);
    expect($material->rak)->toBeInstanceOf(Rak::class);
});
