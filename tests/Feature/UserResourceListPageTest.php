<?php

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('user list page renders friendly manage type labels', function () {
    Permission::firstOrCreate([
        'name' => 'view any user',
        'guard_name' => 'web',
    ]);

    $viewer = User::factory()->create([
        'manage_type' => 'all',
    ]);
    $viewer->givePermissionTo('view any user');

    User::factory()->create([
        'first_name' => 'Manage',
        'last_name' => 'All',
        'username' => 'manageall',
        'email' => 'manageall@example.com',
        'kode_user' => 'USR-MANAGE-ALL',
        'posisi' => 'Staff',
        'manage_type' => 'all',
    ]);

    Livewire::actingAs($viewer)
        ->test(ListUsers::class)
        ->assertSuccessful()
        ->assertSee('Semua Cabang / Gudang');
});