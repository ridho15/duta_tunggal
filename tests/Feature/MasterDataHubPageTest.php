<?php

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('master data hub page renders without component errors', function () {
    // Seed all permissions so canViewAny() checks work correctly
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $user = User::factory()->create();
    $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

    // Give Super Admin all permissions so hub items are all visible
    $allPermissions = Permission::all();
    $superAdminRole->syncPermissions($allPermissions);

    $user->assignRole($superAdminRole);

    $response = $this->actingAs($user)->get('/admin/master-data-hub');

    $response->assertOk();
    $response->assertSeeText('Data Master');
    $response->assertSeeText('Satuan');
});