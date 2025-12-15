<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Rak;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\SalesOrderService;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderSelfPickupToInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $warehouse;
    protected $rak;
    protected $customer;
    protected $product;
    protected $productCategory;
    protected $salesOrderService;
    protected $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'first_name' => 'Test',
            'kode_user' => 'TU001',
        ]);

        $this->cabang = Cabang::create([
            'nama' => 'Test Cabang',
            'kode' => 'TC001',
            'alamat' => 'Jl. Test No. 123',
            'telepon' => '021-1234567',
        ]);

        $this->warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH001',
            'kode' => 'WH001',
            'cabang_id' => $this->cabang->id,
            'branch_id' => $this->cabang->id,
            'address' => 'Jl. Test Warehouse',
            'location' => 'Jakarta',
        ]);

        $this->rak = Rak::create([
            'name' => 'Test Rak',
            'code' => 'R001',
            'warehouse_id' => $this->warehouse->id,
            'type' => 'shelf',
        ]);

        $this->productCategory = ProductCategory::create([
            'name' => 'Test Category',
            'code' => 'PC001',
            'kode' => 'PC001',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'code' => 'CUST001',
            'address' => 'Jl. Test Customer',
            'telephone' => '021-7654321',
            'phone' => '081234567890',
            'email' => 'test@customer.com',
            'perusahaan' => 'PT Test Customer',
            'tipe' => 'PKP',
            'fax' => '021-7654322',
            'nik_npwp' => '1234567890123456',
            'tempo_kredit' => 30,
            'kredit_limit' => 10000000,
            'tipe_pembayaran' => 'Kredit',
            'keterangan' => 'Test customer for self pickup',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->product = Product::create([
            'name' => 'Test Product',
            'sku' => 'PROD001',
            'cabang_id' => $this->cabang->id,
            'product_category_id' => $this->productCategory->id,
            'sell_price' => 100000,
            'cost_price' => 80000,
            'kode_merk' => 'TEST',
            'uom_id' => 1,
            'is_active' => true,
            'is_manufacture' => false,
            'is_raw_material' => false,
        ]);

        // Create inventory stock
        InventoryStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'qty_available' => 50,
            'qty_reserved' => 0,
        ]);

        // Create required COA
        ChartOfAccount::create([
            'code' => '1120',
            'name' => 'PIUTANG DAGANG',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '4000',
            'name' => 'PENJUALAN',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '2120.06',
            'name' => 'PPN KELUARAN',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '4100.01',
            'name' => 'POTONGAN PENJUALAN',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '6100.02',
            'name' => 'BIAYA PENGIRIMAN / PENGANGKUTAN',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '1140.10',
            'name' => 'PERSEDIAAN BARANG DAGANGAN',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->salesOrderService = app(SalesOrderService::class);
        $this->invoiceService = app(InvoiceService::class);

        // Authenticate user
        $this->actingAs($this->user);
    }

    public function test_complete_flow_from_sales_order_self_pickup_to_invoice()
    {
        // ==========================================
        // STEP 1: CREATE SALES ORDER WITH SELF PICKUP
        // ==========================================

        $saleOrder = SaleOrder::create([
            'customer_id' => $this->customer->id,
            'so_number' => 'SO-' . now()->format('Ymd') . '-0001',
            'order_date' => now(),
            'status' => 'draft',
            'delivery_date' => now()->addDays(1),
            'total_amount' => 500000, // 5 units * 100000
            'tipe_pengiriman' => 'Ambil Sendiri',
            'created_by' => $this->user->id,
        ]);

        $saleOrderItem = SaleOrderItem::create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 100000,
            'discount' => 0,
            'tax' => 0,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
        ]);

        $this->assertDatabaseHas('sale_orders', [
            'id' => $saleOrder->id,
            'so_number' => $saleOrder->so_number,
            'status' => 'draft',
            'tipe_pengiriman' => 'Ambil Sendiri',
        ]);

        $this->assertDatabaseHas('sale_order_items', [
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        // ==========================================
        // STEP 2: APPROVE SALES ORDER
        // ==========================================

        $result = $this->salesOrderService->requestApprove($saleOrder);
        $this->assertTrue($result);

        $result = $this->salesOrderService->approve($saleOrder);
        $this->assertTrue($result);

        $saleOrder->refresh();
        $this->assertEquals('approved', $saleOrder->status);

        // For self pickup, warehouse confirmation and stock reservation are not created (only for "Kirim Langsung")
        // Stock will be reduced directly when completed

        // ==========================================
        // STEP 3: COMPLETE SALES ORDER (NO DELIVERY ORDER NEEDED)
        // ==========================================

        $result = $this->salesOrderService->completed($saleOrder);
        $this->assertTrue($result);

        $saleOrder->refresh();
        $this->assertEquals('completed', $saleOrder->status);

        // ==========================================
        // STEP 4: CREATE INVOICE FROM COMPLETED SALES ORDER
        // ==========================================

        $invoice = Invoice::create([
            'invoice_number' => $this->invoiceService->generateInvoiceNumber(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_date' => now(),
            'subtotal' => 500000,
            'tax' => 0,
            'total' => 500000,
            'due_date' => now()->addDays(30),
            'status' => 'draft',
            'cabang_id' => $this->cabang->id,
        ]);

        $invoiceItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 100000,
            'total' => 500000,
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'status' => 'draft',
            'total' => 500000,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'total' => 500000,
        ]);

        // ==========================================
        // STEP 5: VERIFY RELATIONSHIPS
        // ==========================================

        // Invoice should be linked to SaleOrder
        $this->assertEquals($saleOrder->id, $invoice->fromModel->id);
        $this->assertInstanceOf(SaleOrder::class, $invoice->fromModel);

        // ==========================================
        // STEP 6: VERIFY STOCK MOVEMENTS (STOCK SHOULD BE REDUCED WHEN COMPLETED)
        // ==========================================

        // For self pickup, stock should be reduced when completed
        $inventoryStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        // Stock should be reduced by 5 units
        $this->assertEquals(45, $inventoryStock->qty_available);
    }
}