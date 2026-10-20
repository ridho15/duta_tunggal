<?php

/**
 * Tahap 1 Spesifikasi UI/UX v1.1 (6.6) — tidak ada stack trace; error server tampil
 * sebagai pesan umum Bahasa Indonesia + kode yang juga tercatat di log.
 */

use App\Support\ErrorCode;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    config(['app.debug' => false]);
    ErrorCode::reset();

    // Meniru request Livewire dari halaman Surat Jalan (modul dibaca dari Referer).
    Route::middleware('web')->get('/livewire-uji-error', function () {
        throw new RuntimeException("SQLSTATE[42S22]: Unknown column 'order_number'");
    });
});

it('menampilkan pesan umum + kode, bukan pesan teknis', function () {
    $response = $this->get('/livewire-uji-error', ['Referer' => url('/admin/surat-jalans/5/edit')]);

    $response->assertStatus(500)
        ->assertSee('Terjadi kesalahan sistem')
        ->assertSee('SJ-500-' . ErrorCode::ref())
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('order_number')
        ->assertHeader('X-Error-Code', 'SJ-500-' . ErrorCode::ref());
});

it('mengembalikan JSON berbahasa Indonesia untuk request API', function () {
    $this->getJson('/livewire-uji-error', ['Referer' => url('/admin/surat-jalans/create')])
        ->assertStatus(500)
        ->assertJsonPath('error_code', 'SJ-500-' . ErrorCode::ref())
        ->assertJsonMissingPath('exception');
});

it('halaman 403 dan 404 berbahasa Indonesia', function () {
    Route::middleware('web')->get('/uji-403', fn () => abort(403));

    $this->get('/uji-403')->assertStatus(403)->assertSee('Anda tidak punya akses ke halaman ini');
    $this->get('/halaman-yang-tidak-ada')->assertStatus(404)->assertSee('Halaman atau data tidak ditemukan');
});

it('mode debug tetap menampilkan detail untuk developer', function () {
    config(['app.debug' => true]);

    $this->get('/livewire-uji-error')->assertStatus(500)->assertSee('order_number');
});

it('kode modul diambil dari URL halaman', function () {
    expect(ErrorCode::module(Illuminate\Http\Request::create('/admin/delivery-orders/12/edit')))->toBe('DO')
        ->and(ErrorCode::module(Illuminate\Http\Request::create('/admin/stock-transfers/create')))->toBe('PS')
        ->and(ErrorCode::module(Illuminate\Http\Request::create('/admin/tidak-dikenal')))->toBe('SYS');
});
