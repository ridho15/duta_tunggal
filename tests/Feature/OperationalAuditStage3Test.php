<?php

namespace Tests\Feature;

use App\Models\BillOfMaterial;
use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\Production;
use App\Models\ProductionPlan;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OperationalAuditStage3Test extends TestCase
{
    use DatabaseTransactions;

    protected User $adminUser;
    protected Warehouse $warehouse1;
    protected Warehouse $warehouse2;
    protected Product $rawMaterial;
    protected Product $finishGood;
    protected UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $cabang = Cabang::first() ?? Cabang::factory()->create();

        $this->adminUser = User::factory()->create([
            'cabang_id' => $cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->adminUser->assignRole('Super Admin');

        $this->warehouse1 = Warehouse::factory()->create([
            'name' => 'Gudang Utama Audit 3 ' . uniqid(),
            'status' => 1,
            'cabang_id' => $cabang->id,
        ]);

        $this->warehouse2 = Warehouse::factory()->create([
            'name' => 'Gudang Cabang Audit 3 ' . uniqid(),
            'status' => 1,
            'cabang_id' => $cabang->id,
        ]);

        $this->uom = UnitOfMeasure::first() ?? UnitOfMeasure::create([
            'name' => 'Pieces',
            'abbreviation' => 'PCS',
        ]);

        $this->rawMaterial = Product::factory()->create([
            'sku' => 'MAT-TEST-001',
            'name' => 'Bahan Baku Gandum Test',
            'cabang_id' => $cabang->id,
            'uom_id' => $this->uom->id,
            'cost_price' => 50000,
        ]);

        $this->finishGood = Product::factory()->create([
            'sku' => 'FG-TEST-001',
            'name' => 'Barang Jadi Roti Test',
            'cabang_id' => $cabang->id,
            'uom_id' => $this->uom->id,
            'cost_price' => 100000,
        ]);
    }

    public function test_super_admin_can_force_cancel_spk_in_progress_and_clean_up_raw_materials(): void
    {
        // 1. Setup SPK with BOM
        $bom = BillOfMaterial::create([
            'code' => 'BOM-TEST-01',
            'nama_bom' => 'Resep Roti Test',
            'product_id' => $this->finishGood->id,
            'quantity' => 10,
            'uom_id' => $this->uom->id,
            'is_active' => true,
            'cabang_id' => $this->adminUser->cabang_id,
        ]);

        $plan = ProductionPlan::create([
            'plan_number' => 'PLN-TEST-01',
            'name' => 'Rencana Roti Test',
            'bill_of_material_id' => $bom->id,
            'product_id' => $this->finishGood->id,
            'quantity' => 10,
            'uom_id' => $this->uom->id,
            'warehouse_id' => $this->warehouse1->id,
            'cabang_id' => $this->adminUser->cabang_id,
            'status' => 'in_progress',
            'created_by' => $this->adminUser->id,
        ]);

        $spk = ManufacturingOrder::create([
            'mo_number' => 'MO-TEST-001',
            'production_plan_id' => $plan->id,
            'status' => 'in_progress',
            'cabang_id' => $this->adminUser->cabang_id,
        ]);

        // Add initial stock for raw material
        $stock = InventoryStock::firstOrCreate(
            ['product_id' => $this->rawMaterial->id, 'warehouse_id' => $this->warehouse1->id],
            ['qty_available' => 50, 'qty_reserved' => 0]
        );
        $stock->update(['qty_available' => 50]);

        // Create completed Material Issue that deducted 20 pcs
        $mi = MaterialIssue::create([
            'issue_number' => 'MI-TEST-001',
            'issue_date' => now(),
            'manufacturing_order_id' => $spk->id,
            'production_plan_id' => $plan->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => 'issue',
            'status' => 'completed',
            'total_cost' => 100000,
            'created_by' => $this->adminUser->id,
        ]);

        StockMovement::create([
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 20,
            'type' => 'manufacture_out',
            'from_model_type' => MaterialIssue::class,
            'from_model_id' => $mi->id,
            'reference_id' => $mi->issue_number,
        ]);

        // Stock decreased by 20 -> available is 30
        $this->assertEquals(30.0, (float) $stock->fresh()->qty_available);

        // Perform force cancel as Super Admin
        $response = $this->actingAs($this->adminUser)->post(route('manufaktur.spk.cancel', $spk), [
            'cancel_reason' => 'Kerusakan mesin total di pabrik',
            'force_cancel' => '1',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('cancelled', $spk->fresh()->status);
        $this->assertEquals('cancelled', $plan->fresh()->status);
        $this->assertEquals('cancelled', $mi->fresh()->status);

        // Stock movement deleted -> available restored to 50
        $this->assertEquals(50.0, (float) $stock->fresh()->qty_available);
    }

    public function test_material_issue_can_be_cancelled_when_pending_approval(): void
    {
        $mi = MaterialIssue::create([
            'issue_number' => 'MI-TEST-CANCEL-01',
            'issue_date' => now(),
            'warehouse_id' => $this->warehouse1->id,
            'type' => 'issue',
            'status' => 'pending_approval',
            'total_cost' => 50000,
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('manufaktur.material-issue.cancel', $mi), [
            'cancel_reason' => 'Salah alokasi gudang bahan baku',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(MaterialIssue::STATUS_CANCELLED, $mi->fresh()->status);
    }

    public function test_super_admin_can_void_completed_material_issue(): void
    {
        $stock = InventoryStock::firstOrCreate(
            ['product_id' => $this->rawMaterial->id, 'warehouse_id' => $this->warehouse1->id],
            ['qty_available' => 100, 'qty_reserved' => 0]
        );
        $stock->update(['qty_available' => 100]);

        $mi = MaterialIssue::create([
            'issue_number' => 'MI-TEST-VOID-01',
            'issue_date' => now(),
            'warehouse_id' => $this->warehouse1->id,
            'type' => 'issue',
            'status' => 'completed',
            'total_cost' => 200000,
            'created_by' => $this->adminUser->id,
        ]);

        StockMovement::create([
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse1->id,
            'quantity' => 15,
            'type' => 'manufacture_out',
            'from_model_type' => MaterialIssue::class,
            'from_model_id' => $mi->id,
            'reference_id' => $mi->issue_number,
        ]);

        $this->assertEquals(85.0, (float) $stock->fresh()->qty_available);

        $response = $this->actingAs($this->adminUser)->post(route('manufaktur.material-issue.void', $mi), [
            'cancel_reason' => 'Salah ambil batch produksi',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals(MaterialIssue::STATUS_CANCELLED, $mi->fresh()->status);
        $this->assertEquals(100.0, (float) $stock->fresh()->qty_available);
    }

    public function test_super_admin_can_void_approved_stock_transfer(): void
    {
        // Setup initial stocks
        $stockOrigin = InventoryStock::firstOrCreate(
            ['product_id' => $this->rawMaterial->id, 'warehouse_id' => $this->warehouse1->id],
            ['qty_available' => 50, 'qty_reserved' => 0]
        );
        $stockOrigin->update(['qty_available' => 50]);

        $stockDest = InventoryStock::firstOrCreate(
            ['product_id' => $this->rawMaterial->id, 'warehouse_id' => $this->warehouse2->id],
            ['qty_available' => 0, 'qty_reserved' => 0]
        );
        $stockDest->update(['qty_available' => 0]);

        $transfer = StockTransfer::create([
            'transfer_number' => 'TR-TEST-001',
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'transfer_date' => now(),
            'status' => 'Request',
        ]);

        $item = StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->rawMaterial->id,
            'quantity' => 20,
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
        ]);

        // Approve transfer
        $service = app(StockTransferService::class);
        $service->approveStockTransfer($transfer);

        $this->assertEquals('Approved', $transfer->fresh()->status);
        $this->assertEquals(30.0, (float) $stockOrigin->fresh()->qty_available);
        $this->assertEquals(20.0, (float) $stockDest->fresh()->qty_available);

        // Void the approved transfer
        $response = $this->actingAs($this->adminUser)->post(route('gudang.transfer.void', $transfer), [
            'cancel_reason' => 'Truk angkut mengalami kerusakan di jalan',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('Cancelled', $transfer->fresh()->status);

        // Balances should be restored!
        $this->assertEquals(50.0, (float) $stockOrigin->fresh()->qty_available);
        $this->assertEquals(0.0, (float) $stockDest->fresh()->qty_available);
    }

    public function test_production_finished_can_be_reverted_to_draft_before_qc_processed(): void
    {
        $plan = ProductionPlan::create([
            'plan_number' => 'PLN-TEST-PRD-01',
            'name' => 'Rencana Produksi Test',
            'product_id' => $this->finishGood->id,
            'quantity' => 100,
            'uom_id' => $this->uom->id,
            'warehouse_id' => $this->warehouse1->id,
            'cabang_id' => $this->adminUser->cabang_id,
            'status' => 'in_progress',
            'created_by' => $this->adminUser->id,
        ]);

        $spk = ManufacturingOrder::create([
            'mo_number' => 'MO-TEST-PROD-01',
            'production_plan_id' => $plan->id,
            'status' => 'in_progress',
            'cabang_id' => $this->adminUser->cabang_id,
        ]);

        $production = Production::create([
            'production_number' => 'PRD-TEST-001',
            'manufacturing_order_id' => $spk->id,
            'quantity_produced' => 100,
            'production_date' => now(),
            'status' => 'draft',
        ]);

        // Finish production
        $this->actingAs($this->adminUser)->post(route('manufaktur.production.finish', $production));
        $this->assertEquals('finished', $production->fresh()->status);

        // QC is created automatically in status 0 (uninspected)
        $qc = QualityControl::where('from_model_type', Production::class)
            ->where('from_model_id', $production->id)
            ->first();

        $this->assertNotNull($qc);
        $this->assertEquals(0, (int) $qc->status);

        // Revert to draft
        $response = $this->actingAs($this->adminUser)->post(route('manufaktur.production.revert-draft', $production));
        $response->assertSessionHas('success');

        $this->assertEquals('draft', $production->fresh()->status);
        $this->assertNull(QualityControl::where('from_model_type', Production::class)->where('from_model_id', $production->id)->first());
    }

    public function test_in_progress_stock_opname_can_be_cancelled(): void
    {
        $opname = StockOpname::create([
            'opname_number' => 'OPN-TEST-001',
            'opname_date' => now(),
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'in_progress',
            'created_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->post(route('gudang.opname.cancel', $opname), [
            'cancel_reason' => 'Pemadaman listrik total saat audit gudang',
        ]);

        $response->assertSessionHas('success');
        $this->assertEquals('cancelled', $opname->fresh()->status);
        $this->assertStringContainsString('DIBATALKAN: Pemadaman listrik total', $opname->fresh()->notes);
    }
}

