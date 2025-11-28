<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
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
use App\Services\PurchaseReceiptService;
use App\Services\QualityControlService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteProcurementAccountingFlowTest extends TestCase
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
        $unbilledPurchaseCoa = ChartOfAccount::where('code', '2100.10')->first(); // Updated to use new liability COA
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
    public function complete_procurement_flow_with_full_accounting()
    {
        // ==========================================
        // STEP 1: CREATE ORDER REQUEST
        // ==========================================
        $orderRequest = OrderRequest::factory()->create([
            'request_number' => 'OR-20251112-0001',
            'request_date' => now(),
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $orderRequestItem = OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'note' => 'Complete procurement flow test',
        ]);

        $this->assertDatabaseHas('order_requests', [
            'id' => $orderRequest->id,
            'status' => 'approved',
            'request_number' => 'OR-20251112-0001'
        ]);

        $this->assertDatabaseHas('order_request_items', [
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        // ==========================================
        // STEP 2: CREATE PURCHASE ORDER
        // ==========================================
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-20251112-0001',
            'order_date' => now(),
            'expected_date' => now()->addDays(7),
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'Eklusif',
            'refer_item_model_id' => $orderRequestItem->id,
            'refer_item_model_type' => OrderRequestItem::class,
            'currency_id' => $this->currency->id,
        ]);

        $this->assertDatabaseHas('purchase_orders', [
            'id' => $purchaseOrder->id,
            'status' => 'approved',
            'po_number' => 'PO-20251112-0001',
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
        ]);

        // ==========================================
        // STEP 3: CREATE PURCHASE RECEIPT
        // ==========================================
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'receipt_number' => 'RN-20251112-0001',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
            'other_cost' => 0,
        ]);

        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'is_sent' => false,
        ]);

        $this->assertDatabaseHas('purchase_receipts', [
            'id' => $purchaseReceipt->id,
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_number' => 'RN-20251112-0001',
            'status' => 'completed'
        ]);

        $this->assertDatabaseHas('purchase_receipt_items', [
            'purchase_receipt_id' => $purchaseReceipt->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'is_sent' => false,
        ]);

        // ==========================================
        // STEP 4: SEND TO QUALITY CONTROL
        // ==========================================
        $purchaseReceiptService = app(PurchaseReceiptService::class);
        $qcResult = $purchaseReceiptService->createTemporaryProcurementEntriesForReceiptItem($receiptItem);

        $this->assertEquals('posted', $qcResult['status']);
        $this->assertCount(2, $qcResult['entries']);

        // Verify receipt item is marked as sent
        $receiptItem->refresh();
        $this->assertEquals(1, $receiptItem->is_sent);

        // ==========================================
        // STEP 5: COMPLETE QUALITY CONTROL
        // ==========================================
        $qualityControl = QualityControl::factory()->create([
            'qc_number' => 'QC-20251112-0001',
            'from_model_type' => PurchaseReceiptItem::class,
            'from_model_id' => $receiptItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'passed_quantity' => 10,
            'rejected_quantity' => 0,
            'status' => 0, // Not completed yet
            'inspected_by' => $this->user->id,
            'notes' => 'All items passed QC',
        ]);

        $this->assertDatabaseHas('quality_controls', [
            'id' => $qualityControl->id,
            'qc_number' => 'QC-20251112-0001',
            'passed_quantity' => 10,
            'rejected_quantity' => 0,
            'status' => 0,
        ]);

        $qualityControlService = app(QualityControlService::class);
        $completeResult = $qualityControlService->completeQualityControl($qualityControl, []);

        // Verify QC is completed
        $qualityControl->refresh();
        $this->assertEquals(1, $qualityControl->status);
        $this->assertNotNull($qualityControl->date_send_stock);

        // ==========================================
        // STEP 6: VERIFY INVENTORY STOCK UPDATED
        // ==========================================
        $inventoryStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertNotNull($inventoryStock);
        $this->assertEquals(10, $inventoryStock->qty_available);
        $this->assertEquals(0, $inventoryStock->qty_reserved);

        // Verify stock movement was created
        $stockMovement = StockMovement::where('product_id', $this->product->id)
            ->where('type', 'purchase_in')
            ->where('quantity', 10)
            ->first();

        $this->assertNotNull($stockMovement);
        $this->assertEquals($this->warehouse->id, $stockMovement->warehouse_id);
        $this->assertEquals(100000, $stockMovement->value); // 10 * 10000

        // ==========================================
        // STEP 7: CREATE PURCHASE INVOICE (INV PEMBELIAN)
        // ==========================================
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-20251112-0001',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'supplier_name' => $this->supplier->name,
            'supplier_phone' => $this->supplier->phone ?? null,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100000,
            'tax' => 10000, // 10% of subtotal
            'total' => 110000, // subtotal + tax
            'status' => 'sent', // Invoice is sent/posted
        ]);

        $invoiceItem = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'price' => 10000,
            'total' => 100000,
        ]);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-20251112-0001',
            'subtotal' => 100000,
            'tax' => 10000,
            'total' => 110000,
            'status' => 'sent'
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'price' => 10000,
            'total' => 100000,
        ]);

        // Post the invoice to ledger
        $ledgerService = app(\App\Services\LedgerPostingService::class);
        $invoicePosting = $ledgerService->postInvoice($invoice->fresh());
        $this->assertEquals('posted', $invoicePosting['status']);

        // ==========================================
        // STEP 8: CREATE ACCOUNT PAYABLE
        // ==========================================
        $accountPayable = AccountPayable::factory()->create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total' => 110000,
            'paid' => 0,
            'remaining' => 110000,
            'status' => 'Belum Lunas',
            'created_by' => $this->user->id,
        ]);

        $this->assertDatabaseHas('account_payables', [
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total' => 110000,
            'paid' => 0,
            'remaining' => 110000,
            'status' => 'Belum Lunas'
        ]);

        // ==========================================
        // STEP 9: CREATE VENDOR PAYMENT
        // ==========================================
        $vendorPayment = VendorPayment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'payment_date' => now(),
            'ntpn' => 'NTPN20251112001',
            'total_payment' => 110000,
            'coa_id' => $this->cashCoa->id,
            'payment_method' => 'Cash',
            'status' => 'Paid',
            'notes' => 'Complete procurement flow payment',
        ]);

        $this->assertDatabaseHas('vendor_payments', [
            'supplier_id' => $this->supplier->id,
            'total_payment' => 110000,
            'payment_method' => 'Cash',
            'status' => 'Paid'
        ]);

        // Create vendor payment detail linking to invoice
        $paymentDetail = VendorPaymentDetail::factory()->create([
            'vendor_payment_id' => $vendorPayment->id,
            'invoice_id' => $invoice->id,
            'method' => 'Cheque',
            'amount' => 110000,
            'coa_id' => $this->cashCoa->id,
            'payment_date' => now(),
            'notes' => 'Payment detail for complete procurement flow',
        ]);

        $this->assertDatabaseHas('vendor_payment_details', [
            'vendor_payment_id' => $vendorPayment->id,
            'invoice_id' => $invoice->id,
            'amount' => 110000,
        ]);

        // Update account payable status after payment
        $accountPayable->update([
            'paid' => 110000,
            'remaining' => 0,
            'status' => 'Lunas'
        ]);

        $accountPayable->refresh();
        $this->assertEquals('Lunas', $accountPayable->status);
        $this->assertEquals(0, $accountPayable->remaining);

        // ==========================================
        // VERIFICATION: COMPLETE ACCOUNTING FLOW
        // ==========================================

        // Get all journal entries for this procurement flow
        $procurementEntries = JournalEntry::where(function($query) use ($receiptItem) {
            $query->where('source_type', PurchaseReceiptItem::class)
                  ->where('source_id', $receiptItem->id);
        })->get();

        // Get all entries for the complete flow (QC + Invoice + Payment)
        $allProcurementEntries = JournalEntry::where(function($query) use ($receiptItem, $invoice, $vendorPayment) {
            $query->where(function($q) use ($receiptItem) {
                $q->where('source_type', PurchaseReceiptItem::class)
                  ->where('source_id', $receiptItem->id);
            })
            ->orWhere(function($q) use ($invoice) {
                $q->where('source_type', Invoice::class)
                  ->where('source_id', $invoice->id);
            })
            ->orWhere(function($q) use ($vendorPayment) {
                $q->where('source_type', VendorPayment::class)
                  ->where('source_id', $vendorPayment->id);
            });
        })->get();

        // Should have entries from:
        // 1. QC Send (2 entries: temp procurement debit, unbilled purchase credit)
        // 2. QC Complete (2 entries: inventory debit, temp procurement credit)
        // 3. Invoice (2 entries: unbilled purchase debit, AP credit)
        // 4. Payment (2 entries: AP debit, cash credit)
        $this->assertGreaterThanOrEqual(8, $allProcurementEntries->count());

        // ==========================================
        // VERIFICATION: TEMPORARY PROCUREMENT ENTRIES (QC Send)
        // ==========================================
        $tempProcurementDebit = $procurementEntries->where('debit', 100000)
            ->where('credit', 0)
            ->where('journal_type', 'procurement')
            ->first();
        $this->assertNotNull($tempProcurementDebit);
        $this->assertEquals($this->product->temporary_procurement_coa_id, $tempProcurementDebit->coa_id);
        $this->assertTrue(strpos($tempProcurementDebit->description, 'Temporary Procurement') !== false);

        $unbilledPurchaseCredit = $procurementEntries->where('debit', 0)
            ->where('credit', 100000)
            ->where('journal_type', 'procurement')
            ->first();
        $this->assertNotNull($unbilledPurchaseCredit);
        $this->assertEquals($this->product->unbilled_purchase_coa_id, $unbilledPurchaseCredit->coa_id);

        // ==========================================
        // VERIFICATION: INVENTORY ENTRIES (QC Complete)
        // ==========================================
        $inventoryDebit = $procurementEntries->where('debit', 100000)
            ->where('journal_type', 'inventory')
            ->first();
        $this->assertNotNull($inventoryDebit);
        $this->assertEquals($this->product->inventory_coa_id, $inventoryDebit->coa_id);
        $this->assertTrue(strpos($inventoryDebit->description, 'Inventory Stock') !== false);

        $tempProcurementCloseCredit = $procurementEntries->where('debit', 0)
            ->where('credit', 100000)
            ->where('journal_type', 'inventory')
            ->first();
        $this->assertNotNull($tempProcurementCloseCredit);
        $this->assertEquals($this->product->temporary_procurement_coa_id, $tempProcurementCloseCredit->coa_id);
        $this->assertTrue(strpos($tempProcurementCloseCredit->description, 'Close Temporary Procurement') !== false);

        // ==========================================
        // VERIFICATION: INVOICE ENTRIES
        // ==========================================
        $invoiceEntries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertCount(3, $invoiceEntries); // unbilled purchase debit, PPN masukan debit, AP credit

        $unbilledPurchaseDebit = $invoiceEntries->where('debit', 100000)->where('credit', 0)->first();
        $this->assertNotNull($unbilledPurchaseDebit);
        $this->assertEquals($this->product->unbilled_purchase_coa_id, $unbilledPurchaseDebit->coa_id);

        $ppnMasukanDebit = $invoiceEntries->where('debit', 10000)->where('credit', 0)->first();
        $this->assertNotNull($ppnMasukanDebit);

        $apCredit = $invoiceEntries->where('debit', 0)->where('credit', 110000)->first();
        $this->assertNotNull($apCredit);
        $this->assertEquals($this->apCoa->id, $apCredit->coa_id);

        // ==========================================
        // VERIFICATION: PAYMENT ENTRIES
        // ==========================================
        $paymentEntries = JournalEntry::where('source_type', VendorPayment::class)
            ->where('source_id', $vendorPayment->id)
            ->get();

        $this->assertCount(2, $paymentEntries);

        $apDebit = $paymentEntries->where('debit', 110000)->where('credit', 0)->first();
        $this->assertNotNull($apDebit);
        $this->assertEquals($this->apCoa->id, $apDebit->coa_id);

        $cashCredit = $paymentEntries->where('debit', 0)->where('credit', 110000)->first();
        $this->assertNotNull($cashCredit);
        $this->assertEquals($this->cashCoa->id, $cashCredit->coa_id);

        // ==========================================
        // VERIFICATION: FINAL BALANCE SHEET
        // ==========================================

        // Inventory Asset: +100000 (from QC completion)
        $this->inventoryCoa->load('journalEntries');
        $this->assertEquals(100000, $this->inventoryCoa->calculateEndingBalance());

        // Unbilled Purchase Liability: 0 (credited 100000 from QC send, debited 100000 from invoice)
        $this->unbilledPurchaseCoa->load('journalEntries');
        $this->assertEquals(0, $this->unbilledPurchaseCoa->calculateEndingBalance());

        // Temporary Procurement: 0 (debited 100000 from QC send, credited 100000 from QC complete)
        $this->temporaryProcurementCoa->load('journalEntries');
        $this->assertEquals(0, $this->temporaryProcurementCoa->calculateEndingBalance());

        // Account Payable: 0 (credited 110000 from invoice, debited 110000 from payment)
        $this->apCoa->load('journalEntries');
        $this->assertEquals(0, $this->apCoa->calculateEndingBalance());

        // PPN Masukan: +10000 (debited from invoice)
        $this->ppnMasukanCoa->load('journalEntries');
        $this->assertEquals(10000, $this->ppnMasukanCoa->calculateEndingBalance());

        // Cash: -110000 (credited 110000 from payment)
        $this->cashCoa->load('journalEntries');
        $this->assertEquals(-110000, $this->cashCoa->calculateEndingBalance());

        // ==========================================
        // VERIFICATION: DOUBLE-ENTRY BOOKKEEPING
        // ==========================================
        $allEntries = JournalEntry::whereIn('id', $allProcurementEntries->pluck('id'))
            ->orWhereIn('id', $invoiceEntries->pluck('id'))
            ->orWhereIn('id', $paymentEntries->pluck('id'))
            ->get();

        $totalDebit = $allEntries->sum('debit');
        $totalCredit = $allEntries->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(420000, $totalDebit); // Total of all procurement journal entries

        // ==========================================
        // VERIFICATION: BUSINESS FLOW COMPLETION
        // ==========================================
        $orderRequest->refresh();
        $this->assertEquals('approved', $orderRequest->status);

        $purchaseOrder->refresh();
        $this->assertEquals('approved', $purchaseOrder->status);

        $purchaseReceipt->refresh();
        $this->assertEquals('completed', $purchaseReceipt->status);

        $qualityControl->refresh();
        $this->assertEquals(1, $qualityControl->status);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);

        $accountPayable->refresh();
        $this->assertEquals('Lunas', $accountPayable->status);

        $vendorPayment->refresh();
        $this->assertEquals('Paid', $vendorPayment->status);

        // Final inventory check
        $inventoryStock->refresh();
        $this->assertEquals(10, $inventoryStock->qty_available);
    }
}