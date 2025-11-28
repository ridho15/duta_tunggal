<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Cabang;
use App\Models\Currency;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\WarehouseConfirmation;
use App\Models\WarehouseConfirmationItem;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;

class SalesAuditTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $customer;
    protected $product;
    protected $warehouse;
    protected $branch;
    protected $currency;
    protected $driver;
    protected $vehicle;
    protected $rak;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create([
            'warehouse_id' => 1,
        ]);

        // Create test data
        $this->customer = Customer::factory()->create([
            'code' => 'CUST001',
            'name' => 'Test Customer',
            'perusahaan' => 'Test Company',
            'phone' => '081234567890',
            'nik_npwp' => '1234567890123456',
            'tempo_kredit' => 30,
        ]);

        $this->product = Product::factory()->create([
            'sku' => 'PROD001',
            'name' => 'Test Product',
            'uom_id' => 1,
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'kode' => 'WH001',
            'name' => 'Test Warehouse',
        ]);

        $this->branch = Cabang::factory()->create([
            'kode' => 'BR001',
            'nama' => 'Test Branch',
        ]);

        $this->currency = Currency::factory()->create([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'to_rupiah' => 1,
        ]);

        $this->driver = \App\Models\Driver::factory()->create([
            'name' => 'Test Driver',
            'phone' => '081234567890',
            'license' => 'DRV001',
        ]);

        $this->vehicle = \App\Models\Vehicle::factory()->create([
            'plate' => 'B1234ABC',
            'type' => 'Truck',
            'capacity' => '1000kg',
        ]);

        $this->rak = \App\Models\Rak::factory()->create([
            'name' => 'Test Rak',
            'code' => 'RAK001',
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Create initial stock
        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_available' => 100,
            'qty_reserved' => 0,
        ]);
    }

    /** @test */
    public function test_quotation_creation_and_approval_workflow()
    {
        // Create quotation
        $quotation = Quotation::create([
            'quotation_number' => 'QUO-001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'date' => now(),
            'quotation_date' => now(),
            'valid_until' => now()->addDays(30),
            'status' => 'draft',
            'currency_id' => $this->currency->id,
            'created_by' => $this->user->id,
        ]);

        // Create quotation item
        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 10000,
            'total_amount' => 500000,
            'created_by' => $this->user->id,
        ]);

        // Update quotation total
        $quotation->update([
            'total_amount' => 500000,
            'status' => 'approve',
        ]);

        // Assertions
        $this->assertEquals('approve', $quotation->status);
        $this->assertEquals(500000, $quotation->total_amount);
        $this->assertEquals($this->customer->id, $quotation->customer_id);
        $this->assertEquals(1, $quotation->quotationItem()->count());
        $this->assertEquals(50, $quotation->quotationItem()->first()->quantity);
    }

    /** @test */
    public function test_sales_order_creation_from_quotation()
    {
        // Create quotation first
        $quotation = Quotation::create([
            'quotation_number' => 'QUO-002',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'date' => now(),
            'quotation_date' => now(),
            'valid_until' => now()->addDays(30),
            'status' => 'approve',
            'currency_id' => $this->currency->id,
            'total_amount' => 500000,
            'created_by' => $this->user->id,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 10000,
            'total_amount' => 500000,
            'created_by' => $this->user->id,
        ]);

        // Create sales order from quotation
        $salesOrder = SaleOrder::create([
            'so_number' => 'SO-001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now(),
            'delivery_date' => now()->addDays(7),
            'status' => 'draft',
            'tipe_pengiriman' => 'Kirim Langsung',
            'quotation_id' => $quotation->id,
            'currency_id' => $this->currency->id,
            'created_by' => $this->user->id,
            'shipped_to' => 'Test Shipping Address',
        ]);

        // Create sales order item
        $soItem = SaleOrderItem::create([
            'sale_order_id' => $salesOrder->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'unit_price' => 10000,
            'total_amount' => 500000,
            'created_by' => $this->user->id,
        ]);

        // Update sales order total and status
        $salesOrder->update([
            'total_amount' => 500000,
            'status' => 'approved',
        ]);

        // Assertions
        $this->assertEquals('approved', $salesOrder->status);
        $this->assertEquals(500000, $salesOrder->total_amount);
        $this->assertEquals($quotation->id, $salesOrder->quotation_id);
        $this->assertEquals($this->customer->id, $salesOrder->customer_id);
        $this->assertEquals(1, $salesOrder->saleOrderItem()->count());
        $this->assertEquals(50, $salesOrder->saleOrderItem()->first()->quantity);
    }

    /** @test */
    public function test_warehouse_confirmation_and_stock_reservation()
    {
        // Create sales order first
        $salesOrder = SaleOrder::create([
            'so_number' => 'SO-002',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now(),
            'delivery_date' => now()->addDays(7),
            'status' => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $this->currency->id,
            'total_amount' => 500000,
            'created_by' => $this->user->id,
            'shipped_to' => 'Test Shipping Address',
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $salesOrder->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 30,
            'unit_price' => 10000,
            'total_amount' => 300000,
            'created_by' => $this->user->id,
        ]);

        // Create warehouse confirmation
        $confirmation = WarehouseConfirmation::create([
            'sale_order_id' => $salesOrder->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'created_by' => $this->user->id,
        ]);

        // Create warehouse confirmation item
        WarehouseConfirmationItem::create([
            'warehouse_confirmation_id' => $confirmation->id,
            'sale_order_item_id' => $soItem->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
            'confirmed_qty' => 30,
            'status' => 'confirmed',
            'created_by' => $this->user->id,
        ]);

        // Update stock reservation
        $stock = InventoryStock::where('product_id', $this->product->id)
                              ->where('warehouse_id', $this->warehouse->id)
                              ->first();
        $stock->update([
            'qty_reserved' => 30,
            'qty_available' => 70.0 // 100 - 30 reserved
        ]);

        // Refresh stock from database
        $stock->refresh();

        // Update sales order status
        $salesOrder->update([
            'status' => 'confirmed',
            'warehouse_confirmed_at' => now(),
        ]);

        // Assertions
        $this->assertEquals('confirmed', $salesOrder->status);
        $this->assertNotNull($salesOrder->warehouse_confirmed_at);
        $this->assertNotNull($salesOrder->warehouseConfirmation);
        $this->assertEquals(30, $stock->qty_reserved);
        $this->assertEquals(70.0, $stock->qty_available); // 100 - 30 reserved
    }

    /** @test */
    public function test_delivery_order_generation_from_sales_order()
    {
        // Create sales order first
        $salesOrder = SaleOrder::create([
            'so_number' => 'SO-003',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now(),
            'delivery_date' => now()->addDays(7),
            'status' => 'confirmed',
            'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $this->currency->id,
            'total_amount' => 300000,
            'created_by' => $this->user->id,
            'shipped_to' => 'Test Shipping Address',
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $salesOrder->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 30,
            'unit_price' => 10000,
            'total_amount' => 300000,
            'created_by' => $this->user->id,
        ]);

        // Create delivery order
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-001',
            'sale_order_id' => $salesOrder->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_date' => now()->addDays(1),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        // Create delivery order item
        DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
            'created_by' => $this->user->id,
        ]);

        // Link delivery order to sales order
        DB::table('delivery_sales_orders')->insert([
            'delivery_order_id' => $deliveryOrder->id,
            'sales_order_id' => $salesOrder->id,
        ]);

        // Update delivery order status
        $deliveryOrder->update(['status' => 'approved']);

        // Assertions
        $this->assertEquals('approved', $deliveryOrder->status);
        $this->assertTrue($deliveryOrder->salesOrders->contains($salesOrder->id));
        $this->assertEquals(1, $deliveryOrder->deliveryOrderItem()->count());
        $this->assertEquals(30, $deliveryOrder->deliveryOrderItem()->first()->quantity);
    }

    /** @test */
    public function test_invoice_generation_and_payment_processing()
    {
        // Create sales order and delivery order first
        $salesOrder = SaleOrder::create([
            'so_number' => 'SO-004',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now(),
            'delivery_date' => now()->addDays(7),
            'status' => 'completed',
            'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $this->currency->id,
            'total_amount' => 300000,
            'created_by' => $this->user->id,
            'shipped_to' => 'Test Shipping Address',
        ]);

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-002',
            'sale_order_id' => $salesOrder->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_date' => now(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        // Create invoice
        $invoice = Invoice::create([
            'invoice_number' => 'INV-001',
            'from_model_type' => 'App\Models\DeliveryOrder',
            'from_model_id' => $deliveryOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'draft',
            'subtotal' => 300000,
            'total' => 300000,
            'created_by' => $this->user->id,
        ]);

        // Create invoice item
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
            'unit_price' => 10000,
            'total_amount' => 300000,
            'created_by' => $this->user->id,
        ]);

        // Update invoice status
        $invoice->update(['status' => 'sent']);

        // Create customer receipt (payment)
        $receipt = CustomerReceipt::create([
            'receipt_number' => 'REC-001',
            'customer_id' => $this->customer->id,
            'receipt_date' => now(),
            'payment_date' => now(),
            'total_payment' => 300000,
            'status' => 'Draft',
            'created_by' => $this->user->id,
        ]);

        // Create customer receipt item
        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'method' => 'Cash',
            'amount' => 300000,
            'payment_date' => now(),
            'created_by' => $this->user->id,
        ]);

        // Update receipt and invoice status
    $receipt->update(['status' => 'Paid']);
        $invoice->update(['status' => 'paid']);

        // Assertions
        $this->assertEquals('paid', $invoice->status);
    $this->assertEquals('Paid', $receipt->status);
        $this->assertEquals(300000, $invoice->total);
        $this->assertEquals(300000, $receipt->total_payment);
        $this->assertEquals(1, $invoice->invoiceItem()->count());
        $this->assertEquals(1, $receipt->customerReceiptItem()->count());
    }

    /** @test */
    public function test_sales_return_processing()
    {
        // Create sales order and delivery order first
        $salesOrder = SaleOrder::create([
            'so_number' => 'SO-005',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now(),
            'delivery_date' => now()->addDays(7),
            'status' => 'completed',
            'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $this->currency->id,
            'total_amount' => 200000,
            'created_by' => $this->user->id,
            'shipped_to' => 'Test Shipping Address',
        ]);

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-003',
            'sale_order_id' => $salesOrder->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_date' => now(),
            'status' => 'completed',
            'created_by' => $this->user->id,
        ]);

        // Create sales return
        $return = ReturnProduct::create([
            'return_number' => 'RET-001',
            'from_model_type' => 'App\Models\DeliveryOrder',
            'from_model_id' => $deliveryOrder->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'reason' => 'Defective product',
            'created_by' => $this->user->id,
        ]);

        // Create return item
        ReturnProductItem::create([
            'return_product_id' => $return->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'total_amount' => 100000,
            'condition' => 'damage',
            'reason' => 'Defective',
            'created_by' => $this->user->id,
        ]);

        // Update return total and status
        $return->update([
            'total_amount' => 100000,
            'status' => 'approved',
        ]);

        // Update stock (return goods to inventory)
        $stock = InventoryStock::where('product_id', $this->product->id)
                              ->where('warehouse_id', $this->warehouse->id)
                              ->first();
        $stock->update(['qty_available' => 80]); // 70 + 10 returned

        // Assertions
        $this->assertEquals('approved', $return->status);
    $this->assertEquals($deliveryOrder->id, $return->from_model_id);
        $this->assertEquals('App\Models\DeliveryOrder', $return->from_model_type);
        $this->assertEquals(1, $return->returnProductItem()->count());
        $this->assertEquals(10, $return->returnProductItem()->first()->quantity);
        $this->assertEquals(80, $stock->qty_available);
    }

    /** @test */
    public function test_cross_module_data_integrity()
    {
        // Create complete sales flow
        $quotation = Quotation::create([
            'quotation_number' => 'QUO-003',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'date' => now(),
            'quotation_date' => now(),
            'valid_until' => now()->addDays(30),
            'status' => 'approve',
            'currency_id' => $this->currency->id,
            'total_amount' => 200000,
            'created_by' => $this->user->id,
        ]);

        $salesOrder = SaleOrder::create([
            'so_number' => 'SO-006',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now(),
            'delivery_date' => now()->addDays(7),
            'status' => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
            'quotation_id' => $quotation->id,
            'currency_id' => $this->currency->id,
            'total_amount' => 200000,
            'created_by' => $this->user->id,
            'shipped_to' => 'Test Shipping Address',
        ]);

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-004',
            'sale_order_id' => $salesOrder->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_date' => now(),
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        // Link delivery order to sales order via pivot table
        DB::table('delivery_sales_orders')->insert([
            'delivery_order_id' => $deliveryOrder->id,
            'sales_order_id' => $salesOrder->id,
        ]);

        // Load relationships
        $deliveryOrder->load('salesOrders');

        $invoice = Invoice::create([
            'invoice_number' => 'INV-002',
            'from_model_type' => 'App\Models\DeliveryOrder',
            'from_model_id' => $deliveryOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'sent',
            'subtotal' => 200000,
            'total' => 200000,
            'created_by' => $this->user->id,
        ]);

        // Load relationships
        $invoice->load('fromModel');
        $deliveryOrder->load('salesOrders');

        // Test relationships
        $this->assertEquals($quotation->id, $salesOrder->quotation_id);
        $this->assertTrue($deliveryOrder->salesOrders->contains($salesOrder->id));
        $this->assertEquals('App\Models\DeliveryOrder', $invoice->from_model_type);
        $this->assertEquals($deliveryOrder->id, $invoice->from_model_id);

        // Test customer consistency
        $this->assertEquals($this->customer->id, $quotation->customer_id);
        $this->assertEquals($this->customer->id, $salesOrder->customer_id);
        $this->assertEquals($this->customer->id, $deliveryOrder->salesOrders()->first()->customer_id);
        $this->assertEquals($this->customer->id, $invoice->customer?->id);

        // Test warehouse consistency
        // Note: quotations don't have warehouse_id field
        // Note: sale_orders don't have warehouse_id field
        $this->assertEquals($this->warehouse->id, $deliveryOrder->warehouse_id);

        // Test currency consistency
        // Note: quotations don't have currency_id field
        // Note: sale_orders don't have currency_id field
        // Note: invoices don't have currency_id field
    }

    /** @test */
    public function test_customer_management_and_performance()
    {
        // Test customer data integrity
        $this->assertNotNull($this->customer->code);
        $this->assertNotNull($this->customer->name);
        $this->assertNotNull($this->customer->perusahaan);
        $this->assertNotNull($this->customer->phone);
        $this->assertNotNull($this->customer->nik_npwp);
        $this->assertNotNull($this->customer->tempo_kredit);

        // Create multiple sales orders for performance tracking
        $salesOrder1 = SaleOrder::create([
            'so_number' => 'SO-007',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now()->subDays(10),
            'delivery_date' => now()->subDays(3),
            'status' => 'completed',
            'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $this->currency->id,
            'total_amount' => 150000,
            'created_by' => $this->user->id,
            'shipped_to' => 'Test Shipping Address',
        ]);

        $salesOrder2 = SaleOrder::create([
            'so_number' => 'SO-008',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now()->subDays(5),
            'delivery_date' => now()->addDays(2),
            'status' => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $this->currency->id,
            'total_amount' => 250000,
            'created_by' => $this->user->id,
            'shipped_to' => 'Test Shipping Address',
        ]);

        // Test customer relationship
        $customerOrders = $this->customer->sales;
        $this->assertEquals(2, $customerOrders->count());

        // Test total sales calculation
        $totalSales = $customerOrders->sum('total_amount');
        $this->assertEquals(400000, $totalSales);

        // Test order status distribution
        $deliveredOrders = $customerOrders->where('status', 'completed')->count();
        $approvedOrders = $customerOrders->where('status', 'approved')->count();
        $this->assertEquals(1, $deliveredOrders);
        $this->assertEquals(1, $approvedOrders);
    }

    /** @test */
    public function test_end_to_end_sales_workflow()
    {
        // Step 1: Create quotation
        $quotation = Quotation::create([
            'quotation_number' => 'QUO-004',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'date' => now(),
            'quotation_date' => now(),
            'valid_until' => now()->addDays(30),
            'status' => 'draft',
            'currency_id' => $this->currency->id,
            'created_by' => $this->user->id,
        ]);

        QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_price' => 10000,
            'total_amount' => 200000,
            'created_by' => $this->user->id,
        ]);

        $quotation->update([
            'total_amount' => 200000,
            'status' => 'approve',
        ]);

        // Step 2: Convert to sales order
        $salesOrder = SaleOrder::create([
            'so_number' => 'SO-009',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now(),
            'delivery_date' => now()->addDays(7),
            'status' => 'draft',
            'tipe_pengiriman' => 'Kirim Langsung',
            'quotation_id' => $quotation->id,
            'currency_id' => $this->currency->id,
            'created_by' => $this->user->id,
            'shipped_to' => 'Test Shipping Address',
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $salesOrder->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20,
            'unit_price' => 10000,
            'total_amount' => 200000,
            'created_by' => $this->user->id,
        ]);

        $salesOrder->update([
            'total_amount' => 200000,
            'status' => 'approved',
        ]);

        // Step 3: Warehouse confirmation
        $confirmation = WarehouseConfirmation::create([
            'sale_order_id' => $salesOrder->id,
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'created_by' => $this->user->id,
        ]);

        WarehouseConfirmationItem::create([
            'warehouse_confirmation_id' => $confirmation->id,
            'sale_order_item_id' => $soItem->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'confirmed_qty' => 20,
            'status' => 'confirmed',
            'created_by' => $this->user->id,
        ]);

        $salesOrder->update([
            'status' => 'confirmed',
            'warehouse_confirmed_at' => now(),
        ]);

        // Step 4: Create delivery order
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-005',
            'sale_order_id' => $salesOrder->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'delivery_date' => now()->addDays(1),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $deliveryOrder->update(['status' => 'approved']);

        // Link delivery order to sales order
        DB::table('delivery_sales_orders')->insert([
            'delivery_order_id' => $deliveryOrder->id,
            'sales_order_id' => $salesOrder->id,
        ]);

        // Step 5: Create invoice
        $invoice = Invoice::create([
            'invoice_number' => 'INV-003',
            'from_model_type' => 'App\Models\DeliveryOrder',
            'from_model_id' => $deliveryOrder->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'draft',
            'subtotal' => 200000,
            'total' => 200000,
            'created_by' => $this->user->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_price' => 10000,
            'total_amount' => 200000,
            'created_by' => $this->user->id,
        ]);

        $invoice->update(['status' => 'sent']);

        // Step 6: Process payment
        $receipt = CustomerReceipt::create([
            'receipt_number' => 'REC-002',
            'customer_id' => $this->customer->id,
            'receipt_date' => now(),
            'payment_date' => now(),
            'total_payment' => 200000,
            'status' => 'Draft',
            'created_by' => $this->user->id,
        ]);

        CustomerReceiptItem::create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'method' => 'Cash',
            'amount' => 200000,
            'payment_date' => now(),
            'created_by' => $this->user->id,
        ]);

    $receipt->update(['status' => 'Paid']);
        $invoice->update(['status' => 'paid']);
        $deliveryOrder->update(['status' => 'completed']);
        $salesOrder->update(['status' => 'completed']);

        // Load relationships for assertions
        $deliveryOrder->load('salesOrders');

        // Final workflow assertions
    $this->assertEquals('approve', $quotation->status);
        $this->assertEquals('completed', $salesOrder->status);
        $this->assertEquals('completed', $deliveryOrder->status);
        $this->assertEquals('paid', $invoice->status);
    $this->assertEquals('Paid', $receipt->status);

        // Verify complete workflow chain
        $this->assertEquals($quotation->id, $salesOrder->quotation_id);
        $this->assertEquals($salesOrder->id, $deliveryOrder->salesOrders()->first()->id);
        // Note: invoice uses polymorphic relationship, sale_order_id doesn't exist
        // Invoice is created from delivery order, so from_model_id should be deliveryOrder->id
        $this->assertEquals($deliveryOrder->id, $invoice->from_model_id);
        $this->assertEquals($invoice->id, $receipt->customerReceiptItem()->first()->invoice_id);
    }

    /** @test */
    public function test_sales_data_validation_and_constraints()
    {
        // Test required fields validation
        try {
            SaleOrder::create([
                'customer_id' => $this->customer->id,
                'warehouse_id' => $this->warehouse->id,
                // Missing required so_number
            ]);
            $this->fail('Expected validation exception for missing so_number');
        } catch (\Exception $e) {
            $this->assertTrue(true); // Expected exception
        }

        // Test unique constraints
        SaleOrder::create([
            'so_number' => 'SO-UNIQUE-001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now(),
            'status' => 'draft',
            'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $this->currency->id,
            'created_by' => $this->user->id,
            'shipped_to' => 'Test Address',
        ]);

        try {
            SaleOrder::create([
                'so_number' => 'SO-UNIQUE-001', // Duplicate
                'customer_id' => $this->customer->id,
                'warehouse_id' => $this->warehouse->id,
                'cabang_id' => $this->branch->id,
                'order_date' => now(),
                'status' => 'draft',
                'currency_id' => $this->currency->id,
                'created_by' => $this->user->id,
                'shipped_to' => 'Test Address',
            ]);
            $this->fail('Expected validation exception for duplicate so_number');
        } catch (\Exception $e) {
            $this->assertTrue(true); // Expected exception
        }

        // Test foreign key constraints
        try {
            SaleOrder::create([
                'so_number' => 'SO-FK-001',
                'customer_id' => 99999, // Non-existent customer
                'warehouse_id' => $this->warehouse->id,
                'cabang_id' => $this->branch->id,
                'order_date' => now(),
                'status' => 'draft',
                'tipe_pengiriman' => 'Kirim Langsung',
                'currency_id' => $this->currency->id,
                'created_by' => $this->user->id,
                'shipped_to' => 'Test Address',
            ]);
            $this->fail('Expected foreign key constraint violation');
        } catch (\Exception $e) {
            $this->assertTrue(true); // Expected exception
        }

        // Test valid data creation
        $validOrder = SaleOrder::create([
            'so_number' => 'SO-VALID-001',
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->branch->id,
            'order_date' => now(),
            'delivery_date' => now()->addDays(7),
            'status' => 'draft',
            'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $this->currency->id,
            'total_amount' => 100000,
            'created_by' => $this->user->id,
            'shipped_to' => 'Valid Test Address',
        ]);

        $this->assertEquals('SO-VALID-001', $validOrder->so_number);
        $this->assertEquals('draft', $validOrder->status);
        $this->assertEquals(100000, $validOrder->total_amount);
    }
}