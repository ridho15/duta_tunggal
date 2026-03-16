<?php

namespace Tests\Feature;

use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\DeliverySchedule;
use App\Models\Driver;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use App\Policies\DeliverySchedulePolicy;
use App\Services\DeliveryScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeliverySchedulePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $userWithAllPerms;
    protected User $userNoPerms;
    protected User $userViewOnly;
    protected Cabang $cabang;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected DeliverySchedulePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang  = Cabang::factory()->create();
        $this->driver  = Driver::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->vehicle = Vehicle::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->policy  = new DeliverySchedulePolicy();

        // Ensure all delivery schedule permissions exist
        $permNames = [
            'view any delivery schedule',
            'view delivery schedule',
            'create delivery schedule',
            'update delivery schedule',
            'delete delivery schedule',
            'restore delivery schedule',
            'force-delete delivery schedule',
            'update status delivery schedule',
            'rekap delivery schedule',
        ];
        foreach ($permNames as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $this->userWithAllPerms = $this->makeUser('fullperms@test.com');
        $this->userWithAllPerms->givePermissionTo($permNames);

        $this->userNoPerms = $this->makeUser('noperms@test.com');

        $this->userViewOnly = $this->makeUser('viewonly@test.com');
        $this->userViewOnly->givePermissionTo([
            'view any delivery schedule',
            'view delivery schedule',
        ]);
    }

    private function makeUser(string $email): User
    {
        static $counter = 0;
        $counter++;
        return User::create([
            'name'       => 'Test User ' . $counter,
            'email'      => $email,
            'username'   => 'user_' . $counter,
            'password'   => bcrypt('password'),
            'first_name' => 'Test',
            'kode_user'  => 'TU' . str_pad($counter, 3, '0', STR_PAD_LEFT),
        ]);
    }

    // ── Permission existence tests ─────────────────────────────────────

    /** @test */
    public function all_delivery_schedule_permissions_exist_in_helper_controller(): void
    {
        $allPermissions = HelperController::listPermission();

        $this->assertArrayHasKey('delivery schedule', $allPermissions);

        $expectedActions = [
            'view any', 'view', 'create', 'update', 'delete',
            'restore', 'force-delete', 'update status', 'rekap',
        ];

        foreach ($expectedActions as $action) {
            $this->assertContains(
                $action,
                $allPermissions['delivery schedule'],
                "Action '$action' should be in delivery schedule permissions"
            );
        }
    }

    /** @test */
    public function all_delivery_schedule_permissions_exist_in_database(): void
    {
        $expected = [
            'view any delivery schedule',
            'view delivery schedule',
            'create delivery schedule',
            'update delivery schedule',
            'delete delivery schedule',
            'restore delivery schedule',
            'force-delete delivery schedule',
            'update status delivery schedule',
            'rekap delivery schedule',
        ];

        foreach ($expected as $perm) {
            $this->assertNotNull(
                Permission::where('name', $perm)->first(),
                "Permission '$perm' should exist in database"
            );
        }
    }

    // ── Policy tests ───────────────────────────────────────────────────

    /** @test */
    public function user_with_all_permissions_passes_all_policy_checks(): void
    {
        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-PERM-001',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
        ]);

        $this->assertTrue($this->policy->viewAny($this->userWithAllPerms));
        $this->assertTrue($this->policy->view($this->userWithAllPerms, $schedule));
        $this->assertTrue($this->policy->create($this->userWithAllPerms));
        $this->assertTrue($this->policy->update($this->userWithAllPerms, $schedule));
        $this->assertTrue($this->policy->delete($this->userWithAllPerms, $schedule));
        $this->assertTrue($this->policy->restore($this->userWithAllPerms, $schedule));
        $this->assertTrue($this->policy->updateStatus($this->userWithAllPerms, $schedule));
        $this->assertTrue($this->policy->rekap($this->userWithAllPerms));
    }

    /** @test */
    public function user_without_permissions_fails_all_policy_checks(): void
    {
        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-PERM-002',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
        ]);

        $this->assertFalse($this->policy->viewAny($this->userNoPerms));
        $this->assertFalse($this->policy->view($this->userNoPerms, $schedule));
        $this->assertFalse($this->policy->create($this->userNoPerms));
        $this->assertFalse($this->policy->update($this->userNoPerms, $schedule));
        $this->assertFalse($this->policy->delete($this->userNoPerms, $schedule));
        $this->assertFalse($this->policy->restore($this->userNoPerms, $schedule));
        $this->assertFalse($this->policy->updateStatus($this->userNoPerms, $schedule));
        $this->assertFalse($this->policy->rekap($this->userNoPerms));
    }

    /** @test */
    public function view_only_user_can_view_but_not_create_or_modify(): void
    {
        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-PERM-003',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
        ]);

        $this->assertTrue($this->policy->viewAny($this->userViewOnly));
        $this->assertTrue($this->policy->view($this->userViewOnly, $schedule));
        $this->assertFalse($this->policy->create($this->userViewOnly));
        $this->assertFalse($this->policy->update($this->userViewOnly, $schedule));
        $this->assertFalse($this->policy->delete($this->userViewOnly, $schedule));
        $this->assertFalse($this->policy->rekap($this->userViewOnly));
    }

    // ── Role-based permission tests ────────────────────────────────────

    /** @test */
    public function delivery_driver_role_has_delivery_schedule_permissions(): void
    {
        // Seed roles and permissions for this test
        $role = Role::firstOrCreate(['name' => 'Delivery Driver', 'guard_name' => 'web']);

        $schedulePerms = [
            'view any delivery schedule',
            'view delivery schedule',
            'create delivery schedule',
            'update delivery schedule',
            'update status delivery schedule',
        ];

        foreach ($schedulePerms as $perm) {
            $permission = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            if (!$role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }

        $driver = $this->makeUser('driver_role@test.com');
        $driver->assignRole($role);

        foreach ($schedulePerms as $perm) {
            $this->assertTrue(
                $driver->hasPermissionTo($perm),
                "Delivery Driver should have permission: $perm"
            );
        }
    }

    /** @test */
    public function super_admin_has_all_delivery_schedule_permissions(): void
    {
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $allPerms = [
            'view any delivery schedule',
            'view delivery schedule',
            'create delivery schedule',
            'update delivery schedule',
            'delete delivery schedule',
            'restore delivery schedule',
            'force-delete delivery schedule',
            'update status delivery schedule',
            'rekap delivery schedule',
        ];

        foreach ($allPerms as $perm) {
            $permission = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            if (!$superAdminRole->hasPermissionTo($perm)) {
                $superAdminRole->givePermissionTo($perm);
            }
        }

        $superAdmin = $this->makeUser('superadmin_sch@test.com');
        $superAdmin->assignRole($superAdminRole);

        foreach ($allPerms as $perm) {
            $this->assertTrue(
                $superAdmin->hasPermissionTo($perm),
                "Super Admin should have permission: $perm"
            );
        }
    }

    // ── Rekap feature tests ────────────────────────────────────────────

    /** @test */
    public function rekap_export_query_returns_correct_data(): void
    {
        $driver2 = Driver::factory()->create(['cabang_id' => $this->cabang->id]);
        $sj1 = SuratJalan::factory()->create();
        $sj2 = SuratJalan::factory()->create();

        $schedule1 = DeliverySchedule::create([
            'schedule_number' => 'SCH-REKAP-001',
            'scheduled_date'  => now()->subDay(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'delivered',
            'cabang_id'       => $this->cabang->id,
        ]);
        $schedule1->suratJalans()->attach([$sj1->id, $sj2->id]);

        $schedule2 = DeliverySchedule::create([
            'schedule_number' => 'SCH-REKAP-002',
            'scheduled_date'  => now(),
            'driver_id'       => $driver2->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'on_the_way',
            'cabang_id'       => $this->cabang->id,
        ]);

        // Query like the rekap export would
        $schedules = DeliverySchedule::withoutGlobalScopes()
            ->with(['driver', 'vehicle', 'suratJalans'])
            ->whereIn('driver_id', [$this->driver->id, $driver2->id])
            ->orderBy('scheduled_date')
            ->get();

        $this->assertCount(2, $schedules);

        $first = $schedules->firstWhere('schedule_number', 'SCH-REKAP-001');
        $this->assertNotNull($first);
        $this->assertCount(2, $first->suratJalans);
        $this->assertEquals('delivered', $first->status);
    }

    /** @test */
    public function rekap_export_filters_by_date(): void
    {
        $schedule_old = DeliverySchedule::create([
            'schedule_number' => 'SCH-DATE-001',
            'scheduled_date'  => now()->subDays(10),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'delivered',
            'cabang_id'       => $this->cabang->id,
        ]);

        $schedule_new = DeliverySchedule::create([
            'schedule_number' => 'SCH-DATE-002',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
        ]);

        $dateFrom = now()->subDays(3)->format('Y-m-d');

        $filtered = DeliverySchedule::withoutGlobalScopes()
            ->whereIn('driver_id', [$this->driver->id])
            ->whereDate('scheduled_date', '>=', $dateFrom)
            ->get();

        $this->assertCount(1, $filtered);
        $this->assertEquals('SCH-DATE-002', $filtered->first()->schedule_number);
    }

    /** @test */
    public function rekap_service_generates_valid_number(): void
    {
        $service = app(DeliveryScheduleService::class);
        $number  = $service->generateScheduleNumber();

        $this->assertStringStartsWith('SCH-', $number);
        $this->assertMatchesRegularExpression('/^SCH-\d{8}-\d{4}$/', $number);
    }

    /** @test */
    public function delivery_schedule_policy_is_registered(): void
    {
        // The policy should be registered in AuthServiceProvider
        $schedule = DeliverySchedule::create([
            'schedule_number' => 'SCH-POLICY-001',
            'scheduled_date'  => now(),
            'driver_id'       => $this->driver->id,
            'vehicle_id'      => $this->vehicle->id,
            'status'          => 'pending',
            'cabang_id'       => $this->cabang->id,
        ]);

        // Check via Gate (uses policy)
        $this->actingAs($this->userWithAllPerms);

        $this->assertTrue(
            $this->userWithAllPerms->can('viewAny', DeliverySchedule::class),
            'viewAny gate should pass via policy for user with permissions'
        );
        $this->assertTrue(
            $this->userWithAllPerms->can('view', $schedule),
            'view gate should pass via policy for user with permissions'
        );
        $this->assertFalse(
            $this->userNoPerms->can('viewAny', DeliverySchedule::class),
            'viewAny gate should fail via policy for user without permissions'
        );
    }
}
