<?php

namespace Tests\Feature\Master;

use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeWarehouseTest extends TestCase
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
            'nama' => 'Cabang Gudang Test',
            'alamat' => 'Jl. Gudang No. 1',
            'status' => true,
        ]);
    }

    public function test_user_can_view_warehouse_index_page(): void
    {
        $kode = 'GDG-' . rand(100, 999);
        $warehouse = Warehouse::create([
            'kode' => $kode,
            'name' => 'Gudang Utama Blade',
            'cabang_id' => $this->cabang->id,
            'location' => 'Blok A No. 10',
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.gudang.index'));

        $response->assertOk();
        $response->assertSee('Daftar Gudang Logistik');
        $response->assertSee($kode);
        $response->assertSee('Gudang Utama Blade');
    }

    public function test_user_can_view_create_warehouse_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.gudang.create'));

        $response->assertOk();
        $response->assertSee('Tambah Gudang Baru');
    }

    public function test_user_can_store_new_warehouse(): void
    {
        $kode = 'GDG-' . rand(100, 999);
        $payload = [
            'kode' => $kode,
            'name' => 'Gudang Baru Test',
            'cabang_id' => $this->cabang->id,
            'location' => 'Kawasan Pergudangan Surya Cipta',
            'telepon' => '021-998877',
            'tipe' => 'Besar',
            'status' => 1,
            'is_quarantine' => 0,
        ];

        $response = $this->actingAs($this->user)->post(route('master.gudang.store'), $payload);

        $response->assertRedirect(route('master.gudang.index'));
        $this->assertDatabaseHas('warehouses', [
            'kode' => $kode,
            'name' => 'Gudang Baru Test',
            'cabang_id' => $this->cabang->id,
        ]);
    }

    public function test_user_can_view_warehouse_show_page_with_raks(): void
    {
        $warehouse = Warehouse::create([
            'kode' => 'GDG-' . rand(100, 999),
            'name' => 'Gudang Detail Test',
            'cabang_id' => $this->cabang->id,
            'location' => 'Jl. Industri',
            'status' => 1,
        ]);

        $rak = Rak::create([
            'code' => 'RAK-01-' . rand(10, 99),
            'name' => 'Rak Pallet Utama',
            'warehouse_id' => $warehouse->id,
            'description' => 'Khusus barang jadi',
        ]);

        $response = $this->actingAs($this->user)->get(route('master.gudang.show', $warehouse));

        $response->assertOk();
        $response->assertSee('Gudang Detail Test');
        $response->assertSee($rak->code);
        $response->assertSee('Rak Pallet Utama');
    }

    public function test_user_can_view_edit_warehouse_page(): void
    {
        $warehouse = Warehouse::create([
            'kode' => 'GDG-' . rand(100, 999),
            'name' => 'Gudang Edit Test',
            'cabang_id' => $this->cabang->id,
            'location' => 'Jl. Edit No. 2',
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.gudang.edit', $warehouse));

        $response->assertOk();
        $response->assertSee('Edit Gudang');
        $response->assertSee($warehouse->name);
    }

    public function test_user_can_update_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'kode' => 'GDG-' . rand(100, 999),
            'name' => 'Gudang Sebelum Update',
            'cabang_id' => $this->cabang->id,
            'location' => 'Jl. Lama No. 3',
            'status' => 1,
        ]);

        $payload = [
            'kode' => $warehouse->kode,
            'name' => 'Gudang Sesudah Update',
            'cabang_id' => $this->cabang->id,
            'location' => 'Jl. Baru No. 4',
            'telepon' => '021-123456',
            'status' => 0,
            'is_quarantine' => 1,
        ];

        $response = $this->actingAs($this->user)->put(route('master.gudang.update', $warehouse), $payload);

        $response->assertRedirect(route('master.gudang.index'));
        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'Gudang Sesudah Update',
            'status' => 0,
            'is_quarantine' => 1,
        ]);
    }

    public function test_user_can_delete_unlinked_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'kode' => 'GDG-' . rand(100, 999),
            'name' => 'Gudang Siap Hapus',
            'cabang_id' => $this->cabang->id,
            'location' => 'Jl. Siap Hapus',
            'status' => 1,
        ]);

        $response = $this->actingAs($this->user)->delete(route('master.gudang.destroy', $warehouse));

        $response->assertRedirect(route('master.gudang.index'));
        $this->assertSoftDeleted('warehouses', [
            'id' => $warehouse->id,
        ]);
    }

    public function test_deletion_is_blocked_if_warehouse_has_active_stock(): void
    {
        $warehouse = Warehouse::create([
            'kode' => 'GDG-' . rand(100, 999),
            'name' => 'Gudang Ada Stok',
            'cabang_id' => $this->cabang->id,
            'location' => 'Jl. Stok Ada',
            'status' => 1,
        ]);

        $product = Product::first() ?? Product::factory()->create();
        if ($product) {
            InventoryStock::updateOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                ],
                [
                    'qty_available' => 25,
                    'qty_reserved' => 0,
                    'qty_min' => 0,
                ]
            );
        }

        $response = $this->actingAs($this->user)->delete(route('master.gudang.destroy', $warehouse));

        $response->assertRedirect(route('master.gudang.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_can_create_rak_in_warehouse(): void
    {
        $warehouse = Warehouse::create([
            'kode' => 'GDG-' . rand(100, 999),
            'name' => 'Gudang Rak Test',
            'cabang_id' => $this->cabang->id,
            'location' => 'Jl. Rak',
            'status' => 1,
        ]);

        $rakCode = 'RAK-B01-' . rand(10, 99);
        $payload = [
            'warehouse_id' => $warehouse->id,
            'code' => $rakCode,
            'name' => 'Rak B01',
            'description' => 'Tingkat 2',
        ];

        $response = $this->actingAs($this->user)->post(route('master.rak.store'), $payload);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('raks', [
            'warehouse_id' => $warehouse->id,
            'code' => $rakCode,
            'name' => 'Rak B01',
        ]);
    }

    public function test_user_can_update_rak(): void
    {
        $warehouse = Warehouse::create([
            'kode' => 'GDG-' . rand(100, 999),
            'name' => 'Gudang Rak Update',
            'cabang_id' => $this->cabang->id,
            'location' => 'Jl. Rak Update',
            'status' => 1,
        ]);

        $rak = Rak::create([
            'code' => 'RAK-C01-' . rand(10, 99),
            'name' => 'Rak C01 Lama',
            'warehouse_id' => $warehouse->id,
        ]);

        $payload = [
            'warehouse_id' => $warehouse->id,
            'code' => $rak->code,
            'name' => 'Rak C01 Baru',
            'description' => 'Updated desc',
        ];

        $response = $this->actingAs($this->user)->put(route('master.rak.update', $rak), $payload);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('raks', [
            'id' => $rak->id,
            'name' => 'Rak C01 Baru',
        ]);
    }

    public function test_user_can_delete_rak(): void
    {
        $warehouse = Warehouse::create([
            'kode' => 'GDG-' . rand(100, 999),
            'name' => 'Gudang Rak Hapus',
            'cabang_id' => $this->cabang->id,
            'location' => 'Jl. Rak Hapus',
            'status' => 1,
        ]);

        $rak = Rak::create([
            'code' => 'RAK-D01-' . rand(10, 99),
            'name' => 'Rak D01 Hapus',
            'warehouse_id' => $warehouse->id,
        ]);

        $response = $this->actingAs($this->user)->delete(route('master.rak.destroy', $rak));

        $response->assertSessionHas('success');
        $this->assertSoftDeleted('raks', [
            'id' => $rak->id,
        ]);
    }
}
