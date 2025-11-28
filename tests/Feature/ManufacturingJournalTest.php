<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
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
        ChartOfAccount::firstOrCreate(['code' => '1140.01'], ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '1140.02'], ['name' => 'Persediaan Barang Dalam Proses', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '1140.03'], ['name' => 'Persediaan Barang Jadi', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '1150'], ['name' => 'Barang Dalam Proses', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '6000'], ['name' => 'Beban Produksi', 'type' => 'Expense', 'is_active' => true]);
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
            'product_id' => Product::factory()->create(['uom_id' => $uom->id])->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 1,
            'status' => 'in_progress',
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
            'approved_by' => $user->id, // Use created user
            'approved_at' => now(), // Set approved_at
        ]);

        $bdpCoa = ChartOfAccount::where('code', '1150')->firstOrFail();
        $bbCoa = ChartOfAccount::where('code', '1140.01')->firstOrFail();

        $this->assertDatabaseHas('journal_entries', [
            'coa_id' => $bdpCoa->id,
            'reference' => 'MI-TEST-001',
            'journal_type' => 'manufacturing_issue',
            'debit' => 5000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'coa_id' => $bbCoa->id,
            'reference' => 'MI-TEST-001',
            'journal_type' => 'manufacturing_issue',
            'debit' => 0,
            'credit' => 5000,
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
            'product_id' => Product::factory()->create(['uom_id' => $uom->id])->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 1,
            'status' => 'in_progress',
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
        $user = \App\Models\User::factory()->create();
        $issue->update([
            'status' => MaterialIssue::STATUS_COMPLETED,
            'total_cost' => 2000,
            'approved_by' => $user->id,
            'approved_at' => now(),
        ]);

        $bdpCoa = ChartOfAccount::where('code', '1140.02')->firstOrFail();
        $bbCoa = ChartOfAccount::where('code', '1140.01')->firstOrFail();

        $this->assertDatabaseHas('journal_entries', [
            'coa_id' => $bbCoa->id,
            'reference' => 'MR-TEST-001',
            'journal_type' => 'manufacturing_return',
            'debit' => 2000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'coa_id' => $bdpCoa->id,
            'reference' => 'MR-TEST-001',
            'journal_type' => 'manufacturing_return',
            'debit' => 0,
            'credit' => 2000,
        ]);
    }
}
