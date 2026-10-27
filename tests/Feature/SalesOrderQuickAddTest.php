<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesOrderQuickAddTest extends TestCase
{
    /**
     * Memastikan halaman pembuatan Sales Order merender komponen Quick-Add Search Bar
     * dan mempertahankan dropdown pemilihan produk di setiap baris item.
     */
    public function test_sales_order_create_renders_quick_add_search_bar_and_item_dropdowns(): void
    {
        $role = Role::firstOrCreate(['name' => 'Sales Admin'], ['guard_name' => 'web']);
        $cabang = Cabang::first() ?? Cabang::create([
            'kode' => 'TGR',
            'nama' => 'Cabang Tangerang',
            'alamat' => 'Jl. Duta Tunggal No. 1',
            'is_active' => true,
        ]);

        $user = User::where('email', 'like', '%admin%')->first() ?? User::factory()->create([
            'email' => 'quickadd_' . uniqid() . '@example.com',
            'username' => 'quickadd_' . uniqid(),
            'cabang_id' => $cabang->id,
            'manage_type' => ['all'],
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'rate' => 1]);

        $customer = Customer::withoutGlobalScopes()->first() ?? Customer::create([
            'code' => 'CUST-QO-TEST',
            'name' => 'Pelanggan Uji Penawaran',
            'perusahaan' => 'PT Uji Penawaran Sejahtera',
            'address' => 'Jl. Industri No. 88 Jakarta',
            'telephone' => '021-888999',
            'phone' => '08123456789',
            'fax' => '021-888990',
            'email' => 'customer.test@example.com',
            'tipe' => 'PKP',
            'nik_npwp' => '01.234.567.8-999.000',
            'tempo_kredit' => 30,
            'kredit_limit' => 100000000,
            'tipe_pembayaran' => 'Kredit',
            'cabang_id' => $cabang->id,
        ]);

        $product = Product::first();

        $response = $this->actingAs($user)->get(route('penjualan.so.create'));

        $response->assertStatus(200);

        // 1. Verifikasi elemen Quick-Add Search Bar
        $response->assertSee('id="quickAddContainer"', false);
        $response->assertSee('id="quickSearchInput"', false);
        $response->assertSee('id="quickSearchResults"', false);
        $response->assertSee('id="btnQuickAddClear"', false);
        $response->assertSee('id="quickAddFeedback"', false);
        $response->assertSee('Pencarian Cepat / Scan Barcode Produk', false);
        $response->assertSee('Scanner Ready', false);

        // 2. Verifikasi fungsi engine JavaScript Quick-Add
        $response->assertSee('selectQuickProduct', false);
        $response->assertSee('renderQuickSearchResults', false);
        $response->assertSee('flashRowHighlight', false);
        $response->assertSee('clearQuickSearch', false);

        // 3. Verifikasi dropdown select produk di baris form items tetap dipertahankan
        $response->assertSee('select data-searchable name="items[${idx}][product_id]"', false);
        $response->assertSee('id="addItemBtn"', false);
    }
}
