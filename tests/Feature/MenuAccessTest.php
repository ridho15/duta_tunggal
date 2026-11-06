<?php

use App\Support\MenuAccess;
use Spatie\Permission\Models\Role;

function menuUser(?string $role): App\Models\User
{
    $user = App\Models\User::factory()->create();
    if ($role) {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user->assignRole($role);
    }
    MenuAccess::flush();
    test()->actingAs($user);

    return $user;
}

it('menu hp_kiriman: Checker dan Owner boleh, Kasir ditolak', function () {
    menuUser('Checker');
    expect(MenuAccess::allows('hp_kiriman'))->toBeTrue();

    menuUser('Owner');
    expect(MenuAccess::allows('hp_kiriman'))->toBeTrue();

    menuUser('Kasir');
    expect(MenuAccess::allows('hp_kiriman'))->toBeFalse();
});

it('pengguna tanpa peran yang dikenal tidak dibatasi peta ini (diatur permission/Policy)', function () {
    menuUser(null);
    expect(MenuAccess::allows('hp_kiriman'))->toBeTrue();
});

it('menu yang tidak terdaftar tidak dibatasi', function () {
    menuUser('Kasir');
    expect(MenuAccess::allows('menu_tidak_ada'))->toBeTrue();
});
