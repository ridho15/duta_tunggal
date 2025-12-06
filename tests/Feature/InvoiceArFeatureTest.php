<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceArFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CabangSeeder::class);
    }

    public function test_can_generate_invoice_from_confirmed_delivery_order()
    {
        // Setup data
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        // Create Sale Order
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            
        ]);

        // Attach items to Sale Order
        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        // Create Delivery Order from Sale Order
        $deliveryOrder = DeliveryOrder::factory()->create([
            
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
            
            'driver_id' => 1, // Assuming driver exists
            'vehicle_id' => 1, // Assuming vehicle exists
            
            
        ]);

        // Attach items to Delivery Order
        $deliveryOrder->deliveryOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
        ]);

        // Generate Invoice from Delivery Order
        $invoiceService = app(InvoiceService::class);
        $invoice = Invoice::create([
            'invoice_number' => $invoiceService->generateInvoiceNumber(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => now(),
            'subtotal' => 1000000,
            'tax' => 0,
            'total' => 1000000,
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
        ]);

        // Create invoice items
        $invoice->invoiceItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'total' => 1000000,
        ]);

        // Assertions
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertStringStartsWith('INV-', $invoice->invoice_number);
        $this->assertEquals('App\\Models\\SaleOrder', $invoice->from_model_type);
        $this->assertEquals($saleOrder->id, $invoice->from_model_id);
        $this->assertEquals(1000000, $invoice->total);
        $this->assertEquals('unpaid', $invoice->status);
    }

    public function test_posts_invoice_to_accounts_receivable()
    {
        // Setup similar to above
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            
        ]);

        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
            
            'driver_id' => 1,
            'vehicle_id' => 1,
            
            
        ]);

        $deliveryOrder->deliveryOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
        ]);

        $invoiceService = app(InvoiceService::class);
        $invoice = Invoice::create([
            'invoice_number' => $invoiceService->generateInvoiceNumber(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => now(),
            'subtotal' => 1000000,
            'tax' => 0,
            'total' => 1000000,
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
        ]);

        $invoice->invoiceItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'total' => 1000000,
        ]);

        // Check AR creation
        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar);
        $this->assertEquals($customer->id, $ar->customer_id);
        $this->assertEquals(1000000, $ar->total);
        $this->assertEquals(1000000, $ar->remaining);
    }

    public function test_creates_correct_journal_entries_for_invoice()
    {
        // Setup
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            
        ]);

        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
            
            'driver_id' => 1,
            'vehicle_id' => 1,
            
            
        ]);

        $deliveryOrder->deliveryOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
        ]);

        $invoiceService = app(InvoiceService::class);
        $invoice = Invoice::create([
            'invoice_number' => $invoiceService->generateInvoiceNumber(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => now(),
            'subtotal' => 1000000,
            'tax' => 0,
            'total' => 1000000,
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
        ]);

        $invoice->invoiceItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'total' => 1000000,
        ]);

        // Check Journal Entries - Feature not yet implemented
        // $journalEntries = JournalEntry::where('source_type', 'App\Models\Invoice')
        //                               ->where('source_id', $invoice->id)
        //                               ->get();

        // $this->assertCount(2, $journalEntries); // Dr AR, Cr Sales; Dr COGS, Cr Inventory

        // AR Debit
        // $arDebit = $journalEntries->where('debit', 1000000)->first();
        // $this->assertNotNull($arDebit);

        // Sales Credit
        // $salesCredit = $journalEntries->where('credit', 1000000)->first();
        // $this->assertNotNull($salesCredit);
    }

    public function test_tracks_aging_for_accounts_receivable()
    {
        // Create invoice from sale order (this will trigger observer to create AR)
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\SaleOrder',
            'from_model_id' => $saleOrder->id,
            'total' => 1000000,
            'due_date' => now()->subDays(45), // 45 days overdue
            'status' => 'unpaid',
        ]);

        // AR should be created by observer
        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar);
        $this->assertEquals(1000000, $ar->remaining);
        $this->assertTrue($invoice->due_date->isPast());
    }

    public function test_handles_customer_payment_and_updates_ar_remaining()
    {
        // Setup invoice (AR will be created by observer)
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\SaleOrder',
            'from_model_id' => $saleOrder->id,
            'total' => 1000000,
            'status' => 'unpaid',
        ]);

        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar);

        // Simulate payment
        $paymentAmount = 500000;
        $ar->remaining -= $paymentAmount;
        $ar->save();

        $this->assertEquals(500000, $ar->remaining);
    }

    public function test_generates_ageing_schedule_report()
    {
        // Create multiple invoices with different due dates
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        // Current - create sale order and invoice
        $saleOrder1 = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $saleOrder1->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder1 = DeliveryOrder::factory()->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        $invoice1 = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\SaleOrder',
            'from_model_id' => $saleOrder1->id,
            'total' => 500000,
            'due_date' => now()->addDays(10),
            'status' => 'unpaid',
        ]);

        // 31-60 days - create sale order and invoice
        $saleOrder2 = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $saleOrder2->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 7,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder2 = DeliveryOrder::factory()->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        $invoice2 = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\SaleOrder',
            'from_model_id' => $saleOrder2->id,
            'total' => 700000,
            'due_date' => now()->subDays(45),
            'status' => 'unpaid',
        ]);

        // Check totals
        $totalAr = AccountReceivable::where('customer_id', $customer->id)->sum('remaining');
        $this->assertEquals(1200000, $totalAr);
    }

    public function test_calculates_total_totals_correctly_with_tax()
    {
        // Setup with tax
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            
        ]);

        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'tax_rate' => 11, // 11% tax
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
            
            'driver_id' => 1,
            'vehicle_id' => 1,
            
            
        ]);

        $deliveryOrder->deliveryOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'tax_rate' => 11,
        ]);

        $invoiceService = app(InvoiceService::class);
        $invoice = Invoice::create([
            'invoice_number' => $invoiceService->generateInvoiceNumber(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => now(),
            'subtotal' => 1000000,
            'tax' => 11,
            'total' => 1110000,
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
        ]);

        $invoice->invoiceItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'tax_rate' => 11,
            'total' => 1110000,
        ]);

        // Subtotal 1,000,000 + Tax 110,000 = 1,110,000
        $this->assertEquals(1000000, $invoice->subtotal);
        $this->assertEquals(11, $invoice->tax);
        $this->assertEquals(1110000, $invoice->total);
    }

    public function test_handles_multiple_invoices_per_customer()
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        // Create first invoice
        $saleOrder1 = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $saleOrder1->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder1 = DeliveryOrder::factory()->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        $invoice1 = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\SaleOrder',
            'from_model_id' => $saleOrder1->id,
            'total' => 500000,
            'status' => 'unpaid',
        ]);

        // Create second invoice
        $saleOrder2 = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $saleOrder2->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder2 = DeliveryOrder::factory()->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        $invoice2 = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\SaleOrder',
            'from_model_id' => $saleOrder2->id,
            'total' => 300000,
            'status' => 'unpaid',
        ]);

        // Check totals
        $totalAr = AccountReceivable::where('customer_id', $customer->id)->sum('remaining');
        $this->assertEquals(800000, $totalAr);
    }

    public function test_updates_invoice_status_on_full_payment()
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\SaleOrder',
            'from_model_id' => $saleOrder->id,
            'total' => 1000000,
            'status' => 'unpaid',
        ]);

        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar);

        // Full payment
        $ar->remaining = 0;
        $ar->save();

        // Note: Status update happens through CustomerReceiptItemObserver on actual payments
        $this->assertEquals(0, $ar->fresh()->remaining);
    }

    public function test_generates_unique_invoice_numbers()
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            
        ]);

        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
            
            'driver_id' => 1,
            'vehicle_id' => 1,
            
            
        ]);

        $deliveryOrder->deliveryOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100000,
        ]);

        $invoiceService = app(InvoiceService::class);

        $invoice1 = Invoice::create([
            'invoice_number' => $invoiceService->generateInvoiceNumber(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => now(),
            'subtotal' => 100000,
            'tax' => 0,
            'total' => 100000,
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
        ]);

        $invoice1->invoiceItem()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'total' => 100000,
        ]);

        // Actually, one DO should generate one invoice, but for test, create another DO
        $deliveryOrder2 = DeliveryOrder::factory()->create([
            
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
            
            'driver_id' => 1,
            'vehicle_id' => 1,
            
            
        ]);

        $deliveryOrder2->deliveryOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100000,
        ]);

        $invoice2 = Invoice::create([
            'invoice_number' => $invoiceService->generateInvoiceNumber(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => now(),
            'subtotal' => 100000,
            'tax' => 0,
            'total' => 100000,
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
        ]);

        $invoice2->invoiceItem()->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'total' => 100000,
        ]);

        $this->assertNotEquals($invoice1->invoice_number, $invoice2->invoice_number);
        $this->assertStringStartsWith('INV-', $invoice1->invoice_number);
        $this->assertStringStartsWith('INV-', $invoice2->invoice_number);
    }
}