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

// B24/B25: invoice baru hanya dari Surat Jalan (Customer → SO → centang SJ); invoice otomatis per DO dan jalur SO langsung sudah tidak ada.
it('form invoice baru menuntun urutan Customer → Sales Order → Surat Jalan', function () {
    $ctx = hintContext();

    Livewire::actingAs($ctx['user'])
        ->test(CreateSalesInvoice::class)
        ->fillForm(['selected_customer' => $ctx['customer']->id])
        ->assertSee('Hanya Sales Order milik customer yang sudah punya Surat Jalan terbit')
        ->assertSee('Surat Jalan yang digabung dalam satu invoice harus dari SO yang sama')
        ->assertDontSee('Sumber Penjualan Langsung');
});
