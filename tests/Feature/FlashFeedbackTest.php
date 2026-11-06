<?php

/**
 * Pesan kegagalan/peringatan dari service dan observer harus sampai ke layout Blade.
 * Sebelumnya dikirim lewat Notification Filament yang tidak dirender di layout Blade.
 */

use App\Support\Flash;
use Illuminate\Http\Request;

function withSessionRequest(): void
{
    $request = Request::create('/');
    $request->setLaravelSession(app('session.store'));
    app()->instance('request', $request);
}

function renderFlash(): string
{
    view()->share('errors', new Illuminate\Support\ViewErrorBag);

    return view('layouts.partials.flash')->render();
}

it('Flash::error dan Flash::warning tampil di partial flash layout Blade', function () {
    withSessionRequest();

    Flash::error('Gagal Membuat Jurnal Produksi', 'COA WIP tidak ditemukan.');
    Flash::warning('Kemungkinan penerimaan ganda', 'Referensi TRF-1 sudah tercatat.');

    $html = renderFlash();

    expect($html)->toContain('Gagal Membuat Jurnal Produksi: COA WIP tidak ditemukan.')
        ->and($html)->toContain('Kemungkinan penerimaan ganda: Referensi TRF-1 sudah tercatat.');
});

it('dua pesan error pada request yang sama digabung, tidak saling menimpa', function () {
    withSessionRequest();

    Flash::error('Pertama', 'a');
    Flash::error('Kedua', 'b');

    expect(session('error'))->toBe('Pertama: a | Kedua: b');
});

it('tanpa sesi (artisan/queue) tidak melempar error dan tidak menulis flash', function () {
    app()->instance('request', Request::create('/'));

    Flash::error('Tanpa sesi', 'masuk log saja');

    expect(request()->hasSession())->toBeFalse();
});
