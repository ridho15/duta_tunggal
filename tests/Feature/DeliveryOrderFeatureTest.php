<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderLog;
use App\Models\Driver;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DeliveryOrderService;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOrderFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $warehouse;
    protected $customer;
    protected $product;
    protected $driver;
    protected $vehicle;
    protected $saleOrder;
    protected $saleOrderItem;
    protected $deliveryOrderService;
    protected $productService;

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
        $this->product = Product::factory()->create();
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

        // Create Sale Order
        $this->saleOrder = SaleOrder::create([
            'customer_id' => $this->customer->id,
            'so_number' => 'SO-' . now()->format('Ymd') . '-0001',
            'order_date' => now(),
            'status' => 'confirmed',
            'delivery_date' => now()->addDays(1),
            'total_amount' => 1000000,
            'tipe_pengiriman' => 'Kirim Langsung',
            'created_by' => $this->user->id,
        ]);

        $this->saleOrderItem = SaleOrderItem::create([
            'sale_order_id' => $this->saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'tax' => 0,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->deliveryOrderService = new DeliveryOrderService();
        $this->productService = new ProductService();

        // Authenticate user
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_create_delivery_order_manually()
    {
        $data = [
            'do_number' => 'DO-20251101-0001',
            'delivery_date' => now()->addDays(1)->toDateString(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'notes' => 'Manual DO creation test',
            'created_by' => $this->user->id,
        ];

        $deliveryOrder = DeliveryOrder::create($data);

        $this->assertDatabaseHas('delivery_orders', $data);
        $this->assertEquals('DO-20251101-0001', $deliveryOrder->do_number);
        $this->assertEquals('draft', $deliveryOrder->status);
    }

    /** @test */
    public function it_can_create_delivery_order_from_sale_order()
    {
        // Create DO from SO
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => $this->deliveryOrderService->generateDoNumber(),
            'delivery_date' => now()->addDays(1),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        // Attach SO
        $deliveryOrder->salesOrders()->attach($this->saleOrder->id);

        // Create DO items
        DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $this->saleOrderItem->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseHas('delivery_orders', ['id' => $deliveryOrder->id]);
        $this->assertDatabaseHas('delivery_sales_orders', [
            'delivery_order_id' => $deliveryOrder->id,
            'sales_order_id' => $this->saleOrder->id,
        ]);
        $this->assertDatabaseHas('delivery_order_items', [
            'delivery_order_id' => $deliveryOrder->id,
            'quantity' => 5,
        ]);
    }

    /** @test */
    public function it_tracks_approval_logs_and_status_changes()
    {
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        // Update status to request_approve
        $this->deliveryOrderService->updateStatus($deliveryOrder, 'request_approve');

        $this->assertEquals('request_approve', $deliveryOrder->fresh()->status);
        $this->assertDatabaseHas('delivery_order_logs', [
            'delivery_order_id' => $deliveryOrder->id,
            'status' => 'request_approve',
            'confirmed_by' => $this->user->id,
        ]);

        // Create and publish surat jalan before approving delivery order
        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-' . now()->format('Ymd') . '-0001',
            'issued_at' => now(),
            'created_by' => $this->user->id,
            'status' => 1, // Published status
        ]);

        // Attach to delivery order
        $suratJalan->deliveryOrder()->attach($deliveryOrder->id);

        // Update to approved
        $this->deliveryOrderService->updateStatus($deliveryOrder, 'approved');

        $this->assertEquals('approved', $deliveryOrder->fresh()->status);
        $this->assertDatabaseHas('delivery_order_logs', [
            'delivery_order_id' => $deliveryOrder->id,
            'status' => 'approved',
        ]);
    }

    /** @test */
    public function it_can_assign_vehicle_and_driver_for_shipping()
    {
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        // Assign driver and vehicle
        $deliveryOrder->update([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        $this->assertEquals($this->driver->id, $deliveryOrder->fresh()->driver_id);
        $this->assertEquals($this->vehicle->id, $deliveryOrder->fresh()->vehicle_id);
        $this->assertEquals($this->driver->name, $deliveryOrder->fresh()->driver->name);
        $this->assertEquals($this->vehicle->plate, $deliveryOrder->fresh()->vehicle->plate);
    }

    /** @test */
    public function it_can_generate_surat_jalan()
    {
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'approved',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'created_by' => $this->user->id,
        ]);

        // Create Surat Jalan
        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-' . now()->format('Ymd') . '-0001',
            'issued_at' => now(),
            'created_by' => $this->user->id,
            'status' => 0,
        ]);

        // Attach to delivery order
        $suratJalan->deliveryOrder()->attach($deliveryOrder->id);

        $this->assertDatabaseHas('surat_jalans', [
            'sj_number' => 'SJ-' . now()->format('Ymd') . '-0001',
        ]);

        $this->assertDatabaseHas('surat_jalan_delivery_orders', [
            'surat_jalan_id' => $suratJalan->id,
            'delivery_order_id' => $deliveryOrder->id,
        ]);
    }

    /** @test */
    public function it_handles_proof_of_delivery()
    {
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'sent',
            'created_by' => $this->user->id,
        ]);

        // Simulate delivery confirmation
        $deliveryOrder->update([
            'status' => 'received',
            'notes' => 'Received by customer with signature. Qty confirmed.',
        ]);

        $this->assertEquals('received', $deliveryOrder->fresh()->status);
        $this->assertTrue(str_contains($deliveryOrder->fresh()->notes, 'Received by customer'));
    }

    /** @test */
    public function it_updates_stock_on_delivery()
    {
        // Create DO with items
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
        ]);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $this->saleOrderItem->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
        ]);

        // Simulate stock update on delivery
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'sales',
            'quantity' => -5, // Negative for outbound
            'date' => now(),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $deliveryOrder->id,
            'value' => 100000,
        ]);

        $stockMovement = StockMovement::where('product_id', $this->product->id)
            ->where('type', 'sales')
            ->where('quantity', -5)
            ->where('from_model_type', DeliveryOrder::class)
            ->where('from_model_id', $deliveryOrder->id)
            ->first();

        $this->assertNotNull($stockMovement);
    }

    /** @test */
    public function it_calculates_total_value_correctly()
    {
        $deliveryOrder = DeliveryOrder::factory()->create();

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $this->saleOrderItem->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        // Assuming saleOrderItem has unit_price = 100000, discount = 0, tax = 0
        $expectedTotal = 2 * (100000 - 0 + 0); // 200000

        $this->assertEquals(200000, $deliveryOrder->fresh()->total);
    }

    /** @test */
    public function it_generates_unique_do_number()
    {
        $doNumber1 = $this->deliveryOrderService->generateDoNumber();
        DeliveryOrder::create([
            'do_number' => $doNumber1,
            'delivery_date' => now(),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'created_by' => $this->user->id,
        ]);

        $doNumber2 = $this->deliveryOrderService->generateDoNumber();

        $this->assertNotEquals($doNumber1, $doNumber2);
        $this->assertStringStartsWith('DO-' . now()->format('Ymd') . '-', $doNumber2);
    }

    /** @test */
    public function it_handles_delivery_order_lifecycle()
    {
        // 1. Create
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => $this->deliveryOrderService->generateDoNumber(),
            'delivery_date' => now()->addDays(1),
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals('draft', $deliveryOrder->status);

        // 2. Confirm
        $this->deliveryOrderService->updateStatus($deliveryOrder, 'request_approve');
        $this->assertEquals('request_approve', $deliveryOrder->fresh()->status);

        // 3. Create and publish surat jalan before approving
        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-' . now()->format('Ymd') . '-0001',
            'issued_at' => now(),
            'created_by' => $this->user->id,
            'status' => 1, // Published status
        ]);

        // Attach to delivery order
        $suratJalan->deliveryOrder()->attach($deliveryOrder->id);

        // 4. Approve
        $this->deliveryOrderService->updateStatus($deliveryOrder, 'approved');
        $this->assertEquals('approved', $deliveryOrder->fresh()->status);

        // 5. Assign shipping
        $deliveryOrder->update([
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
        ]);

        // 6. Ship
        $this->deliveryOrderService->updateStatus($deliveryOrder, 'sent');
        $this->assertEquals('sent', $deliveryOrder->fresh()->status);

        // 7. Deliver
        $this->deliveryOrderService->updateStatus($deliveryOrder, 'received');
        $this->assertEquals('received', $deliveryOrder->fresh()->status);

        // 7. Close
        $this->deliveryOrderService->updateStatus($deliveryOrder, 'completed');
        $this->assertEquals('completed', $deliveryOrder->fresh()->status);

        // Check logs
        $logs = DeliveryOrderLog::where('delivery_order_id', $deliveryOrder->id)->get();
        $this->assertCount(5, $logs); // request_approve, approved, sent, received, completed
        $this->assertEquals(['request_approve', 'approved', 'sent', 'received', 'completed'], $logs->pluck('status')->toArray());
    }

    /** @test */
    public function it_manages_stock_correctly_through_delivery_order_lifecycle()
    {
        // Clean up any existing inventory stock for this product/warehouse
        InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->forceDelete();

        // Setup initial stock
        $this->product->update(['cost_price' => 50000]); // Set cost price for accounting

        // Create initial inventory stock record (will be updated by StockMovementObserver)
        InventoryStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_available' => 0, // Start with 0, StockMovementObserver will add the quantity
            'qty_reserved' => 0,
            'qty_min' => 10,
        ]);

        // Create stock movement to add initial inventory
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 100,
            'value' => 50000,
            'date' => now(),
            'notes' => 'Initial stock for testing',
        ]);

        // Verify initial stock
        $initialStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(100, $initialStock->qty_available);
        $this->assertEquals(0, $initialStock->qty_reserved);

        // Create delivery order
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-' . now()->format('Ymd') . '-0001',
            'delivery_date' => now()->addDays(1)->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        // Add delivery order item
        DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // 1. APPROVED: Stock should be reserved
        // Create and publish surat jalan before approving delivery order
        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-' . now()->format('Ymd') . '-0001',
            'issued_at' => now(),
            'created_by' => $this->user->id,
            'status' => 1, // Published status
        ]);

        // Attach to delivery order
        $suratJalan->deliveryOrder()->attach($deliveryOrder->id);

        $this->deliveryOrderService->updateStatus($deliveryOrder, 'request_approve');
        $this->deliveryOrderService->updateStatus($deliveryOrder, 'approved');

        $stockAfterApproved = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(80, $stockAfterApproved->qty_available); // 100 - 20
        $this->assertEquals(20, $stockAfterApproved->qty_reserved); // +20

        // 2. SENT: Reservation should be released, stock available again, accounting posted
        $this->deliveryOrderService->updateStatus($deliveryOrder, 'sent');

        // Verify journals were created automatically by observer
        $journalCount = \App\Models\JournalEntry::where('source_type', \App\Models\DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->count();
        $this->assertGreaterThan(0, $journalCount, 'Journals should be created automatically when status changes to sent');

        $stockAfterSent = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(100, $stockAfterSent->qty_available); // Back to 100
        $this->assertEquals(0, $stockAfterSent->qty_reserved); // Reservation released

        // 3. COMPLETED: Stock should be permanently reduced
        $this->deliveryOrderService->updateStatus($deliveryOrder, 'completed');

        $stockAfterCompleted = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        $this->assertEquals(80, $stockAfterCompleted->qty_available); // Permanently reduced by 20
        $this->assertEquals(0, $stockAfterCompleted->qty_reserved);

        // Verify stock movements were created
        $stockMovements = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->orderBy('created_at', 'asc')
            ->take(2)
            ->get();

        // Should have: purchase_in (+100), sales (+20)
        $this->assertCount(2, $stockMovements);
        $this->assertEquals('purchase_in', $stockMovements[0]->type);
        $this->assertEquals(100, $stockMovements[0]->quantity);
        $this->assertEquals('sales', $stockMovements[1]->type);
        $this->assertEquals(20, $stockMovements[1]->quantity); // Quantity stored as positive
    }
}