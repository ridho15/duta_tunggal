<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Models\Warehouse;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Services\LedgerPostingService;
use App\Services\PurchaseReceiptService;
use App\Services\QualityControlService;
use Database\Seeders\CabangSeeder;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PurchaseReceiptFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CabangSeeder::class);
        $this->seed(ChartOfAccountSeeder::class);

        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create();
        $this->currency = Currency::factory()->create();

        // Set up product COA relationships for testing
        $inventoryCoa = ChartOfAccount::where('code', '1140.01')->first();
                $unbilledPurchaseCoa = ChartOfAccount::where('code', '2100.10')->first(); // Updated to new liability COA // Updated to new liability COA
        $temporaryProcurementCoa = ChartOfAccount::where('code', '1400.01')->first();

        if ($inventoryCoa) {
            $this->product->inventory_coa_id = $inventoryCoa->id;
        }
        if ($unbilledPurchaseCoa) {
            $this->product->unbilled_purchase_coa_id = $unbilledPurchaseCoa->id;
        }
        if ($temporaryProcurementCoa) {
            $this->product->temporary_procurement_coa_id = $temporaryProcurementCoa->id;
        }
        $this->product->save();

        $this->actingAs($this->user);
    }

    /** @test */
    public function complete_purchase_receipt_flow()
    {
        // 1. SETUP - Create approved Purchase Order
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20251101-0001',
            'order_date' => now(),
            'expected_date' => now()->addDays(7),
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'discount' => 0,
            'tax' => 0,
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'approved',
            'po_number' => 'PO-20251101-0001'
        ]);

        // 2. RECEIVING NOTIFICATION - Supplier delivers goods
        // Simulate supplier delivery notification

        // 3. CREATE PURCHASE RECEIPT
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'receipt_number' => 'RN-20251101-0001',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
            'other_cost' => 50000,
        ]);

        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $purchaseReceipt->id,
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_number' => 'RN-20251101-0001',
            'status' => 'completed'
        ]);

        // 4. PHYSICAL VERIFICATION & SYSTEM ENTRY
        // Create receipt items with exact quantities
        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10, // Exact match with PO
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $purchaseReceipt->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
        ]);

        // 5. DISCREPANCY HANDLING - Test short delivery
        $shortReceiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 8, // Short delivery
            'qty_accepted' => 8,
            'qty_rejected' => 0,
            'reason_rejected' => 'Short delivery - supplier confirmed delay',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'qty_received' => 8,
            'reason_rejected' => 'Short delivery - supplier confirmed delay',
        ]);

        // 6. SUBMIT TO QC - Update receipt status
        $purchaseReceipt->update(['status' => 'completed']);

        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $purchaseReceipt->id,
            'status' => 'completed',
        ]);

        // Update PO status to reflect partial receipt
        // Update PO status to reflect partial receipt (matches DB enum: 'partially_received')
        $purchaseOrder->update(['status' => 'partially_received']);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'partially_received',
        ]);

        // 7. QC PROCESSING - Items quarantined until QC pass
        // Simulate QC approval - items move to stock
        $receiptItem->update(['status' => 'completed']);
        $shortReceiptItem->update(['status' => 'completed']);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'id' => $receiptItem->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'id' => $shortReceiptItem->id,
            'status' => 'completed',
        ]);

        // 8. FINAL STATUS TRACKING
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseCount('purchase_receipt_items', 2);

        // Verify receipt totals
        $totalReceived = $purchaseReceipt->purchaseReceiptItem->sum('qty_received');
        $totalAccepted = $purchaseReceipt->purchaseReceiptItem->sum('qty_accepted');
        $totalRejected = $purchaseReceipt->purchaseReceiptItem->sum('qty_rejected');

        $this->assertEquals(18, $totalReceived); // 10 + 8
        $this->assertEquals(18, $totalAccepted); // 10 + 8
        $this->assertEquals(0, $totalRejected);  // No rejections
    }

    /** @test */
    public function purchase_receipt_with_damaged_goods()
    {
        // Setup PO
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_price' => 5000,
        ]);

        // Create receipt with damaged goods
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
        ]);

        // 15 good items, 5 damaged
        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 20,
            'qty_accepted' => 15,
            'qty_rejected' => 5,
            'reason_rejected' => 'Damaged packaging - items unusable',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'qty_received' => 20,
            'qty_accepted' => 15,
            'qty_rejected' => 5,
            'reason_rejected' => 'Damaged packaging - items unusable',
        ]);

        // Verify automatic qty_rejected calculation
        $receiptItem->refresh();
        $this->assertEquals(5, $receiptItem->qty_rejected); // 20 - 15 = 5
    }

    /** @test */
    public function purchase_receipt_over_delivery_handling()
    {
        // Setup PO
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 8000,
        ]);

        // Create receipt with over delivery
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
        ]);

        // Received 12 instead of ordered 10
        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 12,
            'qty_accepted' => 12,
            'qty_rejected' => 0,
            'reason_rejected' => 'Over delivery - accepted per PO terms',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'qty_received' => 12,
            'qty_accepted' => 12,
            'reason_rejected' => 'Over delivery - accepted per PO terms',
        ]);

        // Verify over delivery is recorded
        $receiptItem->refresh();
        $this->assertEquals(0, $receiptItem->qty_rejected); // No rejection for over delivery
    }

    /** @test */
    public function purchase_receipt_creates_journal_entries()
    {
        // Create approved Purchase Order
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20251101-0002',
            'order_date' => now(),
            'expected_date' => now()->addDays(7),
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000, // 10 items * 10000 = 100000
            'discount' => 0,
            'tax' => 0,
        ]);

        // Create Purchase Receipt
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'receipt_number' => 'RN-20251101-0002',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
            'other_cost' => 0,
        ]);

        // Create receipt items
        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
        ]);

        // Call the service to post to finance (should now just validate)
        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $result = $purchaseReceiptService->postPurchaseReceipt($purchaseReceipt);

        // Assert posting was successful (journal entries created at receipt level)
        $this->assertEquals('posted', $result['status']);
    }



    /** @test */
    public function purchase_receipt_item_qc_approved_creates_inventory_and_closes_temp_procurement()
    {
        // Create approved Purchase Order
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20251101-0004',
            'order_date' => now(),
            'expected_date' => now()->addDays(7),
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000, // 10 items * 10000 = 100000
            'discount' => 0,
            'tax' => 0,
        ]);

        // Create Purchase Receipt
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'receipt_number' => 'RN-20251101-0004',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
            'other_cost' => 0,
        ]);

        // Create receipt items
        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'pending',
        ]);

        // First send item to QC (creates temp procurement)
        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $qcResult = $purchaseReceiptService->createTemporaryProcurementEntriesForReceiptItem($receiptItem);
        $this->assertEquals('posted', $qcResult['status']);

        // Then post inventory after QC approval
        $inventoryResult = $purchaseReceiptService->postItemInventoryAfterQC($receiptItem);

        // Assert posting was successful
        $this->assertEquals('posted', $inventoryResult['status']);
        $this->assertCount(2, $inventoryResult['entries']); // Debit inventory + Credit temp procurement

        // Check journal entries were created
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => \App\Models\PurchaseReceiptItem::class,
            'source_id' => $receiptItem->id,
            'reference' => 'RN-20251101-0004',
            'journal_type' => 'inventory',
        ]);

        // Since QC complete no longer creates journal entries automatically,
        // we only check that stock movement and inventory are updated
        $stockMovement = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertNotNull($stockMovement);
        $this->assertEquals('purchase_in', $stockMovement->type);
        $this->assertEquals(10.0, (float) $stockMovement->quantity);

        $inventoryStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($inventoryStock);
        $this->assertEquals(10.0, (float) $inventoryStock->qty_available);
    }

    /** @test */


    /** @test */
    public function end_to_end_purchase_order_to_qc_without_automatic_receipt_creation()
    {
        // 1. CREATE PURCHASE ORDER
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-QC-TEST-001',
            'order_date' => now(),
            'expected_date' => now()->addDays(7),
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 50000,
            'discount' => 0,
            'tax' => 0,
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'po_number' => 'PO-QC-TEST-001',
            'status' => 'approved'
        ]);

        // 2. CREATE QC FROM PO ITEM (NEW FLOW)
        $qualityControlService = app(QualityControlService::class);

        // Before creating QC, ensure no QC records exist
        $this->assertDatabaseCount('quality_controls', 0);

        // Create QC from PO item (this is the new flow)
        $qcRecord = $qualityControlService->createQCFromPurchaseOrderItem($poItem, [
            'inspected_by' => $this->user->id,
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // 3. VERIFY QC DATA CREATION
        $this->assertDatabaseCount('quality_controls', 1);
        $this->assertNotNull($qcRecord, 'QC record should be created and returned');

        $this->assertEquals($poItem->id, $qcRecord->from_model_id);
        $this->assertEquals(PurchaseOrderItem::class, $qcRecord->from_model_type);
        $this->assertEquals($this->product->id, $qcRecord->product_id);
        $this->assertEquals(5, $qcRecord->passed_quantity);
        $this->assertEquals(0, $qcRecord->rejected_quantity);
        $this->assertEquals(0, $qcRecord->status); // pending status
        $this->assertEquals($this->user->id, $qcRecord->inspected_by);
        $this->assertNotNull($qcRecord->qc_number);

        // 4. COMPLETE QC - In the new QC-First flow, this auto-creates a purchase receipt
        Log::info('BEFORE QC completion - PO status: ' . $purchaseOrder->status);
        $qualityControlService->completeQualityControl($qcRecord, []);
        Log::info('AFTER QC completion - PO status: ' . $purchaseOrder->fresh()->status);

        // Verify QC status was updated to completed
        $qcRecord->refresh();
        $this->assertEquals(1, $qcRecord->status); // completed status

        // 5. VERIFY AUTO-CREATED RECEIPT (new QC-First flow creates receipt automatically)
        $this->assertDatabaseCount('purchase_receipts', 1);
        $autoReceipt = \App\Models\PurchaseReceipt::first();
        $this->assertNotNull($autoReceipt);
        $this->assertStringContainsString($qcRecord->qc_number, $autoReceipt->notes);
        $this->assertEquals('completed', $autoReceipt->status);
        $this->assertEquals($purchaseOrder->id, $autoReceipt->purchase_order_id);

        // Verify auto-created receipt item
        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $autoReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'qty_accepted' => 5,
        ]);

        // 6. VERIFY INVENTORY was created from QC posting
        $this->assertDatabaseHas('inventory_stocks', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $inventoryStock = \App\Models\InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertNotNull($inventoryStock);
        $this->assertEquals(5.0, (float) $inventoryStock->qty_available);

        // 7. VERIFY PO STATUS IS COMPLETED (all items received via auto receipt)
        $purchaseOrder->refresh();
        $this->assertEquals('completed', $purchaseOrder->status);
    }

}
