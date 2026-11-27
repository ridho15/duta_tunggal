<?php

/**
 * Catatan Ko Handy 27 Nov 2026: SO yang sudah dibuatkan DO (contoh SO-00056 → DO-DUTA-K01.2-20261126-02) "hilang" dari
 * Daftar Pesanan Penjualan karena keluar dari tab default "Perlu tindakan".
 * Kini: default tab Semua, pencarian mencari di semua status, dan nomor DO terkait tampil di baris SO.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = stkContext();
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $this->ctx['user']->assignRole('Super Admin');
    stkSetStock($this->ctx['product'], $this->ctx['warehouse'], 20);
    [$this->so, $item] = stkSaleOrder($this->ctx, 8, ['created_by' => $this->ctx['user']->id]);
    $this->do = stkDeliveryOrder($this->ctx, $this->so, $item, 8, 'approved');   // seluruh qty sudah dialokasikan ke DO
});

it('SO yang sudah dibuatkan DO tetap tampil di daftar (default) bersama nomor DO-nya', function () {
    $this->actingAs($this->ctx['user'])->get(route('penjualan.so.index'))->assertOk()
        ->assertSee($this->so->so_number)
        ->assertSee($this->do->do_number);
});

it('mencari nomor SO dari tab Perlu tindakan tetap menemukan SO yang sudah dibuatkan DO', function () {
    $this->actingAs($this->ctx['user'])->get(route('penjualan.so.index', ['tab' => 'perlu_tindakan', 'search' => $this->so->so_number]))
        ->assertOk()
        ->assertSee($this->so->so_number)
        ->assertSee('semua tab', false);
});

it('filter status menampilkan SO Dibatalkan / Ditolak yang tidak punya tab sendiri', function () {
    [$canceled] = stkSaleOrder($this->ctx, 1, ['created_by' => $this->ctx['user']->id]);
    $canceled->forceFill(['status' => 'canceled'])->saveQuietly();

    $this->actingAs($this->ctx['user'])->get(route('penjualan.so.index', ['tab' => 'perlu_tindakan', 'status' => 'canceled']))
        ->assertOk()
        ->assertSee($canceled->so_number)
        ->assertDontSee($this->so->so_number);   // SO lain (status berbeda) tidak ikut

    // Status tak dikenal diabaikan (tidak error, tidak memfilter)
    $this->get(route('penjualan.so.index', ['status' => 'xyz']))->assertOk()->assertSee($this->so->so_number);
});
