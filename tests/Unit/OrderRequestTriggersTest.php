<?php

namespace Tests\Unit;

use App\Models\InventoryStock;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Customer;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRequestTriggersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure a UnitOfMeasure exists because ProductFactory depends on it.
        \App\Models\UnitOfMeasure::factory()->create();
    }

    public function test_manual_order_request_creation()
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user);

        $orderRequest = OrderRequest::factory()->create([
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'status' => 'draft',
        ]);

        $item = OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseHas('order_requests', ['id' => $orderRequest->id, 'status' => 'draft']);
        $this->assertDatabaseHas('order_request_items', ['id' => $item->id, 'order_request_id' => $orderRequest->id]);
        $this->assertEquals(1, $orderRequest->orderRequestItem()->count());
    }

    public function test_auto_generated_from_sale_order_when_stock_insufficient()
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $customer = Customer::factory()->create();

        // Inventory has only 2 available
        InventoryStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => null,
            'qty_available' => 2,
            'qty_min' => 1,
        ]);

    $saleOrder = SaleOrder::factory()->create(['customer_id' => $customer->id]);
        $saleItem = SaleOrderItem::create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'warehouse_id' => $warehouse->id,
            'rak_id' => null,
        ]);

        $this->assertTrue($saleOrder->hasInsufficientStock());
        $insufficient = $saleOrder->getInsufficientStockItems();
        $this->assertNotEmpty($insufficient);

        // Simulate trigger that creates OrderRequest for shortages
        $orderRequest = OrderRequest::create([
            'request_number' => 'AUTO-SO-' . now()->timestamp,
            'warehouse_id' => $warehouse->id,
            'request_date' => now(),
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        foreach ($insufficient as $info) {
            $shortage = $info['shortage'];
            OrderRequestItem::create([
                'order_request_id' => $orderRequest->id,
                'product_id' => $info['item']->product_id,
                'quantity' => $shortage,
            ]);
        }

        $this->assertDatabaseHas('order_requests', ['id' => $orderRequest->id]);
        $this->assertEquals(1, $orderRequest->orderRequestItem()->count());
        $this->assertDatabaseHas('order_request_items', ['order_request_id' => $orderRequest->id, 'quantity' => 8]);
    }

    public function test_auto_generated_from_minimum_stock_alert()
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Create inventory that meets minimum threshold (qty_available <= qty_min)
        InventoryStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => null,
            'qty_available' => 3,
            'qty_min' => 5,
        ]);

        // Simulate scheduler that finds stock below minimum and creates order request
        $under = InventoryStock::whereColumn('qty_available', '<=', 'qty_min')->first();
        $this->assertNotNull($under);

        $needed = $under->qty_min - $under->qty_available;
        $orderRequest = OrderRequest::create([
            'request_number' => 'AUTO-MIN-' . now()->timestamp,
            'warehouse_id' => $under->warehouse_id,
            'request_date' => now(),
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $product->id,
            'quantity' => $needed > 0 ? $needed : 0,
        ]);

        $this->assertDatabaseHas('order_requests', ['id' => $orderRequest->id]);
        $this->assertDatabaseHas('order_request_items', ['order_request_id' => $orderRequest->id, 'quantity' => $needed]);
    }

    public function test_auto_generated_from_production_planning_requirement()
    {
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Simulate production planning output needing raw material X qty 20
        $requiredQty = 20;

        $orderRequest = OrderRequest::create([
            'request_number' => 'AUTO-PROD-' . now()->timestamp,
            'warehouse_id' => $warehouse->id,
            'request_date' => now(),
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $product->id,
            'quantity' => $requiredQty,
        ]);

        $this->assertDatabaseHas('order_requests', ['id' => $orderRequest->id]);
        $this->assertDatabaseHas('order_request_items', ['order_request_id' => $orderRequest->id, 'quantity' => $requiredQty]);
    }
}
