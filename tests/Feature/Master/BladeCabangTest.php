<?php

namespace Tests\Feature\Master;

use App\Models\Cabang;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeCabangTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole($role);
    }

    public function test_user_can_view_cabang_index_page(): void
    {
        $kode = 'CBG-' . rand(100, 999);
        $cabang = Cabang::create([
            'kode' => $kode,
            'nama' => 'Cabang Uji Coba',
            'alamat' => 'Jl. Pengujian No. 123',
            'telepon' => '081234567890',
            'status' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.cabang.index'));

        $response->assertOk();
        $response->assertSee('Daftar Kantor Cabang');
        $response->assertSee($kode);
        $response->assertSee('Cabang Uji Coba');
    }

    public function test_user_can_view_create_cabang_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.cabang.create'));

        $response->assertOk();
        $response->assertSee('Tambah Cabang Baru');
    }

    public function test_user_can_store_new_cabang(): void
    {
        $kode = 'CBG-' . rand(100, 999);
        $payload = [
            'kode' => $kode,
            'nama' => 'Cabang Baru Test',
            'alamat' => 'Jl. Ahmad Yani No. 99',
            'telepon' => '082199887766',
            'status' => 1,
            'kenaikan_harga' => 2.5,
            'tipe_penjualan' => 'retail',
        ];

        $response = $this->actingAs($this->user)->post(route('master.cabang.store'), $payload);

        $response->assertRedirect(route('master.cabang.index'));
        $this->assertDatabaseHas('cabangs', [
            'kode' => $kode,
            'nama' => 'Cabang Baru Test',
        ]);
    }

    public function test_user_can_view_edit_cabang_page(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-' . rand(100, 999),
            'nama' => 'Cabang Edit Test',
            'alamat' => 'Jl. Uji No. 1',
            'status' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.cabang.edit', $cabang));

        $response->assertOk();
        $response->assertSee('Edit Cabang');
        $response->assertSee($cabang->nama);
    }

    public function test_user_can_update_cabang(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-' . rand(100, 999),
            'nama' => 'Cabang Sebelum Update',
            'alamat' => 'Jl. Uji Lama No. 2',
            'status' => true,
        ]);

        $payload = [
            'kode' => $cabang->kode,
            'nama' => 'Cabang Sesudah Update',
            'status' => 0,
            'alamat' => 'Alamat baru cabang',
            'telepon' => '081299998888',
        ];

        $response = $this->actingAs($this->user)->put(route('master.cabang.update', $cabang), $payload);

        $response->assertRedirect(route('master.cabang.index'));
        $this->assertDatabaseHas('cabangs', [
            'id' => $cabang->id,
            'nama' => 'Cabang Sesudah Update',
            'status' => 0,
        ]);
    }

    public function test_user_can_delete_unlinked_cabang(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-' . rand(100, 999),
            'nama' => 'Cabang Siap Hapus',
            'alamat' => 'Jl. Siap Hapus',
            'status' => true,
        ]);

        $response = $this->actingAs($this->user)->delete(route('master.cabang.destroy', $cabang));

        $response->assertRedirect(route('master.cabang.index'));
        $this->assertSoftDeleted('cabangs', [
            'id' => $cabang->id,
        ]);
    }

    public function test_deletion_is_blocked_if_cabang_has_warehouse(): void
    {
        $cabang = Cabang::create([
            'kode' => 'CBG-' . rand(100, 999),
            'nama' => 'Cabang Terikat Gudang',
            'alamat' => 'Jl. Terikat Gudang',
            'status' => true,
        ]);

        Warehouse::create([
            'kode' => 'GDG-' . rand(100, 999),
            'name' => 'Gudang Terikat',
            'location' => 'Kawasan Industri Cikarang',
            'cabang_id' => $cabang->id,
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->delete(route('master.cabang.destroy', $cabang));

        $response->assertRedirect(route('master.cabang.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('cabangs', [
            'id' => $cabang->id,
            'deleted_at' => null,
        ]);
    }
}
