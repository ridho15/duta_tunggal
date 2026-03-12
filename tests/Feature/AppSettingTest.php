<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DeliveryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the AppSetting model and DO-approval toggle (Task 20).
 */
class AppSettingTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // AppSetting model unit-level tests
    // -------------------------------------------------------------------------

    /** @test */
    public function it_returns_default_when_key_is_absent(): void
    {
        $value = AppSetting::get('non_existent_key', 'fallback');
        $this->assertSame('fallback', $value);
    }

    /** @test */
    public function it_persists_and_retrieves_a_setting(): void
    {
        AppSetting::set('some_key', 'some_value', 'A test setting');

        $this->assertDatabaseHas('app_settings', [
            'key'   => 'some_key',
            'value' => 'some_value',
        ]);

        $this->assertSame('some_value', AppSetting::get('some_key'));
    }

    /** @test */
    public function it_upserts_existing_setting(): void
    {
        AppSetting::set('some_key', 'first');
        AppSetting::set('some_key', 'second');

        $this->assertDatabaseCount('app_settings', 1);
        $this->assertSame('second', AppSetting::get('some_key'));
    }

    /** @test */
    public function it_casts_truthy_strings_to_boolean_true(): void
    {
        AppSetting::set('flag', '1');
        $this->assertTrue(AppSetting::get('flag'));

        AppSetting::set('flag2', 'true');
        $this->assertTrue(AppSetting::get('flag2'));
    }

    /** @test */
    public function it_casts_falsy_strings_to_boolean_false(): void
    {
        AppSetting::set('flag', '0');
        $this->assertFalse(AppSetting::get('flag'));

        AppSetting::set('flag2', 'false');
        $this->assertFalse(AppSetting::get('flag2'));
    }

    /** @test */
    public function do_approval_required_returns_true_by_default(): void
    {
        // No DB row — should fall back to config/env (default true)
        $this->assertTrue(AppSetting::doApprovalRequired());
    }

    /** @test */
    public function do_approval_required_returns_false_when_disabled_via_db(): void
    {
        AppSetting::set('do_approval_required', '0', 'Disable DO approval');
        $this->assertFalse(AppSetting::doApprovalRequired());
    }

    /** @test */
    public function do_approval_required_returns_true_when_enabled_via_db(): void
    {
        AppSetting::set('do_approval_required', '1', 'Enable DO approval');
        $this->assertTrue(AppSetting::doApprovalRequired());
    }

    // -------------------------------------------------------------------------
    // DO workflow integration tests
    // -------------------------------------------------------------------------

    private function makeUser(): User
    {
        return User::create([
            'name'       => 'Settings Tester',
            'email'      => 'settings@example.com',
            'username'   => 'settingstester',
            'password'   => bcrypt('password'),
            'first_name' => 'Settings',
            'kode_user'  => 'ST001',
        ]);
    }

    private function makeSaleOrder(Cabang $cabang, Customer $customer, Warehouse $warehouse, User $user): SaleOrder
    {
        $so = SaleOrder::create([
            'so_number'              => 'SO-SETTING-001',
            'customer_id'            => $customer->id,
            'status'                 => 'confirmed',
            'tipe_pengiriman'        => 'Kirim Langsung',
            'order_date'             => now()->toDateString(),
            'delivery_date'          => now()->toDateString(),
            'cabang_id'              => $cabang->id,
            'warehouse_id'           => $warehouse->id,
            'warehouse_confirmed_at' => now(),
            'created_by'             => $user->id,
        ]);

        return $so;
    }

    /** @test */
    public function when_approval_required_do_sent_action_requires_approved_status(): void
    {
        AppSetting::set('do_approval_required', '1');

        // Verify the setting is active
        $this->assertTrue(AppSetting::doApprovalRequired());

        $user      = $this->makeUser();
        $cabang    = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
        $customer  = Customer::factory()->create();
        $driver    = Driver::factory()->create();
        $vehicle   = Vehicle::factory()->create();
        $so        = $this->makeSaleOrder($cabang, $customer, $warehouse, $user);

        $do = DeliveryOrder::create([
            'do_number'     => 'DO-SETTING-001',
            'delivery_date' => now()->addDays(1)->toDateString(),
            'driver_id'     => $driver->id,
            'vehicle_id'    => $vehicle->id,
            'warehouse_id'  => $warehouse->id,
            'status'        => 'draft',       // Not yet approved
            'created_by'    => $user->id,
            'cabang_id'     => $cabang->id,
        ]);
        $do->salesOrders()->attach($so->id);

        // With approval required, a draft DO must NOT be directly sentable
        $this->assertDatabaseHas('delivery_orders', ['id' => $do->id, 'status' => 'draft']);

        // Simulate transitioning through approval
        $do->update(['status' => 'request_approve']);
        $do->update(['status' => 'approved']);

        $this->assertDatabaseHas('delivery_orders', ['id' => $do->id, 'status' => 'approved']);

        // Send is now permitted (service call)
        $service = app(DeliveryOrderService::class);
        $service->updateStatus($do, 'sent');

        $this->assertDatabaseHas('delivery_orders', ['id' => $do->id, 'status' => 'sent']);
    }

    /** @test */
    public function when_approval_not_required_do_can_be_sent_from_draft(): void
    {
        AppSetting::set('do_approval_required', '0');

        $this->assertFalse(AppSetting::doApprovalRequired());

        $user      = $this->makeUser();
        $cabang    = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
        $customer  = Customer::factory()->create();
        $driver    = Driver::factory()->create();
        $vehicle   = Vehicle::factory()->create();

        $so = SaleOrder::create([
            'so_number'              => 'SO-SETTING-002',
            'customer_id'            => $customer->id,
            'status'                 => 'confirmed',
            'tipe_pengiriman'        => 'Kirim Langsung',
            'order_date'             => now()->toDateString(),
            'delivery_date'          => now()->toDateString(),
            'cabang_id'              => $cabang->id,
            'warehouse_id'           => $warehouse->id,
            'warehouse_confirmed_at' => now(),
            'created_by'             => $user->id,
        ]);

        $do = DeliveryOrder::create([
            'do_number'     => 'DO-SETTING-002',
            'delivery_date' => now()->addDays(1)->toDateString(),
            'driver_id'     => $driver->id,
            'vehicle_id'    => $vehicle->id,
            'warehouse_id'  => $warehouse->id,
            'status'        => 'draft',
            'created_by'    => $user->id,
            'cabang_id'     => $cabang->id,
        ]);
        $do->salesOrders()->attach($so->id);

        // With approval NOT required, service should allow sending directly from draft
        $service = app(DeliveryOrderService::class);
        $service->updateStatus($do, 'sent');

        $this->assertDatabaseHas('delivery_orders', ['id' => $do->id, 'status' => 'sent']);
    }
}
