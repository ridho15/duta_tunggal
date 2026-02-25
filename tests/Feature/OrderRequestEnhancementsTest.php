<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\Cabang;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Currency;
use App\Models\UnitOfMeasure;
use App\Services\OrderRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class OrderRequestEnhancementsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $cabang;
    protected $warehouse;
    protected $supplier;
    protected $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required data
        UnitOfMeasure::factory()->create();
        Currency::factory()->create();
        
        // Create test data
        $this->cabang = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->supplier = Supplier::factory()->create();
        $this->product = Product::factory()->create(['supplier_id' => $this->supplier->id]);
        $this->user = User::factory()->create(['cabang_id' => $this->cabang->id]);
        
        $this->actingAs($this->user);
    }

    /** @test */
    public function order_request_can_store_price_fields()
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $orderRequestItem = OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 15000,
            'discount' => 1000,
            'tax' => 500,
            'subtotal' => 149500,
        ]);

        $this->assertDatabaseHas('order_request_items', [
            'id' => $orderRequestItem->id,
            'unit_price' => 15000,
            'discount' => 1000,
            'tax' => 500,
            'subtotal' => 149500,
        ]);

        echo "✓ Test passed: Order request can store price fields\n";
    }

    /** @test */
    public function order_request_status_can_be_closed()
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $orderRequest->update(['status' => 'closed']);

        $this->assertEquals('closed', $orderRequest->fresh()->status);
        $this->assertDatabaseHas('order_requests', [
            'id' => $orderRequest->id,
            'status' => 'closed',
        ]);

        echo "✓ Test passed: Order request can be closed\n";
    }

    /** @test */
    public function order_request_tracks_partial_fulfillment()
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $orderRequestItem = OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'unit_price' => 10000,
            'fulfilled_quantity' => 0,
        ]);

        // Create first partial PO
        $purchaseOrder = PurchaseOrder::create([
            'po_number' => 'PO-TEST-001',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
            'tempo_hutang' => 0,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
            'unit_price' => 10000,
            'refer_item_model_type' => OrderRequestItem::class,
            'refer_item_model_id' => $orderRequestItem->id,
            'currency_id' => 1,
        ]);

        $this->assertEquals(30, $orderRequestItem->fresh()->fulfilled_quantity);

        // Remaining quantity - need to refresh the model first
        $orderRequestItem->refresh();
        $remaining = $orderRequestItem->quantity - $orderRequestItem->fulfilled_quantity;
        $this->assertEquals(70, $remaining);

        echo "✓ Test passed: Order request tracks partial fulfillment (30/100)\n";
    }

    /** @test */
    public function po_from_order_request_is_auto_approved()
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 12000,
        ]);

        $orderRequestService = app(OrderRequestService::class);
        
        $data = [
            'create_purchase_order' => true,
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-TEST-002',
            'order_date' => now(),
        ];

        $result = $orderRequestService->approve($orderRequest, $data);

        $purchaseOrder = $orderRequest->fresh()->purchaseOrder;
        
        $this->assertNotNull($purchaseOrder);
        $this->assertEquals('approved', $purchaseOrder->status);

        echo "✓ Test passed: PO created from OR is auto-approved\n";
    }

    /** @test */
    public function po_inherits_prices_from_order_request()
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $customPrice = 25000;
        $customDiscount = 500;
        $customTax = 1000;
        
        $orderRequestItem = OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 20,
            'unit_price' => $customPrice,
            'discount' => $customDiscount,
            'tax' => $customTax,
        ]);

        $orderRequestService = app(OrderRequestService::class);
        
        $purchaseOrder = $orderRequestService->createPurchaseOrder($orderRequest, [
            'supplier_id' => $this->supplier->id,
            'po_number' => 'PO-TEST-003',
            'order_date' => now(),
        ]);

        $poItem = $purchaseOrder->purchaseOrderItem->first();
        
        $this->assertEquals($customPrice, $poItem->unit_price);
        $this->assertEquals($customDiscount, $poItem->discount);
        $this->assertEquals($customTax, $poItem->tax);

        echo "✓ Test passed: PO inherits correct prices from OR (unit_price: {$customPrice}, discount: {$customDiscount}, tax: {$customTax})\n";
    }

    /** @test */
    public function one_order_request_can_be_split_into_multiple_pos()
    {
        $orderRequest = OrderRequest::factory()->create([
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $orderRequestItem = OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'unit_price' => 10000,
            'fulfilled_quantity' => 0,
        ]);

        // First PO - 40 units
        $po1 = PurchaseOrder::create([
            'po_number' => 'PO-TEST-004',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
            'tempo_hutang' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po1->id,
            'product_id' => $this->product->id,
            'quantity' => 40,
            'unit_price' => 10000,
            'refer_item_model_type' => OrderRequestItem::class,
            'refer_item_model_id' => $orderRequestItem->id,
            'currency_id' => 1,
        ]);

        // Second PO - 30 units
        $po2 = PurchaseOrder::create([
            'po_number' => 'PO-TEST-005',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
            'tempo_hutang' => 0,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po2->id,
            'product_id' => $this->product->id,
            'quantity' => 30,
            'unit_price' => 10000,
            'refer_item_model_type' => OrderRequestItem::class,
            'refer_item_model_id' => $orderRequestItem->id,
            'currency_id' => 1,
        ]);

        $orderRequestItem->refresh();
        
        $this->assertEquals(70, $orderRequestItem->fulfilled_quantity);
        $remaining = $orderRequestItem->quantity - $orderRequestItem->fulfilled_quantity;
        $this->assertEquals(30, $remaining);

        echo "✓ Test passed: One OR split into multiple POs (PO1: 40 units, PO2: 30 units, Remaining: 30 units)\n";
    }
}
