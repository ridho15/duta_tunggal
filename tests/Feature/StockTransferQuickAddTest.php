<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Role;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use Tests\TestCase;

class StockTransferQuickAddTest extends TestCase
{
    /**
     * Memastikan halaman pembuatan & pengubahan Transfer Antar Gudang
     * merender komponen Quick-Add Search Bar, top-prepend logic di Vanilla JS, dan
     * mempertahankan dropdown pemilihan produk bawaan pada setiap baris item tabel.
     */
    public function test_stock_transfer_create_and_edit_render_quick_add_search_bar_and_item_dropdowns(): void
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);

        $cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-STQA',
            'nama' => 'Cabang Test Stock Transfer Quick Add',
            'alamat' => 'Jl. Logistik Gudang No. 77',
            'status' => true,
        ]);

        $user = User::withoutGlobalScopes()->where('email', 'like', '%admin%')->first() ?? User::factory()->create([
            'email' => 'quickadd_st_' . uniqid() . '@example.com',
            'username' => 'quickadd_st_' . uniqid(),
            'cabang_id' => $cabang->id,
            'manage_type' => ['all'],
            'email_verified_at' => now(),
        ]);
        if (! $user->hasRole('Super Admin')) {
            $user->assignRole($role);
        }

        // 1. Test Create View
        $responseCreate = $this->actingAs($user)->get(route('gudang.transfer.create'));
        $responseCreate->assertStatus(200);

        // Verifikasi elemen visual Quick-Add Search Bar
        $responseCreate->assertSee('id="quickAddContainer"', false);
        $responseCreate->assertSee('id="quickSearchInput"', false);
        $responseCreate->assertSee('id="quickSearchResults"', false);
        $responseCreate->assertSee('id="btnQuickAddClear"', false);
        $responseCreate->assertSee('id="quickAddFeedback"', false);
        $responseCreate->assertSee('Pencarian Cepat / Scan Barcode Produk', false);
        $responseCreate->assertSee('Scanner Ready', false);

        // Verifikasi integrasi Vanilla JS Quick-Add & top prepend
        $responseCreate->assertSee('onQuickSearchInput', false);
        $responseCreate->assertSee('selectQuickProduct', false);
        $responseCreate->assertSee('clearQuickSearch', false);
        $responseCreate->assertSee('container.prepend', false);

        // Verifikasi dropdown dan input bawaan form tabel tetap dipertahankan
        $responseCreate->assertSee('id="itemsTable"', false);
        $responseCreate->assertSee('id="itemsContainer"', false);
        $responseCreate->assertSee('id="btnAddRow"', false);

        // 2. Test Edit View
        $transfer = StockTransfer::withoutGlobalScopes()->first();
        if ($transfer) {
            $responseEdit = $this->actingAs($user)->get(route('gudang.transfer.edit', $transfer));
            $responseEdit->assertStatus(200);

            // Verifikasi elemen Quick-Add Search Bar di edit
            $responseEdit->assertSee('id="quickAddContainer"', false);
            $responseEdit->assertSee('id="quickSearchInput"', false);
            $responseEdit->assertSee('id="quickSearchResults"', false);
            $responseEdit->assertSee('id="btnQuickAddClear"', false);
            $responseEdit->assertSee('id="quickAddFeedback"', false);

            // Verifikasi fungsi Vanilla JS di edit
            $responseEdit->assertSee('onQuickSearchInput', false);
            $responseEdit->assertSee('selectQuickProduct', false);
            $responseEdit->assertSee('container.prepend', false);

            // Verifikasi dropdown dan input bawaan form tabel tetap dipertahankan
            $responseEdit->assertSee('id="itemsTable"', false);
            $responseEdit->assertSee('id="itemsContainer"', false);
            $responseEdit->assertSee('id="btnAddRow"', false);
        }
    }
}
