<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Tests\TestCase;

class PurchaseOrderQuickAddTest extends TestCase
{
    /**
     * Memastikan halaman pembuatan & pengubahan Purchase Order (Pesanan Pembelian Mandiri)
     * merender komponen Quick-Add Search Bar, top-prepend logic di Alpine.js, dan
     * mempertahankan dropdown/pencarian produk bawaan pada setiap baris item.
     */
    public function test_purchase_order_create_and_edit_render_quick_add_search_bar_and_item_dropdowns(): void
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin'], ['guard_name' => 'web']);

        $cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-POQA',
            'nama' => 'Cabang Test PO Quick Add',
            'alamat' => 'Jl. Pengadaan Mandiri No. 99',
            'status' => true,
        ]);

        $user = User::withoutGlobalScopes()->where('email', 'like', '%admin%')->first() ?? User::factory()->create([
            'email' => 'quickadd_po_' . uniqid() . '@example.com',
            'username' => 'quickadd_po_' . uniqid(),
            'cabang_id' => $cabang->id,
            'manage_type' => ['all'],
            'email_verified_at' => now(),
        ]);
        if (! $user->hasRole('Super Admin')) {
            $user->assignRole($role);
        }

        Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);

        // 1. Test Create View
        $responseCreate = $this->actingAs($user)->get(route('pembelian.po.create'));
        $responseCreate->assertStatus(200);

        // Verifikasi elemen visual Quick-Add Search Bar
        $responseCreate->assertSee('id="quickAddContainer"', false);
        $responseCreate->assertSee('id="quickSearchInput"', false);
        $responseCreate->assertSee('id="quickSearchResults"', false);
        $responseCreate->assertSee('id="btnQuickAddClear"', false);
        $responseCreate->assertSee('id="quickAddFeedback"', false);
        $responseCreate->assertSee('Pencarian Cepat / Scan Barcode Produk', false);
        $responseCreate->assertSee('Scanner Ready', false);

        // Verifikasi integrasi Alpine.js Quick-Add & top prepend
        $responseCreate->assertSee('quickSearchQuery', false);
        $responseCreate->assertSee('onQuickSearchInput', false);
        $responseCreate->assertSee('selectQuickProduct', false);
        $responseCreate->assertSee('clearQuickSearch', false);
        $responseCreate->assertSee('this.items.unshift', false);

        // Verifikasi dropdown dan input bawaan form item tetap dipertahankan
        $responseCreate->assertSee(':name="\'items[\' + index + \'][product_id]\'"', false);
        $responseCreate->assertSee('id="btnAddRow"', false);

        // 2. Test Edit View
        $po = PurchaseOrder::withoutGlobalScopes()->first();
        if ($po) {
            $responseEdit = $this->actingAs($user)->get(route('pembelian.po.edit', $po));
            $responseEdit->assertStatus(200);

            // Verifikasi elemen Quick-Add Search Bar di edit
            $responseEdit->assertSee('id="quickAddContainer"', false);
            $responseEdit->assertSee('id="quickSearchInput"', false);
            $responseEdit->assertSee('id="quickSearchResults"', false);
            $responseEdit->assertSee('id="btnQuickAddClear"', false);
            $responseEdit->assertSee('id="quickAddFeedback"', false);

            // Verifikasi Alpine methods di edit
            $responseEdit->assertSee('quickSearchQuery', false);
            $responseEdit->assertSee('onQuickSearchInput', false);
            $responseEdit->assertSee('selectQuickProduct', false);
            $responseEdit->assertSee('this.items.unshift', false);

            // Verifikasi dropdown dan input bawaan form item tetap dipertahankan
            $responseEdit->assertSee(':name="\'items[\' + index + \'][product_id]\'"', false);
            $responseEdit->assertSee('id="btnAddRow"', false);
        }
    }
}
