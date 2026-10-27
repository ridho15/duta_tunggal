<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class QuotationQuickAddTest extends TestCase
{
    /**
     * Memastikan halaman pembuatan & pengubahan Penawaran Harga merender komponen
     * Quick-Add Search Bar, top-prepend logic, dan mempertahankan dropdown produk tiap baris.
     */
    public function test_quotation_create_and_edit_render_quick_add_search_bar_and_item_dropdowns(): void
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any quotation',
            'view quotation',
            'create quotation',
            'update quotation',
        ];
        foreach ($perms as $p) {
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'TGR',
            'nama' => 'Cabang Tangerang',
            'alamat' => 'Jl. Duta Tunggal No. 1',
            'is_active' => true,
        ]);

        $user = User::withoutGlobalScopes()->where('email', 'like', '%admin%')->first() ?? User::factory()->create([
            'email' => 'quickadd_qo_' . uniqid() . '@example.com',
            'username' => 'quickadd_qo_' . uniqid(),
            'cabang_id' => $cabang->id,
            'manage_type' => ['all'],
            'email_verified_at' => now(),
        ]);
        if (! $user->hasRole('Super Admin')) {
            $user->assignRole($role);
        }

        Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'rate' => 1]);

        // 1. Test Create View
        $responseCreate = $this->actingAs($user)->get(route('penjualan.quotations.create'));
        $responseCreate->assertStatus(200);

        // Verifikasi elemen Quick-Add Search Bar di create
        $responseCreate->assertSee('id="quickAddContainer"', false);
        $responseCreate->assertSee('id="quickSearchInput"', false);
        $responseCreate->assertSee('id="quickSearchResults"', false);
        $responseCreate->assertSee('id="btnQuickAddClear"', false);
        $responseCreate->assertSee('id="quickAddFeedback"', false);
        $responseCreate->assertSee('Pencarian Cepat / Scan Barcode Produk', false);

        // Verifikasi fungsi engine JavaScript Quick-Add & top prepend di create
        $responseCreate->assertSee('selectQuickProduct', false);
        $responseCreate->assertSee('renderQuickSearchResults', false);
        $responseCreate->assertSee('renumberItemRows', false);
        $responseCreate->assertSee('flashRowHighlight', false);
        $responseCreate->assertSee('container.prepend', false);

        // Verifikasi dropdown select produk di baris form items tetap dipertahankan
        $responseCreate->assertSee('select data-searchable name="items[${idx}][product_id]"', false);
        $responseCreate->assertSee('id="btnAddRow"', false);

        // 2. Test Edit View
        $quotation = Quotation::first();
        if ($quotation) {
            $responseEdit = $this->actingAs($user)->get(route('penjualan.quotations.edit', $quotation));
            $responseEdit->assertStatus(200);

            // Verifikasi elemen Quick-Add Search Bar di edit
            $responseEdit->assertSee('id="quickAddContainer"', false);
            $responseEdit->assertSee('id="quickSearchInput"', false);
            $responseEdit->assertSee('id="quickSearchResults"', false);
            $responseEdit->assertSee('id="btnQuickAddClear"', false);
            $responseEdit->assertSee('id="quickAddFeedback"', false);

            // Verifikasi engine JavaScript di edit
            $responseEdit->assertSee('selectQuickProduct', false);
            $responseEdit->assertSee('renderQuickSearchResults', false);
            $responseEdit->assertSee('renumberItemRows', false);
            $responseEdit->assertSee('container.prepend', false);
            $responseEdit->assertSee('calculateRow', false);

            // Verifikasi dropdown select produk di baris form items tetap dipertahankan
            $responseEdit->assertSee('select data-searchable name="items[${idx}][product_id]"', false);
        }
    }
}
