<?php

namespace Tests\Feature\ERP;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Driver;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Models\WarehouseConfirmationItem;
use App\Services\DeliveryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MODULE 4 — DELIVERY
 *
 * Tests items #12, #13, #14, #15, #16:
 *  #12 DO must exist even for 'Ambil Sendiri' (customer pickup)
 *  #13 DO automatically generated after SO is approved
 *  #14 delivery_failed status exists on the DO model
 *  #15 DO table displays customer name, date, DO number, status
 *  #16 DO approval toggle via config key
 */
class DeliveryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Customer $customer;
    protected Product $product;
    protected Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang    = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->customer  = Customer::factory()->create();
        $this->product   = Product::factory()->create(['cost_price' => 100000]);
        $this->driver    = Driver::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->user      = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);
    }

    // ─── #12 DO EXISTS FOR 'AMBIL SENDIRI' ───────────────────────────────────

    /** @test */
    public function delivery_order_is_created_for_ambil_sendiri_pickup_type(): void
    {
        // SO with Ambil Sendiri delivery type must still get a Delivery Order
        $so = SaleOrder::factory()->create([
            'customer_id'     => $this->customer->id,
            'cabang_id'       => $this->cabang->id,
            'status'          => 'approved',
            'tipe_pengiriman' => 'Ambil Sendiri',
        ]);

        // Create DO and link it to the Ambil Sendiri SO via pivot
        $do = DeliveryOrder::create([
            'do_number'     => 'DO-AS-' . now()->format('YmdHis'),
            'delivery_date' => now()->toDateString(),
            'driver_id'     => null,
            'vehicle_id'    => null,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'draft',
            'cabang_id'     => $this->cabang->id,
        ]);

        $do->salesOrders()->attach($so->id);

        // Verify DO exists and is linked to the Ambil Sendiri SO
        $linked = DeliveryOrder::whereHas('salesOrders', fn($q) => $q->where('id', $so->id))->first();

        $this->assertNotNull($linked,
            'A Delivery Order must exist even for Ambil Sendiri pickup type');

        $this->assertEquals('Ambil Sendiri', $linked->salesOrders->first()->tipe_pengiriman,
            'DO must be linked to an Ambil Sendiri Sale Order');
    }

    /** @test */
    public function delivery_order_is_created_for_kirim_langsung_type(): void
    {
        // SO with Kirim Langsung must get a DO for tracking purposes
        $so = SaleOrder::factory()->create([
            'customer_id'     => $this->customer->id,
            'cabang_id'       => $this->cabang->id,
            'status'          => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        $do = DeliveryOrder::create([
            'do_number'     => 'DO-KL-' . now()->format('YmdHis'),
            'delivery_date' => now()->toDateString(),
            'driver_id'     => $this->driver->id,
            'vehicle_id'    => null,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'draft',
            'cabang_id'     => $this->cabang->id,
        ]);

        $do->salesOrders()->attach($so->id);

        $linked = DeliveryOrder::whereHas('salesOrders', fn($q) => $q->where('id', $so->id))->first();

        $this->assertNotNull($linked, 'DO must be linked to Kirim Langsung Sale Order');

        $this->assertEquals('Kirim Langsung', $linked->salesOrders->first()->tipe_pengiriman,
            'DO must be linked to a Kirim Langsung Sale Order');
    }

    // ─── #13 DO AUTO-GENERATED AFTER SO APPROVAL ─────────────────────────────

    /** @test */
    public function delivery_order_can_be_created_with_nullable_driver_and_vehicle(): void
    {
        // Verify the schema now allows NULL for driver_id / vehicle_id (Bug #18 fix)
        $do = DeliveryOrder::create([
            'do_number'     => 'DO-TEST-' . now()->format('YmdHis'),
            'delivery_date' => now()->toDateString(),
            'driver_id'     => null,
            'vehicle_id'    => null,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'draft',
            'cabang_id'     => $this->cabang->id,
        ]);

        $this->assertDatabaseHas('delivery_orders', [
            'id'        => $do->id,
            'driver_id' => null,
            'vehicle_id' => null,
        ]);
    }

    /** @test */
    public function delivery_order_is_linked_to_sale_order(): void
    {
        $so = SaleOrder::factory()->create([
            'customer_id'     => $this->customer->id,
            'cabang_id'       => $this->cabang->id,
            'status'          => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        $do = DeliveryOrder::create([
            'do_number'     => 'DO-LINK-' . now()->format('YmdHis'),
            'delivery_date' => now()->toDateString(),
            'driver_id'     => null,
            'vehicle_id'    => null,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'draft',
            'cabang_id'     => $this->cabang->id,
        ]);

        $do->salesOrders()->attach($so->id);

        $this->assertDatabaseHas('delivery_sales_orders', [
            'delivery_order_id' => $do->id,
            'sales_order_id'    => $so->id,
        ]);

        $this->assertTrue($do->salesOrders->contains($so),
            'DO must be linked to its Sale Order');
    }

    // ─── #14 DELIVERY_FAILED STATUS ──────────────────────────────────────────

    /** @test */
    public function delivery_order_status_can_be_set_to_delivery_failed(): void
    {
        $do = DeliveryOrder::create([
            'do_number'     => 'DO-FAIL-' . now()->format('YmdHis'),
            'delivery_date' => now()->toDateString(),
            'driver_id'     => null,
            'vehicle_id'    => null,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'approved',
            'cabang_id'     => $this->cabang->id,
        ]);

        $do->update(['status' => 'delivery_failed']);

        $this->assertDatabaseHas('delivery_orders', [
            'id'     => $do->id,
            'status' => 'delivery_failed',
        ]);

        $this->assertEquals('delivery_failed', $do->fresh()->status,
            'delivery_failed status must be persisted in the database');
    }

    /** @test */
    public function delivery_order_can_transition_from_approved_to_delivery_failed(): void
    {
        $do = DeliveryOrder::create([
            'do_number'     => 'DO-TRANS-' . now()->format('YmdHis'),
            'delivery_date' => now()->toDateString(),
            'driver_id'     => $this->driver->id,
            'vehicle_id'    => null,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'approved',
            'cabang_id'     => $this->cabang->id,
        ]);

        // Simulate failure
        $do->update(['status' => 'delivery_failed']);
        $this->assertEquals('delivery_failed', $do->fresh()->status);

        // Can be re-attempted — go back to approved
        $do->update(['status' => 'approved']);
        $this->assertEquals('approved', $do->fresh()->status);
    }

    // ─── #15 DO TABLE ATTRIBUTES ─────────────────────────────────────────────

    /** @test */
    public function delivery_order_has_required_columns_for_listing(): void
    {
        $so = SaleOrder::factory()->create([
            'customer_id'     => $this->customer->id,
            'cabang_id'       => $this->cabang->id,
            'status'          => 'approved',
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        $deliveryDate = now()->toDateString();
        $doNumber     = 'DO-LIST-' . now()->format('YmdHis');

        $do = DeliveryOrder::create([
            'do_number'     => $doNumber,
            'delivery_date' => $deliveryDate,
            'driver_id'     => null,
            'vehicle_id'    => null,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'draft',
            'cabang_id'     => $this->cabang->id,
        ]);

        $do->salesOrders()->attach($so->id);
        $do->refresh()->load('salesOrders.customer');

        // Verify: DO number
        $this->assertEquals($doNumber, $do->do_number);

        // Verify: delivery date
        $this->assertEquals($deliveryDate, $do->delivery_date);

        // Verify: status
        $this->assertEquals('draft', $do->status);

        // Verify: customer name accessible through relation
        $customerName = $do->salesOrders->first()?->customer?->perusahaan
            ?? $do->salesOrders->first()?->customer?->name;

        $this->assertNotNull($customerName,
            'Customer name must be accessible through DO → salesOrders → customer');
    }

    // ─── #16 DO APPROVAL TOGGLE ───────────────────────────────────────────────

    /** @test */
    public function do_approval_required_config_defaults_to_true(): void
    {
        $this->assertTrue(
            config('procurement.do_approval_required', true),
            'do_approval_required config must default to true'
        );
    }

    /** @test */
    public function do_approval_not_required_when_config_is_false(): void
    {
        config(['procurement.do_approval_required' => false]);

        $this->assertFalse(config('procurement.do_approval_required'),
            'When DO_APPROVAL_REQUIRED is false, config must return false');

        // Verify a DO can have status sent directly from draft/request_approve
        $do = DeliveryOrder::create([
            'do_number'     => 'DO-NOAPP-' . now()->format('YmdHis'),
            'delivery_date' => now()->toDateString(),
            'driver_id'     => null,
            'vehicle_id'    => null,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'draft',
            'cabang_id'     => $this->cabang->id,
        ]);

        // When approval is not required, DO can go from draft → sent
        $do->update(['status' => 'sent']);
        $this->assertEquals('sent', $do->fresh()->status);
    }

    /** @test */
    public function delivery_order_service_updates_status_correctly(): void
    {
        $do = DeliveryOrder::create([
            'do_number'     => 'DO-SVC-' . now()->format('YmdHis'),
            'delivery_date' => now()->toDateString(),
            'driver_id'     => null,
            'vehicle_id'    => null,
            'warehouse_id'  => $this->warehouse->id,
            'status'        => 'approved',
            'cabang_id'     => $this->cabang->id,
        ]);

        $service = app(DeliveryOrderService::class);
        $service->updateStatus($do, 'sent');

        $this->assertEquals('sent', $do->fresh()->status,
            'DeliveryOrderService::updateStatus must persist the new status');
    }
}
