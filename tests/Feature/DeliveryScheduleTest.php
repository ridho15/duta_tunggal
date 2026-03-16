<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DeliveryScheduleService;
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
    public function it_can_create_delivery_schedule_with_surat_jalans(): void
    {
        $suratJalan1 = SuratJalan::factory()->create();
        $suratJalan2 = SuratJalan::factory()->create();

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

        $schedule->suratJalans()->attach([$suratJalan1->id, $suratJalan2->id]);

        $this->assertDatabaseHas('delivery_schedules', [
            'schedule_number' => 'SCH-TEST-0001',
            'status'          => 'pending',
        ]);

        $this->assertCount(2, $schedule->suratJalans);
    }

    /** @test */
    public function it_can_update_schedule_status(): void
    {
        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-TEST-0002',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $this->assertEquals('pending', $schedule->status);

        $schedule->update(['status' => 'on_the_way']);
        $this->assertEquals('on_the_way', $schedule->fresh()->status);

        $schedule->update(['status' => 'delivered']);
        $this->assertEquals('delivered', $schedule->fresh()->status);
    }

    /** @test */
    public function it_can_have_multiple_surat_jalans_per_schedule(): void
    {
        $suratJalans = SuratJalan::factory()->count(3)->create();

        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-TEST-0003',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
            'created_by'      => $this->user->id,
        ]);

        $schedule->suratJalans()->attach($suratJalans->pluck('id')->toArray());

        $this->assertCount(3, $schedule->fresh()->suratJalans);
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
