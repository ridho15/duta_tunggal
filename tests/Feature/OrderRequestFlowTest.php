<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure required models exist
        \App\Models\UnitOfMeasure::factory()->create();
        Currency::factory()->create();
        Supplier::factory()->create();
    }

    public function test_complete_order_request_flow()
    {
        // 1. CREATE REQUEST
        $user = User::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $this->actingAs($user);

        $service = new OrderRequestService();

        $orderRequest = OrderRequest::factory()->create([
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'status' => 'draft',
            'request_number' => 'OR-20251031-0001',
        ]);

        $item = OrderRequestItem::factory()->create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('order_requests', [
            'id' => $orderRequest->id,
            'status' => 'draft',
            'request_number' => 'OR-20251031-0001'
        ]);

        // 2. APPROVAL WORKFLOW - Directly approve (since no pending status yet)
        $supplier = Supplier::factory()->create();
        $approvalData = [
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-20251031-0001',
            'order_date' => now(),
            'expected_date' => now()->addDays(7),
            'note' => 'Converted from Order Request',
        ];

        $approvedRequest = $service->approve($orderRequest, $approvalData);

        $this->assertEquals('approved', $approvedRequest->status);
        $this->assertDatabaseHas('purchase_orders', [
            'po_number' => 'PO-20251031-0001',
            'supplier_id' => $supplier->id,
            'status' => 'approved',
        ]);

        // 3. TRACKING - Check PO reference and status
        $purchaseOrder = $approvedRequest->purchaseOrder;
        $this->assertNotNull($purchaseOrder);
        $this->assertEquals('PO-20251031-0001', $purchaseOrder->po_number);
        $this->assertEquals($warehouse->id, $purchaseOrder->warehouse_id);

        // Check PO items created
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);

        // Test rejection flow
        $orderRequest2 = OrderRequest::factory()->create([
            'warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
            'status' => 'draft',
        ]);

        $service->reject($orderRequest2);

        $orderRequest2->refresh();
        $this->assertEquals('rejected', $orderRequest2->status);
        $this->assertFalse($orderRequest2->purchaseOrder()->exists()); // No PO created for rejected
    }
}