<?php

namespace Tests\Feature\Master;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeUomCategoryTest extends TestCase
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

    // ==================== SATUAN (UOM) TESTS ====================

    public function test_user_can_view_satuan_index_page(): void
    {
        $uom = UnitOfMeasure::create([
            'name' => 'Satuan Tes ' . rand(100, 999),
            'abbreviation' => 'ST' . rand(10, 99),
        ]);

        $response = $this->actingAs($this->user)->get(route('master.satuan.index'));

        $response->assertOk();
        $response->assertSee('Daftar Satuan Ukuran');
        $response->assertSee($uom->name);
        $response->assertSee($uom->abbreviation);
    }

    public function test_user_can_view_create_satuan_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.satuan.create'));

        $response->assertOk();
        $response->assertSee('Tambah Satuan Ukuran');
    }

    public function test_user_can_store_new_satuan(): void
    {
        $abbr = 'TB' . rand(10, 99);
        $payload = [
            'name' => 'Tabung Besar ' . rand(100, 999),
            'abbreviation' => $abbr,
        ];

        $response = $this->actingAs($this->user)->post(route('master.satuan.store'), $payload);

        $response->assertRedirect(route('master.satuan.index'));
        $this->assertDatabaseHas('unit_of_measures', [
            'abbreviation' => $abbr,
        ]);
    }

    public function test_user_can_view_edit_satuan_page(): void
    {
        $uom = UnitOfMeasure::create([
            'name' => 'Karton Edit ' . rand(100, 999),
            'abbreviation' => 'KE' . rand(10, 99),
        ]);

        $response = $this->actingAs($this->user)->get(route('master.satuan.edit', $uom));

        $response->assertOk();
        $response->assertSee('Edit Satuan');
        $response->assertSee($uom->name);
    }

    public function test_user_can_update_satuan(): void
    {
        $uom = UnitOfMeasure::create([
            'name' => 'Uom Sebelum ' . rand(100, 999),
            'abbreviation' => 'US' . rand(10, 99),
        ]);

        $payload = [
            'name' => 'Uom Sesudah ' . rand(100, 999),
            'abbreviation' => 'UD' . rand(10, 99),
        ];

        $response = $this->actingAs($this->user)->put(route('master.satuan.update', $uom), $payload);

        $response->assertRedirect(route('master.satuan.index'));
        $this->assertDatabaseHas('unit_of_measures', [
            'id' => $uom->id,
            'name' => $payload['name'],
            'abbreviation' => $payload['abbreviation'],
        ]);
    }

    public function test_user_can_delete_unlinked_satuan(): void
    {
        $uom = UnitOfMeasure::create([
            'name' => 'Satuan Hapus ' . rand(100, 999),
            'abbreviation' => 'SH' . rand(10, 99),
        ]);

        $response = $this->actingAs($this->user)->delete(route('master.satuan.destroy', $uom));

        $response->assertRedirect(route('master.satuan.index'));
        $this->assertSoftDeleted('unit_of_measures', [
            'id' => $uom->id,
        ]);
    }

    public function test_deletion_is_blocked_if_satuan_linked_to_product(): void
    {
        $uom = UnitOfMeasure::create([
            'name' => 'Satuan Terikat ' . rand(100, 999),
            'abbreviation' => 'ST' . rand(10, 99),
        ]);

        $product = Product::first();
        if ($product) {
            $product->update(['uom_id' => $uom->id]);
        }

        $response = $this->actingAs($this->user)->delete(route('master.satuan.destroy', $uom));

        $response->assertRedirect(route('master.satuan.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('unit_of_measures', [
            'id' => $uom->id,
            'deleted_at' => null,
        ]);
    }

    // ==================== KATEGORI PRODUK TESTS ====================

    public function test_user_can_view_kategori_index_page(): void
    {
        $cat = ProductCategory::create([
            'name' => 'Kategori Tes ' . rand(100, 999),
            'kode' => 'KAT-' . rand(100, 999),
            'kenaikan_harga' => 5,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.kategori.index'));

        $response->assertOk();
        $response->assertSee('Daftar Kategori Produk');
        $response->assertSee($cat->name);
        $response->assertSee($cat->kode);
    }

    public function test_user_can_view_create_kategori_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('master.kategori.create'));

        $response->assertOk();
        $response->assertSee('Tambah Kategori Produk');
    }

    public function test_user_can_store_new_kategori(): void
    {
        $kode = 'KTG-' . rand(100, 999);
        $name = 'Kategori Baru ' . rand(100, 999);
        $payload = [
            'kode' => $kode,
            'name' => $name,
            'kenaikan_harga' => 7.5,
        ];

        $response = $this->actingAs($this->user)->post(route('master.kategori.store'), $payload);

        $response->assertRedirect(route('master.kategori.index'));
        $this->assertDatabaseHas('product_categories', [
            'kode' => $kode,
            'name' => $name,
        ]);
    }

    public function test_user_can_view_edit_kategori_page(): void
    {
        $cat = ProductCategory::create([
            'name' => 'Kategori Edit ' . rand(100, 999),
            'kode' => 'KTE-' . rand(100, 999),
            'kenaikan_harga' => 3,
        ]);

        $response = $this->actingAs($this->user)->get(route('master.kategori.edit', $cat));

        $response->assertOk();
        $response->assertSee('Edit Kategori');
        $response->assertSee($cat->name);
    }

    public function test_user_can_update_kategori(): void
    {
        $cat = ProductCategory::create([
            'name' => 'Kategori Awal ' . rand(100, 999),
            'kode' => 'KTA-' . rand(100, 999),
            'kenaikan_harga' => 2,
        ]);

        $payload = [
            'kode' => $cat->kode,
            'name' => 'Kategori Akhir ' . rand(100, 999),
            'kenaikan_harga' => 4.5,
        ];

        $response = $this->actingAs($this->user)->put(route('master.kategori.update', $cat), $payload);

        $response->assertRedirect(route('master.kategori.index'));
        $this->assertDatabaseHas('product_categories', [
            'id' => $cat->id,
            'name' => $payload['name'],
            'kenaikan_harga' => 4.5,
        ]);
    }

    public function test_user_can_delete_unlinked_kategori(): void
    {
        $cat = ProductCategory::create([
            'name' => 'Kategori Siap Hapus ' . rand(100, 999),
            'kode' => 'KSH-' . rand(100, 999),
        ]);

        $response = $this->actingAs($this->user)->delete(route('master.kategori.destroy', $cat));

        $response->assertRedirect(route('master.kategori.index'));
        $this->assertSoftDeleted('product_categories', [
            'id' => $cat->id,
        ]);
    }

    public function test_deletion_is_blocked_if_kategori_linked_to_product(): void
    {
        $cat = ProductCategory::create([
            'name' => 'Kategori Terikat ' . rand(100, 999),
            'kode' => 'KTB-' . rand(100, 999),
        ]);

        $product = Product::first();
        if ($product) {
            $product->update(['product_category_id' => $cat->id]);
        }

        $response = $this->actingAs($this->user)->delete(route('master.kategori.destroy', $cat));

        $response->assertRedirect(route('master.kategori.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('product_categories', [
            'id' => $cat->id,
            'deleted_at' => null,
        ]);
    }
}
