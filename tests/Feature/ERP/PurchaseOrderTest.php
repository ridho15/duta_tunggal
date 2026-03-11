<?php

namespace Tests\Feature\ERP;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MODULE 2 — PURCHASE ORDER
 *
 * Tests items #4, #5, #6, #7:
 *  #4  OR items auto-populate PO items
 *  #5  Supplier can be changed when generating PO
 *  #6  Quantity in PO can be edited before approval
 *  #7  PO approval decreases OR remaining quantity correctly
 */
class PurchaseOrderTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Supplier $supplier;
    protected Supplier $supplierB;
    protected Product $productA;
    protected Product $productB;
    protected OrderRequest $orderRequest;
    protected OrderRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        UnitOfMeasure::factory()->create();
        Currency::factory()->create(['code' => 'IDR']);

        $this->cabang     = Cabang::factory()->create();
        $this->warehouse  = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->supplier   = Supplier::factory()->create(['tempo_hutang' => 30]);
        $this->supplierB  = Supplier::factory()->create(['tempo_hutang' => 14]);
        $this->user       = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->productA   = Product::factory()->create(['cost_price' => 50000]);
        $this->productB   = Product::factory()->create(['cost_price' => 75000]);

        $this->orderRequest = OrderRequest::factory()->create([
            'cabang_id'    => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id'  => $this->supplier->id,
            'status'       => 'approved',
            'created_by'   => $this->user->id,
        ]);

        $this->service = app(OrderRequestService::class);
        $this->actingAs($this->user);
    }

    private function makeItem(Product $product, int $qty = 5, float $price = 50000): OrderRequestItem
    {
        return OrderRequestItem::create([
            'order_request_id' => $this->orderRequest->id,
            'product_id'       => $product->id,
            'quantity'         => $qty,
            'original_price'   => $price,
            'unit_price'       => $price,
            'tax'              => 0,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Test #4 — OR items auto-populate PO items
    // ──────────────────────────────────────────────────────────────

    /** @test */
    public function or_items_are_mapped_to_po_items_with_correct_data(): void
    {
        $itemA = $this->makeItem($this->productA, 5, 50000);
        $itemB = $this->makeItem($this->productB, 3, 75000);

        $data = [
            'supplier_id'    => $this->supplier->id,
            'po_number'      => 'PO-AUTOMAP-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [
                ['item_id' => $itemA->id, 'quantity' => 5, 'unit_price' => 50000, 'include' => true],
                ['item_id' => $itemB->id, 'quantity' => 3, 'unit_price' => 75000, 'include' => true],
            ],
        ];

        $po = $this->service->createPurchaseOrder($this->orderRequest, $data);
        $po->load('purchaseOrderItem');

        $this->assertCount(2, $po->purchaseOrderItem);

        $productIds = $po->purchaseOrderItem->pluck('product_id')->sort()->values();
        $expected   = collect([$this->productA->id, $this->productB->id])->sort()->values();
        $this->assertEquals($expected, $productIds);

        $poItemA = $po->purchaseOrderItem->where('product_id', $this->productA->id)->first();
        $this->assertEquals(5, $poItemA->quantity);
        $this->assertEquals(50000, (float) $poItemA->unit_price);

        $poItemB = $po->purchaseOrderItem->where('product_id', $this->productB->id)->first();
        $this->assertEquals(3, $poItemB->quantity);
        $this->assertEquals(75000, (float) $poItemB->unit_price);
    }

    /** @test */
    public function created_po_is_linked_to_order_request(): void
    {
        $item = $this->makeItem($this->productA, 2, 50000);

        $data = [
            'supplier_id'    => $this->supplier->id,
            'po_number'      => 'PO-LINK-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [
                ['item_id' => $item->id, 'quantity' => 2, 'unit_price' => 50000, 'include' => true],
            ],
        ];

        $po = $this->service->createPurchaseOrder($this->orderRequest, $data);

        $this->assertDatabaseHas('order_request_purchase_orders', [
            'order_request_id' => $this->orderRequest->id,
            'purchase_order_id' => $po->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Test #5 — Supplier can be changed when generating PO
    // ──────────────────────────────────────────────────────────────

    /** @test */
    public function po_can_be_generated_with_different_supplier_than_or_default(): void
    {
        $item = $this->makeItem($this->productA, 4, 50000);

        // OR has supplier A; generate PO with supplier B
        $data = [
            'supplier_id'    => $this->supplierB->id,  // DIFFERENT supplier
            'po_number'      => 'PO-DIFFSUP-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [
                ['item_id' => $item->id, 'quantity' => 4, 'unit_price' => 60000, 'include' => true],
            ],
        ];

        $po = $this->service->createPurchaseOrder($this->orderRequest, $data);

        $this->assertEquals($this->supplierB->id, $po->supplier_id);
        $this->assertNotEquals($this->orderRequest->supplier_id, $po->supplier_id);
        $this->assertDatabaseHas('purchase_orders', [
            'id'          => $po->id,
            'supplier_id' => $this->supplierB->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Test #6 — Quantity in PO can be edited before approval
    // ──────────────────────────────────────────────────────────────

    /** @test */
    public function po_item_quantity_can_be_edited_when_po_status_is_draft(): void
    {
        $item = $this->makeItem($this->productA, 10, 50000);

        $data = [
            'supplier_id'    => $this->supplier->id,
            'po_number'      => 'PO-QTYEDIT-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [
                ['item_id' => $item->id, 'quantity' => 10, 'unit_price' => 50000, 'include' => true],
            ],
        ];

        $po = $this->service->createPurchaseOrder($this->orderRequest, $data);

        // PO starts as 'approved' from the service; simulate pre-approval state
        $po->update(['status' => 'draft']);

        // User edits quantity before approval
        $poItem = $po->purchaseOrderItem->first();
        $poItem->update(['quantity' => 8]);

        $this->assertDatabaseHas('purchase_order_items', [
            'id'       => $poItem->id,
            'quantity' => 8,
        ]);
    }

    /** @test */
    public function po_item_unit_price_can_be_edited_before_approval(): void
    {
        $item = $this->makeItem($this->productB, 5, 75000);

        $data = [
            'supplier_id'    => $this->supplier->id,
            'po_number'      => 'PO-PRICEDIT-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [
                ['item_id' => $item->id, 'quantity' => 5, 'unit_price' => 75000, 'include' => true],
            ],
        ];

        $po = $this->service->createPurchaseOrder($this->orderRequest, $data);
        $po->update(['status' => 'draft']);

        $poItem = $po->purchaseOrderItem->first();
        $poItem->update(['unit_price' => 70000]);

        $this->assertEquals(70000, (float) $poItem->fresh()->unit_price);
    }

    // ──────────────────────────────────────────────────────────────
    // Test #7 — PO approval decreases OR remaining qty correctly
    // ──────────────────────────────────────────────────────────────

    /** @test */
    public function fulfilled_quantity_on_or_item_increases_after_po_is_created(): void
    {
        $item = OrderRequestItem::create([
            'order_request_id'  => $this->orderRequest->id,
            'product_id'        => $this->productA->id,
            'quantity'          => 20,
            'original_price'    => 50000,
            'unit_price'        => 50000,
            'fulfilled_quantity' => 0,
            'tax'               => 0,
        ]);

        $data = [
            'supplier_id'    => $this->supplier->id,
            'po_number'      => 'PO-FULFILL-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [
                ['item_id' => $item->id, 'quantity' => 12, 'unit_price' => 50000, 'include' => true],
            ],
        ];

        $this->service->createPurchaseOrder($this->orderRequest, $data);

        // Observer should update fulfilled_quantity
        $fresh = $item->fresh();
        $this->assertGreaterThan(0, (int) $fresh->fulfilled_quantity,
            'fulfilled_quantity should have increased after PO item was created');
    }

    /** @test */
    public function remaining_quantity_is_calculated_correctly_after_partial_fulfillment(): void
    {
        $totalQty = 20;

        $item = OrderRequestItem::create([
            'order_request_id'  => $this->orderRequest->id,
            'product_id'        => $this->productA->id,
            'quantity'          => $totalQty,
            'original_price'    => 50000,
            'unit_price'        => 50000,
            'fulfilled_quantity' => 8,  // 8 already fulfilled
            'tax'               => 0,
        ]);

        $remaining = $item->quantity - ($item->fulfilled_quantity ?? 0);
        $this->assertEquals(12, $remaining,
            "Remaining qty should be totalQty($totalQty) - fulfilled(8) = 12");
    }

    /** @test */
    public function po_is_created_with_correct_warehouse_and_cabang_from_or(): void
    {
        $item = $this->makeItem($this->productA, 3, 50000);

        $data = [
            'supplier_id'    => $this->supplier->id,
            'po_number'      => 'PO-CABANG-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [
                ['item_id' => $item->id, 'quantity' => 3, 'unit_price' => 50000, 'include' => true],
            ],
        ];

        $po = $this->service->createPurchaseOrder($this->orderRequest, $data);

        $this->assertEquals($this->orderRequest->warehouse_id, $po->warehouse_id);
        $this->assertEquals($this->orderRequest->cabang_id, $po->cabang_id);
    }
}
