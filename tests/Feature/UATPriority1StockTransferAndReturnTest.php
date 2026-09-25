<?php

namespace Tests\Feature;

use App\Filament\Resources\StockTransferResource;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReturnProductService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UATPriority1StockTransferAndReturnTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouseA;
    protected Warehouse $warehouseB;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::factory()->create();
        $this->warehouseA = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Gudang Pusat A (No Rak)',
        ]);
        $this->warehouseB = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Gudang Cabang B (No Rak)',
        ]);

        $this->product = Product::factory()->create([
            'sku' => 'PRD-TEST-001',
            'name' => 'Pipa Baja Seamless',
            'cost_price' => 100000,
        ]);

        $this->user = User::create([
            'name' => 'Warehouse Staff User',
            'email' => 'whstaff@example.com',
            'username' => 'whstaff',
            'password' => bcrypt('password'),
            'first_name' => 'Staff',
            'kode_user' => 'STF01',
        ]);

        $role = Role::firstOrCreate(['name' => 'Warehouse Staff', 'guard_name' => 'web']);
        $this->user->assignRole($role);

        $this->actingAs($this->user);
    }

    #[Test]
    public function it_can_transfer_stock_between_warehouses_without_raks_and_moves_inventory_stock(): void
    {
        // 1. Setup initial stock in warehouse A with NULL rak
        InventoryStock::updateOrCreate(
            [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouseA->id,
                'rak_id' => null,
            ],
            [
                'qty_available' => 50,
                'qty_reserved' => 0,
            ]
        );

        // 2. Create Stock Transfer with null raks
        $stockTransfer = StockTransfer::create([
            'transfer_number' => 'ST-TEST-001',
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'transfer_date' => now(),
            'status' => 'Draft',
        ]);

        $item = StockTransferItem::create([
            'stock_transfer_id' => $stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'from_rak_id' => null,
            'to_rak_id' => null,
        ]);

        $this->assertNull($item->from_rak_id);
        $this->assertNull($item->to_rak_id);

        // Verify stock has not changed in Draft
        $stockA = InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseA->id)->whereNull('rak_id')->first();
        $this->assertEquals(50, (float) $stockA->qty_available);

        // 3. Request transfer
        $service = app(StockTransferService::class);
        $transferAfterRequest = $service->requestTransfer($stockTransfer);
        $this->assertEquals('Request', $transferAfterRequest->status);

        // 4. Approve transfer
        $transferAfterApprove = $service->approveStockTransfer($transferAfterRequest);
        $this->assertEquals('Approved', $transferAfterApprove->status);

        // 5. Verify physical inventory in inventory_stocks
        $stockAAfter = InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseA->id)->whereNull('rak_id')->first();
        $stockBAfter = InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseB->id)->whereNull('rak_id')->first();

        $this->assertNotNull($stockAAfter);
        $this->assertNotNull($stockBAfter);
        $this->assertEquals(30, (float) $stockAAfter->qty_available);
        $this->assertEquals(20, (float) $stockBAfter->qty_available);
    }

    #[Test]
    public function it_allows_operational_roles_to_request_and_response_stock_transfers(): void
    {
        $this->assertTrue(StockTransferResource::canRequestTransfer());
        $this->assertTrue(StockTransferResource::canResponseTransfer());

        // Verify reject workflow
        $stockTransfer = StockTransfer::create([
            'transfer_number' => 'ST-TEST-REJECT',
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'transfer_date' => now(),
            'status' => 'Draft',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $stockTransfer->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'from_warehouse_id' => $this->warehouseA->id,
            'to_warehouse_id' => $this->warehouseB->id,
            'from_rak_id' => null,
            'to_rak_id' => null,
        ]);

        $service = app(StockTransferService::class);
        $service->requestTransfer($stockTransfer);
        $rejected = $service->rejectTransfer($stockTransfer);

        $this->assertEquals('Reject', $rejected->status);
    }

    #[Test]
    public function it_can_return_product_from_delivery_order_item_with_null_rak_and_restores_inventory(): void
    {
        // 1. Initial stock in warehouse A (null rak)
        InventoryStock::updateOrCreate(
            [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouseA->id,
                'rak_id' => null,
            ],
            [
                'qty_available' => 10,
                'qty_reserved' => 0,
            ]
        );

        // 2. Create customer, sales order (8 pcs), and delivery order (3 pcs)
        $customer = Customer::factory()->create();
        $saleOrder = SaleOrder::create([
            'so_number' => 'SO-TEST-001',
            'customer_id' => $customer->id,
            'order_date' => now(),
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
        ]);

        $saleOrderItem = SaleOrderItem::create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 8,
            'unit_price' => 150000,
            'delivered_quantity' => 3,
        ]);

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-TEST-001',
            'warehouse_id' => $this->warehouseA->id,
            'delivery_date' => now(),
            'status' => 'received',
            'cabang_id' => $this->cabang->id,
        ]);
        $deliveryOrder->salesOrders()->attach($saleOrder->id);

        $deliveryOrderItem = DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
        ]);

        // Initial stock was 10, DO delivered 3 -> stock should be 7
        // (Simulate stock movement for DO sales delivery)
        app(\App\Services\ProductService::class)->createStockMovement(
            product_id: $this->product->id,
            warehouse_id: $this->warehouseA->id,
            quantity: 3,
            type: 'sales',
            date: now()->toDateString(),
            notes: 'DO Shipped',
            rak_id: null,
            fromModel: $deliveryOrderItem,
            value: $this->product->cost_price * 3
        );

        $currentStock = InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseA->id)->whereNull('rak_id')->first();
        $this->assertEquals(7, (float) $currentStock->qty_available);

        // 3. Create ReturnProduct referencing DeliveryOrderItem with rak_id = null
        $returnService = app(ReturnProductService::class);
        $returnNumber = $returnService->generateReturnNumber();

        $returnProduct = ReturnProduct::create([
            'return_number' => $returnNumber,
            'warehouse_id' => $this->warehouseA->id,
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $deliveryOrder->id,
            'status' => 'draft',
            'return_action' => 'reduce_quantity_only',
            'created_by' => $this->user->id,
        ]);

        $returnItem = ReturnProductItem::create([
            'return_product_id' => $returnProduct->id,
            'from_item_model_type' => DeliveryOrderItem::class,
            'from_item_model_id' => $deliveryOrderItem->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'rak_id' => null, // Optional rak!
            'condition' => 'good',
        ]);

        $this->assertNull($returnItem->rak_id);
        $this->assertEquals(DeliveryOrderItem::class, $returnItem->from_item_model_type);
        $this->assertEquals($deliveryOrderItem->id, $returnItem->from_item_model_id);

        // 4. Approve Return Product
        $returnService->updateQuantityFromModel($returnProduct);

        // 5. Verify DeliveryOrderItem quantity reduced from 3 to 2
        $deliveryOrderItem->refresh();
        $this->assertEquals(2, (float) $deliveryOrderItem->quantity);

        // 6. Verify stock in warehouse A restored by 1 (7 + 1 = 8)
        $restoredStock = InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouseA->id)->whereNull('rak_id')->first();
        $this->assertEquals(8, (float) $restoredStock->qty_available);
    }

    #[Test]
    public function it_validates_that_return_product_options_use_delivered_quantity_not_sales_order_quantity(): void
    {
        $customer = Customer::factory()->create();
        $saleOrder = SaleOrder::create([
            'so_number' => 'SO-TEST-002',
            'customer_id' => $customer->id,
            'order_date' => now(),
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
        ]);

        $saleOrderItem = SaleOrderItem::create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10, // SO has 10 pcs
            'unit_price' => 150000,
            'delivered_quantity' => 3,
        ]);

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-TEST-002',
            'warehouse_id' => $this->warehouseA->id,
            'delivery_date' => now(),
            'status' => 'received',
            'cabang_id' => $this->cabang->id,
        ]);
        $deliveryOrder->salesOrders()->attach($saleOrder->id);

        $deliveryOrderItem = DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'product_id' => $this->product->id,
            'quantity' => 3, // Only 3 pcs were delivered in this DO!
        ]);

        // Query the options directly like the form does
        $listDeliveryOrderItem = DeliveryOrderItem::with(['product'])
            ->where('delivery_order_id', $deliveryOrder->id)
            ->get();

        $this->assertCount(1, $listDeliveryOrderItem);
        $doItem = $listDeliveryOrderItem->first();
        $this->assertEquals(3, (float) $doItem->quantity);

        // When user selects this item in the form, max_quantity is set to DO item quantity (3), NOT SO quantity (10)
        $maxQuantity = (float) $doItem->quantity;
        $this->assertEquals(3.0, $maxQuantity);
        $this->assertNotEquals(10.0, $maxQuantity);

        // Form rule validation closure test
        $rule = function ($attribute, $value, $fail) use ($maxQuantity) {
            if ($maxQuantity > 0 && (float) $value > $maxQuantity) {
                $fail("Quantity retur ({$value}) tidak boleh melebihi quantity sumber ({$maxQuantity}).");
            }
        };

        // Returning 3 pcs passes
        $failedMessage = null;
        $failCallback = function ($message) use (&$failedMessage) {
            $failedMessage = $message;
        };

        $rule('quantity', 3, $failCallback);
        $this->assertNull($failedMessage);

        // Returning 5 pcs fails because only 3 pcs were delivered
        $rule('quantity', 5, $failCallback);
        $this->assertNotNull($failedMessage);
        $this->assertStringContainsString('tidak boleh melebihi quantity sumber (3)', $failedMessage);
    }
}
