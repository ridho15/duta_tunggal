<?php

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Cabang;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Clear cached permissions
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Create the required permissions for UserPolicy
    Permission::firstOrCreate(['name' => 'view any user', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'view user', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'create user', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'update user', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'delete user', 'guard_name' => 'web']);

    $this->branch = Cabang::factory()->create([
        'kode' => 'CBG-TEST',
        'nama' => 'Cabang Test',
    ]);

    $this->warehouse = Warehouse::factory()->create([
        'cabang_id' => $this->branch->id,
        'kode' => 'WH-TEST',
        'name' => 'Warehouse Test',
    ]);

    $this->admin = User::factory()->create([
        'first_name' => 'Admin',
        'last_name' => 'ERP',
        'name' => 'Admin ERP',
        'username' => 'admin_erp',
        'email' => 'admin@example.com',
        'manage_type' => 'all',
        'cabang_id' => $this->branch->id,
        'warehouse_id' => $this->warehouse->id,
        'status' => true,
    ]);

    // Give required permissions to the admin user
    $this->admin->givePermissionTo([
        'view any user',
        'view user',
        'create user',
        'update user',
        'delete user'
    ]);
});

test('user list page can be rendered', function () {
    Livewire::actingAs($this->admin)
        ->test(ListUsers::class)
        ->assertSuccessful();
});

test('user create page can be rendered without TypeError', function () {
    // Testing that the CreateUser page loads successfully
    // (This guarantees that the cabang_id disabled callback with null manage_type does not throw a TypeError)
    Livewire::actingAs($this->admin)
        ->test(CreateUser::class)
        ->assertSuccessful()
        ->assertFormExists()
        ->assertFormFieldExists('username')
        ->assertFormFieldExists('manage_type')
        ->assertFormFieldExists('cabang_id')
        ->assertFormFieldExists('konfirmasi_password');
});

test('user creation validates password and konfirmasi_password mismatch', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateUser::class)
        ->fillForm([
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'secret123',
            'konfirmasi_password' => 'different123',
            'manage_type' => ['cabang'],
            'cabang_id' => $this->branch->id,
            'first_name' => 'New',
            'last_name' => 'User',
            'posisi' => 'Staff',
            'status' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['password' => 'same', 'konfirmasi_password' => 'same']);
});

test('user can be created successfully with correct properties', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateUser::class)
        ->fillForm([
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'secret123',
            'konfirmasi_password' => 'secret123',
            'manage_type' => ['cabang'],
            'cabang_id' => $this->branch->id,
            'first_name' => 'New',
            'last_name' => 'User',
            'posisi' => 'Staff',
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Verify database entry
    $user = User::where('username', 'newuser')->first();
    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('newuser@example.com')
        ->and($user->name)->toBe('New User') // verified name concatenation
        ->and($user->manage_type)->toBe(['cabang'])
        ->and($user->cabang_id)->toBe($this->branch->id)
        ->and((bool) $user->status)->toBeTrue()
        ->and($user->kode_user)->toStartWith('USR-') // verified automatic user code generation
        ->and(Hash::check('secret123', $user->password))->toBeTrue();
});

test('user can be created with an international phone number', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateUser::class)
        ->fillForm([
            'username' => 'intluser',
            'email' => 'intluser@example.com',
            'password' => 'secret123',
            'konfirmasi_password' => 'secret123',
            'manage_type' => ['cabang'],
            'cabang_id' => $this->branch->id,
            'first_name' => 'Intl',
            'last_name' => 'User',
            'telepon' => ' (+62) 830 9787 333 ',
            'posisi' => 'Staff',
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'username' => 'intluser',
        'telepon' => '(+62) 830 9787 333',
    ]);
});

test('user creation handles null last_name safely', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateUser::class)
        ->fillForm([
            'username' => 'singleuser',
            'email' => 'single@example.com',
            'password' => 'secret123',
            'konfirmasi_password' => 'secret123',
            'manage_type' => ['all'],
            'first_name' => 'Single',
            'last_name' => null, // null last name
            'posisi' => 'Manager',
            'status' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('username', 'singleuser')->first();
    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Single') // fallback name
        ->and($user->kode_user)->toStartWith('USR-') // verified automatic user code generation
        ->and($user->manage_type)->toBe(['all']);
});

test('user edit page can be rendered', function () {
    $targetUser = User::factory()->create([
        'username' => 'target',
        'email' => 'target@example.com',
        'first_name' => 'Target',
        'last_name' => 'User',
        'name' => 'Target User',
        'manage_type' => 'cabang',
        'cabang_id' => $this->branch->id,
        'kode_user' => 'USR-TARGET',
        'posisi' => 'Staff',
        'status' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(EditUser::class, ['record' => $targetUser->getKey()])
        ->assertSuccessful()
        ->assertFormExists();
});

test('user edit does not overwrite password when left empty', function () {
    $originalPassword = Hash::make('original123');
    $targetUser = User::factory()->create([
        'username' => 'target2',
        'email' => 'target2@example.com',
        'password' => $originalPassword,
        'first_name' => 'Target',
        'last_name' => 'Two',
        'name' => 'Target Two',
        'manage_type' => 'all',
        'kode_user' => 'USR-TARGET2',
        'posisi' => 'Staff',
        'status' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(EditUser::class, ['record' => $targetUser->getKey()])
        ->fillForm([
            'first_name' => 'Updated',
            'password' => '', // blank to keep original
            'konfirmasi_password' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $targetUser->refresh();
    expect($targetUser->first_name)->toBe('Updated')
        ->and($targetUser->name)->toBe('Updated Two')
        ->and($targetUser->password)->toBe($originalPassword); // remains unchanged
});
