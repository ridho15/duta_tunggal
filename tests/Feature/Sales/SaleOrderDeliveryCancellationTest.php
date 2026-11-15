<?php

/**
 * Keputusan Ko Handy #4: pembatalan SO/DO.
 *  - SO tanpa DO aktif hanya dibatalkan Manager/Super Admin.
 *  - DO: Sales MENGAJUKAN pembatalan; Gudang/Manager/Super Admin MENGHAPUS.
 */

use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function cancelUser(array $ctx, string $role): User
{
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    $u = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all']);
    $u->assignRole($role);
    MenuAccess::flush();

    return $u;
}

it('#4 SO: hanya Manager/Super Admin yang bisa membatalkan SO tanpa DO', function () {
    $ctx = stkContext();
    $so = SaleOrder::factory()->create(['customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'status' => 'approved']);
    SaleOrderItem::factory()->create(['sale_order_id' => $so->id, 'product_id' => $ctx['product']->id, 'quantity' => 2, 'unit_price' => 10000, 'discount' => 0, 'tax' => 0, 'tipe_pajak' => null]);

    $sales = cancelUser($ctx, 'Sales');
    $this->actingAs($sales)->post(route('penjualan.so.cancel', $so), ['reason' => 'Coba batal'])->assertRedirect();
    expect($so->fresh()->status)->toBe('approved'); // ditolak, tidak berubah

    $manager = cancelUser($ctx, 'Sales Manager');
    $this->actingAs($manager)->post(route('penjualan.so.cancel', $so), ['reason' => 'Pelanggan membatalkan'])->assertRedirect();
    expect($so->fresh()->status)->toBe('canceled');
});

it('#4 DO: Sales mengajukan, Gudang menghapus; Sales tidak bisa menghapus', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $item] = stkSaleOrder($ctx, 5);
    $do = stkDeliveryOrder($ctx, $so, $item, 5, 'approved');

    $sales = cancelUser($ctx, 'Sales');
    // Sales mengajukan pembatalan
    $this->actingAs($sales)->post(route('penjualan.do.request-cancel', $do), ['reason' => 'Pesanan berubah'])->assertRedirect();
    expect($do->fresh()->cancel_requested_at)->not->toBeNull()
        ->and($do->fresh()->status)->not->toBe('closed');

    // Sales TIDAK bisa menghapus/membatalkan
    $this->actingAs($sales)->post(route('penjualan.do.cancel', $do), ['reason' => 'Pesanan berubah'])->assertRedirect();
    expect($do->fresh()->status)->not->toBe('closed');

    // Gudang (Checker punya 'response delivery order') MENGHAPUS
    $gudang = cancelUser($ctx, 'Checker');
    $gudang->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'response delivery order', 'guard_name' => 'web']));
    MenuAccess::flush();
    $this->actingAs($gudang)->post(route('penjualan.do.cancel', $do), ['reason' => 'Disetujui gudang'])->assertRedirect();
    expect($do->fresh()->status)->toBe('closed');
});
