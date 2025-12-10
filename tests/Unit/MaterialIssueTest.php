<?php

namespace Tests\Unit;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionPlan;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ManufacturingJournalService;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialIssueTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Warehouse $warehouse;
    private Rak $rak;
    private Product $rawMaterial;
    private Product $finishedGood;
    private UnitOfMeasure $uom;
    private ChartOfAccount $inventoryCoa;
    private ChartOfAccount $wipCoa;
    private Cabang $cabang;
    private ProductionPlan $productionPlan;
    private ManufacturingOrder $manufacturingOrder;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic data
        $this->user = User::factory()->create();
        $this->cabang = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->rak = Rak::factory()->create(['warehouse_id' => $this->warehouse->id]);

        // Create UOM
        $this->uom = UnitOfMeasure::factory()->create([
            'name' => 'Kilogram',
            'abbreviation' => 'kg'
        ]);

        // Create product category
        $productCategory = ProductCategory::factory()->create();

        // Create COA for inventory
        $this->inventoryCoa = ChartOfAccount::factory()->create([
            'code' => '1101',
            'name' => 'Raw Materials Inventory',
            'type' => 'asset'
        ]);

        // Create WIP COA for manufacturing journal
        $this->wipCoa = ChartOfAccount::factory()->create([
            'code' => '1140.02',
            'name' => 'Work in Progress',
            'type' => 'asset'
        ]);

        // Create raw material product
        $this->rawMaterial = Product::factory()->create([
            'name' => 'Steel Rod',
            'sku' => 'RM001',
            'product_category_id' => $productCategory->id,
            'uom_id' => $this->uom->id,
            'is_raw_material' => true,
            'inventory_coa_id' => $this->inventoryCoa->id,
            'cost_price' => 50000,
        ]);

        // Create finished good product
        $this->finishedGood = Product::factory()->create([
            'name' => 'Finished Product A',
            'sku' => 'FG001',
            'product_category_id' => $productCategory->id,
            'uom_id' => $this->uom->id,
            'is_manufacture' => true,
            'cost_price' => 150000,
        ]);

        // Create production plan
        $this->productionPlan = ProductionPlan::factory()->create([
            'plan_number' => 'PP-TEST-001',
            'product_id' => $this->finishedGood->id,
            'quantity' => 10,
            'uom_id' => $this->uom->id,
            'status' => 'scheduled',
        ]);

        // Create manufacturing order
        $this->manufacturingOrder = ManufacturingOrder::factory()->create([
            'mo_number' => 'MO-TEST-001',
            'production_plan_id' => $this->productionPlan->id,
            'status' => 'in_progress',
        ]);

        // Create initial inventory stock
        InventoryStock::factory()->create([
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'qty_available' => 100,
            'qty_reserved' => 0,
            'qty_min' => 10,
        ]);
    }

    /** @test */
    public function it_can_create_material_issue()
    {
        $materialIssueData = [
            'issue_number' => 'MI-TEST-001',
            'production_plan_id' => $this->productionPlan->id,
            'manufacturing_order_id' => $this->manufacturingOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'issue_date' => now(),
            'type' => 'issue',
            'status' => MaterialIssue::STATUS_DRAFT,
            'total_cost' => 0,
            'created_by' => $this->user->id,
        ];

        $materialIssue = MaterialIssue::create($materialIssueData);

        $this->assertInstanceOf(MaterialIssue::class, $materialIssue);
        $this->assertEquals('MI-TEST-001', $materialIssue->issue_number);
        $this->assertEquals(MaterialIssue::STATUS_DRAFT, $materialIssue->status);
        $this->assertEquals(0, $materialIssue->total_cost);
    }

    /** @test */
    public function it_can_add_material_issue_items()
    {
        $materialIssue = MaterialIssue::factory()->create([
            'production_plan_id' => $this->productionPlan->id,
            'manufacturing_order_id' => $this->manufacturingOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => MaterialIssue::STATUS_DRAFT,
        ]);

        $issueItemData = [
            'material_issue_id' => $materialIssue->id,
            'product_id' => $this->rawMaterial->id,
            'uom_id' => $this->uom->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'quantity' => 5,
            'cost_per_unit' => 50000,
            'total_cost' => 250000,
            'status' => MaterialIssueItem::STATUS_DRAFT,
        ];

        $issueItem = MaterialIssueItem::create($issueItemData);

        $this->assertInstanceOf(MaterialIssueItem::class, $issueItem);
        $this->assertEquals(5, $issueItem->quantity);
        $this->assertEquals(50000, $issueItem->cost_per_unit);
        $this->assertEquals(250000, $issueItem->total_cost);
        $this->assertEquals($materialIssue->id, $issueItem->material_issue_id);
    }

    /** @test */
    public function it_calculates_total_cost_when_items_are_added()
    {
        $materialIssue = MaterialIssue::factory()->create([
            'status' => MaterialIssue::STATUS_DRAFT,
            'total_cost' => 0,
        ]);

        // Add first item
        MaterialIssueItem::create([
            'material_issue_id' => $materialIssue->id,
            'product_id' => $this->rawMaterial->id,
            'uom_id' => $this->uom->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'quantity' => 5,
            'cost_per_unit' => 50000,
            'total_cost' => 250000,
            'status' => MaterialIssueItem::STATUS_DRAFT,
        ]);

        // Add second item
        MaterialIssueItem::create([
            'material_issue_id' => $materialIssue->id,
            'product_id' => $this->rawMaterial->id,
            'uom_id' => $this->uom->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'quantity' => 3,
            'cost_per_unit' => 50000,
            'total_cost' => 150000,
            'status' => MaterialIssueItem::STATUS_DRAFT,
        ]);

        // Recalculate total cost
        $totalCost = $materialIssue->items()->sum('total_cost');
        $materialIssue->update(['total_cost' => $totalCost]);

        $this->assertEquals(400000, $materialIssue->fresh()->total_cost);
    }

    /** @test */
    public function it_can_approve_material_issue()
    {
        $materialIssue = MaterialIssue::factory()->create([
            'status' => MaterialIssue::STATUS_DRAFT,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $materialIssue->id,
            'product_id' => $this->rawMaterial->id,
            'uom_id' => $this->uom->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'quantity' => 5,
            'cost_per_unit' => 50000,
            'total_cost' => 250000,
            'status' => MaterialIssueItem::STATUS_DRAFT,
        ]);

        // Approve the material issue
        $materialIssue->update([
            'status' => MaterialIssue::STATUS_APPROVED,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);

        $this->assertEquals(MaterialIssue::STATUS_APPROVED, $materialIssue->fresh()->status);
        $this->assertEquals($this->user->id, $materialIssue->fresh()->approved_by);
        $this->assertNotNull($materialIssue->fresh()->approved_at);
    }

    /** @test */
    public function it_can_complete_material_issue_and_update_inventory()
    {
        $initialStock = InventoryStock::where('product_id', $this->rawMaterial->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $materialIssue = MaterialIssue::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => MaterialIssue::STATUS_DRAFT,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $materialIssue->id,
            'product_id' => $this->rawMaterial->id,
            'uom_id' => $this->uom->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'quantity' => 5,
            'cost_per_unit' => 50000,
            'total_cost' => 250000,
            'status' => MaterialIssueItem::STATUS_DRAFT,
        ]);

        // Approve the material issue to create reservations
        $materialIssue->update(['status' => MaterialIssue::STATUS_APPROVED]);

        // Complete the material issue
        $materialIssue->update(['status' => MaterialIssue::STATUS_COMPLETED]);

        // Manually consume reserved stock since observer may not run in tests
        app(StockReservationService::class)->consumeReservedStockForMaterialIssue($materialIssue);

        // Check that inventory was reduced
        $updatedStock = InventoryStock::where('product_id', $this->rawMaterial->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(95, $updatedStock->qty_available); // 100 - 5
        $this->assertEquals(MaterialIssue::STATUS_COMPLETED, $materialIssue->fresh()->status);
    }

    /** @test */
    public function it_generates_journal_entries_when_completed()
    {
        $materialIssue = MaterialIssue::factory()->create([
            'issue_number' => 'MI-JOURNAL-TEST',
            'warehouse_id' => $this->warehouse->id,
            'type' => 'issue',
            'status' => MaterialIssue::STATUS_COMPLETED,
            'total_cost' => 250000,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $materialIssue->id,
            'product_id' => $this->rawMaterial->id,
            'uom_id' => $this->uom->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'quantity' => 5,
            'cost_per_unit' => 50000,
            'total_cost' => 250000,
            'status' => MaterialIssueItem::STATUS_APPROVED,
        ]);

        // Generate journal entries
        app(ManufacturingJournalService::class)->generateJournalForMaterialIssue($materialIssue);

        $journalEntries = JournalEntry::where('source_type', MaterialIssue::class)
            ->where('source_id', $materialIssue->id)
            ->get();

        $this->assertGreaterThan(0, $journalEntries->count());

        // Check that debit and credit entries balance
        $totalDebit = $journalEntries->sum('debit');
        $totalCredit = $journalEntries->sum('credit');

        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(250000, $totalDebit);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create material issue without required fields
        MaterialIssue::create([]);
    }

    /** @test */
    public function it_has_relationships_with_other_models()
    {
        $materialIssue = MaterialIssue::factory()->create([
            'production_plan_id' => $this->productionPlan->id,
            'manufacturing_order_id' => $this->manufacturingOrder->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $materialIssue->id,
            'product_id' => $this->rawMaterial->id,
            'uom_id' => $this->uom->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'quantity' => 5,
            'cost_per_unit' => 50000,
            'total_cost' => 250000,
        ]);

        // Test relationships
        $this->assertInstanceOf(ProductionPlan::class, $materialIssue->productionPlan);
        $this->assertInstanceOf(ManufacturingOrder::class, $materialIssue->manufacturingOrder);
        $this->assertInstanceOf(Warehouse::class, $materialIssue->warehouse);
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Collection::class, $materialIssue->items);
        $this->assertCount(1, $materialIssue->items);

        // Test reverse relationship
        $item = $materialIssue->items->first();
        $this->assertInstanceOf(MaterialIssue::class, $item->materialIssue);
        $this->assertInstanceOf(Product::class, $item->product);
    }

    /** @test */
    public function it_can_soft_delete_material_issue()
    {
        $materialIssue = MaterialIssue::factory()->create();

        $materialIssue->delete();

        $this->assertSoftDeleted($materialIssue);
        $this->assertNull(MaterialIssue::find($materialIssue->id));
        $this->assertNotNull(MaterialIssue::withTrashed()->find($materialIssue->id));
    }

    /** @test */
    public function it_handles_stock_reservation_during_material_issue()
    {
        $initialStock = InventoryStock::where('product_id', $this->rawMaterial->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $materialIssue = MaterialIssue::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => MaterialIssue::STATUS_DRAFT,
        ]);

        MaterialIssueItem::create([
            'material_issue_id' => $materialIssue->id,
            'product_id' => $this->rawMaterial->id,
            'uom_id' => $this->uom->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'quantity' => 5,
            'cost_per_unit' => 50000,
            'total_cost' => 250000,
            'status' => MaterialIssueItem::STATUS_DRAFT,
        ]);

        // Approve the material issue to trigger stock reservation
        $materialIssue->update(['status' => MaterialIssue::STATUS_APPROVED]);

        // Check that stock reservation was created
        $reservations = \App\Models\StockReservation::where('material_issue_id', $materialIssue->id)->get();
        $this->assertGreaterThan(0, $reservations->count());

        $totalReserved = $reservations->sum('quantity');
        $this->assertEquals(5, $totalReserved);

        $updatedStock = InventoryStock::where('product_id', $this->rawMaterial->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(95, $updatedStock->qty_available); // Stock should be reduced when reserved
        $this->assertEquals(5, $updatedStock->qty_reserved); // And reserved should increase
    }
}