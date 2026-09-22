<?php

use App\Models\BillOfMaterial;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Services\ManufacturingService;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();

    ChartOfAccount::firstOrCreate(['code' => '1-101'], ['name' => 'PERSEDIAAN BAHAN BAKU - RAW MATERIAL INVENTORY', 'type' => 'Asset', 'is_active' => true]);
    ChartOfAccount::firstOrCreate(['code' => '1-201'], ['name' => 'PERSEDIAAN BARANG DALAM PROSES - WIP INVENTORY', 'type' => 'Asset', 'is_active' => true]);
    ChartOfAccount::firstOrCreate(['code' => '1140.02'], ['name' => 'Persediaan Barang Produksi', 'type' => 'Asset', 'is_active' => true]);
    ChartOfAccount::firstOrCreate(['code' => '1400.04'], ['name' => 'POS SEMENTARA PRODUKSI', 'type' => 'Asset', 'is_active' => true]);
    ChartOfAccount::firstOrCreate(['code' => '5230'], ['name' => 'BIAYA TENAGA KERJA PROSES PRODUKSI', 'type' => 'Expense', 'is_active' => true]);
    ChartOfAccount::firstOrCreate(['code' => '6100'], ['name' => 'Beban Overhead Produksi', 'type' => 'Expense', 'is_active' => true]);
});

function buildLateIssueFixture(string $productionNumber): array
{
    $branch = Cabang::factory()->create();
    $uom = UnitOfMeasure::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
    $user = User::factory()->create(['cabang_id' => $branch->id]);

    $rawMaterialCoa = ChartOfAccount::where('code', '1-101')->firstOrFail();
    $wipCoa = ChartOfAccount::where('code', '1-201')->firstOrFail();
    $tempCoa = ChartOfAccount::where('code', '1400.04')->firstOrFail();
    $laborCoa = ChartOfAccount::where('code', '5230')->firstOrFail();
    $overheadCoa = ChartOfAccount::where('code', '6100')->firstOrFail();
    $finishedGoodsCoa = ChartOfAccount::where('code', '1140.02')->firstOrFail();

    $finishedProduct = Product::factory()->create([
        'is_manufacture' => true,
        'uom_id' => $uom->id,
        'inventory_coa_id' => $finishedGoodsCoa->id,
        'manufacturing_labor_coa_id' => $laborCoa->id,
        'manufacturing_overhead_coa_id' => $overheadCoa->id,
        'cabang_id' => $branch->id,
    ]);

    $rawMaterial = Product::factory()->create([
        'is_raw_material' => true,
        'uom_id' => $uom->id,
        'inventory_coa_id' => $rawMaterialCoa->id,
        'cost_price' => 100,
        'cabang_id' => $branch->id,
    ]);

    $bom = BillOfMaterial::factory()->create([
        'cabang_id' => $branch->id,
        'product_id' => $finishedProduct->id,
        'is_active' => true,
        'labor_cost' => 50,
        'overhead_cost' => 25,
        'labor_coa_id' => $laborCoa->id,
        'overhead_coa_id' => $overheadCoa->id,
    ]);

    $plan = ProductionPlan::factory()->create([
        'cabang_id' => $branch->id,
        'product_id' => $finishedProduct->id,
        'bill_of_material_id' => $bom->id,
        'quantity' => 4,
        'uom_id' => $uom->id,
        'warehouse_id' => $warehouse->id,
    ]);

    $mo = ManufacturingOrder::factory()->create([
        'cabang_id' => $branch->id,
        'production_plan_id' => $plan->id,
        'status' => 'in_progress',
    ]);

    WarehouseConfirmation::create([
        'confirmable_type' => ManufacturingOrder::class,
        'confirmable_id' => $mo->id,
        'confirmation_type' => 'manufacturing_order',
        'status' => 'confirmed',
        'confirmed_by' => $user->id,
        'confirmed_at' => now(),
    ]);

    $production = Production::create([
        'cabang_id' => $branch->id,
        'manufacturing_order_id' => $mo->id,
        'production_number' => $productionNumber,
        'production_date' => now(),
        'status' => 'draft',
        'quantity_produced' => 4,
        'warehouse_id' => $warehouse->id,
    ]);

    $issue = MaterialIssue::factory()->create([
        'manufacturing_order_id' => $mo->id,
        'issue_date' => now(),
        'issue_number' => 'MI-' . $productionNumber,
        'type' => 'issue',
        'status' => 'draft',
        'total_cost' => 0,
    ]);

    MaterialIssueItem::factory()->create([
        'material_issue_id' => $issue->id,
        'product_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 8,
        'cost_per_unit' => 100,
        'total_cost' => 800,
    ]);

    return compact(
        'warehouse',
        'user',
        'wipCoa',
        'tempCoa',
        'finishedGoodsCoa',
        'finishedProduct',
        'production',
        'issue'
    );
}

function confirmAndCompleteIssue(MaterialIssue $issue, User $user): void
{
    app(ManufacturingService::class)->createWarehouseConfirmationForMaterialIssue($issue);

    WarehouseConfirmation::query()
        ->where('confirmable_type', MaterialIssue::class)
        ->where('confirmable_id', $issue->id)
        ->with('warehouseConfirmationItems')
        ->get()
        ->each(function (WarehouseConfirmation $confirmation) {
            $item = $confirmation->warehouseConfirmationItems->sole();

            $item->update([
                'status' => 'confirmed',
                'confirmed_qty' => $item->requested_qty,
            ]);
        });

    $issue->refresh();

    expect($issue->status)->toBe(MaterialIssue::STATUS_COMPLETED);

    if ($issue->status !== MaterialIssue::STATUS_COMPLETED) {
        $issue->update([
            'status' => MaterialIssue::STATUS_COMPLETED,
            'approved_by' => $issue->approved_by ?? $user->id,
            'approved_at' => $issue->approved_at ?? now(),
        ]);
    }
}

test('completed material issue refreshes existing wip journal', function () {
    $fixture = buildLateIssueFixture('PROD-WIP-LATE-001');

    expect((float) JournalEntry::where('reference', 'PROD-WIP-LATE-001')->where('journal_type', 'manufacturing_wip')->sum('debit'))
        ->toBe(300.0);

    confirmAndCompleteIssue($fixture['issue'], $fixture['user']);

    $entries = JournalEntry::where('reference', 'PROD-WIP-LATE-001')
        ->where('journal_type', 'manufacturing_wip')
        ->get();

    expect($entries)->toHaveCount(3)
        ->and((float) $entries->sum('debit'))->toBe(300.0)
        ->and((float) $entries->sum('credit'))->toBe(300.0)
        ->and($entries->contains(fn ($entry) => (int) $entry->coa_id === (int) $fixture['wipCoa']->id && (float) $entry->debit === 300.0))->toBeTrue();
});

test('qc completion uses refreshed wip when material issue completes after production creation', function () {
    $fixture = buildLateIssueFixture('PROD-WIP-LATE-002');

    $fixture['production']->update(['status' => 'finished']);

    confirmAndCompleteIssue($fixture['issue'], $fixture['user']);

    $qc = QualityControl::create([
        'qc_number' => 'QC-WIP-LATE-001',
        'passed_quantity' => 2,
        'rejected_quantity' => 0,
        'status' => 0,
        'inspected_by' => $fixture['user']->id,
        'warehouse_id' => $fixture['warehouse']->id,
        'product_id' => $fixture['finishedProduct']->id,
        'from_model_type' => Production::class,
        'from_model_id' => $fixture['production']->id,
    ]);

    app(QualityControlService::class)->completeQualityControl($qc, [
        'warehouse_id' => $fixture['warehouse']->id,
        'rak_id' => null,
    ]);

    $completionEntries = JournalEntry::where('reference', 'PROD-WIP-LATE-002')
        ->where('journal_type', 'manufacturing_completion')
        ->get();

    expect($completionEntries)->toHaveCount(2)
        ->and((float) $completionEntries->sum('debit'))->toBe(150.0)
        ->and((float) $completionEntries->sum('credit'))->toBe(150.0)
        ->and($completionEntries->contains(fn ($entry) => (int) $entry->coa_id === (int) $fixture['finishedGoodsCoa']->id && (float) $entry->debit === 150.0))->toBeTrue()
        ->and($completionEntries->contains(fn ($entry) => (int) $entry->coa_id === (int) $fixture['wipCoa']->id && (float) $entry->credit === 150.0))->toBeTrue();
});