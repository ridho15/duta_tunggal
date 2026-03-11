<?php

namespace Tests\Feature\ERP;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Product;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ReturnProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MODULE 9 — CUSTOMER RETURN
 *
 * Tests items #27, #28, #29:
 *  #27 Customer return request can be created in draft status
 *  #28 Return product item (inspection) can be attached to the return
 *  #29 Return action variants work: reduce_quantity_only / close_do_partial / close_so_complete
 */
class CustomerReturnTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Customer $customer;
    protected Product $product;
    protected DeliveryOrder $do;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::factory()->create(['code' => 'IDR']);

        $this->cabang    = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->customer  = Customer::factory()->create();
        $this->product   = Product::factory()->create();
        $this->user      = User::factory()->create(['cabang_id' => $this->cabang->id]);

        $this->do = DeliveryOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
            'status'       => 'sent',
        ]);

        $this->actingAs($this->user);
    }

    // ─── #27 RETURN REQUEST CREATION ─────────────────────────────────────────

    /** @test */
    public function return_product_can_be_created_in_draft_status(): void
    {
        $return = ReturnProduct::create([
            'return_number'  => 'RTN-TEST-001',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Produk rusak saat diterima',
            'return_action'  => 'reduce_quantity_only',
        ]);

        $this->assertDatabaseHas('return_products', [
            'id'             => $return->id,
            'return_number'  => 'RTN-TEST-001',
            'status'         => 'draft',
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'from_model_id'  => $this->do->id,
        ]);

        $this->assertEquals('draft', $return->status);
        $this->assertEquals('Produk rusak saat diterima', $return->reason);
    }

    /** @test */
    public function return_product_can_reference_delivery_order(): void
    {
        $return = ReturnProduct::create([
            'return_number'  => 'RTN-TEST-002',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Test reason',
            'return_action'  => 'reduce_quantity_only',
        ]);

        $fromModel = $return->fromModel;

        $this->assertInstanceOf(DeliveryOrder::class, $fromModel,
            'Return product fromModel must resolve to a DeliveryOrder');

        $this->assertEquals($this->do->id, $fromModel->id);
    }

    /** @test */
    public function return_number_is_stored_correctly(): void
    {
        $return = ReturnProduct::create([
            'return_number'  => 'RTN-20260311-001',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Damaged goods',
        ]);

        $this->assertEquals('RTN-20260311-001', $return->return_number);

        $this->assertDatabaseHas('return_products', [
            'return_number' => 'RTN-20260311-001',
        ]);
    }

    // ─── #28 RETURN PRODUCT ITEM / INSPECTION ────────────────────────────────

    /** @test */
    public function return_product_item_can_be_added_to_return(): void
    {
        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $this->do->id,
            'product_id'        => $this->product->id,
            'quantity'          => 10,
        ]);

        $return = ReturnProduct::create([
            'return_number'  => 'RTN-INSP-001',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Inspection required',
            'return_action'  => 'reduce_quantity_only',
        ]);

        $returnItem = ReturnProductItem::create([
            'return_product_id'  => $return->id,
            'from_item_model_id' => $doItem->id,
            'from_item_model_type'=> 'App\\Models\\DeliveryOrderItem',
            'product_id'         => $this->product->id,
            'quantity'           => 2,
            'condition'          => 'damage',
            'note'               => 'Packaging torn',
        ]);

        $this->assertDatabaseHas('return_product_items', [
            'return_product_id'   => $return->id,
            'product_id'          => $this->product->id,
            'quantity'            => 2,
            'condition'           => 'damage',
        ]);

        $this->assertEquals(1, $return->fresh()->returnProductItem->count(),
            'Return product must have one inspection item');
    }

    /** @test */
    public function return_product_item_stores_condition_and_note(): void
    {
        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $this->do->id,
            'product_id'        => $this->product->id,
            'quantity'          => 5,
        ]);

        $return = ReturnProduct::create([
            'return_number'  => 'RTN-NOTE-001',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Quality check',
        ]);

        $returnItem = ReturnProductItem::create([
            'return_product_id'   => $return->id,
            'from_item_model_id'  => $doItem->id,
            'from_item_model_type'=> 'App\\Models\\DeliveryOrderItem',
            'product_id'          => $this->product->id,
            'quantity'            => 1,
            'condition'           => 'good',
            'note'                => 'Minor scratch, still functional',
        ]);

        $this->assertEquals('good', $returnItem->condition);
        $this->assertEquals('Minor scratch, still functional', $returnItem->note);
    }

    /** @test */
    public function return_product_can_have_multiple_items(): void
    {
        $product2 = Product::factory()->create();

        $doItem1 = DeliveryOrderItem::create([
            'delivery_order_id' => $this->do->id,
            'product_id'        => $this->product->id,
            'quantity'          => 10,
        ]);

        $doItem2 = DeliveryOrderItem::create([
            'delivery_order_id' => $this->do->id,
            'product_id'        => $product2->id,
            'quantity'          => 5,
        ]);

        $return = ReturnProduct::create([
            'return_number'  => 'RTN-MULTI-001',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Multiple defects',
            'return_action'  => 'reduce_quantity_only',
        ]);

        ReturnProductItem::create([
            'return_product_id'   => $return->id,
            'from_item_model_id'  => $doItem1->id,
            'from_item_model_type'=> 'App\\Models\\DeliveryOrderItem',
            'product_id'          => $this->product->id,
            'quantity'            => 3,
            'condition'           => 'damage',
        ]);

        ReturnProductItem::create([
            'return_product_id'   => $return->id,
            'from_item_model_id'  => $doItem2->id,
            'from_item_model_type'=> 'App\\Models\\DeliveryOrderItem',
            'product_id'          => $product2->id,
            'quantity'            => 1,
            'condition'           => 'repair',
        ]);

        $this->assertEquals(2, $return->fresh()->returnProductItem->count(),
            'Return product must have two inspection items');
    }

    // ─── #29 RETURN ACTION VARIANTS ──────────────────────────────────────────

    /** @test */
    public function return_action_reduce_quantity_only_is_stored(): void
    {
        $return = ReturnProduct::create([
            'return_number'  => 'RTN-ACT-001',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Defective',
            'return_action'  => 'reduce_quantity_only',
        ]);

        $this->assertDatabaseHas('return_products', [
            'id'            => $return->id,
            'return_action' => 'reduce_quantity_only',
        ]);
    }

    /** @test */
    public function return_action_close_do_partial_is_stored(): void
    {
        $return = ReturnProduct::create([
            'return_number'  => 'RTN-ACT-002',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Partial return',
            'return_action'  => 'close_do_partial',
        ]);

        $this->assertDatabaseHas('return_products', [
            'id'            => $return->id,
            'return_action' => 'close_do_partial',
        ]);
    }

    /** @test */
    public function return_action_close_so_complete_is_stored(): void
    {
        $return = ReturnProduct::create([
            'return_number'  => 'RTN-ACT-003',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Complete return, cancel order',
            'return_action'  => 'close_so_complete',
        ]);

        $this->assertDatabaseHas('return_products', [
            'id'            => $return->id,
            'return_action' => 'close_so_complete',
        ]);
    }

    /** @test */
    public function return_can_be_approved_and_deducts_delivery_item_quantity(): void
    {
        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $this->do->id,
            'product_id'        => $this->product->id,
            'quantity'          => 10,
        ]);

        $return = ReturnProduct::create([
            'return_number'  => 'RTN-APPROVE-001',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Return for approval test',
            'return_action'  => 'reduce_quantity_only',
        ]);

        ReturnProductItem::create([
            'return_product_id'   => $return->id,
            'from_item_model_id'  => $doItem->id,
            'from_item_model_type'=> 'App\\Models\\DeliveryOrderItem',
            'product_id'          => $this->product->id,
            'quantity'            => 4,
            'condition'           => 'damage',
        ]);

        $service = app(ReturnProductService::class);
        $service->updateQuantityFromModel($return);

        // The DO item quantity should have been reduced by 4 (from 10 → 6)
        $this->assertDatabaseHas('delivery_order_items', [
            'id'       => $doItem->id,
            'quantity' => 6,
        ]);

        // The return must be marked as approved
        $this->assertDatabaseHas('return_products', [
            'id'     => $return->id,
            'status' => 'approved',
        ]);
    }

    /** @test */
    public function approved_return_status_is_set_by_service(): void
    {
        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $this->do->id,
            'product_id'        => $this->product->id,
            'quantity'          => 5,
        ]);

        $return = ReturnProduct::create([
            'return_number'  => 'RTN-STATUS-001',
            'from_model_id'  => $this->do->id,
            'from_model_type'=> 'App\\Models\\DeliveryOrder',
            'warehouse_id'   => $this->warehouse->id,
            'status'         => 'draft',
            'reason'         => 'Status change test',
            'return_action'  => 'reduce_quantity_only',
        ]);

        ReturnProductItem::create([
            'return_product_id'   => $return->id,
            'from_item_model_id'  => $doItem->id,
            'from_item_model_type'=> 'App\\Models\\DeliveryOrderItem',
            'product_id'          => $this->product->id,
            'quantity'            => 1,
            'condition'           => 'good',
        ]);

        $this->assertEquals('draft', $return->status,
            'Return must start in draft status');

        app(ReturnProductService::class)->updateQuantityFromModel($return);

        $return->refresh();

        $this->assertEquals('approved', $return->status,
            'Service must set return status to approved');
    }

    /** @test */
    public function all_three_return_action_values_are_valid(): void
    {
        $validActions = ['reduce_quantity_only', 'close_do_partial', 'close_so_complete'];

        foreach ($validActions as $i => $action) {
            $return = ReturnProduct::create([
                'return_number'  => "RTN-VALID-{$i}",
                'from_model_id'  => $this->do->id,
                'from_model_type'=> 'App\\Models\\DeliveryOrder',
                'warehouse_id'   => $this->warehouse->id,
                'status'         => 'draft',
                'reason'         => "Testing action: {$action}",
                'return_action'  => $action,
            ]);

            $this->assertDatabaseHas('return_products', [
                'return_number' => "RTN-VALID-{$i}",
                'return_action' => $action,
            ]);
        }

        $this->assertCount(3, ReturnProduct::whereIn('return_action', $validActions)->get());
    }
}
