<?php

use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->cabang = Cabang::factory()->create([
        'kode' => 'BRANCH001',
        'nama' => 'Test Branch',
        'alamat' => 'Test Address',
        'telepon' => '0211234567',
        'status' => true
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'view any customer',
        'view customer',
        'create customer',
        'update customer',
        'view any supplier',
        'view supplier',
        'create supplier',
        'update supplier',
        'view any cabang',
        'view cabang',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission]);
    }

    $this->user->givePermissionTo($permissions);
    Auth::login($this->user);
});

it('can create customer with credit limit', function () {
    $customerData = [
        'code' => 'CUST-TEST001',
        'name' => 'Test Customer',
        'perusahaan' => 'Test Company',
        'nik_npwp' => '1234567890123456',
        'address' => 'Test Address',
        'telephone' => '0211234567',
        'phone' => '081234567890',
        'email' => 'test@example.com',
        'fax' => '0211234567',
        'tempo_kredit' => 30,
        'kredit_limit' => 50000000,
        'tipe_pembayaran' => 'Kredit',
        'tipe' => 'PKP',
        'isSpecial' => false,
        'keterangan' => 'Test customer'
    ];

    Livewire::test(CreateCustomer::class)
        ->fillForm($customerData)
        ->call('create')
        ->assertHasNoFormErrors();

    $customer = Customer::where('code', 'CUST-TEST001')->first();

    expect($customer)
        ->not->toBeNull()
        ->and($customer->kredit_limit)->toBe(50000000)
        ->and($customer->tempo_kredit)->toBe(30);
});

it('can create supplier with payment terms', function () {
    $supplierData = [
        'code' => 'SUP-TEST001',
        'perusahaan' => 'Test Supplier Company',
        'name' => 'Test Supplier',
        'kontak_person' => 'John Doe',
        'npwp' => '1234567890123456',
        'address' => 'Test Address',
        'phone' => '0211234567',
        'handphone' => '081234567890',
        'email' => 'supplier@example.com',
        'fax' => '0211234567',
        'tempo_hutang' => 45,
        'keterangan' => 'Test supplier'
    ];

    Livewire::test(CreateSupplier::class)
        ->fillForm($supplierData)
        ->call('create')
        ->assertHasNoFormErrors();

    $supplier = Supplier::where('code', 'SUP-TEST001')->first();

    expect($supplier)
        ->not->toBeNull()
        ->and($supplier->tempo_hutang)->toBe(45);
});

it('validates customer contact information', function () {
    // Test invalid email
    $invalidData = [
        'code' => 'CUST-INVALID001',
        'name' => 'Test Customer',
        'perusahaan' => 'Test Company',
        'nik_npwp' => '1234567890123456',
        'address' => 'Test Address',
        'telephone' => '0211234567',
        'phone' => '081234567890',
        'email' => 'invalid-email',
        'fax' => '0211234567',
        'tempo_kredit' => 30,
        'kredit_limit' => 1000000,
        'tipe_pembayaran' => 'Kredit',
        'tipe' => 'PKP',
        'isSpecial' => false
    ];

    Livewire::test(CreateCustomer::class)
        ->fillForm($invalidData)
        ->call('create')
        ->assertHasFormErrors(['email']);

    // Test invalid phone format
    $invalidPhoneData = [
        'code' => 'CUST-INVALID002',
        'name' => 'Test Customer',
        'perusahaan' => 'Test Company',
        'nik_npwp' => '1234567890123456',
        'address' => 'Test Address',
        'telephone' => '0211234567',
        'phone' => 'invalid-phone',
        'email' => 'test@example.com',
        'fax' => '0211234567',
        'tempo_kredit' => 30,
        'kredit_limit' => 1000000,
        'tipe_pembayaran' => 'Kredit',
        'tipe' => 'PKP',
        'isSpecial' => false
    ];

    Livewire::test(CreateCustomer::class)
        ->fillForm($invalidPhoneData)
        ->call('create')
        ->assertHasFormErrors(['phone']);
});

it('validates supplier contact information', function () {
    // Test invalid NPWP (empty)
    $invalidNpwpData = [
        'code' => 'SUP-INVALID001',
        'perusahaan' => 'Test Company',
        'name' => 'Test Supplier',
        'kontak_person' => 'John Doe',
        'npwp' => '',
        'address' => 'Test Address',
        'phone' => '0211234567',
        'handphone' => '081234567890',
        'email' => 'supplier@example.com',
        'fax' => '0211234567',
        'tempo_hutang' => 0
    ];

    Livewire::test(CreateSupplier::class)
        ->fillForm($invalidNpwpData)
        ->call('create')
        ->assertHasFormErrors(['npwp']);

    // Test invalid email
    $invalidEmailData = [
        'code' => 'SUP-INVALID002',
        'perusahaan' => 'Test Company',
        'name' => 'Test Supplier',
        'kontak_person' => 'John Doe',
        'npwp' => '1234567890123456',
        'address' => 'Test Address',
        'phone' => '0211234567',
        'handphone' => '081234567890',
        'email' => 'invalid-email',
        'fax' => '0211234567',
        'tempo_hutang' => 30
    ];

    Livewire::test(CreateSupplier::class)
        ->fillForm($invalidEmailData)
        ->call('create')
        ->assertHasFormErrors(['email']);
});

it('can update customer credit limit', function () {
    $customer = Customer::factory()->create([
        'tempo_kredit' => 30,
    ]);

    $updateData = [
        'kredit_limit' => 25000000,
        'tempo_kredit' => 45,
    ];

    Livewire::test(EditCustomer::class, ['record' => $customer->getKey()])
        ->fillForm($updateData)
        ->call('save')
        ->assertHasNoFormErrors();

    $customer->refresh();

    expect($customer->kredit_limit)->toBe(25000000)
        ->and($customer->tempo_kredit)->toBe(45);
});

it('can update supplier payment terms', function () {
    $supplier = Supplier::factory()->create([
        'tempo_hutang' => 30,
    ]);

    $updateData = [
        'code' => $supplier->code,
        'perusahaan' => $supplier->perusahaan,
        'name' => $supplier->name,
        'kontak_person' => $supplier->kontak_person,
        'npwp' => $supplier->npwp,
        'address' => $supplier->address,
        'phone' => $supplier->phone,
        'handphone' => $supplier->handphone,
        'email' => $supplier->email,
        'fax' => $supplier->fax,
        'tempo_hutang' => 60,
        'keterangan' => $supplier->keterangan,
    ];

    Livewire::test(EditSupplier::class, ['record' => $supplier->getKey()])
        ->fillForm($updateData)
        ->call('save')
        ->assertHasNoFormErrors();

    $supplier->refresh();

    expect($supplier->tempo_hutang)->toBe(60);
});

it('customer code must be unique', function () {
    // Create first customer
    Customer::factory()->create([
        'code' => 'DUPLICATE-CODE',
    ]);

    // Try to create second customer with same code
    $duplicateData = [
        'code' => 'DUPLICATE-CODE',
        'name' => 'Test Customer',
        'perusahaan' => 'Test Company',
        'nik_npwp' => '1234567890123456',
        'address' => 'Test Address',
        'telephone' => '0211234567',
        'phone' => '081234567890',
        'email' => 'test@example.com',
        'fax' => '0211234567',
        'tempo_kredit' => 30,
        'kredit_limit' => 1000000,
        'tipe_pembayaran' => 'Kredit',
        'tipe' => 'PKP',
        'isSpecial' => false
    ];

    Livewire::test(CreateCustomer::class)
        ->fillForm($duplicateData)
        ->call('create')
        ->assertHasFormErrors(['code']);
});

it('supplier code must be unique', function () {
    // Create first supplier
    Supplier::factory()->create([
        'code' => 'DUPLICATE-SUP-CODE',
    ]);

    // Try to create second supplier with same code
    $duplicateData = [
        'code' => 'DUPLICATE-SUP-CODE',
        'perusahaan' => 'Test Company',
        'name' => 'Test Supplier',
        'kontak_person' => 'John Doe',
        'npwp' => '1234567890123456',
        'address' => 'Test Address',
        'phone' => '0211234567',
        'handphone' => '081234567890',
        'email' => 'supplier@example.com',
        'fax' => '0211234567',
        'tempo_hutang' => 30
    ];

    Livewire::test(CreateSupplier::class)
        ->fillForm($duplicateData)
        ->call('create')
        ->assertHasFormErrors(['code']);
});