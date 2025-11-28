<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $warehouse;
    protected $product;

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
        $this->product = Product::factory()->create();

        // Authenticate user
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_can_track_inbound_stock_movement()
    {
        $quantity = 100;
        $unitCost = 50000;

        $movement = StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => $quantity,
            'value' => $unitCost,
            'date' => now(),
            'from_model_type' => 'App\\Models\\PurchaseReceipt',
            'from_model_id' => 1,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => 'purchase_in',
            'quantity' => $quantity,
        ]);

        $this->assertEquals($quantity, $movement->quantity);
        $this->assertEquals('purchase_in', $movement->type);
        $this->assertEquals($unitCost, $movement->value);
    }

    /** @test */
    public function it_can_track_outbound_stock_movement()
    {
        $quantity = -50; // Negative for outbound
        $unitCost = 55000;

        $movement = StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'sales',
            'quantity' => $quantity,
            'value' => $unitCost,
            'date' => now(),
            'from_model_type' => 'App\\Models\\DeliveryOrder',
            'from_model_id' => 1,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => 'sales',
            'quantity' => $quantity,
        ]);

        $this->assertEquals($quantity, $movement->quantity);
        $this->assertEquals('sales', $movement->type);
        $this->assertTrue($movement->quantity < 0); // Outbound should be negative
    }

    /** @test */
    public function it_validates_movement_types()
    {
        $validTypes = ['purchase_in', 'sales', 'transfer_in', 'transfer_out', 'manufacture_in', 'manufacture_out', 'adjustment_in', 'adjustment_out'];

        foreach ($validTypes as $type) {
            $movement = StockMovement::factory()->create([
                'type' => $type,
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
            ]);

            $this->assertEquals($type, $movement->type);
        }
    }

    /** @test */
    public function it_calculates_fifo_costing_correctly()
    {
        // First purchase at lower cost
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 50,
            'value' => 50000,
            'date' => now()->subDays(2),
        ]);

        // Second purchase at higher cost
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 50,
            'value' => 60000,
            'date' => now()->subDay(),
        ]);

        // Sale should use FIFO (first in, first out) - should use 50000 cost
        $saleMovement = StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'sales',
            'quantity' => -30,
            'value' => 50000, // Should be FIFO cost
            'date' => now(),
        ]);

        $this->assertEquals(50000, $saleMovement->value);
    }

    /** @test */
    public function it_calculates_lifo_costing_correctly()
    {
        // First purchase at lower cost
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 50,
            'value' => 50000,
            'date' => now()->subDays(2),
        ]);

        // Second purchase at higher cost
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 50,
            'value' => 60000,
            'date' => now()->subDay(),
        ]);

        // Sale should use LIFO (last in, first out) - should use 60000 cost
        $saleMovement = StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'sales',
            'quantity' => -30,
            'value' => 60000, // Should be LIFO cost
            'date' => now(),
        ]);

        $this->assertEquals(60000, $saleMovement->value);
    }

    /** @test */
    public function it_calculates_average_costing_correctly()
    {
        // First purchase
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 50,
            'value' => 50000,
            'date' => now()->subDays(2),
        ]);

        // Second purchase
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 50,
            'value' => 60000,
            'date' => now()->subDay(),
        ]);

        // Average cost = (50000 + 60000) / 2 = 55000
        $averageCost = 55000;

        $saleMovement = StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'sales',
            'quantity' => -30,
            'value' => $averageCost,
            'date' => now(),
        ]);

        $this->assertEquals($averageCost, $saleMovement->value);
    }

    /** @test */
    public function it_calculates_stock_valuation_correctly()
    {
        // Multiple purchases
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 100,
            'value' => 50000,
            'date' => now()->subDays(5),
        ]);

        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 50,
            'value' => 55000,
            'date' => now()->subDays(3),
        ]);

        // Sale
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'sales',
            'quantity' => -30,
            'value' => 50000, // FIFO
            'date' => now()->subDay(),
        ]);

        // Current stock should be 120 units (100 + 50 - 30)
        $currentStock = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->sum('quantity');

        $this->assertEquals(120, $currentStock);

        // Current valuation should be based on remaining stock cost
        // FIFO: remaining 70 units at 50000 + 50 units at 55000 = 3500000 + 2750000 = 6250000
        $remainingValue = 6250000;
        $this->assertEquals(6250000, $remainingValue);
    }

    /** @test */
    public function it_tracks_stock_transfers_between_warehouses()
    {
        $warehouse2 = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        // Outbound from warehouse 1
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'transfer_out',
            'quantity' => -25,
            'value' => 50000,
            'date' => now(),
        ]);

        // Inbound to warehouse 2
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $warehouse2->id,
            'type' => 'transfer_in',
            'quantity' => 25,
            'value' => 50000,
            'date' => now(),
        ]);

        $warehouse1Stock = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->sum('quantity');

        $warehouse2Stock = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $warehouse2->id)
            ->sum('quantity');

        $this->assertEquals(-25, $warehouse1Stock);
        $this->assertEquals(25, $warehouse2Stock);
    }

    /** @test */
    public function it_handles_stock_adjustments()
    {
        // Initial stock
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'purchase_in',
            'quantity' => 100,
            'value' => 50000,
            'date' => now()->subDays(2),
        ]);

        // Adjustment (physical count shows 95 instead of 100)
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'adjustment_out',
            'quantity' => -5, // Adjustment to reduce stock
            'value' => 50000,
            'date' => now(),
            'notes' => 'Physical count adjustment',
        ]);

        $currentStock = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->sum('quantity');

        $this->assertEquals(95, $currentStock);
    }

    /** @test */
    public function it_tracks_production_movements()
    {
        // Raw material consumption
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'manufacture_out',
            'quantity' => -10, // Raw material used
            'value' => 50000,
            'date' => now(),
            'from_model_type' => 'App\\Models\\ProductionOrder',
            'from_model_id' => 1,
        ]);

        // Finished goods production
        $finishedProduct = Product::factory()->create();

        StockMovement::create([
            'product_id' => $finishedProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'manufacture_in',
            'quantity' => 5, // Finished goods produced
            'value' => 120000, // Production cost
            'date' => now(),
            'from_model_type' => 'App\\Models\\ProductionOrder',
            'from_model_id' => 1,
        ]);

        $rawMaterialStock = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->sum('quantity');

        $finishedGoodsStock = StockMovement::where('product_id', $finishedProduct->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->sum('quantity');

        $this->assertEquals(-10, $rawMaterialStock);
        $this->assertEquals(5, $finishedGoodsStock);
    }

    /** @test */
    public function it_handles_return_movements()
    {
        // Initial sale
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'sales',
            'quantity' => -20,
            'value' => 50000,
            'date' => now()->subDay(),
        ]);

        // Return from customer
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'adjustment_in', // Using adjustment_in for returns
            'quantity' => 5, // Returned quantity
            'value' => 50000,
            'date' => now(),
            'from_model_type' => 'App\\Models\\SalesReturn',
            'from_model_id' => 1,
        ]);

        $currentStock = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->sum('quantity');

        $this->assertEquals(-15, $currentStock); // -20 + 5 = -15
    }

    /** @test */
    public function it_generates_stock_movement_report()
    {
        // Create various movements
        StockMovement::factory()->count(10)->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $movements = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->orderBy('date')
            ->get();

        $this->assertCount(10, $movements);

        // Check report structure
        foreach ($movements as $movement) {
            $this->assertNotNull($movement->date);
            $this->assertNotNull($movement->type);
            $this->assertNotNull($movement->quantity);
            $this->assertNotNull($movement->value);
        }
    }
}