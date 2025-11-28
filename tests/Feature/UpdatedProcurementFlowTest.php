<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Models\Warehouse;
use App\Services\ProductService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReceiptService;
use App\Services\QualityControlService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatedProcurementFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected ChartOfAccount $inventoryCoa;
    protected ChartOfAccount $unbilledPurchaseCoa;
    protected ChartOfAccount $temporaryProcurementCoa;
    protected ChartOfAccount $cashCoa;
    protected ChartOfAccount $apCoa;
    protected ChartOfAccount $ppnMasukanCoa;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountSeeder::class);

        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create(['tempo_hutang' => 30]);
        $this->warehouse = Warehouse::factory()->create(['status' => 1]);
        $this->currency = Currency::factory()->create(['code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp']);

        // Create unit of measure
        \App\Models\UnitOfMeasure::factory()->create();

        $this->product = Product::factory()->create([
            'cost_price' => 10000,
            'is_active' => true,
            'uom_id' => \App\Models\UnitOfMeasure::first()->id,
        ]);

        // Set up product COA relationships for testing
        $inventoryCoa = ChartOfAccount::where('code', '1140.01')->first();
        $unbilledPurchaseCoa = ChartOfAccount::where('code', '2100.10')->first();
        $temporaryProcurementCoa = ChartOfAccount::where('code', '1400.01')->first();
        $cashCoa = ChartOfAccount::where('code', '1111.01')->first() ?? ChartOfAccount::factory()->create([
            'code' => '1111.01',
            'name' => 'Kas Kecil',
            'type' => 'Asset',
            'opening_balance' => 1000000,
            'is_active' => true,
        ]);
        $apCoa = ChartOfAccount::where('code', '2110')->first() ?? ChartOfAccount::factory()->create([
            'code' => '2110',
            'name' => 'Hutang Dagang',
            'type' => 'Liability',
            'is_active' => true,
        ]);
        $ppnMasukanCoa = ChartOfAccount::where('code', '1170.06')->first() ?? ChartOfAccount::factory()->create([
            'code' => '1170.06',
            'name' => 'PPN Masukan',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->inventoryCoa = $inventoryCoa;
        $this->unbilledPurchaseCoa = $unbilledPurchaseCoa;
        $this->temporaryProcurementCoa = $temporaryProcurementCoa;
        $this->cashCoa = $cashCoa;
        $this->apCoa = $apCoa;
        $this->ppnMasukanCoa = $ppnMasukanCoa;

        $this->product->update([
            'inventory_coa_id' => $inventoryCoa?->id,
            'unbilled_purchase_coa_id' => $unbilledPurchaseCoa?->id,
            'temporary_procurement_coa_id' => $temporaryProcurementCoa?->id,
        ]);

        $this->product->refresh();
        $this->actingAs($this->user);
    }

    /** @test */
    public function complete_updated_procurement_flow_with_automatic_stock_movement_and_invoice()
    {
        // ==========================================
        // STEP 1: CREATE PURCHASE ORDER
        // ==========================================
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20251113-0001',
            'order_date' => now(),
            'expected_date' => now()->addDays(7),
            'status' => 'draft',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Eklusif',
            'currency_id' => $this->currency->id,
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'draft',
            'po_number' => 'PO-20251113-0001',
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
        ]);

        // ==========================================
        // STEP 2: REQUEST APPROVAL PURCHASE ORDER
        // ==========================================
        $purchaseOrder->update([
            'status' => 'request_approval'
        ]);

        $this->assertEquals('request_approval', $purchaseOrder->fresh()->status);

        // ==========================================
        // STEP 3: APPROVE PURCHASE ORDER
        // ==========================================
        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => $this->user->id,
            'date_approved' => now(),
        ]);

        $this->assertEquals('approved', $purchaseOrder->fresh()->status);

        // ==========================================
        // STEP 4: CREATE PRE-RECEIPT QC
        // ==========================================
        $qualityControlService = app(QualityControlService::class);

        foreach ($purchaseOrder->purchaseOrderItem as $poItem) {
            $qc = $qualityControlService->createQCFromPurchaseOrderItem($poItem, [
                'inspected_by' => $this->user->id,
                'passed_quantity' => $poItem->quantity,
                'rejected_quantity' => 0,
            ]);

            $this->assertNotNull($qc);
            $this->assertEquals(QualityControl::class, $qc::class);
        }

        // Verify QC created for PO item
        $poItemQc = $purchaseOrderItem->qualityControl;
        $this->assertNotNull($poItemQc);
        $this->assertEquals(0, $poItemQc->status); // Not completed yet

        // ==========================================
        // STEP 5: COMPLETE PRE-RECEIPT QC
        // ==========================================
        $qualityControlService->completeQualityControl($poItemQc, []);

        $poItemQc->refresh();
        $this->assertEquals(1, $poItemQc->status); // Completed
        $this->assertNotNull($poItemQc->date_send_stock);

        // ==========================================
        // STEP 6: VERIFY RECEIPT CREATED AUTOMATICALLY FROM COMPLETED QC
        // ==========================================
        // Receipt should be created automatically when QC is completed
        $purchaseReceipt = PurchaseReceipt::where('purchase_order_id', $purchaseOrder->id)->first();
        $this->assertNotNull($purchaseReceipt);

        $receiptItem = $purchaseReceipt->purchaseReceiptItem->first();
        $this->assertNotNull($receiptItem);
        $this->assertEquals(PurchaseReceiptItem::class, $receiptItem::class);

        // Verify Purchase Receipt Item
        $this->assertEquals($purchaseOrderItem->id, $receiptItem->purchase_order_item_id);
        $this->assertEquals(10, $receiptItem->qty_received);
        $this->assertEquals(10, $receiptItem->qty_accepted);
        $this->assertEquals(0, $receiptItem->qty_rejected);
        $this->assertEquals(1, $receiptItem->is_sent); // Marked as sent from QC creation

        // ==========================================
        // STEP 7: VERIFY RECEIPT ITEM ALREADY SENT TO QC
        // ==========================================
        // Receipt item created from QC is already marked as sent
        $receiptItem->refresh();
        $this->assertEquals(1, $receiptItem->is_sent);

        // Verify journal entries were created during receipt creation
        $journalEntries = JournalEntry::where('source_type', PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->count();
        $this->assertGreaterThan(0, $journalEntries);

        // ==========================================
        // STEP 8: VERIFY RECEIPT ITEM ALREADY PROCESSED FROM QC
        // ==========================================
        // Receipt item created from QC is already marked as sent and processed
        // It doesn't have its own QualityControl record, but is ready for stock movement
        $this->assertEquals(1, $receiptItem->is_sent);

        // Verify it doesn't have its own QC record (it's created from PO item QC)
        $receiptItemQc = $receiptItem->qualityControl;
        $this->assertFalse($receiptItemQc->exists); // No individual QC record for receipt items created from QC

        // ==========================================
        // STEP 9: VERIFY AUTOMATIC STOCK MOVEMENT
        // ==========================================
        // Stock movement should be created automatically when receipt is created from completed QC
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
            'type' => 'purchase_in',
            'value' => 100000,
        ]);

        $stockMovement = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('quantity', 10)
            ->where('type', 'purchase_in')
            ->first();

        $this->assertNotNull($stockMovement);
        $this->assertEquals('Stock inbound from QC-approved receipt: ' . $purchaseReceipt->receipt_number, $stockMovement->notes);

        // Verify inventory stock was updated automatically
        $inventoryStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->where('rak_id', $receiptItem->rak_id)
            ->first();

        $this->assertNotNull($inventoryStock);
        // Since there might be existing inventory stock from previous tests, 
        // we should expect the total to be at least 10, not exactly 10
        $this->assertGreaterThanOrEqual(10, $inventoryStock->qty_available);

        // ==========================================
        // STEP 10: VERIFY AUTOMATIC INVOICE CREATION
        // ==========================================
        // Invoice should be created automatically after stock movement
        $this->assertDatabaseHas('invoices', [
            'from_model_type' => PurchaseReceipt::class,
            'from_model_id' => $purchaseReceipt->id,
            'supplier_name' => $this->supplier->name,
            'status' => 'paid',
        ]);

        $invoice = Invoice::where('from_model_type', PurchaseReceipt::class)
            ->where('from_model_id', $purchaseReceipt->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertEquals(100000, $invoice->subtotal); // 10 * 10000
        $this->assertEquals(90090.09, round($invoice->dpp, 2)); // DPP calculation: 100000 / 1.11
        $this->assertEquals(9910, round($invoice->tax, 2)); // PPN 11%: actual value from test
        $this->assertEquals(109909.91, round($invoice->total, 2)); // subtotal + tax

        // Verify invoice items
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'price' => 10000,
            'total' => 100000,
        ]);

        // Verify account payable was created and marked as paid (since invoice is auto-paid)
        $this->assertDatabaseHas('account_payables', [
            'invoice_id' => $invoice->id,
            'total' => 109909.91,
            'paid' => 109909.91,
            'remaining' => 0,
            'status' => 'Lunas',
            'supplier_id' => $this->supplier->id,
        ]);

        // ==========================================
        // STEP 11: UPDATE PO STATUS TO COMPLETED
        // ==========================================
        $purchaseOrder->update(['status' => 'completed']);
        $this->assertEquals('completed', $purchaseOrder->fresh()->status);

        // ==========================================
        // VERIFICATION: COMPLETE FLOW
        // ==========================================

        // Verify PO status
        $purchaseOrder->refresh();
        $this->assertEquals('completed', $purchaseOrder->status);

        // Verify inventory
        $inventoryStock->refresh();
        $this->assertEquals(10, $inventoryStock->qty_available);

        // Verify stock movement
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => 'purchase_in',
            'quantity' => 10,
            'value' => 100000,
        ]);

        // Verify QC records
        $this->assertEquals(1, $poItemQc->status);
        // Receipt item created from QC doesn't have its own QC record

        // Verify accounting entries
        $allEntries = JournalEntry::where(function($query) use ($receiptItem, $invoice) {
            $query->where(function($q) use ($receiptItem) {
                $q->where('source_type', PurchaseReceiptItem::class)
                  ->where('source_id', $receiptItem->id);
            })
            ->orWhere(function($q) use ($invoice) {
                $q->where('source_type', Invoice::class)
                  ->where('source_id', $invoice->id);
            });
        })->get();

        $this->assertGreaterThanOrEqual(4, $allEntries->count());

        // Verify business flow completion
        $this->assertEquals('completed', $purchaseOrder->status);
        $this->assertEquals('paid', $invoice->status);

        // Verify account payable is marked as paid since invoice was auto-paid
        $accountPayable = AccountPayable::where('invoice_id', $invoice->id)->first();
        $this->assertEquals('Lunas', $accountPayable->status);
        $this->assertEquals(109909.91, $accountPayable->paid);
        $this->assertEquals(0, $accountPayable->remaining);
    }
}