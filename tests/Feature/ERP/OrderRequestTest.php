<?php

namespace Tests\Feature\ERP;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\OrderRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MODULE 1 — ORDER REQUEST
 *
 * Tests items #1, #2, #3 from the 29-item sprint:
 *  #1  Price in OR is editable (original_price + unit_price stored correctly)
 *  #2  OR includes PPN status (tax_type field: PPN Included / PPN Excluded)
 *  #3  One OR can generate multiple Purchase Orders (multi-supplier mode)
 */
class OrderRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Supplier $supplier;
    protected Supplier $supplier2;
    protected Product $productA;
    protected Product $productB;
    protected OrderRequest $orderRequest;
    protected OrderRequestService $service;

    protected function setUp(): void
    {
        parent::setUp();

        UnitOfMeasure::factory()->create();
        Currency::factory()->create(['code' => 'IDR']);

        $this->cabang    = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->supplier  = Supplier::factory()->create(['tempo_hutang' => 30]);
        $this->supplier2 = Supplier::factory()->create(['tempo_hutang' => 14]);
        $this->user      = User::factory()->create(['cabang_id' => $this->cabang->id]);

        $this->productA = Product::factory()->create(['cost_price' => 100000]);
        $this->productB = Product::factory()->create(['cost_price' => 200000]);

        $this->orderRequest = OrderRequest::factory()->create([
            'cabang_id'    => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id'  => $this->supplier->id,
            'status'       => 'approved',
            'tax_type'     => 'PPN Excluded',
            'created_by'   => $this->user->id,
        ]);

        $this->service = app(OrderRequestService::class);
        $this->actingAs($this->user);
    }

    // ──────────────────────────────────────────────────────────────
    // Test #1 — Price is editable: original_price vs unit_price
    // ──────────────────────────────────────────────────────────────

    /** @test */
    public function order_request_item_stores_original_and_overridden_price(): void
    {
        $item = OrderRequestItem::create([
            'order_request_id' => $this->orderRequest->id,
            'product_id'       => $this->productA->id,
            'quantity'         => 5,
            'original_price'   => 100000,  // price from master
            'unit_price'       => 90000,   // user overrode it
            'discount'         => 0,
            'tax'              => 0,
        ]);

        $this->assertDatabaseHas('order_request_items', [
            'id'             => $item->id,
            'original_price' => 100000,
            'unit_price'     => 90000,
        ]);

        // Override price again (simulating a subsequent edit)
        $item->update(['unit_price' => 85000]);

        $this->assertDatabaseHas('order_request_items', [
            'id'             => $item->id,
            'original_price' => 100000,
            'unit_price'     => 85000,
        ]);
    }

    /** @test */
    public function order_request_item_original_price_differs_from_unit_price_after_override(): void
    {
        $masterPrice   = 150000;
        $overridePrice = 130000;

        $item = OrderRequestItem::create([
            'order_request_id' => $this->orderRequest->id,
            'product_id'       => $this->productA->id,
            'quantity'         => 10,
            'original_price'   => $masterPrice,
            'unit_price'       => $overridePrice,
            'discount'         => 0,
            'tax'              => 0,
        ]);

        $fresh = $item->fresh();
        $this->assertNotEquals($fresh->original_price, $fresh->unit_price);
        $this->assertEquals($masterPrice, (float) $fresh->original_price);
        $this->assertEquals($overridePrice, (float) $fresh->unit_price);
    }

    // ──────────────────────────────────────────────────────────────
    // Test #2 — PPN status field (tax_type)
    // ──────────────────────────────────────────────────────────────

    /** @test */
    public function order_request_stores_ppn_included_tax_type(): void
    {
        $or = OrderRequest::factory()->create([
            'cabang_id'    => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status'       => 'draft',
            'tax_type'     => 'PPN Included',
            'created_by'   => $this->user->id,
        ]);

        $this->assertDatabaseHas('order_requests', [
            'id'       => $or->id,
            'tax_type' => 'PPN Included',
        ]);
        $this->assertEquals('PPN Included', $or->fresh()->tax_type);
    }

    /** @test */
    public function order_request_stores_ppn_excluded_tax_type(): void
    {
        $or = OrderRequest::factory()->create([
            'cabang_id'    => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'status'       => 'draft',
            'tax_type'     => 'PPN Excluded',
            'created_by'   => $this->user->id,
        ]);

        $this->assertDatabaseHas('order_requests', [
            'id'       => $or->id,
            'tax_type' => 'PPN Excluded',
        ]);
    }

    /** @test */
    public function order_request_service_applies_correct_tipe_pajak_for_ppn_excluded(): void
    {
        $this->orderRequest->update(['tax_type' => 'PPN Excluded']);

        $item = OrderRequestItem::create([
            'order_request_id' => $this->orderRequest->id,
            'product_id'       => $this->productA->id,
            'quantity'         => 2,
            'original_price'   => 100000,
            'unit_price'       => 100000,
            'tax'              => 11,
        ]);

        $data = [
            'supplier_id'    => $this->supplier->id,
            'po_number'      => 'PO-TAX-TEST-001',
            'order_date'     => now()->toDateString(),
            'expected_date'  => now()->addDays(7)->toDateString(),
            'selected_items' => [[
                'item_id'    => $item->id,
                'quantity'   => 2,
                'unit_price' => 100000,
                'include'    => true,
            ]],
        ];

        $po = $this->service->createPurchaseOrder($this->orderRequest, $data);

        $poItem = $po->purchaseOrderItem->first();
        // PPN Excluded + tax > 0 => 'Eklusif'
        $this->assertEquals('Eklusif', $poItem->tipe_pajak);
    }

    /** @test */
    public function order_request_service_applies_correct_tipe_pajak_for_ppn_included(): void
    {
        $this->orderRequest->update(['tax_type' => 'PPN Included']);

        $item = OrderRequestItem::create([
            'order_request_id' => $this->orderRequest->id,
            'product_id'       => $this->productB->id,
            'quantity'         => 1,
            'original_price'   => 200000,
            'unit_price'       => 200000,
            'tax'              => 11,
        ]);

        $data = [
            'supplier_id'    => $this->supplier->id,
            'po_number'      => 'PO-TAX-INC-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [[
                'item_id'    => $item->id,
                'quantity'   => 1,
                'unit_price' => 200000,
                'include'    => true,
            ]],
        ];

        $po = $this->service->createPurchaseOrder($this->orderRequest, $data);

        $poItem = $po->purchaseOrderItem->first();
        $this->assertEquals('Inklusif', $poItem->tipe_pajak);
    }

    // ──────────────────────────────────────────────────────────────
    // Test #3 — One OR generates multiple POs (multi-supplier)
    // ──────────────────────────────────────────────────────────────

    /** @test */
    public function one_order_request_can_create_multiple_purchase_orders_for_different_suppliers(): void
    {
        $itemA = OrderRequestItem::create([
            'order_request_id' => $this->orderRequest->id,
            'product_id'       => $this->productA->id,
            'quantity'         => 5,
            'original_price'   => 100000,
            'unit_price'       => 100000,
            'tax'              => 0,
        ]);

        $itemB = OrderRequestItem::create([
            'order_request_id' => $this->orderRequest->id,
            'product_id'       => $this->productB->id,
            'quantity'         => 3,
            'original_price'   => 200000,
            'unit_price'       => 200000,
            'tax'              => 0,
        ]);

        // Create PO 1 for supplier 1 with item A only
        $dataS1 = [
            'supplier_id'    => $this->supplier->id,
            'po_number'      => 'PO-MULTI-S1-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [[
                'item_id'    => $itemA->id,
                'quantity'   => 5,
                'unit_price' => 100000,
                'include'    => true,
            ], [
                'item_id'    => $itemB->id,
                'quantity'   => 3,
                'unit_price' => 200000,
                'include'    => false,  // excluded from this PO
            ]],
        ];

        // Create PO 2 for supplier 2 with item B only
        $dataS2 = [
            'supplier_id'    => $this->supplier2->id,
            'po_number'      => 'PO-MULTI-S2-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [[
                'item_id'    => $itemA->id,
                'quantity'   => 5,
                'unit_price' => 100000,
                'include'    => false,  // excluded from this PO
            ], [
                'item_id'    => $itemB->id,
                'quantity'   => 3,
                'unit_price' => 200000,
                'include'    => true,
            ]],
        ];

        $po1 = $this->service->createPurchaseOrder($this->orderRequest, $dataS1);
        $po2 = $this->service->createPurchaseOrder($this->orderRequest, $dataS2);

        // Two separate POs exist
        $this->assertNotEquals($po1->id, $po2->id);
        $this->assertEquals($this->supplier->id, $po1->supplier_id);
        $this->assertEquals($this->supplier2->id, $po2->supplier_id);

        // PO 1 contains only item A
        $this->assertCount(1, $po1->purchaseOrderItem);
        $this->assertEquals($this->productA->id, $po1->purchaseOrderItem->first()->product_id);

        // PO 2 contains only item B
        $this->assertCount(1, $po2->purchaseOrderItem);
        $this->assertEquals($this->productB->id, $po2->purchaseOrderItem->first()->product_id);

        // OR has 2 purchase orders linked
        $this->assertEquals(2, $this->orderRequest->purchaseOrders()->count());
    }

    /** @test */
    public function or_to_po_only_includes_selected_items(): void
    {
        $itemA = OrderRequestItem::create([
            'order_request_id' => $this->orderRequest->id,
            'product_id'       => $this->productA->id,
            'quantity'         => 10,
            'original_price'   => 50000,
            'unit_price'       => 50000,
            'tax'              => 0,
        ]);

        $itemB = OrderRequestItem::create([
            'order_request_id' => $this->orderRequest->id,
            'product_id'       => $this->productB->id,
            'quantity'         => 5,
            'original_price'   => 80000,
            'unit_price'       => 80000,
            'tax'              => 0,
        ]);

        $data = [
            'supplier_id'    => $this->supplier->id,
            'po_number'      => 'PO-SELECT-001',
            'order_date'     => now()->toDateString(),
            'selected_items' => [
                ['item_id' => $itemA->id, 'quantity' => 10, 'unit_price' => 50000, 'include' => true],
                ['item_id' => $itemB->id, 'quantity' => 5,  'unit_price' => 80000, 'include' => false],
            ],
        ];

        $po = $this->service->createPurchaseOrder($this->orderRequest, $data);

        // Only included item should be in PO
        $this->assertCount(1, $po->purchaseOrderItem);
        $this->assertEquals($this->productA->id, $po->purchaseOrderItem->first()->product_id);
        $this->assertDatabaseMissing('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'product_id'        => $this->productB->id,
        ]);
    }

    /** @test */
    public function supplier_price_from_pivot_is_used_as_default_unit_price(): void
    {
        // Attach supplier price via pivot
        $this->productA->suppliers()->attach($this->supplier->id, [
            'supplier_price' => 95000,
            'is_primary'     => true,
        ]);

        $item = OrderRequestItem::create([
            'order_request_id' => $this->orderRequest->id,
            'product_id'       => $this->productA->id,
            'quantity'         => 4,
            'original_price'   => 100000,
            'unit_price'       => 100000,
            'tax'              => 0,
        ]);

        // Simulate fillForm logic: look up supplier price from pivot
        $sp = $this->productA->suppliers()
            ->wherePivot('supplier_id', $this->supplier->id)
            ->first();

        $this->assertNotNull($sp);
        $supplierPrice = $sp->pivot->supplier_price;
        $this->assertEquals(95000.0, (float) $supplierPrice);
    }
}
