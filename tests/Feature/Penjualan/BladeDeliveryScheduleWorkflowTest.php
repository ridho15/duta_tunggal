<?php

namespace Tests\Feature\Penjualan;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DeliveryScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeDeliveryScheduleWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected User $superAdmin;
    protected User $adminPengiriman;
    protected User $kasirUser;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected Customer $customer;
    protected Product $product;
    protected DeliveryOrder $deliveryOrder;
    protected SuratJalan $suratJalan;

    protected function setUp(): void
    {
        parent::setUp();

        $roleSuper = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $rolePengiriman = Role::firstOrCreate(['name' => 'Admin Pengiriman', 'guard_name' => 'web']);
        $roleKasir = Role::firstOrCreate(['name' => 'Kasir', 'guard_name' => 'web']);

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-SCH-TEST',
            'nama' => 'Cabang Jadwal Test',
            'status' => true,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'name' => 'Gudang Logistik Test',
                'cabang_id' => $this->cabang->id,
                'kode' => 'GDG-SCH-01',
                'tipe' => 'utama',
                'status' => 'aktif',
            ]);

        $uniq = uniqid();
        $this->superAdmin = User::factory()->create([
            'email' => "sadmin_{$uniq}@example.com",
            'username' => "sadmin_{$uniq}",
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);
        $this->superAdmin->assignRole($roleSuper);

        $this->adminPengiriman = User::factory()->create([
            'email' => "pengiriman_{$uniq}@example.com",
            'username' => "pengiriman_{$uniq}",
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);
        $this->adminPengiriman->assignRole($rolePengiriman);

        $this->kasirUser = User::factory()->create([
            'email' => "kasir_{$uniq}@example.com",
            'username' => "kasir_{$uniq}",
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);
        $this->kasirUser->assignRole($roleKasir);

        $this->driver = Driver::withoutGlobalScopes()->firstOrCreate(
            ['name' => 'Budi Driver Test'],
            [
                'phone' => '081234567890',
                'license' => 'SIM B1 UMUM',
                'cabang_id' => $this->cabang->id,
            ]
        );

        $this->vehicle = Vehicle::withoutGlobalScopes()->firstOrCreate(
            ['plate' => 'B 9999 XYZ'],
            [
                'type' => 'Truck CDD',
                'capacity' => '4 Ton',
                'cabang_id' => $this->cabang->id,
            ]
        );

        $this->customer = Customer::withoutGlobalScopes()->first() ?? Customer::create([
            'code' => 'CUST-SCH-' . $uniq,
            'name' => 'Toko Bangunan Maju',
            'address' => 'Jl. Pengiriman Logistik No. 12',
            'phone' => '081987654321',
            'cabang_id' => $this->cabang->id,
        ]);

        $category = ProductCategory::withoutGlobalScopes()->first() ?? ProductCategory::create([
            'kode' => 'CAT-SCH',
            'name' => 'Kategori Logistik',
        ]);

        $uom = UnitOfMeasure::withoutGlobalScopes()->first() ?? UnitOfMeasure::create([
            'name' => 'Zak',
            'abbreviation' => 'zk',
        ]);

        $this->product = Product::withoutGlobalScopes()->first() ?? Product::create([
            'name' => 'Semen Gresik 50kg',
            'code' => 'SMN-GRS-50',
            'sku' => 'SMN-GRS-50',
            'product_category_id' => $category->id,
            'unit_id' => $uom->id,
            'price' => 75000,
            'cost' => 60000,
            'cabang_id' => $this->cabang->id,
            'status' => 'aktif',
        ]);

        // Siapkan DO dan Surat Jalan untuk muatan
        $this->deliveryOrder = DeliveryOrder::withoutGlobalScopes()->create([
            'do_number' => 'DO-TEST-' . $uniq,
            'delivery_date' => now(),
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->suratJalan = SuratJalan::withoutGlobalScopes()->create([
            'sj_number' => 'SJ-TEST-' . $uniq,
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED,
            'created_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->suratJalan->deliveryOrder()->sync([$this->deliveryOrder->id]);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('penjualan.schedules.index'));
        $response->assertRedirect('/login');
    }

    public function test_unauthorized_role_is_forbidden_to_access_schedules(): void
    {
        $response = $this->actingAs($this->kasirUser)->get(route('penjualan.schedules.index'));
        $response->assertStatus(403);
    }

    public function test_authorized_user_can_view_delivery_schedules_index(): void
    {
        $response = $this->actingAs($this->adminPengiriman)->get(route('penjualan.schedules.index'));
        $response->assertStatus(200);
        $response->assertViewIs('penjualan.schedules.index');
        $response->assertSee('Jadwal Kirim &amp; Armada', false);
        $response->assertSee('Menunggu Berangkat');
        $response->assertSee('Dalam Perjalanan');
        $response->assertSee('Selesai Terkirim');
    }

    public function test_authorized_user_can_view_create_schedule_form(): void
    {
        $response = $this->actingAs($this->superAdmin)->get(route('penjualan.schedules.create'));
        $response->assertStatus(200);
        $response->assertViewIs('penjualan.schedules.create');
        $response->assertSee('Buat Jadwal Pengiriman');
        $response->assertSee($this->driver->name);
        $response->assertSee($this->vehicle->plate);
    }

    public function test_can_store_delivery_schedule_with_internal_fleet(): void
    {
        $scheduleNum = 'SCH-UNIT-' . uniqid();
        $payload = [
            'schedule_number' => $scheduleNum,
            'scheduled_date' => now()->format('Y-m-d\TH:i'),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'cabang_id' => $this->cabang->id,
            'surat_jalan_ids' => [$this->suratJalan->id],
            'delivery_order_ids' => [$this->deliveryOrder->id],
            'notes' => 'Pengiriman material batch pagi',
        ];

        $response = $this->actingAs($this->adminPengiriman)->post(route('penjualan.schedules.store'), $payload);

        $schedule = DeliverySchedule::withoutGlobalScopes()->where('schedule_number', $scheduleNum)->first();
        $this->assertNotNull($schedule);
        $response->assertRedirect(route('penjualan.schedules.show', $schedule->id));
        $response->assertSessionHas('success');

        $this->assertEquals('pending', $schedule->status);
        $this->assertEquals($this->driver->id, $schedule->driver_id);
        $this->assertEquals($this->vehicle->id, $schedule->vehicle_id);
        $this->assertTrue($schedule->suratJalan->contains('id', $this->suratJalan->id));
        $this->assertTrue($schedule->deliveryOrders->contains('id', $this->deliveryOrder->id));
    }

    public function test_can_store_delivery_schedule_with_ekspedisi(): void
    {
        $scheduleNum = 'SCH-EKSP-' . uniqid();
        $payload = [
            'schedule_number' => $scheduleNum,
            'scheduled_date' => now()->format('Y-m-d\TH:i'),
            'delivery_method' => 'ekspedisi',
            'driver_name' => 'J&T Cargo Indah',
            'vehicle_info' => 'CDD Box B 1234 EXP',
            'tracking_number' => 'JT-EXP-8899',
            'cabang_id' => $this->cabang->id,
            'surat_jalan_ids' => [$this->suratJalan->id],
            'notes' => 'Kirim ekspedisi pihak ketiga',
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('penjualan.schedules.store'), $payload);

        $schedule = DeliverySchedule::withoutGlobalScopes()->where('schedule_number', $scheduleNum)->first();
        $this->assertNotNull($schedule);
        $response->assertRedirect(route('penjualan.schedules.show', $schedule->id));

        $this->assertEquals('ekspedisi', $schedule->delivery_method);
        $this->assertEquals('J&T Cargo Indah', $schedule->driver_name);
        $this->assertEquals('JT-EXP-8899', $schedule->tracking_number);
    }

    public function test_validation_fails_when_internal_fleet_has_no_driver_or_vehicle(): void
    {
        $payload = [
            'scheduled_date' => now()->format('Y-m-d\TH:i'),
            'delivery_method' => 'internal',
            'driver_id' => null,
            'vehicle_id' => null,
            'cabang_id' => $this->cabang->id,
            'surat_jalan_ids' => [$this->suratJalan->id],
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('penjualan.schedules.store'), $payload);
        $response->assertSessionHasErrors(['driver_id', 'vehicle_id']);
    }

    public function test_validation_fails_when_no_surat_jalan_or_do_is_selected(): void
    {
        $payload = [
            'scheduled_date' => now()->format('Y-m-d\TH:i'),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'cabang_id' => $this->cabang->id,
            'surat_jalan_ids' => [],
            'delivery_order_ids' => [],
        ];

        $response = $this->actingAs($this->superAdmin)->post(route('penjualan.schedules.store'), $payload);
        $response->assertSessionHasErrors(['surat_jalan_ids']);
    }

    public function test_can_view_delivery_schedule_detail_manifest(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-SHOW-' . uniqid(),
            'scheduled_date' => now(),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
            'created_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $schedule->suratJalan()->sync([$this->suratJalan->id]);

        $response = $this->actingAs($this->adminPengiriman)->get(route('penjualan.schedules.show', $schedule->id));
        $response->assertStatus(200);
        $response->assertViewIs('penjualan.schedules.show');
        $response->assertSee($schedule->schedule_number);
        $response->assertSee($this->driver->name);
        $response->assertSee($this->vehicle->plate);
    }

    public function test_can_edit_and_update_pending_schedule(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-EDIT-' . uniqid(),
            'scheduled_date' => now(),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
            'created_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);
        $schedule->suratJalan()->sync([$this->suratJalan->id]);

        $response = $this->actingAs($this->adminPengiriman)->get(route('penjualan.schedules.edit', $schedule->id));
        $response->assertStatus(200);
        $response->assertViewIs('penjualan.schedules.edit');

        $updatedPayload = [
            'schedule_number' => $schedule->schedule_number,
            'scheduled_date' => now()->addDay()->format('Y-m-d\TH:i'),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'cabang_id' => $this->cabang->id,
            'surat_jalan_ids' => [$this->suratJalan->id],
            'notes' => 'Catatan telah diperbarui',
        ];

        $updateResponse = $this->actingAs($this->adminPengiriman)->put(route('penjualan.schedules.update', $schedule->id), $updatedPayload);
        $updateResponse->assertRedirect(route('penjualan.schedules.show', $schedule->id));

        $schedule->refresh();
        $this->assertEquals('Catatan telah diperbarui', $schedule->notes);
    }

    public function test_cannot_edit_schedule_if_not_pending(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-LOCK-' . uniqid(),
            'scheduled_date' => now(),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'delivered',
            'created_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->adminPengiriman)->get(route('penjualan.schedules.edit', $schedule->id));
        $response->assertRedirect(route('penjualan.schedules.show', $schedule->id));
        $response->assertSessionHas('error');
    }

    public function test_can_start_delivery_schedule_and_move_to_on_the_way(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-START-' . uniqid(),
            'scheduled_date' => now(),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
            'created_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);
        $schedule->deliveryOrders()->sync([$this->deliveryOrder->id]);

        $response = $this->actingAs($this->adminPengiriman)->post(route('penjualan.schedules.start', $schedule->id));
        $response->assertRedirect(route('penjualan.schedules.show', $schedule->id));
        $response->assertSessionHas('success');

        $schedule->refresh();
        $this->assertEquals('on_the_way', $schedule->status);
    }

    public function test_can_complete_delivery_schedule(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-DONE-' . uniqid(),
            'scheduled_date' => now(),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'on_the_way',
            'created_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);
        $schedule->deliveryOrders()->sync([$this->deliveryOrder->id]);

        $response = $this->actingAs($this->adminPengiriman)->post(route('penjualan.schedules.complete', $schedule->id));
        $response->assertRedirect(route('penjualan.schedules.show', $schedule->id));
        $response->assertSessionHas('success');

        $schedule->refresh();
        $this->assertEquals('delivered', $schedule->status);
    }

    public function test_can_fail_delivery_schedule_with_reason(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-FAIL-' . uniqid(),
            'scheduled_date' => now(),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'on_the_way',
            'created_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->adminPengiriman)->post(route('penjualan.schedules.fail', $schedule->id), [
            'reason' => 'Toko customer tutup dan banjir di lokasi',
        ]);

        $response->assertRedirect(route('penjualan.schedules.show', $schedule->id));
        $response->assertSessionHas('success');

        $schedule->refresh();
        $this->assertEquals('failed', $schedule->status);
        $this->assertStringContainsString('Toko customer tutup dan banjir di lokasi', (string) $schedule->notes);
    }

    public function test_can_cancel_pending_delivery_schedule(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-CNC-' . uniqid(),
            'scheduled_date' => now(),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
            'created_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->adminPengiriman)->post(route('penjualan.schedules.cancel', $schedule->id), [
            'cancel_reason' => 'Driver sakit mendadak',
        ]);

        $response->assertRedirect(route('penjualan.schedules.show', $schedule->id));
        $response->assertSessionHas('success');

        $schedule->refresh();
        $this->assertEquals('cancelled', $schedule->status);
        $this->assertStringContainsString('Driver sakit mendadak', (string) $schedule->notes);
    }

    public function test_can_delete_pending_delivery_schedule(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-DEL-' . uniqid(),
            'scheduled_date' => now(),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
            'created_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('penjualan.schedules.destroy', $schedule->id));
        $response->assertRedirect(route('penjualan.schedules.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('delivery_schedules', ['id' => $schedule->id]);
    }

    public function test_can_stream_pdf_work_order(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-PDF-' . uniqid(),
            'scheduled_date' => now(),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'pending',
            'created_by' => $this->superAdmin->id,
            'cabang_id' => $this->cabang->id,
        ]);
        $schedule->deliveryOrders()->sync([$this->deliveryOrder->id]);

        $response = $this->actingAs($this->adminPengiriman)->get(route('penjualan.schedules.pdf', $schedule->id));
        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    public function test_can_create_driver_via_quick_modal_endpoint(): void
    {
        $response = $this->actingAs($this->adminPengiriman)->postJson(route('penjualan.schedules.quickDriver'), [
            'name' => 'Driver Kilat Baru',
            'phone' => '087788990011',
            'license' => 'SIM B2',
            'cabang_id' => $this->cabang->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'driver' => [
                'name' => 'Driver Kilat Baru',
            ],
        ]);

        $this->assertDatabaseHas('drivers', [
            'name' => 'Driver Kilat Baru',
            'cabang_id' => $this->cabang->id,
        ]);
    }

    public function test_can_create_vehicle_via_quick_modal_endpoint(): void
    {
        $response = $this->actingAs($this->adminPengiriman)->postJson(route('penjualan.schedules.quickVehicle'), [
            'plate' => 'B 7777 DT',
            'type' => 'Pickup',
            'capacity' => '1.5 Ton',
            'cabang_id' => $this->cabang->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'vehicle' => [
                'plate' => 'B 7777 DT',
            ],
        ]);

        $this->assertDatabaseHas('vehicles', [
            'plate' => 'B 7777 DT',
            'cabang_id' => $this->cabang->id,
        ]);
    }
}
