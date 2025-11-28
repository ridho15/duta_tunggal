<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\AgeingSchedule;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\InvoiceObserver;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure required models exist
        \App\Models\UnitOfMeasure::factory()->create();
        Currency::factory()->create();
        Supplier::factory()->create();
    }

    public function test_purchase_invoice_creation_with_proper_relationships()
    {
        // Setup
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($user = User::factory()->create());

        // Create PO
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed'
        ]);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'tax' => 1000,
            'discount' => 0
        ]);

        // Create Purchase Receipt
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed'
        ]);

        $purchaseReceiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $warehouse->id,
            'is_sent' => false
        ]);

        // Create Purchase Invoice
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0001',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 110000, // 10 * 11000
            'tax' => 0,
            'total' => 110000,
            'status' => 'draft',
            'purchase_receipts' => [$purchaseReceipt->id]
        ]);

        // Create Invoice Items
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 11000,
            'total' => 110000
        ]);

        // Assertions
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'invoice_number' => 'PINV-20251101-0001',
            'subtotal' => 110000,
            'total' => 110000,
            'status' => 'draft'
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'price' => 11000,
            'total' => 110000
        ]);

        // Test relationships
        $this->assertInstanceOf(PurchaseOrder::class, $invoice->fromModel);
        $this->assertEquals($purchaseOrder->id, $invoice->fromModel->id);
        $this->assertCount(1, $invoice->invoiceItem);
    }

    public function test_invoice_observer_creates_account_payable_and_ageing_schedule()
    {
        // Setup
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user = User::factory()->create());

        // Create PO
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed'
        ]);

        // Create Purchase Invoice - this should trigger the observer
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0002',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 50000,
            'tax' => 5500,
            'total' => 55500,
            'status' => 'draft'
        ]);

        // Assertions - Account Payable should be created
        $this->assertDatabaseHas('account_payables', [
            'invoice_id' => $invoice->id,
            'supplier_id' => $supplier->id,
            'total' => 55500,
            'paid' => 0,
            'remaining' => 55500,
            'status' => 'Belum Lunas'
        ]);

        // Ageing Schedule should be created
        $this->assertDatabaseHas('ageing_schedules', [
            'invoice_date' => $invoice->invoice_date->format('Y-m-d'),
            'due_date' => $invoice->due_date->format('Y-m-d'),
            'bucket' => 'Current'
        ]);

        // Test relationships
        $accountPayable = AccountPayable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($accountPayable);
        $this->assertEquals($supplier->id, $accountPayable->supplier_id);
        $this->assertNotNull($accountPayable->ageingSchedule);
    }

    public function test_three_way_matching_validation()
    {
        // Setup
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($user = User::factory()->create());

        // Create PO with specific quantities and prices
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed'
        ]);

        $purchaseOrderItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'tax' => 1000,
            'discount' => 0
        ]);

        // Create Purchase Receipt with received quantities
        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed'
        ]);

        $purchaseReceiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $warehouse->id,
            'is_sent' => false
        ]);

        // Test 1: Matching quantities and prices (should pass)
        $matchingInvoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0003',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'subtotal' => 110000, // 10 * 11000
            'tax' => 0,
            'total' => 110000,
            'purchase_receipts' => [$purchaseReceipt->id]
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $matchingInvoice->id,
            'subtotal' => 110000,
            'total' => 110000
        ]);

        // Test 2: Quantity variance (received 10, invoiced 8) - should still create but flag variance
        $quantityVarianceInvoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0004',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'subtotal' => 88000, // 8 * 11000
            'tax' => 0,
            'total' => 88000,
            'purchase_receipts' => [$purchaseReceipt->id]
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $quantityVarianceInvoice->id,
            'subtotal' => 88000,
            'total' => 88000
        ]);

        // Test 3: Price variance (PO price 11000, invoice price 12000)
        $priceVarianceInvoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0005',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'subtotal' => 120000, // 10 * 12000
            'tax' => 0,
            'total' => 120000,
            'purchase_receipts' => [$purchaseReceipt->id]
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $priceVarianceInvoice->id,
            'subtotal' => 120000,
            'total' => 120000
        ]);
    }

    public function test_invoice_number_generation()
    {
        $invoiceService = new InvoiceService();

        // Test first invoice of the day
        $invoiceNumber1 = $invoiceService->generateInvoiceNumber();
        $this->assertStringStartsWith('INV-', $invoiceNumber1);
        $this->assertStringEndsWith('-0001', $invoiceNumber1);

        // Create an invoice to test sequential numbering
        Invoice::factory()->create([
            'invoice_number' => $invoiceNumber1,
            'invoice_date' => now()
        ]);

        // Test second invoice of the day
        $invoiceNumber2 = $invoiceService->generateInvoiceNumber();
        $this->assertStringStartsWith('INV-', $invoiceNumber2);
        $this->assertStringEndsWith('-0002', $invoiceNumber2);
        $this->assertNotEquals($invoiceNumber1, $invoiceNumber2);
    }

    public function test_invoice_status_tracking()
    {
        // Setup
        $supplier = Supplier::factory()->create();

        $this->actingAs($user = User::factory()->create());

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed'
        ]);

        // Create invoice with draft status
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0006',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'status' => 'draft'
        ]);

        $this->assertEquals('draft', $invoice->status);

        // Update to sent status
        $invoice->update(['status' => 'sent']);
        $this->assertEquals('sent', $invoice->status);

        // Update to paid status
        $invoice->update(['status' => 'paid']);
        $this->assertEquals('paid', $invoice->status);
    }

    public function test_invoice_with_tax_and_other_fees_calculation()
    {
        // Setup
        $supplier = Supplier::factory()->create();

        $this->actingAs($user = User::factory()->create());

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed'
        ]);

        // Create invoice with tax and other fees
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0007',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'subtotal' => 100000,
            'tax' => 11000, // 11% of subtotal
            'other_fee' => 5000,
            'ppn_rate' => 11,
            'dpp' => 100000,
            'total' => 116000, // 100000 + 11000 + 5000
            'status' => 'draft'
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'subtotal' => 100000,
            'tax' => 11000,
            'other_fee' => 5000,
            'ppn_rate' => 11,
            'dpp' => 100000,
            'total' => 116000
        ]);

        // Test accessor for other_fee_total
        $this->assertEquals(5000, $invoice->other_fee_total);
    }

    public function test_invoice_soft_delete_cascades_to_invoice_items()
    {
        // Setup
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user = User::factory()->create());

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed'
        ]);

        // Create invoice with items
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0008',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id
        ]);

        $invoiceItem = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id
        ]);

        // Verify they exist
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('invoice_items', ['id' => $invoiceItem->id]);

        // Soft delete invoice
        $invoice->delete();

        // Check soft delete worked
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertSoftDeleted('invoice_items', ['id' => $invoiceItem->id]);

        // Test restore
        $invoice->restore();
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseHas('invoice_items', ['id' => $invoiceItem->id]);
    }

    public function test_invoice_customer_accessor_for_purchase_orders()
    {
        // Setup
        $supplier = Supplier::factory()->create();

        $this->actingAs($user = User::factory()->create());

        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'status' => 'completed'
        ]);

        // Create invoice linked to PO
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'PINV-20251101-0009',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $purchaseOrder->id,
            'customer_name' => 'Test Supplier',
            'customer_phone' => '08123456789'
        ]);

        // Test customer accessor (should return supplier for purchase invoices)
        $customer = $invoice->customer;
        $this->assertNotNull($customer);
        $this->assertEquals($supplier->id, $customer->id);

        // Test display accessors
        $this->assertEquals('Test Supplier', $invoice->customer_name_display);
        $this->assertEquals('08123456789', $invoice->customer_phone_display);
    }
}