<?php

namespace Tests\Feature;

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
use App\Models\UnitOfMeasure;
use App\Models\WarehouseConfirmation;
use App\Models\Warehouse;
use App\Services\ManufacturingJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManufacturingJournalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        // Disable global base seeding defined in Tests\TestCase to keep this test lightweight
        \Tests\TestCase::disableBaseSeeding();

        parent::setUp();

        // Seed minimal COA accounts used by manufacturing flows
        ChartOfAccount::firstOrCreate(['code' => '1-101'],  ['name' => 'PERSEDIAAN BAHAN BAKU - RAW MATERIAL INVENTORY', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '1-201'],  ['name' => 'PERSEDIAAN BARANG DALAM PROSES - WIP INVENTORY', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '1140.01'],['name' => 'Persediaan Barang Dagangan', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '1400.04'],['name' => 'POS SEMENTARA PRODUKSI', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '1140.02'],['name' => 'Persediaan Barang Produksi', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '1150'],   ['name' => 'Barang Dalam Proses', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '5230'],   ['name' => 'BIAYA TENAGA KERJA PROSES PRODUKSI', 'type' => 'Expense', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '6100'],   ['name' => 'Beban Overhead Produksi', 'type' => 'Expense', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '6000'],   ['name' => 'Beban Produksi', 'type' => 'Expense', 'is_active' => true]);
    }

    public function test_material_issue_completed_creates_journal_entries(): void
    {
        $uom = UnitOfMeasure::factory()->create();
        $rawMaterial = Product::factory()->create([
            'is_raw_material' => true,
            'uom_id' => $uom->id,
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.01')->first()->id,
        ]);
        $warehouse = Warehouse::factory()->create();
        $user = \App\Models\User::factory()->create();

        $mo = ManufacturingOrder::factory()->create([
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

        $issue = MaterialIssue::factory()->create([
            'manufacturing_order_id' => $mo->id,
            'issue_date' => now(),
            'issue_number' => 'MI-TEST-001',
            'type' => 'issue',
            'status' => 'draft',
            'total_cost' => 0,
        ]);

        MaterialIssueItem::factory()->create([
            'material_issue_id' => $issue->id,
            'product_id' => $rawMaterial->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'cost_per_unit' => 1000,
            'total_cost' => 5000,
        ]);

        // Mark issue as completed -> triggers observer and journal creation
        $issue->update([
            'status' => MaterialIssue::STATUS_COMPLETED,
            'total_cost' => 5000,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $bdpCoa = ChartOfAccount::where('code', '1400.04')->firstOrFail();
        $bbCoa  = ChartOfAccount::where('code', '1140.01')->firstOrFail(); // explicit product COA used

        $this->assertDatabaseHas('journal_entries', [
            'coa_id'       => $bdpCoa->id,
            'reference'    => 'MI-TEST-001',
            'journal_type' => 'manufacturing_issue',
            'debit'        => 5000,
            'credit'       => 0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'coa_id'       => $bbCoa->id,
            'reference'    => 'MI-TEST-001',
            'journal_type' => 'manufacturing_issue',
            'debit'        => 0,
            'credit'       => 5000,
        ]);
    }

    public function test_material_return_completed_creates_journal_entries(): void
    {
        $uom = UnitOfMeasure::factory()->create();
        $rawMaterial = Product::factory()->create([
            'is_raw_material' => true,
            'uom_id' => $uom->id,
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.01')->first()->id,
        ]);
        $warehouse = Warehouse::factory()->create();

        $mo = ManufacturingOrder::factory()->create([
            'status' => 'in_progress',
        ]);

        $user = \App\Models\User::factory()->create();

        WarehouseConfirmation::create([
            'confirmable_type' => ManufacturingOrder::class,
            'confirmable_id' => $mo->id,
            'confirmation_type' => 'manufacturing_order',
            'status' => 'confirmed',
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        $issue = MaterialIssue::factory()->create([
            'manufacturing_order_id' => $mo->id,
            'issue_date' => now(),
            'issue_number' => 'MR-TEST-001',
            'type' => 'return',
            'status' => 'draft',
            'total_cost' => 0,
        ]);

        MaterialIssueItem::factory()->create([
            'material_issue_id' => $issue->id,
            'product_id' => $rawMaterial->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 2,
            'cost_per_unit' => 1000,
            'total_cost' => 2000,
        ]);

        // Mark return as completed -> triggers observer and journal creation
        $issue->update([
            'status' => MaterialIssue::STATUS_COMPLETED,
            'total_cost' => 2000,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $bdpCoa = ChartOfAccount::where('code', '1400.04')->firstOrFail();
        $bbCoa  = ChartOfAccount::where('code', '1140.01')->firstOrFail();

        $this->assertDatabaseHas('journal_entries', [
            'coa_id'       => $bbCoa->id,
            'reference'    => 'MR-TEST-001',
            'journal_type' => 'manufacturing_return',
            'debit'        => 2000,
            'credit'       => 0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'coa_id'       => $bdpCoa->id,
            'reference'    => 'MR-TEST-001',
            'journal_type' => 'manufacturing_return',
            'debit'        => 0,
            'credit'       => 2000,
        ]);
    }

    /**
     * Produksi In Progress journal (new flow):
     *   Dr. 1-201  Persediaan Barang Dalam Proses - WIP INVENTORY
     *       Cr. 1400.04 Pos Sementara Produksi  (material cost)
    *       Cr. 5230   Biaya Tenaga Kerja Proses Produksi  (labor)
    *       Cr. 6100   Beban Overhead Produksi             (overhead)
     */
    public function test_production_in_progress_creates_wip_journal_entries(): void
    {
        $uom       = UnitOfMeasure::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $user      = \App\Models\User::factory()->create();

        $rawMatCoa = ChartOfAccount::where('code', '1-101')->firstOrFail();
        $wipCoa    = ChartOfAccount::where('code', '1-201')->firstOrFail();
        $tempCoa   = ChartOfAccount::where('code', '1400.04')->firstOrFail();
        $laborCoa  = ChartOfAccount::where('code', '5230')->firstOrFail();
        $overheadCoa = ChartOfAccount::where('code', '6100')->firstOrFail();

        // Finished product
        $finishedProduct = Product::factory()->create([
            'is_manufacture' => true,
            'uom_id' => $uom->id,
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.02')->first()->id,
            'manufacturing_labor_coa_id' => $laborCoa->id,
            'manufacturing_overhead_coa_id' => $overheadCoa->id,
        ]);

        // Raw material product with 1-101 COA
        $rawMaterial = Product::factory()->create([
            'is_raw_material' => true,
            'uom_id' => $uom->id,
            'inventory_coa_id' => $rawMatCoa->id,
            'cost_price' => 100,
        ]);

        $bom = BillOfMaterial::factory()->create([
            'product_id'    => $finishedProduct->id,
            'is_active'     => true,
            'labor_cost'    => 500,   // per plan unit
            'overhead_cost' => 200,   // per plan unit
            'labor_coa_id'  => $laborCoa->id,
            'overhead_coa_id' => $overheadCoa->id,
        ]);

        $planQty = 4;
        $plan = ProductionPlan::factory()->create([
            'product_id'           => $finishedProduct->id,
            'bill_of_material_id'  => $bom->id,
            'quantity'             => $planQty,
            'uom_id'               => $uom->id,
        ]);

        $mo = ManufacturingOrder::factory()->create([
            'production_plan_id' => $plan->id,
            'status'             => 'in_progress',
        ]);

        // Create confirmed warehouse confirmation
        WarehouseConfirmation::create([
            'confirmable_type'  => ManufacturingOrder::class,
            'confirmable_id'    => $mo->id,
            'confirmation_type' => 'manufacturing_order',
            'status'            => 'confirmed',
            'confirmed_by'      => $user->id,
            'confirmed_at'      => now(),
        ]);

        // Post material issue (D:1400.04, K:1-101)
        $issue = MaterialIssue::factory()->create([
            'manufacturing_order_id' => $mo->id,
            'issue_date'             => now(),
            'issue_number'           => 'MI-WIP-001',
            'type'                   => 'issue',
            'status'                 => 'draft',
            'total_cost'             => 0,
        ]);
        MaterialIssueItem::factory()->create([
            'material_issue_id' => $issue->id,
            'product_id'        => $rawMaterial->id,
            'warehouse_id'      => $warehouse->id,
            'quantity'          => 8,
            'cost_per_unit'     => 100,
            'total_cost'        => 800,
        ]);
        $issue->update([
            'status'      => MaterialIssue::STATUS_COMPLETED,
            'total_cost'  => 800,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        // Now trigger production in-progress journal directly
        $production = Production::withoutEvents(fn () => Production::create([
            'manufacturing_order_id' => $mo->id,
            'production_number'      => 'PROD-WIP-001',
            'production_date'        => now(),
            'status'                 => 'draft',
            'quantity_produced'      => 4,
        ]));

        app(ManufacturingJournalService::class)->generateJournalForProductionInProgress($production->fresh());

        $laborAmount    = 500 * $planQty;         // 2000
        $overheadAmount = 200 * $planQty;         // 800
        $laborOverhead  = $laborAmount + $overheadAmount;
        $totalWip       = 800 + $laborOverhead;   // 3600

        // Dr 1-201 = totalWip
        $this->assertDatabaseHas('journal_entries', [
            'coa_id'       => $wipCoa->id,
            'reference'    => 'PROD-WIP-001',
            'journal_type' => 'manufacturing_wip',
            'debit'        => $totalWip,
            'credit'       => 0,
        ]);

        // Cr 1400.04 = material cost
        $this->assertDatabaseHas('journal_entries', [
            'coa_id'       => $tempCoa->id,
            'reference'    => 'PROD-WIP-001',
            'journal_type' => 'manufacturing_wip',
            'debit'        => 0,
            'credit'       => 800,
        ]);

        // Cr 5230 = labor
        $this->assertDatabaseHas('journal_entries', [
            'coa_id'       => $laborCoa->id,
            'reference'    => 'PROD-WIP-001',
            'journal_type' => 'manufacturing_wip',
            'debit'        => 0,
            'credit'       => $laborAmount,
        ]);

        // Cr 6100 = overhead
        $this->assertDatabaseHas('journal_entries', [
            'coa_id'       => $overheadCoa->id,
            'reference'    => 'PROD-WIP-001',
            'journal_type' => 'manufacturing_wip',
            'debit'        => 0,
            'credit'       => $overheadAmount,
        ]);

        // Ledger must balance: total debit = total credit for manufacturing_wip
        $wip_entries = JournalEntry::where('journal_type', 'manufacturing_wip')
            ->where('reference', 'PROD-WIP-001')->get();
        $this->assertEquals(
            (float) $wip_entries->sum('debit'),
            (float) $wip_entries->sum('credit')
        );
    }
}
