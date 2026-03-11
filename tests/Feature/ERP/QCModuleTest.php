<?php

namespace Tests\Feature\ERP;

use App\Models\Cabang;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MODULE 6 — QC MODULE
 *
 * Tests items #20, #21:
 *  #20 QC page displays date, supplier name, and PO number
 *  #21 QC filters work: filter by supplier, date, PO number
 */
class QCModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Supplier $supplier;
    protected Product $product;
    protected PurchaseOrder $po;
    protected PurchaseReceipt $receipt;
    protected QualityControl $qc;

    protected function setUp(): void
    {
        parent::setUp();

        UnitOfMeasure::factory()->create();

        $this->cabang    = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->supplier  = Supplier::factory()->create(['perusahaan' => 'PT Test Supplier']);
        $this->product   = Product::factory()->create();
        $this->user      = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);

        // Create PO → Receipt → QC chain
        $this->po = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
            'po_number'    => 'PO-QC-001',
        ]);

        $this->receipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $this->po->id,
            'cabang_id'         => $this->cabang->id,
        ]);

        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $this->receipt->id,
            'product_id'          => $this->product->id,
            'quantity'            => 10,
        ]);

        $this->qc = QualityControl::create([
            'qc_number'        => 'QC-TEST-001',
            'inspected_by'     => $this->user->id,
            'passed_quantity'  => 9,
            'rejected_quantity' => 1,
            'status'           => 0,
            'warehouse_id'     => $this->warehouse->id,
            'product_id'       => $this->product->id,
            'from_model_id'    => $receiptItem->id,
            'from_model_type'  => 'App\\Models\\PurchaseReceiptItem',
            'date_send_stock'  => now()->toDateString(),
        ]);
    }

    // ─── #20 QC DISPLAYS DATE, SUPPLIER, PO NUMBER ───────────────────────────

    /** @test */
    public function qc_record_has_qc_number_and_date(): void
    {
        $this->assertDatabaseHas('quality_controls', [
            'id'        => $this->qc->id,
            'qc_number' => 'QC-TEST-001',
        ]);

        $this->assertNotNull($this->qc->date_send_stock,
            'QC must have a date_send_stock field');
    }

    /** @test */
    public function qc_can_access_supplier_through_receipt_chain(): void
    {
        $fromModel = $this->qc->fromModel;
        $this->assertNotNull($fromModel, 'QC fromModel (PurchaseReceiptItem) must exist');

        // PurchaseReceiptItem → PurchaseReceipt → PurchaseOrder → Supplier
        $receipt = $fromModel->purchaseReceipt;
        $this->assertNotNull($receipt, 'PurchaseReceiptItem must have a receipt');

        $po = $receipt->purchaseOrder;
        $this->assertNotNull($po, 'Receipt must have a PO');
        $this->assertEquals($this->po->id, $po->id);

        $supplier = $po->supplier;
        $this->assertNotNull($supplier, 'PO must have a supplier');
        $this->assertEquals('PT Test Supplier', $supplier->perusahaan);
    }

    /** @test */
    public function qc_can_access_po_number_through_receipt_chain(): void
    {
        $fromModel = $this->qc->fromModel;
        $receipt   = $fromModel?->purchaseReceipt;
        $po        = $receipt?->purchaseOrder;

        $this->assertEquals('PO-QC-001', $po?->po_number,
            'QC must be able to resolve PO number through receipt chain');
    }

    // ─── #21 QC FILTERS ───────────────────────────────────────────────────────

    /** @test */
    public function qc_can_be_filtered_by_date_send_stock(): void
    {
        $today      = now()->toDateString();
        $yesterday  = now()->subDay()->toDateString();

        // Extra QC with a different date
        QualityControl::create([
            'qc_number'        => 'QC-YESTERDAY-001',
            'inspected_by'     => $this->user->id,
            'passed_quantity'  => 5,
            'rejected_quantity' => 0,
            'status'           => 0,
            'warehouse_id'     => $this->warehouse->id,
            'product_id'       => $this->product->id,
            'from_model_id'    => 1,
            'from_model_type'  => 'App\\Models\\PurchaseReceiptItem',
            'date_send_stock'  => $yesterday,
        ]);

        $todayQcs = QualityControl::whereDate('date_send_stock', $today)->get();
        $this->assertGreaterThanOrEqual(1, $todayQcs->count(),
            'QC filter by date_send_stock must return records for today');

        foreach ($todayQcs as $q) {
            $this->assertEquals($today, $q->date_send_stock,
                'All returned QC records must match the filtered date');
        }
    }

    /** @test */
    public function qc_records_can_be_filtered_by_po_number(): void
    {
        // Create second PO with different number
        $po2 = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
            'po_number'    => 'PO-OTHER-999',
        ]);

        // Filter QCs where receipt → PO has po_number = 'PO-QC-001'
        $qcsForPo1 = QualityControl::whereHas('fromModel', function ($q) {
            $q->whereHas('purchaseReceipt', function ($r) {
                $r->whereHas('purchaseOrder', function ($p) {
                    $p->where('po_number', 'PO-QC-001');
                });
            });
        })->get();

        $this->assertGreaterThanOrEqual(1, $qcsForPo1->count(),
            'Must be able to filter QC by PO number');

        foreach ($qcsForPo1 as $q) {
            $this->assertEquals(
                'PO-QC-001',
                $q->fromModel?->purchaseReceipt?->purchaseOrder?->po_number
            );
        }
    }

    /** @test */
    public function qc_records_can_be_filtered_by_supplier(): void
    {
        $supplier2 = Supplier::factory()->create(['perusahaan' => 'PT Other Supplier']);

        // All QCs in setUp belong to $this->supplier
        $qcsForSupplier1 = QualityControl::whereHas('fromModel', function ($q) {
            $q->whereHas('purchaseReceipt', function ($r) {
                $r->whereHas('purchaseOrder', function ($p) use (&$supplierId) {
                    $p->where('supplier_id', $this->supplier->id);
                });
            });
        })->get();

        $this->assertGreaterThanOrEqual(1, $qcsForSupplier1->count(),
            'Must be able to filter QC by supplier');
    }

    /** @test */
    public function qc_record_has_all_required_display_columns(): void
    {
        $qc = $this->qc->fresh();

        // Date column
        $this->assertNotNull($qc->date_send_stock);

        // QC number (DO number equivalent for QC listing)
        $this->assertEquals('QC-TEST-001', $qc->qc_number);

        // Product
        $this->assertEquals($this->product->id, $qc->product_id);

        // Quantities
        $this->assertEquals(9, $qc->passed_quantity);
        $this->assertEquals(1, $qc->rejected_quantity);
    }
}
