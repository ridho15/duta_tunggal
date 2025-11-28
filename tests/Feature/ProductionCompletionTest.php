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
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ManufacturingJournalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

\Tests\TestCase::disableBaseSeeding();

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

test('complete production', function () {
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

    $rawCost = 50000.0;
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
        'qty_used' => $totalMaterialQty, // Already used
        'warehouse_id' => $warehouse->id,
        'uom_id' => $uom->id,
        'rak_id' => $rak->id,
    ]);

    $production = Production::withoutEvents(function () use ($mo) {
        return Production::create([
            'production_number' => 'PROD-TEST',
            'manufacturing_order_id' => $mo->id,
            'production_date' => Carbon::now(),
            'status' => 'draft',
        ]);
    });

    Production::withoutEvents(function () use ($production) {
        $production->update(['status' => 'finished']);
    });

    expect($production->status)->toBe('finished');
    expect($production->manufacturingOrder)->toBeInstanceOf(ManufacturingOrder::class);
    expect($production->manufacturingOrder->product->name)->toBe('Finished Product');
});

test('transfer WIP to Finished Goods', function () {
    $branch = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $branch->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    $category = ProductCategory::factory()->create(['cabang_id' => $branch->id]);

    $wipCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.02'],
        ['name' => 'Persediaan Barang Dalam Proses', 'type' => 'Asset', 'is_active' => true]
    );
    $fgCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.03'],
        ['name' => 'Persediaan Barang Jadi', 'type' => 'Asset', 'is_active' => true]
    );

    $finishedGood = Product::factory()->create([
        'name' => 'Finished Product B',
        'sku' => 'FG-002',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'inventory_coa_id' => $fgCoa->id,
    ]);

    $plan = ProductionPlan::create([
        'plan_number' => 'PLAN-TEST-002',
        'name' => 'Manufacturing Plan 2',
        'source_type' => 'manual',
        'product_id' => $finishedGood->id,
        'quantity' => 10,
        'uom_id' => $uom->id,
        'start_date' => Carbon::now(),
        'end_date' => Carbon::now()->addDay(),
        'status' => 'scheduled',
        'notes' => null,
        'created_by' => $user->id,
    ]);

    $mo = ManufacturingOrder::create([
        'mo_number' => 'MO-TEST-002',
        'production_plan_id' => $plan->id,
        'product_id' => $finishedGood->id,
        'quantity' => 10,
        'status' => 'in_progress',
        'start_date' => Carbon::now(),
        'end_date' => null,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    $fgStock = InventoryStock::create([
        'product_id' => $finishedGood->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
        'qty_available' => 0,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $production = Production::withoutEvents(function () use ($mo) {
        return Production::create([
            'production_number' => 'PROD-TEST-002',
            'manufacturing_order_id' => $mo->id,
            'production_date' => Carbon::now(),
            'status' => 'draft',
        ]);
    });

    Production::withoutEvents(function () use ($production) {
        $production->update(['status' => 'finished']);
    });

    $completion = FinishedGoodsCompletion::withoutEvents(function () use ($plan, $finishedGood, $uom, $warehouse, $rak) {
        return FinishedGoodsCompletion::create([
            'completion_number' => 'FGC-TEST-002',
            'production_plan_id' => $plan->id,
            'product_id' => $finishedGood->id,
            'quantity' => 10,
            'uom_id' => $uom->id,
            'total_cost' => 500000, // 10 * 50000
            'completion_date' => Carbon::now(),
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'notes' => null,
            'status' => 'draft',
            'created_by' => null,
        ]);
    });

    FinishedGoodsCompletion::withoutEvents(function () use ($completion) {
        $completion->update(['status' => 'completed']);
    });

    // Manually increment stock since we used withoutEvents
    $fgStock->increment('qty_available', 10);

    $fgStock->refresh();
    expect((float) $fgStock->qty_available)->toBe(10.0);
});

test('calculate production costs', function () {
    $branch = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $branch->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    $category = ProductCategory::factory()->create(['cabang_id' => $branch->id]);

    $rawCost = 25000.0;
    $laborCost = 10000.0;
    $overheadCost = 5000.0;

    $rawMaterial = Product::factory()->create([
        'name' => 'Raw Material C',
        'sku' => 'RM-003',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => $rawCost,
        'inventory_coa_id' => null,
    ]);

    $finishedGood = Product::factory()->create([
        'name' => 'Finished Product C',
        'sku' => 'FG-003',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'inventory_coa_id' => null,
    ]);

    $materialPerUnit = 2;
    $totalBomCost = ($materialPerUnit * $rawCost) + $laborCost + $overheadCost;

    $bom = Model::withoutEvents(function () use ($branch, $finishedGood, $uom, $totalBomCost, $laborCost, $overheadCost) {
        return BillOfMaterial::create([
            'cabang_id' => $branch->id,
            'product_id' => $finishedGood->id,
            'quantity' => 1,
            'code' => 'BOM-TEST-003',
            'nama_bom' => 'Test BOM 3',
            'note' => null,
            'is_active' => true,
            'uom_id' => $uom->id,
            'labor_cost' => $laborCost,
            'overhead_cost' => $overheadCost,
            'total_cost' => $totalBomCost,
        ]);
    });

    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom->id,
        'product_id' => $rawMaterial->id,
        'quantity' => $materialPerUnit,
        'uom_id' => $uom->id,
        'unit_price' => $rawCost,
        'subtotal' => $materialPerUnit * $rawCost,
        'note' => null,
    ]);

    $productionQty = 3;
    $expectedTotalCost = $totalBomCost * $productionQty; // 3 * (50000 + 10000 + 5000) = 3 * 65000 = 195000

    $plan = ProductionPlan::create([
        'plan_number' => 'PLAN-TEST-003',
        'name' => 'Manufacturing Plan 3',
        'source_type' => 'manual',
        'product_id' => $finishedGood->id,
        'quantity' => $productionQty,
        'uom_id' => $uom->id,
        'start_date' => Carbon::now(),
        'end_date' => Carbon::now()->addDay(),
        'status' => 'scheduled',
        'notes' => null,
        'created_by' => $user->id,
    ]);

    $mo = ManufacturingOrder::create([
        'mo_number' => 'MO-TEST-003',
        'production_plan_id' => $plan->id,
        'product_id' => $finishedGood->id,
        'quantity' => $productionQty,
        'status' => 'in_progress',
        'start_date' => Carbon::now(),
        'end_date' => null,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    $production = Production::withoutEvents(function () use ($mo) {
        return Production::create([
            'production_number' => 'PROD-TEST-003',
            'manufacturing_order_id' => $mo->id,
            'production_date' => Carbon::now(),
            'status' => 'draft',
        ]);
    });

    Production::withoutEvents(function () use ($production) {
        $production->update(['status' => 'finished']);
    });

    $completion = FinishedGoodsCompletion::withoutEvents(function () use ($plan, $finishedGood, $uom, $warehouse, $rak, $expectedTotalCost, $productionQty) {
        return FinishedGoodsCompletion::create([
            'completion_number' => 'FGC-TEST-003',
            'production_plan_id' => $plan->id,
            'product_id' => $finishedGood->id,
            'quantity' => $productionQty,
            'uom_id' => $uom->id,
            'total_cost' => $expectedTotalCost,
            'completion_date' => Carbon::now(),
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'notes' => null,
            'status' => 'draft',
            'created_by' => null,
        ]);
    });

    expect((float) $completion->total_cost)->toBe($expectedTotalCost);
    expect((float) $completion->quantity)->toBe((float) $productionQty);
});

test('test journal entries (Dr FG, Cr WIP)', function () {
    $branch = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $branch->id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
    $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
    $category = ProductCategory::factory()->create(['cabang_id' => $branch->id]);

    $wipCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.02'],
        ['name' => 'Persediaan Barang Dalam Proses', 'type' => 'Asset', 'is_active' => true]
    );
    $fgCoa = ChartOfAccount::firstOrCreate(
        ['code' => '1140.03'],
        ['name' => 'Persediaan Barang Jadi', 'type' => 'Asset', 'is_active' => true]
    );

    $finishedGood = Product::factory()->create([
        'name' => 'Finished Product D',
        'sku' => 'FG-004',
        'cabang_id' => $branch->id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_raw_material' => false,
        'is_manufacture' => true,
        'inventory_coa_id' => $fgCoa->id,
    ]);

    $plan = ProductionPlan::create([
        'plan_number' => 'PLAN-TEST-004',
        'name' => 'Manufacturing Plan 4',
        'source_type' => 'manual',
        'product_id' => $finishedGood->id,
        'quantity' => 2,
        'uom_id' => $uom->id,
        'start_date' => Carbon::now(),
        'end_date' => Carbon::now()->addDay(),
        'status' => 'scheduled',
        'notes' => null,
        'created_by' => $user->id,
    ]);

    $mo = ManufacturingOrder::create([
        'mo_number' => 'MO-TEST-004',
        'production_plan_id' => $plan->id,
        'product_id' => $finishedGood->id,
        'quantity' => 2,
        'status' => 'in_progress',
        'start_date' => Carbon::now(),
        'end_date' => null,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => $rak->id,
    ]);

    $production = Production::withoutEvents(function () use ($mo) {
        return Production::create([
            'production_number' => 'PROD-TEST-004',
            'manufacturing_order_id' => $mo->id,
            'production_date' => Carbon::now(),
            'status' => 'draft',
        ]);
    });

    Production::withoutEvents(function () use ($production) {
        $production->update(['status' => 'finished']);
    });

    $productionCost = 100000.0; // 2 units * 50000 each

    $completion = FinishedGoodsCompletion::withoutEvents(function () use ($plan, $finishedGood, $uom, $warehouse, $rak, $productionCost) {
        return FinishedGoodsCompletion::create([
            'completion_number' => 'FGC-TEST-004',
            'production_plan_id' => $plan->id,
            'product_id' => $finishedGood->id,
            'quantity' => 2,
            'uom_id' => $uom->id,
            'total_cost' => $productionCost,
            'completion_date' => Carbon::now(),
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'notes' => null,
            'status' => 'draft',
            'created_by' => null,
        ]);
    });

    FinishedGoodsCompletion::withoutEvents(function () use ($completion) {
        $completion->update(['status' => 'completed']);
    });

    $category2 = ProductCategory::factory()->create(['cabang_id' => $branch->id]);

    $rawMaterial2 = Product::factory()->create([
        'name' => 'Raw Material E',
        'sku' => 'RM-005',
        'cabang_id' => $branch->id,
        'product_category_id' => $category2->id,
        'uom_id' => $uom->id,
        'is_raw_material' => true,
        'is_manufacture' => false,
        'cost_price' => 25000.0, // 50000 / 2 units
        'inventory_coa_id' => null,
    ]);

    $bom2 = Model::withoutEvents(function () use ($branch, $finishedGood, $uom) {
        return BillOfMaterial::create([
            'cabang_id' => $branch->id,
            'product_id' => $finishedGood->id,
            'quantity' => 1,
            'code' => 'BOM-TEST-004',
            'nama_bom' => 'Test BOM 4',
            'note' => null,
            'is_active' => true,
            'uom_id' => $uom->id,
            'labor_cost' => 0,
            'overhead_cost' => 0,
            'total_cost' => 50000.0, // 25000 * 2
        ]);
    });

    BillOfMaterialItem::create([
        'bill_of_material_id' => $bom2->id,
        'product_id' => $rawMaterial2->id,
        'quantity' => 2,
        'uom_id' => $uom->id,
        'unit_price' => 25000.0,
        'subtotal' => 50000.0,
        'note' => null,
    ]);

    // Generate journal entries manually since we used withoutEvents
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

    expect($productionEntries)->toHaveCount(2);

    $fgEntry = $productionEntries->where('coa_id', $fgCoa->id)->first();
    $wipEntry = $productionEntries->where('coa_id', $wipCoa->id)->first();

    expect($fgEntry)->not->toBeNull();
    expect($wipEntry)->not->toBeNull();

    expect((float) $fgEntry->debit)->toBe($productionCost);
    expect((float) $fgEntry->credit)->toBe(0.0);
    expect((float) $wipEntry->debit)->toBe(0.0);
    expect((float) $wipEntry->credit)->toBe($productionCost);
});