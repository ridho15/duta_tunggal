<?php

/**
 * Voucher Penerimaan Kas/Bank (Ko Handy 27 Nov 2026): pencatat (kasir) mencetak voucher miliknya,
 * Finance Manager / Owner / Super Admin boleh mencetak semua; kasir lain tidak.
 */

use App\Models\CustomerReceipt;
use App\Models\User;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = stkContext();
    $make = function (string $role) {
        $u = User::factory()->create(['cabang_id' => $this->ctx['user']->cabang_id, 'manage_type' => ['all']]);
        $u->assignRole(Role::findByName($role));

        return $u;
    };
    $this->kasir = $make('Kasir');
    $this->kasirLain = $make('Kasir');
    $this->finance = $make('Finance Manager');
    $this->owner = $make('Owner');
    $this->receipt = CustomerReceipt::factory()->create([
        'cabang_id' => $this->ctx['user']->cabang_id,
        'created_by' => $this->kasir->id,
    ]);
    Access::flush();
});

it('pencatat, Finance Manager & Owner boleh cetak; kasir lain tidak', function () {
    expect($this->receipt->canPrintVoucher($this->kasir))->toBeTrue()
        ->and($this->receipt->canPrintVoucher($this->finance))->toBeTrue()
        ->and($this->receipt->canPrintVoucher($this->owner))->toBeTrue()
        ->and($this->receipt->canPrintVoucher($this->kasirLain))->toBeFalse()
        ->and($this->receipt->canPrintVoucher(null))->toBeFalse();
});

it('server menolak kasir lain dengan pesan solusi', function () {
    $this->actingAs($this->kasirLain)->get(route('penjualan.receipts.voucher', $this->receipt->id))
        ->assertForbidden()->assertSee('voucher dicetak oleh pencatatnya', false);
});

it('tanpa centang Cetak Voucher Penerimaan, pencatat pun tidak bisa', function () {
    \Illuminate\Support\Facades\DB::table('user_access_overrides')->insert([
        'user_id' => $this->kasir->id, 'access_key' => 'penjualan.receipts.voucher', 'allowed' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    Access::flush();
    expect($this->receipt->canPrintVoucher($this->kasir))->toBeFalse();
    $this->actingAs($this->kasir)->get(route('penjualan.receipts.voucher', $this->receipt->id))->assertForbidden();
});
