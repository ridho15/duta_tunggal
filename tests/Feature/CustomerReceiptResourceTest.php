<?php

use App\Filament\Resources\CustomerReceiptResource\Pages\CreateCustomerReceipt;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantCustomerReceiptPermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view any customer receipt',
        'create customer receipt',
        'view any customer',
        'view any invoice',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'view any customer receipt',
        'create customer receipt',
        'view any customer',
        'view any invoice',
    ]);
}

beforeEach(function () {
    $this->cabang = Cabang::factory()->create();
    $this->customerCabang = Cabang::factory()->create();
    $this->user = User::factory()->create(['cabang_id' => $this->cabang->id]);
    grantCustomerReceiptPermissions($this->user);
    $this->actingAs($this->user);

    $this->customer = Customer::factory()->create(['cabang_id' => $this->customerCabang->id]);
});

test('customer receipt create form keeps JSON state fields hidden and auto-fills cabang', function () {
    Livewire::actingAs($this->user)
        ->test(CreateCustomerReceipt::class)
        ->assertSuccessful()
        ->assertFormExists()
        ->assertFormFieldExists('selected_invoices')
        ->assertFormFieldExists('invoice_receipts')
        ->fillForm([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
        ])
        ->assertSet('data.cabang_id', $this->customerCabang->id);
});
