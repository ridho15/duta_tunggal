<?php

use App\Filament\Resources\RoleResource\Pages\ViewRole;
use App\Http\Controllers\HelperController;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function registerRoleViewPermissions(): array
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
        Permission::updateOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ], [
            'description' => HelperController::permissionDescriptions()[$permission] ?? null,
        ]);
    }

    return $permissionNames;
}

test('role view page shows permission list and descriptions', function () {
    $permissionNames = registerRoleViewPermissions();

    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['view any role', 'view role', 'update role']);

    $role = Role::create([
        'name' => 'Role View Test',
        'guard_name' => 'web',
        'description' => 'Role untuk menguji tampilan detail permission',
    ]);
    $role->givePermissionTo(array_slice($permissionNames, 0, 12));

    $member = User::factory()->create();
    $member->assignRole($role);

    Livewire::actingAs($viewer)
        ->test(ViewRole::class, ['record' => $role->getKey()])
        ->assertSuccessful()
        ->assertSee('Informasi Role')
        ->assertSee('Hak Akses Role')
        ->assertSee('Ringkasan Hak Akses')
        ->assertSee('Role ini memiliki 12 permission aktif')
        ->assertSee('Cari permission')
        ->assertSee('Sebelumnya')
        ->assertSee('Berikutnya')
        ->assertSee('dapat digunakan oleh 1 user')
        ->assertSee('Role View Test')
        ->assertSee('Role untuk menguji tampilan detail permission')
        ->assertSee('Jumlah User')
        ->assertSee('Jumlah Permission')
        ->assertSee('12');
});