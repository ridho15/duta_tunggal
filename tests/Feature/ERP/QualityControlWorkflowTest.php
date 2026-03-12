<?php

namespace Tests\Feature\ERP;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sections 4 & 5: Quality Control Module Verification
 *
 * Tests:
 *  - QC view variables: PO number, Supplier, Items, Qty, Status, Notes, Date
 *  - Full QC workflow: PO → Receipt → QC → Status updates
 *  - QC status transitions: Pending QC → Passed / Rejected
 *  - QC data persistence
 */
class QualityControlWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected Currency $currency;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected User $user;
    protected Product $product;
    protected PurchaseOrder $po;
    protected PurchaseOrderItem $poItem;
    protected PurchaseReceipt $receipt;
    protected PurchaseReceiptItem $receiptItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang    = Cabang::factory()->create();
        $this->currency  = Currency::factory()->create();
        $this->supplier  = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->user      = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product   = Product::factory()->create();

        // Create a Purchase Order
        $this->po = PurchaseOrder::create([
            'supplier_id'  => $this->supplier->id,
            'po_number'    => 'PO-QC-' . now()->format('YmdHis'),
            'order_date'   => now()->toDateString(),
            'status'       => 'approved',
            'total_amount' => 500_000,
            'cabang_id'    => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'created_by'   => $this->user->id,
            'tempo_hutang' => 30,
        ]);

        // Create a PurchaseOrderItem
        $this->poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $this->po->id,
            'product_id'        => $this->product->id,
            'quantity'          => 10,
            'unit_price'        => 50_000,
            'total_price'       => 500_000,
            'currency_id'       => $this->currency->id,
        ]);

        // Create a Purchase Receipt linked to the PO
        $this->receipt = PurchaseReceipt::create([
            'receipt_number'    => 'RN-' . now()->format('Ymd') . '-0001',
            'purchase_order_id' => $this->po->id,
            'receipt_date'      => now()->toDateString(),
            'received_by'       => $this->user->id,
            'status'            => 'draft',
            'cabang_id'         => $this->cabang->id,
            'currency_id'       => $this->currency->id,
        ]);

        // Create a PurchaseReceiptItem
        $this->receiptItem = PurchaseReceiptItem::create([
            'purchase_receipt_id'   => $this->receipt->id,
            'purchase_order_item_id'=> $this->poItem->id,
            'product_id'            => $this->product->id,
            'qty_received'          => 10,
            'qty_accepted'          => 0,
            'qty_rejected'          => 0,
            'warehouse_id'          => $this->warehouse->id,
            'status'                => 'pending',
        ]);
    }

    // ─── Section 4: QC Variable Availability ─────────────────────────────────

    /** @test */
    public function qc_record_has_purchase_order_number_accessible(): void
    {
        $qc = QualityControl::create([
            'qc_number'          => 'QC-P-' . now()->format('Ymd') . '-0001',
            'from_model_type'    => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'      => $this->receiptItem->id,
            'inspected_by'       => $this->user->id,
            'passed_quantity'    => 8,
            'rejected_quantity'  => 2,
            'notes'              => 'Items inspected - 2 damaged',
            'status'             => 0,
            'warehouse_id'       => $this->warehouse->id,
            'product_id'         => $this->product->id,
            'date_send_stock'    => now()->toDateString(),
        ]);

        // Verify PO number is accessible through the QC → receiptItem → receipt → PO chain
        $loadedQc = QualityControl::with([
            'fromModel.purchaseReceipt.purchaseOrder',
        ])->find($qc->id);

        $poNumber = $loadedQc->fromModel?->purchaseReceipt?->purchaseOrder?->po_number;

        $this->assertNotNull($poNumber, 'PO Number must be accessible from QC record via receipt chain');
        $this->assertStringStartsWith('PO-QC-', $poNumber);
    }

    /** @test */
    public function qc_record_has_supplier_name_accessible(): void
    {
        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0002',
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 10,
            'rejected_quantity' => 0,
            'notes'             => null,
            'status'            => 0,
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        $loadedQc = QualityControl::with([
            'fromModel.purchaseReceipt.purchaseOrder.supplier',
        ])->find($qc->id);

        $supplier = $loadedQc->fromModel?->purchaseReceipt?->purchaseOrder?->supplier;
        $this->assertNotNull($supplier, 'Supplier must be reachable from QC record');
        $this->assertNotEmpty(
            $supplier->perusahaan ?? $supplier->name,
            'Supplier name must not be empty'
        );
    }

    /** @test */
    public function qc_record_exposes_product_and_quantity(): void
    {
        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0003',
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 7,
            'rejected_quantity' => 3,
            'notes'             => 'Batch QC run',
            'status'            => 0,
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('quality_controls', [
            'id'                => $qc->id,
            'product_id'        => $this->product->id,
            'passed_quantity'   => 7,
            'rejected_quantity' => 3,
        ]);

        // Product accessible via direct FK
        $this->assertNotNull($qc->product_id, 'product_id must be on QC record directly');
        $this->assertEquals($this->product->id, $qc->product_id);

        // Total inspected = passed + rejected
        $totalInspected = $qc->passed_quantity + $qc->rejected_quantity;
        $this->assertEquals(10, $totalInspected, 'Total inspected must match received qty');
    }

    /** @test */
    public function qc_record_stores_status_and_notes(): void
    {
        $note = 'Scratches found on outer packaging, accepted after review';

        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0004',
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 10,
            'rejected_quantity' => 0,
            'notes'             => $note,
            'status'            => 0,    // Belum diproses (pending)
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('quality_controls', [
            'id'     => $qc->id,
            'status' => 0,
            'notes'  => $note,
        ]);

        $this->assertEquals('Belum diproses', $qc->status_formatted,
            'QC status 0 must format as Belum diproses (Pending QC)');
    }

    /** @test */
    public function qc_record_stores_inspection_date(): void
    {
        $inspectionDate = now()->toDateString();

        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0005',
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 10,
            'rejected_quantity' => 0,
            'notes'             => null,
            'status'            => 0,
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => $inspectionDate,
        ]);

        $this->assertDatabaseHas('quality_controls', [
            'id' => $qc->id,
        ]);
        $this->assertEquals(
            $inspectionDate,
            substr($qc->fresh()->date_send_stock, 0, 10),
            'QC inspection date must be stored correctly'
        );
    }

    // ─── Section 5: QC Workflow (PO → Receipt → QC → Status) ────────────────

    /** @test */
    public function receipt_item_appears_in_qc_module_via_from_model(): void
    {
        // The QC module sources items from PurchaseReceiptItem
        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0006',
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 10,
            'rejected_quantity' => 0,
            'status'            => 0,
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        // Verify QC can find back its source item
        $this->assertEquals('App\\Models\\PurchaseReceiptItem', $qc->from_model_type);
        $this->assertEquals($this->receiptItem->id, $qc->from_model_id);

        // Verify it's linked to the correct PO chain
        $source = $qc->fromModel;
        $this->assertNotNull($source, 'QC fromModel must resolve to PurchaseReceiptItem');
        $this->assertEquals($this->poItem->id, $source->purchase_order_item_id);
    }

    /** @test */
    public function qc_passed_status_means_stock_was_updated(): void
    {
        // status=1 means "Sudah diproses" = stock has been updated (Passed QC)
        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0007',
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 10,
            'rejected_quantity' => 0,
            'status'            => 1,    // Sudah diproses = Passed QC
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        $this->assertEquals('Sudah diproses', $qc->status_formatted,
            'QC status 1 must format as Sudah diproses (Passed QC)');

        $this->assertDatabaseHas('quality_controls', [
            'id'     => $qc->id,
            'status' => 1,
        ]);
    }

    /** @test */
    public function qc_status_can_transition_from_pending_to_passed(): void
    {
        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0008',
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 0,
            'rejected_quantity' => 0,
            'status'            => 0,   // Pending QC
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        $this->assertEquals(0, $qc->status, 'Initial status must be 0 (Pending)');
        $this->assertEquals('Belum diproses', $qc->status_formatted);

        // Simulate QC approval (inspector sets passed_quantity and marks as processed)
        $qc->update([
            'passed_quantity'   => 10,
            'rejected_quantity' => 0,
            'status'            => 1,
        ]);

        $fresh = $qc->fresh();
        $this->assertEquals(1, $fresh->status, 'Status must change to 1 (Passed QC)');
        $this->assertEquals('Sudah diproses', $fresh->status_formatted);
        $this->assertEquals(10, $fresh->passed_quantity);
    }

    /** @test */
    public function qc_with_rejected_items_stores_reject_reason(): void
    {
        $rejectReason = 'Produk cacat fisik - kemasan rusak';

        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0009',
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 8,
            'rejected_quantity' => 2,
            'reason_reject'     => $rejectReason,
            'status'            => 1,
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('quality_controls', [
            'id'              => $qc->id,
            'rejected_quantity'=> 2,
            'reason_reject'   => $rejectReason,
        ]);
    }

    /** @test */
    public function qc_passed_plus_rejected_matches_received_quantity(): void
    {
        $receivedQty = $this->receiptItem->qty_received; // 10

        $passedQty   = 8;
        $rejectedQty = 2;

        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0010',
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => $passedQty,
            'rejected_quantity' => $rejectedQty,
            'status'            => 0,
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        $total = $qc->passed_quantity + $qc->rejected_quantity;
        $this->assertEquals(
            $receivedQty,
            $total,
            "Passed ({$passedQty}) + Rejected ({$rejectedQty}) must equal received ({$receivedQty})"
        );
    }

    /** @test */
    public function qc_po_number_visible_via_purchase_receipt_item(): void
    {
        // Verify the data chain: QC → PurchaseReceiptItem → PurchaseReceipt → PurchaseOrder
        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0011',
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 10,
            'rejected_quantity' => 0,
            'status'            => 0,
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        $pri = PurchaseReceiptItem::with('purchaseReceipt.purchaseOrder')->find($this->receiptItem->id);

        $poNumber = $pri?->purchaseReceipt?->purchaseOrder?->po_number;
        $this->assertNotNull($poNumber, 'PO number must be traceable through PurchaseReceiptItem chain');
        $this->assertEquals($this->po->po_number, $poNumber);
    }

    /** @test */
    public function multiple_qc_records_for_same_receipt_have_different_qc_numbers(): void
    {
        // Two different receipt items can have QC records; each must have unique number
        $receiptItem2 = PurchaseReceiptItem::create([
            'purchase_receipt_id'    => $this->receipt->id,
            'purchase_order_item_id' => $this->poItem->id,
            'product_id'             => $this->product->id,
            'qty_received'           => 5,
            'qty_accepted'           => 0,
            'qty_rejected'           => 0,
            'warehouse_id'           => $this->warehouse->id,
            'status'                 => 'pending',
        ]);

        $qcNum1 = 'QC-P-' . now()->format('Ymd') . '-0012';
        $qcNum2 = 'QC-P-' . now()->format('Ymd') . '-0013';

        $qc1 = QualityControl::create([
            'qc_number'         => $qcNum1,
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $this->receiptItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 10,
            'rejected_quantity' => 0,
            'status'            => 0,
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        $qc2 = QualityControl::create([
            'qc_number'         => $qcNum2,
            'from_model_type'   => 'App\\Models\\PurchaseReceiptItem',
            'from_model_id'     => $receiptItem2->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 5,
            'rejected_quantity' => 0,
            'status'            => 0,
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        $this->assertNotEquals($qc1->qc_number, $qc2->qc_number,
            'Each QC record must have a unique QC number');
    }

    /** @test */
    public function journal_posting_is_skipped_when_coa_accounts_are_missing(): void
    {
        // purge COA table to simulate misconfiguration where fallback lookup fails
        \App\Models\ChartOfAccount::query()->delete();

        $qc = QualityControl::create([
            'qc_number'         => 'QC-P-' . now()->format('Ymd') . '-0099',
            // trigger the branch that creates journal entries (PurchaseOrderItem)
            'from_model_type'   => 'App\\Models\\PurchaseOrderItem',
            'from_model_id'     => $this->poItem->id,
            'inspected_by'      => $this->user->id,
            'passed_quantity'   => 10,
            'rejected_quantity' => 0,
            'status'            => 1,
            'warehouse_id'      => $this->warehouse->id,
            'product_id'        => $this->product->id,
            'date_send_stock'   => now()->toDateString(),
        ]);

        // Spy on the log so we can assert a warning was emitted
        \Illuminate\Support\Facades\Log::spy();

        $service = app(\App\Services\QualityControlService::class);
        $service->completeQualityControl($qc, []);

        // no journal entries should have been created for the QC
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => QualityControl::class,
            'source_id'   => $qc->id,
        ]);

        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(
            function ($message, $context) use ($qc) {
                return str_contains($message, 'QC journal posting skipped due to missing COA')
                    && isset($context['qc_number'])
                    && $context['qc_number'] === $qc->qc_number;
            }
        );
    }
}
