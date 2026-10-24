<?php

namespace Tests\Feature\Master;

use App\Models\Cabang;
use App\Models\DeliveryOrder;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeArmadaTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;
    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);

        $this->cabang = Cabang::create([
            'kode' => 'CBG-' . rand(100, 999),
            'nama' => 'Cabang Armada Test',
            'alamat' => 'Jl. Armada No. 1',
            'status' => true,
        ]);
    }

    // ==================== KENDARAAN (VEHICLE) TESTS ====================

    public function test_user_can_view_kendaraan_index_page(): void
    {
        $plate = 'B ' . rand(1000, 9999) . ' XYZ';
        $vehicle = Vehicle::create([
            'plate' => $plate,
            'type' => 'Truk Engkel',
            'capacity' => '2.5 Ton',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.kendaraan.index'));

        $response->assertOk();
        $response->assertSee('Daftar Kendaraan Pengiriman');
        $response->assertSee($plate);
        $response->assertSee('Truk Engkel');
    }

    public function test_user_can_view_create_kendaraan_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.kendaraan.create'));

        $response->assertOk();
        $response->assertSee('Tambah Armada Kendaraan');
    }

    public function test_user_can_store_new_kendaraan(): void
    {
        $plate = 'D ' . rand(1000, 9999) . ' ABC';
        $payload = [
            'plate' => $plate,
            'type' => 'Pickup Grand Max',
            'capacity' => '1.5 Ton',
            'cabang_id' => $this->cabang->id,
        ];

        $response = $this->actingAs($this->user)->post(route('master.kendaraan.store'), $payload);

        $response->assertRedirect(route('master.kendaraan.index'));
        $this->assertDatabaseHas('vehicles', [
            'plate' => $plate,
            'type' => 'Pickup Grand Max',
            'cabang_id' => $this->cabang->id,
        ]);
    }

    public function test_user_can_view_edit_kendaraan_page(): void
    {
        $vehicle = Vehicle::create([
            'plate' => 'B ' . rand(1000, 9999) . ' EDIT',
            'type' => 'Blind Van',
            'capacity' => '800 Kg',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.kendaraan.edit', $vehicle));

        $response->assertOk();
        $response->assertSee('Edit Armada');
        $response->assertSee($vehicle->plate);
    }

    public function test_user_can_update_kendaraan(): void
    {
        $vehicle = Vehicle::create([
            'plate' => 'B ' . rand(1000, 9999) . ' UPD',
            'type' => 'Truk Awal',
            'capacity' => '2 Ton',
            'cabang_id' => $this->cabang->id,
        ]);

        $newPlate = 'B ' . rand(1000, 9999) . ' NEW';
        $payload = [
            'plate' => $newPlate,
            'type' => 'Truk Sesudah',
            'capacity' => '3 Ton',
            'cabang_id' => $this->cabang->id,
        ];

        $response = $this->actingAs($this->user)->put(route('master.kendaraan.update', $vehicle), $payload);

        $response->assertRedirect(route('master.kendaraan.index'));
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'plate' => $newPlate,
            'type' => 'Truk Sesudah',
            'capacity' => '3 Ton',
        ]);
    }

    public function test_user_can_delete_unlinked_kendaraan(): void
    {
        $vehicle = Vehicle::create([
            'plate' => 'B ' . rand(1000, 9999) . ' DEL',
            'type' => 'Pickup Siap Hapus',
            'capacity' => '1 Ton',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('master.kendaraan.destroy', $vehicle));

        $response->assertRedirect(route('master.kendaraan.index'));
        $this->assertSoftDeleted('vehicles', [
            'id' => $vehicle->id,
        ]);
    }

    public function test_deletion_is_blocked_if_kendaraan_has_delivery_orders(): void
    {
        $vehicle = Vehicle::create([
            'plate' => 'B ' . rand(1000, 9999) . ' DO',
            'type' => 'Truk Terikat DO',
            'capacity' => '4 Ton',
            'cabang_id' => $this->cabang->id,
        ]);

        $do = DeliveryOrder::first();
        if ($do) {
            $do->update(['vehicle_id' => $vehicle->id]);
        }

        $response = $this->actingAs($this->user)->delete(route('master.kendaraan.destroy', $vehicle));

        $response->assertRedirect(route('master.kendaraan.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'deleted_at' => null,
        ]);
    }

    // ==================== DRIVER / SUPIR TESTS ====================

    public function test_user_can_view_driver_index_page(): void
    {
        $driver = Driver::create([
            'name' => 'Supir Tes ' . rand(100, 999),
            'phone' => '08123456789',
            'license' => 'SIM B1 - 998877',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.driver.index'));

        $response->assertOk();
        $response->assertSee('Daftar Supir / Pengemudi');
        $response->assertSee($driver->name);
        $response->assertSee($driver->phone);
    }

    public function test_user_can_view_create_driver_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.driver.create'));

        $response->assertOk();
        $response->assertSee('Tambah Pengemudi Baru');
    }

    public function test_user_can_store_new_driver(): void
    {
        $name = 'Supir Baru ' . rand(100, 999);
        $payload = [
            'name' => $name,
            'phone' => '087799881122',
            'license' => 'SIM B2 Umum',
            'cabang_id' => $this->cabang->id,
        ];

        $response = $this->actingAs($this->user)->post(route('master.driver.store'), $payload);

        $response->assertRedirect(route('master.driver.index'));
        $this->assertDatabaseHas('drivers', [
            'name' => $name,
            'phone' => '087799881122',
            'cabang_id' => $this->cabang->id,
        ]);
    }

    public function test_user_can_view_edit_driver_page(): void
    {
        $driver = Driver::create([
            'name' => 'Supir Edit ' . rand(100, 999),
            'phone' => '0899112233',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.driver.edit', $driver));

        $response->assertOk();
        $response->assertSee('Edit Driver');
        $response->assertSee($driver->name);
    }

    public function test_user_can_update_driver(): void
    {
        $driver = Driver::create([
            'name' => 'Supir Awal ' . rand(100, 999),
            'phone' => '0811223344',
            'cabang_id' => $this->cabang->id,
        ]);

        $newName = 'Supir Akhir ' . rand(100, 999);
        $payload = [
            'name' => $newName,
            'phone' => '0855667788',
            'license' => 'SIM A',
            'cabang_id' => $this->cabang->id,
        ];

        $response = $this->actingAs($this->user)->put(route('master.driver.update', $driver), $payload);

        $response->assertRedirect(route('master.driver.index'));
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'name' => $newName,
            'phone' => '0855667788',
        ]);
    }

    public function test_user_can_delete_unlinked_driver(): void
    {
        $driver = Driver::create([
            'name' => 'Supir Siap Hapus ' . rand(100, 999),
            'phone' => '081299990000',
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('master.driver.destroy', $driver));

        $response->assertRedirect(route('master.driver.index'));
        $this->assertSoftDeleted('drivers', [
            'id' => $driver->id,
        ]);
    }

    public function test_deletion_is_blocked_if_driver_has_delivery_orders(): void
    {
        $driver = Driver::create([
            'name' => 'Supir Terikat DO ' . rand(100, 999),
            'phone' => '081233445566',
            'cabang_id' => $this->cabang->id,
        ]);

        $do = DeliveryOrder::first();
        if ($do) {
            $do->update(['driver_id' => $driver->id]);
        }

        $response = $this->actingAs($this->user)->delete(route('master.driver.destroy', $driver));

        $response->assertRedirect(route('master.driver.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'deleted_at' => null,
        ]);
    }
}
