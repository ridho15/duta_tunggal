<?php

/**
 * Keputusan Ko Handy 21 Nov 2026: transaksi SELESAI saat sales menekan tombol "Selesai" di Daftar Penjualan.
 * Syarat default: tidak ada DO yang masih menunggu SJ, dan sudah ada barang yang dikirim.
 */

use App\Models\User;
use App\Support\MenuAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function sjFor($test, array $ctx, $do): void
{
    $test->actingAs($ctx['user'])->post(route('penjualan.sj.store'), [
        'delivery_order_ids' => [$do->id], 'issued_at' => now()->toDateString(),
        'delivery_method' => 'ekspedisi', 'expedition_name' => 'JNE Uji', 'cabang_id' => $ctx['cabang']->id,
    ])->assertSessionHasNoErrors();
}

it('Selesai ditolak saat DO belum ber-SJ, lalu boleh setelah SJ terbit; tampil di daftar', function () {
    $ctx = stkContext();
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $item] = stkSaleOrder($ctx, 8, ['created_by' => $ctx['user']->id]);
    $do = stkDeliveryOrder($ctx, $so, $item, 8, 'approved');

    $this->actingAs($ctx['user'])->post(route('penjualan.so.complete', $so))->assertSessionHas('error');
    expect($so->fresh()->status)->not->toBe('completed');

    sjFor($this, $ctx, $do);
    $this->get(route('penjualan.so.index', ['tab' => 'semua']))->assertOk()->assertSee('open-modal-complete-so', false);

    $this->post(route('penjualan.so.complete', $so))->assertSessionHas('success');
    expect($so->fresh()->status)->toBe('completed')->and($so->fresh()->completed_at)->not->toBeNull();
});

it('sales lain (bukan pembuat, bukan manager) tidak bisa menekan Selesai', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $item] = stkSaleOrder($ctx, 4, ['created_by' => $ctx['user']->id]);
    $do = stkDeliveryOrder($ctx, $so, $item, 4, 'approved');
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');
    sjFor($this, $ctx, $do);

    Role::firstOrCreate(['name' => 'Sales', 'guard_name' => 'web']);
    $other = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all']);
    $other->assignRole('Sales');
    MenuAccess::flush();

    $this->actingAs($other)->post(route('penjualan.so.complete', $so))->assertSessionHas('error');
    expect($so->fresh()->status)->not->toBe('completed');
});
