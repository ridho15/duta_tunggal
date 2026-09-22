<?php

use Illuminate\Support\Facades\Validator;

// Aplikasi ter-boot: memastikan locale `id` benar-benar memakai lang/id (bukan kunci mentah) di runtime.

it('locale aplikasi id dan pesan validasi/otentikasi tidak lagi berupa kunci mentah', function () {
    expect(app()->getLocale())->toBe('id')
        ->and(__('validation.required', ['attribute' => 'nama']))->toBe('Isian nama wajib diisi.')
        ->and(__('auth.failed'))->not->toBe('auth.failed')->toContain('tidak cocok')
        ->and(__('passwords.reset'))->not->toBe('passwords.reset')
        ->and(__('pagination.next'))->not->toBe('pagination.next');
});

it('Validator facade memakai atribut Indonesia untuk field penjualan', function () {
    $errors = Validator::make(['total_payment' => null], ['total_payment' => 'required'])->errors();

    expect($errors->first('total_payment'))->toBe('Isian total pembayaran wajib diisi.')
        ->and($errors->first('total_payment'))->not->toContain('validation.');
});

it('pesan kustom pada form tetap menimpa terjemahan bawaan', function () {
    $errors = Validator::make(
        ['customer_id' => null],
        ['customer_id' => 'required'],
        ['customer_id.required' => 'Customer wajib dipilih']
    )->errors();

    expect($errors->first('customer_id'))->toBe('Customer wajib dipilih');
});
