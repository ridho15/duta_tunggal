<?php

namespace Tests\Feature;

use App\Http\Controllers\HelperController;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function super_admin_role_has_all_defined_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        foreach (HelperController::listPermission() as $module => $permissions) {
            foreach ($permissions as $permission) {
                $permissionName = $permission . ' ' . $module;

                $this->assertTrue(
                    $user->hasPermissionTo($permissionName),
                    "Super Admin is missing permission: {$permissionName}"
                );
            }
        }
    }
}
