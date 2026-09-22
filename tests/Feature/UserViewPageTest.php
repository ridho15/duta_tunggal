<?php

use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function registerUserViewPermissions(): array
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissionNames = collect(HelperController::listPermission())
        ->flatMap(function (array $actions, string $module): array {
            return collect($actions)
                ->map(fn (string $action): string => $action . ' ' . $module)
                ->all();
        })
        ->values()
        ->all();

    foreach ($permissionNames as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ], [
            'description' => HelperController::permissionDescriptions()[$permission] ?? null,
        ]);
    }

    return $permissionNames;
}

test('user view page shows permission actions and descriptions', function () {
    $permissionNames = registerUserViewPermissions();

    $branch = Cabang::factory()->create([
        'kode' => 'CBG-USER-VIEW',
        'nama' => 'Cabang User View',
    ]);

    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $branch->id,
        'kode' => 'WH-USER-VIEW',
        'name' => 'Warehouse User View',
    ]);

    $viewer = User::factory()->create([
        'cabang_id' => $branch->id,
        'warehouse_id' => $warehouse->id,
        'manage_type' => 'all',
    ]);
    $viewer->givePermissionTo(['view any user', 'view user', 'create user']);

    $role = Role::firstOrCreate([
        'name' => 'User Auditor',
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo(array_slice($permissionNames, 0, 10));

    $directPermissions = array_slice($permissionNames, 10, 2);

    $target = User::factory()->create([
        'first_name' => 'Detail',
        'last_name' => 'User',
        'username' => 'detailuser',
        'email' => 'detailuser@example.com',
        'kode_user' => 'USR-DETAIL',
        'posisi' => 'Staff',
        'status' => true,
        'manage_type' => 'all',
        'cabang_id' => $branch->id,
        'warehouse_id' => $warehouse->id,
    ]);
    $target->assignRole($role);
    $target->givePermissionTo($directPermissions);

    Livewire::actingAs($viewer)
        ->test(ViewUser::class, ['record' => $target->getKey()])
        ->assertSuccessful()
        ->assertSee('Kemampuan Berdasarkan Permission')
        ->assertSee('Ringkasan Akses')
        ->assertSee('User ini memiliki 12 permission aktif')
        ->assertSee('2 langsung dan 10 dari role')
        ->assertSee('Cari permission')
        ->assertSee('Sebelumnya')
        ->assertSee('Berikutnya')
        ->assertSee('Langsung')
        ->assertSee('Dari Role')
        ->assertSee('Akses dan Penugasan')
        ->assertSee('Cabang User View')
        ->assertSee('Warehouse User View');
});