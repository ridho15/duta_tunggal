<?php

/** T1.8 (X7) — teks bantu pada form Invoice: kapan invoice manual dari SO vs otomatis per DO. */

use App\Filament\Resources\SalesInvoiceResource\Pages\CreateSalesInvoice;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function hintContext(): array
{
    $cabang = Cabang::factory()->create(['kode' => 'HT-'.strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang Hint', 'status' => 1]);
    $permissions = ['view any invoice', 'create invoice', 'view any customer'];
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $user->givePermissionTo($permissions);
    Auth::login($user);

    return ['cabang' => $cabang, 'user' => $user, 'customer' => Customer::factory()->create(['cabang_id' => $cabang->id])];
}

it('pilihan SO menjelaskan bahwa hanya SO Selesai yang muncul dan invoice bertahap terbit otomatis per DO', function () {
    $ctx = hintContext();

    Livewire::actingAs($ctx['user'])
        ->test(CreateSalesInvoice::class)
        ->assertSee('Hanya SO berstatus Selesai yang muncul di sini')
        ->assertSee('invoice terbit OTOMATIS per Delivery Order');
});

it('bila customer belum punya SO Selesai, teks bantu menyatakannya secara eksplisit', function () {
    $ctx = hintContext();
    SaleOrder::create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'so_number' => 'SO-HINT-1', 'order_date' => now(),
        'status' => 'partially_delivered', 'tipe_pengiriman' => 'Kirim Langsung', 'exchange_rate' => 1.0, 'tempo_pembayaran' => 30, 'shipped_to' => 'x',
    ]);

    Livewire::actingAs($ctx['user'])
        ->test(CreateSalesInvoice::class)
        ->fillForm(['selected_customer' => $ctx['customer']->id])
        ->assertSee('Belum ada SO Selesai untuk customer ini');
});
