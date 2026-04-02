<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Filament\Resources\DeliveryScheduleResource;
use App\Services\DeliveryScheduleService;
use App\Models\SuratJalan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected DeliveryScheduleService $scheduleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'       => 'Test User',
            'email'      => 'schedule_test@example.com',
            'username'   => 'scheduleuser',
            'password'   => bcrypt('password'),
            'first_name' => 'Schedule',
            'kode_user'  => 'SCH001',
        ]);

        $this->cabang  = Cabang::factory()->create();
        $this->driver  = Driver::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->vehicle = Vehicle::factory()->create(['cabang_id' => $this->cabang->id]);

        $this->scheduleService = app(DeliveryScheduleService::class);
    }

    /** @test */
    public function it_can_generate_unique_schedule_number(): void
    {
        $first  = $this->scheduleService->generateScheduleNumber();
        $second = $this->scheduleService->generateScheduleNumber();

        $this->assertStringStartsWith('SCH-', $first);
        $this->assertEquals($first, $second, 'Sequential generation before first save should be same');
    }

    /** @test */
    public function it_generates_incrementing_schedule_numbers(): void
    {
        $first = DeliverySchedule::create([
            'schedule_number' => $this->scheduleService->generateScheduleNumber(),
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $second = $this->scheduleService->generateScheduleNumber();

        $this->assertNotEquals($first->schedule_number, $second);
    }

    /** @test */
    public function it_can_create_delivery_schedule_with_surat_jalan(): void
    {
        $deliveryOrder1 = DeliveryOrder::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 'approved']);
        $deliveryOrder2 = DeliveryOrder::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 'approved']);

        $suratJalan1 = SuratJalan::create([
            'sj_number' => 'SJ-TEST-0001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan2 = SuratJalan::create([
            'sj_number' => 'SJ-TEST-0002',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan1->deliveryOrder()->attach([$deliveryOrder1->id]);
        $suratJalan2->deliveryOrder()->attach([$deliveryOrder2->id]);

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-TEST-0001',
            'scheduled_date'  => now()->addDay(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'notes'           => 'Test schedule',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $schedule->suratJalan()->attach([$suratJalan1->id, $suratJalan2->id]);

        $this->assertDatabaseHas('delivery_schedules', [
            'schedule_number' => 'SCH-TEST-0001',
            'status'          => 'pending',
        ]);

        $this->assertCount(2, $schedule->suratJalan);
        $this->assertCount(2, $schedule->relatedDeliveryOrders());
    }

    /** @test */
    public function it_lists_only_surat_jalan_for_the_selected_cabang(): void
    {
        $approved = SuratJalan::create([
            'sj_number' => 'SJ-APPROVED-001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        SuratJalan::create([
            'sj_number' => 'SJ-DRAFT-001',
            'issued_at' => now(),
            'status' => 0,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $otherCabang = Cabang::factory()->create();
        SuratJalan::create([
            'sj_number' => 'SJ-APPROVED-OTHER',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $otherCabang->id,
        ]);

        $options = DeliveryScheduleResource::getSuratJalanOptions($this->cabang->id);

        $this->assertArrayHasKey($approved->id, $options);
        $this->assertSame('SJ-APPROVED-001', $options[$approved->id]);
        $this->assertNotContains('SJ-DRAFT-001', $options);
        $this->assertNotContains('SJ-APPROVED-OTHER', $options);
    }

    /** @test */
    public function it_can_update_schedule_status(): void
    {
        $deliveryOrder1 = DeliveryOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $deliveryOrder2 = DeliveryOrder::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $suratJalan1 = SuratJalan::create([
            'sj_number' => 'SJ-TEST-0003',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan2 = SuratJalan::create([
            'sj_number' => 'SJ-TEST-0004',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan1->deliveryOrder()->attach([$deliveryOrder1->id]);
        $suratJalan2->deliveryOrder()->attach([$deliveryOrder2->id]);

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-TEST-0002',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'notes'           => 'Status sync test',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $schedule->suratJalan()->attach([$suratJalan1->id, $suratJalan2->id]);

        $this->assertEquals('pending', $schedule->status);

        $schedule->update(['status' => 'on_the_way']);
        $this->assertEquals('on_the_way', $schedule->fresh()->status);
        $this->assertEquals('sent', $deliveryOrder1->fresh()->status);
        $this->assertEquals('sent', $deliveryOrder2->fresh()->status);

        $schedule->update(['status' => 'delivered']);
        $this->assertEquals('delivered', $schedule->fresh()->status);
        $this->assertEquals('completed', $deliveryOrder1->fresh()->status);
        $this->assertEquals('completed', $deliveryOrder2->fresh()->status);
    }

    /** @test */
    public function it_can_have_multiple_surat_jalan_per_schedule(): void
    {
        $deliveryOrders = DeliveryOrder::factory()->count(3)->create([
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
        ]);

        $suratJalans = collect($deliveryOrders)->map(function (DeliveryOrder $deliveryOrder, int $index) {
            $suratJalan = SuratJalan::create([
                'sj_number' => 'SJ-TEST-10' . ($index + 1),
                'issued_at' => now(),
                'status' => 1,
                'created_by' => $this->user->id,
                'cabang_id' => $this->cabang->id,
            ]);

            $suratJalan->deliveryOrder()->attach([$deliveryOrder->id]);

            return $suratJalan;
        });

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-TEST-0003',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $schedule->suratJalan()->attach($suratJalans->pluck('id')->toArray());

        $this->assertCount(3, $schedule->fresh()->suratJalan);
        $this->assertCount(3, $schedule->relatedDeliveryOrders());
    }

    /** @test */
    public function it_shows_selected_surat_jalan_and_delivery_orders_preview(): void
    {
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-PREVIEW-001',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan->deliveryOrder()->attach([$deliveryOrder->id]);

        $suratJalanPreview = DeliveryScheduleResource::getSelectedSuratJalanPreviewContent([
            $suratJalan->id,
        ], $this->cabang->id);

        $deliveryOrderPreview = DeliveryScheduleResource::getSelectedDeliveryOrderPreviewContent([
            $suratJalan->id,
        ], $this->cabang->id);

        $this->assertStringContainsString('SJ-PREVIEW-001', $suratJalanPreview);
        $this->assertStringContainsString('Jumlah Surat Jalan: <strong>1</strong>', $suratJalanPreview);
        $this->assertStringContainsString($deliveryOrder->do_number, $deliveryOrderPreview);
        $this->assertStringContainsString('Jumlah Delivery Order: <strong>1</strong>', $deliveryOrderPreview);
    }

    /** @test */
    public function delivered_schedule_completes_related_delivery_orders(): void
    {
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan = SuratJalan::create([
            'sj_number' => 'SJ-TEST-0005',
            'issued_at' => now(),
            'status' => 1,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $suratJalan->deliveryOrder()->attach([$deliveryOrder->id]);

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-TEST-0005',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $schedule->suratJalan()->attach([$suratJalan->id]);

        $schedule->update(['status' => 'delivered']);

        $this->assertEquals('completed', $deliveryOrder->fresh()->status);
    }

    /** @test */
    public function delivery_order_no_longer_requires_driver_and_vehicle(): void
    {
        // Verify DO model has nullable driver_id and vehicle_id
        $columns = \Schema::getColumnListing('delivery_orders');

        $this->assertContains('driver_id', $columns);
        $this->assertContains('vehicle_id', $columns);

        // Verify the fields are nullable by checking SHOW COLUMNS
        $columnDetails = \DB::select("SHOW COLUMNS FROM `delivery_orders` WHERE Field IN ('driver_id', 'vehicle_id')");

        foreach ($columnDetails as $col) {
            $this->assertEquals('YES', $col->Null, "Column {$col->Field} should be nullable on delivery_orders");
        }
    }

    /** @test */
    public function it_provides_correct_status_label(): void
    {
        $schedule = new DeliverySchedule(['status' => 'on_the_way']);
        $this->assertEquals('Sedang Berjalan', $schedule->status_label);

        $schedule->status = 'delivered';
        $this->assertEquals('Selesai / Terkirim', $schedule->status_label);

        $schedule->status = 'pending';
        $this->assertEquals('Menunggu Keberangkatan', $schedule->status_label);
    }

    /** @test */
    public function it_soft_deletes_schedule(): void
    {
        $schedule = DeliverySchedule::withoutGlobalScopes()->create([
            'schedule_number' => 'SCH-TEST-0004',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $id = $schedule->id;
        $schedule->delete();

        // After soft delete, regular find (with SoftDeletes scope) should return null
        $this->assertNull(DeliverySchedule::withoutGlobalScopes()->withoutGlobalScope(\Illuminate\Database\Eloquent\SoftDeletingScope::class)->whereNull('deleted_at')->find($id));
        // But withTrashed() should find it
        $this->assertNotNull(DeliverySchedule::withoutGlobalScopes()->withTrashed()->find($id));
    }
}
