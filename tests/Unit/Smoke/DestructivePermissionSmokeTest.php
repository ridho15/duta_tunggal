<?php

namespace Tests\Unit\Smoke;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class DestructivePermissionSmokeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function only_allowed_roles_have_destructive_permissions()
    {
        // Seed minimal data
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $allowed = [
            'Owner',
            'Super Admin',
            'Purchasing Manager',
            'Inventory Manager',
            'Finance Manager',
        ];

        $rolesWithDestructive = Role::whereHas('permissions', function($q) {
            $q->where('name', 'like', 'delete %')
              ->orWhere('name', 'like', 'force-delete %');
        })->pluck('name')->toArray();

        // All roles that have destructive permissions should be subset of allowed
        foreach ($rolesWithDestructive as $roleName) {
            $this->assertContains($roleName, $allowed, "Role $roleName has destructive permission but is not in allowed list");
        }

        // And assert that at least one allowed role actually has destructive perms (sanity)
        $found = false;
        foreach ($allowed as $a) {
            if (Role::where('name', $a)->whereHas('permissions', function($q){ $q->where('name','like','delete %')->orWhere('name','like','force-delete %'); })->exists()) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'No allowed role has destructive permissions — check seeder mapping');
    }
}
