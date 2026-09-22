<?php

/**
 * Isu 7 — Halaman Utang Usaha (Account Payable): judul bersih (bukan "Rp 0,00"), ringkasan Total Hutang / Sudah Dibayar / Sisa Hutang
 * dalam Bahasa Indonesia (bukan "Total Amount"/"Paid Amount"/"Outstanding"), dan tanggal berformat d/m/Y (bukan timestamp mentah).
 */

use App\Filament\Resources\AccountPayableResource\Pages\ListAccountPayables;
use App\Filament\Widgets\AccountPayableStatsWidget;
use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function apUser(): User
{
    $user = User::factory()->create();
    foreach (['view any account payable', 'view account payable'] as $permission) {
        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $user->givePermissionTo(['view any account payable', 'view account payable']);

    return $user;
}

it('judul halaman bersih, bukan "Rp 0,00"', function () {
    expect((new ListAccountPayables())->getTitle())->toBe('Hutang Usaha (Account Payable)')->not->toContain('Rp')->not->toContain('0,00');
});

it('tabel: Invoice Date dan Due Date berformat d/m/Y, bukan timestamp mentah', function () {
    $user = apUser();
    $ap = AccountPayable::factory()->for(Invoice::factory()->state(['invoice_date' => '2026-09-17', 'due_date' => '2026-10-17']), 'invoice')->create(['status' => 'Belum Lunas']);

    Livewire::actingAs($user)->test(ListAccountPayables::class)
        ->assertTableColumnFormattedStateSet('invoice.invoice_date', '17/09/2026', $ap)
        ->assertTableColumnFormattedStateSet('invoice.due_date', '17/10/2026', $ap)
        ->assertDontSee('00:00:00')->assertDontSee('2026-09-17 00:00:00');
});

it('ringkasan: Total Hutang, Sudah Dibayar, Sisa Hutang berlabel Indonesia dan menjumlah invoice yang belum lunas', function () {
    AccountPayable::factory()->create(['total' => 40000000, 'paid' => 15000000, 'remaining' => 25000000, 'status' => 'Belum Lunas']);
    AccountPayable::factory()->create(['total' => 20000000, 'paid' => 5000000, 'remaining' => 15000000, 'status' => 'Belum Lunas']);
    AccountPayable::factory()->create(['total' => 10000000, 'paid' => 10000000, 'remaining' => 0, 'status' => 'Lunas']);   // lunas: tidak dihitung

    $widget = Livewire::test(AccountPayableStatsWidget::class);

    $widget->assertSee('Total Hutang')->assertSee('Sudah Dibayar')->assertSee('Sisa Hutang')
        ->assertDontSee('Total Amount')->assertDontSee('Paid Amount')->assertDontSee('Outstanding')
        ->assertSee('Rp 60.000.000')   // total 40jt + 20jt (lunas tidak dihitung)
        ->assertSee('Rp 20.000.000')   // dibayar 15jt + 5jt
        ->assertSee('Rp 40.000.000');  // sisa 25jt + 15jt
});
