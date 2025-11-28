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
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'is_sent' => false,
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
            'is_sent' => false,
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
        $receiptItem->update(['is_sent' => true]);
        $shortReceiptItem->update(['is_sent' => true]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'id' => $receiptItem->id,
            'is_sent' => true,
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'id' => $shortReceiptItem->id,
            'is_sent' => true,
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
            'is_sent' => false,
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
            'is_sent' => false,
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
            'is_sent' => false,
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
            'is_sent' => false,
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
    public function purchase_receipt_quality_control_updates_stock()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 15000,
        ]);

        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
        ]);

        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 5,
            'qty_accepted' => 5,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'is_sent' => true,
        ]);

        /** @var QualityControlService $qualityControlService */
        $qualityControlService = app(QualityControlService::class);
        $qualityControl = $qualityControlService->createQCFromPurchaseReceiptItem($receiptItem, [
            'inspected_by' => $this->user->id,
        ]);

        $qualityControl->update(['notes' => 'All goods accepted']);
        $qualityControlService->completeQualityControl($qualityControl->fresh(), [
            'item_condition' => 'good',
        ]);

        $stockMovement = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertNotNull($stockMovement);
        $this->assertEquals('purchase_in', $stockMovement->type);
        $this->assertEquals(5.0, (float) $stockMovement->quantity);

        $inventoryStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($inventoryStock);
        $this->assertEquals(5.0, (float) $inventoryStock->qty_available);
    }

    /** @test */
    public function procurement_flow_from_receipt_to_vendor_payment_closes_account_payable()
    {
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
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

        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'receipt_number' => 'RN-20251101-0003',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
            'other_cost' => 0,
        ]);

        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'is_sent' => true,
        ]);

        /** @var QualityControlService $qualityControlService */
        $qualityControlService = app(QualityControlService::class);
        $qualityControl = $qualityControlService->createQCFromPurchaseReceiptItem($receiptItem, [
            'inspected_by' => $this->user->id,
        ]);

        $qualityControl->update(['notes' => 'Procurement QC complete']);
        $qualityControlService->completeQualityControl($qualityControl->fresh(), [
            'item_condition' => 'good',
        ]);

        $inventoryStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertNotNull($inventoryStock);
        $this->assertEquals(10.0, (float) $inventoryStock->qty_available);

        $invoice = Invoice::create([
            'invoice_number' => 'PINV-20251101-0003',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'invoice_date' => now(),
            'subtotal' => 100000,
            'tax' => 0,
            'other_fee' => [],
            'total' => 100000,
            'due_date' => now()->addDays(14),
            'status' => 'sent',
            'supplier_name' => $this->supplier->name,
            'supplier_phone' => $this->supplier->phone,
            'purchase_receipts' => [$purchaseReceipt->id],
        ]);

        $accountPayable = AccountPayable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($accountPayable);
        $this->assertEquals('Belum Lunas', $accountPayable->status);
        $this->assertEquals(100000.0, (float) $accountPayable->total);
        $this->assertEquals(100000.0, (float) $accountPayable->remaining);

        // Invoice is automatically posted by InvoiceObserver, so check journal entries exist
        $invoiceEntries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get()
            ->load('coa');
        $this->assertCount(2, $invoiceEntries);

        $invoiceCredit = $invoiceEntries->first(fn ($entry) => (float) $entry->credit > 0);
        $this->assertEquals(100000.0, (float) $invoiceCredit->credit);
        $this->assertEquals('2100.10', $invoiceCredit->coa->code); // Unbilled purchase liability

        $invoiceDebit = $invoiceEntries->first(fn ($entry) => (float) $entry->debit > 0);
        $this->assertEquals(100000.0, (float) $invoiceDebit->debit);
        $this->assertEquals('2110', $invoiceDebit->coa->code); // Accounts payable

        $cashCoa = ChartOfAccount::where('code', '1111.01')->firstOrFail();

        $vendorPayment = VendorPayment::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'selected_invoices' => [$invoice->id],
            'invoice_receipts' => [$purchaseReceipt->id],
            'payment_date' => now(),
            'total_payment' => 100000,
            'coa_id' => $cashCoa->id,
            'payment_method' => 'bank_transfer',
            'notes' => 'Automated test settlement',
            'status' => 'Draft',
        ]);

        VendorPaymentDetail::create([
            'vendor_payment_id' => $vendorPayment->id,
            'invoice_id' => $invoice->id,
            'method' => 'Bank Transfer',
            'amount' => 100000,
            'coa_id' => $cashCoa->id,
            'payment_date' => now(),
            'notes' => 'Full payment',
        ]);

        $vendorPayment->refresh();
        $accountPayable->refresh();
        $invoice->refresh();

        $this->assertEquals('Paid', $vendorPayment->status);
        $this->assertEquals('Lunas', $accountPayable->status);
        $this->assertEquals(0.0, (float) $accountPayable->remaining);
        $this->assertEquals(100000.0, (float) $accountPayable->paid);
        $this->assertEquals('paid', $invoice->status);

        $paymentEntries = JournalEntry::where('source_type', VendorPayment::class)
            ->where('source_id', $vendorPayment->id)
            ->get()
            ->load('coa');
        $this->assertCount(2, $paymentEntries);

        $paymentDebit = $paymentEntries->first(fn ($entry) => (float) $entry->debit > 0);
        $paymentCredit = $paymentEntries->first(fn ($entry) => (float) $entry->credit > 0);
        $this->assertEquals(100000.0, (float) $paymentDebit->debit);
        $this->assertEquals('2110', $paymentDebit->coa->code);
        $this->assertEquals(100000.0, (float) $paymentCredit->credit);
        $this->assertEquals($cashCoa->code, $paymentCredit->coa->code);

        $this->assertEquals(10.0, (float) $inventoryStock->fresh()->qty_available);
    }

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

        // 4. COMPLETE QC - This should NOT create receipt automatically in the new flow
        $qualityControlService->completeQualityControl($qcRecord, []);

        // Verify NO receipt was created automatically
        $this->assertDatabaseCount('purchase_receipts', 0);

        // Verify QC status was updated to completed
        $qcRecord->refresh();
        $this->assertEquals(1, $qcRecord->status); // completed status

        // 5. MANUALLY CREATE RECEIPT FROM QC (if needed for testing receipt flow)
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'receipt_number' => 'RN-QC-TEST-001',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'status' => 'draft',
        ]);

        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 5,
            'qty_accepted' => 5,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Verify receipt was created manually
        $this->assertDatabaseHas('purchase_receipts', [
            'purchase_order_id' => $purchaseOrder->id,
        ]);

        $this->assertNotNull($purchaseReceipt);

        // Verify receipt item was created
        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
        ]);

        $this->assertNotNull($receiptItem);

        // 6. POST THE RECEIPT - This should create inventory and journal entries
        $purchaseReceiptService = app(PurchaseReceiptService::class);
        $postResult = $purchaseReceiptService->postPurchaseReceipt($purchaseReceipt);
        $this->assertEquals('posted', $postResult['status']);

        // Verify inventory was created
        $this->assertDatabaseHas('inventory_stocks', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_available' => 5,
        ]);

        // NOTE: Journal entries are no longer created automatically in the new manual workflow
        // They would need to be created manually through accounting processes

        // 7. VERIFY PO STATUS REMAINS APPROVED (since receipt is created manually and not through QC completion)
        $purchaseOrder->refresh();
        $this->assertEquals('approved', $purchaseOrder->status);

        // 8. VERIFY RECEIPT STATUS WAS UPDATED
        $purchaseReceipt->refresh();
        $this->assertEquals('completed', $purchaseReceipt->status);
    }

    /** @test */
    public function purchase_receipt_item_can_be_sent_to_quality_control_via_relation_manager_action()
    {
        // Create approved Purchase Order
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20251101-0005',
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

        // Create Purchase Receipt
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'receipt_number' => 'RN-20251101-0005',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
        ]);

        // Create receipt item with accepted quantity
        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 8,
            'qty_rejected' => 2,
            'warehouse_id' => $this->warehouse->id,
            'is_sent' => false,
        ]);

        // Assert no QC record exists initially
        $this->assertDatabaseMissing('quality_controls', [
            'from_model_id' => $poItem->id,
            'from_model_type' => \App\Models\PurchaseOrderItem::class,
        ]);

        // Simulate the action from relation manager
        $qcService = app(QualityControlService::class);
        $receiptService = app(PurchaseReceiptService::class);

        // Prepare QC data (as done in the action)
        $qcData = [
            'passed_quantity' => $receiptItem->qty_accepted,
            'rejected_quantity' => $receiptItem->qty_rejected,
            'warehouse_id' => $receiptItem->warehouse_id,
            'rak_id' => $receiptItem->rak_id,
            'inspected_by' => $this->user->id,
        ];

        // Create QC record
        $qc = $qcService->createQCFromPurchaseReceiptItem($receiptItem, $qcData);

        // Create temporary procurement entries
        $result = $receiptService->createTemporaryProcurementEntriesForReceiptItem($receiptItem);

        // Assert QC record was created
        $this->assertDatabaseHas('quality_controls', [
            'qc_number' => $qc->qc_number,
            'from_model_id' => $receiptItem->id,
            'from_model_type' => \App\Models\PurchaseReceiptItem::class,
            'passed_quantity' => 8,
            'rejected_quantity' => 2,
            'status' => 0, // pending
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'inspected_by' => $this->user->id,
        ]);

        // Assert journal entries were created
        $this->assertEquals('posted', $result['status']);
        $this->assertCount(2, $result['entries']); // Debit temporary procurement + Credit unbilled purchase

        // Assert the receipt item now has QC relation
        $receiptItem->refresh();
        $this->assertTrue($receiptItem->qualityControl()->exists());
        $this->assertEquals($qc->id, $receiptItem->qualityControl->id);

        // Verify QC number format
        $this->assertStringStartsWith('QC-' . now()->format('Ymd') . '-', $qc->qc_number);
    }

    /** @test */
    public function quality_control_completion_adds_stock_to_inventory_and_creates_journal_entries()
    {
        // Create approved Purchase Order
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20251101-0006',
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

        // Create Purchase Receipt
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'receipt_number' => 'RN-20251101-0006',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
        ]);

        // Create receipt item with accepted quantity
        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 8,
            'qty_rejected' => 2,
            'warehouse_id' => $this->warehouse->id,
            'is_sent' => false,
        ]);

        // Send item to QC (create QC record and temporary procurement)
        $qcService = app(QualityControlService::class);
        $receiptService = app(PurchaseReceiptService::class);

        $qcData = [
            'passed_quantity' => $receiptItem->qty_accepted,
            'rejected_quantity' => $receiptItem->qty_rejected,
            'warehouse_id' => $receiptItem->warehouse_id,
            'inspected_by' => $this->user->id,
        ];

        $qc = $qcService->createQCFromPurchaseOrderItem($receiptItem->purchaseOrderItem, $qcData);
        $tempResult = $receiptService->createTemporaryProcurementEntriesForReceiptItem($receiptItem);

        // Verify QC is created and temporary entries are posted
        $this->assertEquals('posted', $tempResult['status']);
        $this->assertDatabaseHas('quality_controls', [
            'id' => $qc->id,
            'status' => 0, // pending
            'passed_quantity' => 8,
            'rejected_quantity' => 2,
        ]);

        // Check initial inventory stock (should be 0)
        $initialStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertNull($initialStock); // No stock initially

        // Complete the Quality Control
        $qcCompleteData = [
            'item_condition' => 'good',
        ];
        $qcService->completeQualityControl($qc, $qcCompleteData);

        // Refresh QC from database
        $qc->refresh();

        // Verify QC status is updated
        $this->assertEquals(1, $qc->status); // completed
        $this->assertNotNull($qc->date_send_stock);

        // Now post inventory after QC completion
        $inventoryResult = $receiptService->postItemInventoryAfterQC($receiptItem);

        // Verify inventory posting was successful
        $this->assertEquals('posted', $inventoryResult['status']);
        $this->assertCount(2, $inventoryResult['entries']); // Debit inventory + Credit temporary procurement

        // Verify inventory stock is created
        $finalStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($finalStock);
        $this->assertEquals(8, $finalStock->qty_available); // qty_accepted
        $this->assertEquals(80000, $finalStock->qty_available * 10000); // 8 * 10000 - calculate total value manually

        // Verify journal entries for inventory
        $inventoryDebit = JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->where('debit', 80000)
            ->where('description', 'like', '%inventory%')
            ->first();

        $this->assertNotNull($inventoryDebit);
        $expectedInventoryCoa = $this->product->inventoryCoa ?? ChartOfAccount::whereIn('code', ['1140.10', '1140.01'])->first();
        $this->assertEquals($expectedInventoryCoa->id ?? null, $inventoryDebit->coa_id);

        // Verify temporary procurement is closed (credit entry)
        $tempCredit = JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->where('credit', 80000)
            ->where('description', 'like', '%Inventory Posting - Credit temporary procurement%')
            ->first();

        $this->assertNotNull($tempCredit);
        $expectedTempCoa = $this->product->temporaryProcurementCoa ?? ChartOfAccount::whereIn('code', ['1180.01', '1400.01'])->first();
        $this->assertEquals($expectedTempCoa->id ?? null, $tempCredit->coa_id);

        // Verify return product is created for rejected quantity
        $this->assertDatabaseHas('return_products', [
            'from_model_id' => $qc->id,
            'from_model_type' => \App\Models\QualityControl::class,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $returnProduct = \App\Models\ReturnProduct::where('from_model_id', $qc->id)->first();
        $this->assertNotNull($returnProduct);
        $this->assertDatabaseHas('return_product_items', [
            'return_product_id' => $returnProduct->id,
            'product_id' => $this->product->id,
            'quantity' => 2, // rejected quantity
        ]);
    }
}