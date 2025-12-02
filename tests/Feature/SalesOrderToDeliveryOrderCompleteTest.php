<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Driver;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DeliveryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderToDeliveryOrderCompleteTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $warehouse;
    protected $customer;
    protected $product;
    protected $driver;
    protected $vehicle;
    protected $deliveryOrderService;

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
        $this->cabang = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->customer = Customer::factory()->create();
        $this->product = Product::factory()->create([
            'cost_price' => 50000,
            'sell_price' => 75000,
        ]);
        $this->driver = Driver::factory()->create();
        $this->vehicle = Vehicle::factory()->create();

        // Create required COA for journal entries
        ChartOfAccount::create([
            'code' => '1140.10',
            'name' => 'PERSEDIAAN BARANG DAGANGAN - DEFAULT PRODUK',
            'type' => 'Asset',
            'is_active' => true,
        ]);
        ChartOfAccount::create([
            'code' => '1140.20',
            'name' => 'BARANG TERKIRIM',
            'type' => 'Asset',
            'is_active' => true,
        ]);
        ChartOfAccount::create([
            'code' => '1180.10',
            'name' => 'BARANG TERKIRIM - DEFAULT PRODUK',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->deliveryOrderService = new DeliveryOrderService();

        // Authenticate user
        $this->actingAs($this->user);
    }

    public function test_complete_flow_from_sales_order_to_delivery_order_complete_with_stock_movements()
    {
        // ==========================================
        // SETUP: Create initial inventory stock
        // ==========================================

        $initialStockQty = 20;
        $inventoryStock = InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_available' => $initialStockQty,
            'qty_reserved' => 0,
        ]);

        $this->assertDatabaseHas('inventory_stocks', [
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_available' => $initialStockQty,
            'qty_reserved' => 0,
        ]);

        // ==========================================
        // STEP 1: CREATE SALES ORDER
        // ==========================================

        $saleOrder = SaleOrder::create([
            'customer_id' => $this->customer->id,
            'so_number' => 'SO-' . now()->format('Ymd') . '-0001',
            'order_date' => now(),
            'status' => 'draft',
            'delivery_date' => now()->addDays(1),
            'total_amount' => 750000, // 10 units * 75000
            'tipe_pengiriman' => 'Kirim Langsung',
            'created_by' => $this->user->id,
        ]);

        $saleOrderItem = SaleOrderItem::create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 75000,
            'discount' => 0,
            'tax' => 0,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $inventoryStock->rak_id, // Use same rak_id as inventory stock
        ]);

        $this->assertDatabaseHas('sale_orders', [
            'id' => $saleOrder->id,
            'so_number' => $saleOrder->so_number,
            'status' => 'draft',
        ]);

        $this->assertDatabaseHas('sale_order_items', [
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        // ==========================================
        // STEP 2: APPROVE SALES ORDER
        // ==========================================

        $saleOrder->update([
            'status' => 'approved',
            'approve_by' => $this->user->id,
            'approve_at' => now(),
        ]);

        $this->assertEquals('confirmed', $saleOrder->fresh()->status); // Status changes to confirmed when WC is auto-approved

        // SaleOrderObserver should create WarehouseConfirmation automatically
        $warehouseConfirmation = $saleOrder->fresh()->warehouseConfirmation;
        $this->assertNotNull($warehouseConfirmation);
        $this->assertEquals('Confirmed', $warehouseConfirmation->status); // Auto-confirmed since stock is available

        // ==========================================
        // STEP 3: CHECK AUTO-CREATED DELIVERY ORDER
        // ==========================================

        $deliveryOrder = $saleOrder->fresh()->deliveryOrder()->first();
        $this->assertNotNull($deliveryOrder);
        $this->assertEquals('draft', $deliveryOrder->status);

        $deliveryOrderItem = $deliveryOrder->deliveryOrderItem()->first();
        $this->assertNotNull($deliveryOrderItem);
        $this->assertEquals(10, $deliveryOrderItem->quantity);

        // ==========================================
        // STEP 4: REQUEST APPROVE DELIVERY ORDER
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'request_approve');
        $this->assertEquals('request_approve', $deliveryOrder->fresh()->status);

        // ==========================================
        // STEP 5: CREATE AND PUBLISH SURAT JALAN
        // ==========================================

        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-' . now()->format('Ymd') . '-0001',
            'issued_at' => now(),
            'created_by' => $this->user->id,
            'status' => 1, // Published status
        ]);

        // Attach to delivery order
        $suratJalan->deliveryOrder()->attach($deliveryOrder->id);

        $this->assertDatabaseHas('surat_jalans', [
            'id' => $suratJalan->id,
            'sj_number' => $suratJalan->sj_number,
            'status' => 1,
        ]);

        echo "\n\n=== SURAT JALAN CREATED AND PUBLISHED ===";
        echo "\nSJ Number: {$suratJalan->sj_number}";
        echo "\nStatus: Published";

        // ==========================================
        // STEP 6: APPROVE DELIVERY ORDER
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'approved');
        $this->assertEquals('approved', $deliveryOrder->fresh()->status);

        echo "\n\n=== DELIVERY ORDER APPROVED ===";
        echo "\nStatus: {$deliveryOrder->fresh()->status}";

        // DeliveryOrderObserver should create stock reservations
        $stockReservations = StockReservation::where('delivery_order_id', $deliveryOrder->id)->get();
        $this->assertCount(1, $stockReservations);
        $reservation = $stockReservations->first();
        $this->assertEquals(10, $reservation->quantity);

        // Check inventory stock after reservation
        $inventoryStock->refresh();
        $this->assertEquals($initialStockQty - 10, $inventoryStock->qty_available); // 20 - 10 = 10
        $this->assertEquals(10, $inventoryStock->qty_reserved); // 0 + 10 = 10

        // ==========================================
        // STEP 7: SEND DELIVERY ORDER
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'sent');
        $this->assertEquals('sent', $deliveryOrder->fresh()->status);

        // DeliveryOrderObserver should release stock reservations
        $stockReservationsAfterSent = StockReservation::where('delivery_order_id', $deliveryOrder->id)->get();
        $this->assertCount(0, $stockReservationsAfterSent); // Should be deleted

        // Check inventory stock after releasing reservation
        $inventoryStock->refresh();
        $this->assertEquals($initialStockQty, $inventoryStock->qty_available); // Back to 20
        $this->assertEquals(0, $inventoryStock->qty_reserved); // Back to 0

        // ==========================================
        // STEP 8: RECEIVE DELIVERY ORDER
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'received');
        $this->assertEquals('received', $deliveryOrder->fresh()->status);

        // ==========================================
        // STEP 3: ADD ADDITIONAL COST TO DELIVERY ORDER
        // ==========================================

        // Add additional cost to delivery order before completing it
        $deliveryOrder->update([
            'additional_cost' => 50000, // Rp 50,000 shipping cost
            'additional_cost_description' => 'Biaya pengiriman ke Jakarta'
        ]);

        $deliveryOrder->refresh();
        $this->assertEquals(50000, $deliveryOrder->additional_cost);
        $this->assertEquals('Biaya pengiriman ke Jakarta', $deliveryOrder->additional_cost_description);

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'completed');
        $this->assertEquals('completed', $deliveryOrder->fresh()->status);

        // Stock movements are now created automatically when delivery order status changes to completed
        // Verify stock movements are created for sales
        $stockMovements = StockMovement::where('type', 'sales')
            ->where('notes', 'like', "%{$deliveryOrder->do_number}%")
            ->get();
        $this->assertCount(1, $stockMovements);
        $stockMovement = $stockMovements->first();
        $this->assertEquals(10, $stockMovement->quantity);
        $this->assertEquals('sales', $stockMovement->type);

        // Check final inventory stock
        $inventoryStock->refresh();
        $this->assertEquals($initialStockQty - 10, $inventoryStock->qty_available); // 20 - 10 = 10
        $this->assertEquals(0, $inventoryStock->qty_reserved); // Still 0

        // ==========================================
        // VERIFICATION: COMPLETE FLOW
        // ==========================================

        // Verify sales order status is completed (automatically updated when DO is completed)
        $saleOrder->refresh();
        $this->assertEquals('completed', $saleOrder->status);

        // Verify delivery order is completed
        $deliveryOrder->refresh();
        $this->assertEquals('completed', $deliveryOrder->status);

        // Verify surat jalan exists
        $this->assertTrue($deliveryOrder->suratJalan()->exists());

        // Verify delivery order item
        $this->assertEquals(1, $deliveryOrder->deliveryOrderItem()->count());
        $item = $deliveryOrder->deliveryOrderItem()->first();
        $this->assertEquals(10, $item->quantity);
        $this->assertEquals($saleOrderItem->id, $item->sale_order_item_id);

        // Verify sale order item remaining quantity (should be 0 after delivery)
        $saleOrderItem->refresh();
        $this->assertEquals(0, $saleOrderItem->remaining_quantity);
        $this->assertEquals(10, $saleOrderItem->delivered_quantity);

        // Verify invoice is automatically created when sales order is completed
        $invoice = \App\Models\Invoice::where('from_model_type', \App\Models\SaleOrder::class)
            ->where('from_model_id', $saleOrder->id)
            ->first();
        $this->assertNotNull($invoice, 'Invoice should be automatically created when sales order is completed');
        $this->assertStringStartsWith('INV-SO-', $invoice->invoice_number, 'Invoice number should start with INV-SO-');

        // Verify that delivery order additional costs are included in invoice
        $expectedSubtotal = 10 * 75000; // 10 items * Rp 75,000 each = Rp 750,000
        $expectedTax = 0; // Tax is set to 0 in the test sale order item
        $expectedAdditionalCosts = 50000; // Delivery order additional cost
        $expectedTotal = $expectedSubtotal + $expectedTax + $expectedAdditionalCosts;

        $this->assertEquals($expectedSubtotal, $invoice->subtotal, 'Invoice subtotal should match sale order items total');
        $this->assertEquals($expectedTax, $invoice->tax, 'Invoice tax should be 11% of subtotal');
        $this->assertEquals($expectedTotal, $invoice->total, 'Invoice total should include delivery order additional costs');

        // Verify other_fee contains delivery order costs
        $otherFees = $invoice->other_fee;
        $this->assertIsArray($otherFees, 'Invoice other_fee should be an array');
        $this->assertCount(1, $otherFees, 'Should have one other fee entry for delivery cost');

        $deliveryFee = $otherFees[0];
        $this->assertEquals(50000, $deliveryFee['amount'], 'Delivery cost amount should be 50,000');
        $this->assertEquals('Biaya pengiriman ke Jakarta', $deliveryFee['description'], 'Delivery cost description should match');
        $this->assertEquals('delivery_cost', $deliveryFee['type'], 'Fee type should be delivery_cost');
        $this->assertEquals($deliveryOrder->do_number, $deliveryFee['reference'], 'Fee reference should be delivery order number');
    }
}