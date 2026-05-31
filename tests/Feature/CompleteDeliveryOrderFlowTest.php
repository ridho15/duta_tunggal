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
use App\Models\Rak;
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

/**
 * Test complete DO flow with journal entries and stock movements
 * Updated for the bug fix: StockMovement is created at 'sent' status, not 'completed'
 */
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
        $this->cabang = \App\Models\Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->customer = Customer::factory()->create();
        $this->driver = Driver::factory()->create();
        $this->vehicle = Vehicle::factory()->create();

        // Create required COA
        $this->createRequiredCoas();

        $this->product = Product::factory()->create([
            'cost_price' => 50000,
            'sell_price' => 75000,
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.10')->value('id'),
            'goods_delivery_coa_id' => ChartOfAccount::where('code', '1140.20')->value('id'),
            'cogs_coa_id' => ChartOfAccount::where('code', '5100.10')->value('id'),
        ]);

        $this->deliveryOrderService = new DeliveryOrderService();
        $this->actingAs($this->user);
    }

    protected function createRequiredCoas(): void
    {
        $coaCodes = ['1120', '4000', '2120.06', '4100.01', '1140.10', '1140.20', '1180.10', '5100.10'];
        foreach ($coaCodes as $code) {
            ChartOfAccount::create([
                'code' => $code,
                'name' => 'COA ' . $code,
                'type' => $code >= '4000' ? 'Revenue' : 'Asset',
                'is_active' => true,
            ]);
        }
    }

    public function test_complete_delivery_order_flow_with_journal_entries_and_stock_movements()
    {
        // ==========================================
        // SETUP: Create initial inventory stock with SPECIFIC values
        // ==========================================

        $initialStockQty = 20;
        $rak = Rak::where('warehouse_id', $this->warehouse->id)->first();
        if (!$rak) {
            $rak = Rak::factory()->create(['warehouse_id' => $this->warehouse->id]);
        }

        $inventoryStock = InventoryStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $rak->id,
            'qty_available' => $initialStockQty,
            'qty_reserved' => 0,
            'qty_min' => 10,
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
            'total_amount' => 750000,
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
            'rak_id' => $rak->id,
        ]);

        // ==========================================
        // STEP 2: APPROVE SALES ORDER
        // ==========================================

        $saleOrder->update([
            'status' => 'approved',
            'approve_by' => $this->user->id,
            'approve_at' => now(),
        ]);

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
            'rak_id' => $rak->id,
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
            'status' => 1,
        ]);

        $suratJalan->deliveryOrder()->attach($deliveryOrder->id);

        // ==========================================
        // STEP 6: APPROVE DELIVERY ORDER
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'approved');
        $this->assertEquals('approved', $deliveryOrder->fresh()->status);

        // DeliveryOrderObserver should create stock reservations
        $stockReservations = StockReservation::where('delivery_order_id', $deliveryOrder->id)->get();
        $this->assertCount(1, $stockReservations, 'Stock reservation should be created');
        $reservation = $stockReservations->first();
        $this->assertEquals(10, $reservation->quantity);

        // ==========================================
        // STEP 7: SEND DELIVERY ORDER (BUG FIX: Creates StockMovement, keeps reservation)
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'sent');
        $this->assertEquals('sent', $deliveryOrder->fresh()->status);

        // BUG FIX VERIFICATION: StockReservation is NOT deleted - it remains for tracking
        $stockReservationsAfterSent = StockReservation::where('delivery_order_id', $deliveryOrder->id)->get();
        $this->assertCount(1, $stockReservationsAfterSent, 'Reservation should NOT be deleted after sent');

        // BUG FIX VERIFICATION: StockMovement should be created when status changes to 'sent'
        $stockMovementsAtSent = StockMovement::where('type', 'sales')
            ->where('from_model_type', DeliveryOrderItem::class)
            ->whereIn('from_model_id', $deliveryOrder->deliveryOrderItem->pluck('id'))
            ->get();
        $this->assertCount(1, $stockMovementsAtSent, 'StockMovement should be created at sent');

        // Verify StockMovement has shipping_start meta
        $stockMovement = $stockMovementsAtSent->first();
        $meta = is_string($stockMovement->meta) ? json_decode($stockMovement->meta, true) : $stockMovement->meta;
        $this->assertArrayHasKey('shipping_start', $meta, 'StockMovement should have shipping_start meta');
        $this->assertTrue($meta['shipping_start'], 'shipping_start should be true');

        // ==========================================
        // STEP 8: COMPLETE DELIVERY ORDER
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'completed');
        $this->assertEquals('completed', $deliveryOrder->fresh()->status);

        // CHECK JOURNAL ENTRIES ARE CREATED WHEN STATUS BECOMES 'completed'
        $journalEntries = \App\Models\JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->get();

        $this->assertCount(2, $journalEntries, 'Should have 2 journal entries: debit COGS and credit inventory');

        // Check debit entry (COGS)
        $debitEntry = $journalEntries->where('debit', '>', 0)->first();
        $this->assertNotNull($debitEntry, 'Should have debit journal entry for COGS');
        $this->assertEquals(500000, $debitEntry->debit);
        $this->assertEquals(0, $debitEntry->credit);
        $this->assertTrue(strpos($debitEntry->description, 'Cost of Goods Sold') !== false);

        // Check credit entry (Inventory reduction)
        $creditEntry = $journalEntries->where('credit', '>', 0)->first();
        $this->assertNotNull($creditEntry, 'Should have credit journal entry for inventory reduction');
        $this->assertEquals(0, $creditEntry->debit);
        $this->assertEquals(500000, $creditEntry->credit);
        $this->assertTrue(strpos($creditEntry->description, 'Inventory Reduction') !== false);

        // ==========================================
        // STEP 9: RECEIVE DELIVERY ORDER
        // ==========================================

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'received');
        $this->assertEquals('received', $deliveryOrder->fresh()->status);

        // ==========================================
        // STEP 10: VERIFY STOCK MOVEMENTS (Only 1 - created at 'sent', not at 'completed')
        // ==========================================

        $stockMovements = StockMovement::where('type', 'sales')
            ->where('from_model_type', DeliveryOrderItem::class)
            ->whereIn('from_model_id', $deliveryOrder->deliveryOrderItem->pluck('id'))
            ->get();

        // BUG FIX VERIFICATION: Should still have only 1 stock movement (not duplicated at 'completed')
        $this->assertCount(1, $stockMovements, 'Should have 1 stock movement (created at sent, not at completed)');
        $this->assertEquals(10, $stockMovements->first()->quantity);
        $this->assertEquals('sales', $stockMovements->first()->type);
        $this->assertEquals($this->product->id, $stockMovements->first()->product_id);
        $this->assertEquals($this->warehouse->id, $stockMovements->first()->warehouse_id);

        // ==========================================
        // STEP 11: VERIFY SALES ORDER UPDATED TO COMPLETED
        // ==========================================

        $saleOrder->refresh();
        $this->assertEquals('completed', $saleOrder->status);

        // CHECK SALE ORDER ITEM DELIVERED QUANTITY UPDATED
        $saleOrderItem->refresh();
        $this->assertEquals(10, $saleOrderItem->delivered_quantity);

        // CHECK INVOICE CREATED WITH CUSTOMER DATA
        $invoice = \App\Models\Invoice::where('from_model_type', SaleOrder::class)
            ->where('from_model_id', $saleOrder->id)
            ->first();

        $this->assertNotNull($invoice, 'Invoice should be created for completed sale order');
        $this->assertEquals($this->customer->name, $invoice->customer_name);
        $this->assertEquals($this->customer->phone, $invoice->customer_phone);

        echo "\n\n=== DELIVERY ORDER COMPLETE FLOW TEST PASSED ===";
        echo "\n✅ Stock reservation created on approval";
        echo "\n✅ Stock reservation NOT deleted on sent (bug fix)";
        echo "\n✅ StockMovement created with shipping_start meta at sent";
        echo "\n✅ Only 1 StockMovement exists (not duplicated at completed)";
        echo "\n✅ Journal entries created at completed";
        echo "\n✅ Sales order updated to completed";
        echo "\n✅ Invoice created with customer data";
    }
}