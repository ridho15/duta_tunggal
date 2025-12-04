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

class CompleteDeliveryOrderFlowTest extends TestCase
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

    public function test_complete_delivery_order_flow_with_journal_entries_and_stock_movements()
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
            'rak_id' => $inventoryStock->rak_id,
        ]);

        // ==========================================
        // STEP 2: APPROVE SALES ORDER
        // ==========================================

        $saleOrder->update([
            'status' => 'approved',
            'approve_by' => $this->user->id,
            'approve_at' => now(),
        ]);

        // SaleOrderObserver should create WarehouseConfirmation automatically
        $warehouseConfirmation = $saleOrder->fresh()->warehouseConfirmation;
        $this->assertNotNull($warehouseConfirmation);
        $this->assertEquals('Confirmed', $warehouseConfirmation->status);

        // ==========================================
        // STEP 3: CREATE DELIVERY ORDER FROM SALES ORDER
        // ==========================================

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-' . now()->format('Ymd') . '-0001',
            'delivery_date' => now(),
            'warehouse_id' => $this->warehouse->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $deliveryOrderItem = DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $inventoryStock->rak_id,
            'sale_order_item_id' => $saleOrderItem->id,
        ]);

        // Link delivery order to sales order
        $deliveryOrder->salesOrders()->attach($saleOrder->id);

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

        // ==========================================
        // STEP 6: APPROVE DELIVERY ORDER
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'approved');
        $this->assertEquals('approved', $deliveryOrder->fresh()->status);

        // DeliveryOrderObserver should create stock reservations
        $stockReservations = StockReservation::where('delivery_order_id', $deliveryOrder->id)->get();
        $this->assertCount(1, $stockReservations);
        $reservation = $stockReservations->first();
        $this->assertEquals(10, $reservation->quantity);

        // Check inventory stock after reservation
        $inventoryStock->refresh();
        $this->assertEquals($initialStockQty - 10, $inventoryStock->qty_available);
        $this->assertEquals(10, $inventoryStock->qty_reserved);

        // ==========================================
        // STEP 7: SEND DELIVERY ORDER (THIS SHOULD CREATE JOURNAL ENTRIES)
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'sent');
        $this->assertEquals('sent', $deliveryOrder->fresh()->status);

        // DeliveryOrderObserver should release stock reservations
        $stockReservationsAfterSent = StockReservation::where('delivery_order_id', $deliveryOrder->id)->get();
        $this->assertCount(0, $stockReservationsAfterSent);

        // Check inventory stock after releasing reservation
        $inventoryStock->refresh();
        $this->assertEquals($initialStockQty, $inventoryStock->qty_available);
        $this->assertEquals(0, $inventoryStock->qty_reserved);

        // CHECK JOURNAL ENTRIES CREATED WHEN STATUS BECAME 'sent'
        $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->get();

        $this->assertCount(2, $journalEntries, 'Should have 2 journal entries: debit COGS and credit inventory');

        // Check debit entry (COGS)
        $debitEntry = $journalEntries->where('debit', '>', 0)->first();
        $this->assertNotNull($debitEntry, 'Should have debit journal entry for COGS');
        $this->assertEquals(500000, $debitEntry->debit); // 10 units * 50000 cost
        $this->assertEquals(0, $debitEntry->credit);
        $this->assertTrue(strpos($debitEntry->description, 'Cost of Goods Sold') !== false, 'Should contain Cost of Goods Sold in description');

        // Check credit entry (Inventory reduction)
        $creditEntry = $journalEntries->where('credit', '>', 0)->first();
        $this->assertNotNull($creditEntry, 'Should have credit journal entry for inventory reduction');
        $this->assertEquals(0, $creditEntry->debit);
        $this->assertEquals(500000, $creditEntry->credit); // 10 units * 50000 cost
        $this->assertTrue(strpos($creditEntry->description, 'Inventory Reduction') !== false, 'Should contain Inventory Reduction in description');

        // ==========================================
        // STEP 8: RECEIVE DELIVERY ORDER
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'received');
        $this->assertEquals('received', $deliveryOrder->fresh()->status);

        // ==========================================
        // STEP 9: COMPLETE DELIVERY ORDER (THIS SHOULD CREATE STOCK MOVEMENTS)
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'completed');
        $this->assertEquals('completed', $deliveryOrder->fresh()->status);

        // CHECK STOCK MOVEMENTS CREATED WHEN STATUS BECAME 'completed'
        $stockMovements = StockMovement::where('type', 'sales')
            ->where('from_model_type', \App\Models\DeliveryOrderItem::class)
            ->whereIn('from_model_id', $deliveryOrder->deliveryOrderItem->pluck('id'))
            ->get();

        $this->assertCount(1, $stockMovements, 'Should have 1 stock movement for sales');
        $stockMovement = $stockMovements->first();
        $this->assertEquals(10, $stockMovement->quantity);
        $this->assertEquals('sales', $stockMovement->type);
        $this->assertEquals($this->product->id, $stockMovement->product_id);
        $this->assertEquals($this->warehouse->id, $stockMovement->warehouse_id);

        // CHECK INVENTORY STOCK UPDATED BY STOCK MOVEMENT OBSERVER
        $inventoryStock->refresh();
        $this->assertEquals($initialStockQty - 10, $inventoryStock->qty_available); // 20 - 10 = 10
        $this->assertEquals(0, $inventoryStock->qty_reserved);

        // CHECK SALES ORDER UPDATED TO COMPLETED
        $saleOrder->refresh();
        $this->assertEquals('completed', $saleOrder->status);

        // CHECK SALE ORDER ITEM DELIVERED QUANTITY UPDATED
        $saleOrderItem->refresh();
        $this->assertEquals(10, $saleOrderItem->delivered_quantity);

        // CHECK INVOICE CREATED WITH CUSTOMER DATA
        $invoice = \App\Models\Invoice::where('from_model_type', \App\Models\SaleOrder::class)
            ->where('from_model_id', $saleOrder->id)
            ->first();
        
        $this->assertNotNull($invoice, 'Invoice should be created for completed sale order');
        $this->assertEquals($this->customer->name, $invoice->customer_name, 'Invoice customer_name should match sale order customer');
        $this->assertEquals($this->customer->phone, $invoice->customer_phone, 'Invoice customer_phone should match sale order customer');

        echo "\n\n=== DELIVERY ORDER COMPLETE FLOW TEST PASSED ===";
        echo "\n✅ Journal entries created: " . $journalEntries->count();
        echo "\n✅ Stock movements created: " . $stockMovements->count();
        echo "\n✅ Inventory stock updated: available=" . $inventoryStock->qty_available . ", reserved=" . $inventoryStock->qty_reserved;
        echo "\n✅ Sales order status: " . $saleOrder->status;
        echo "\n✅ Invoice created with customer data: name='" . $invoice->customer_name . "', phone='" . $invoice->customer_phone . "'";
    }
}