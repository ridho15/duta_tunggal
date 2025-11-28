<?php

namespace Tests\Unit;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class RolesExistenceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function essential_roles_are_seeded()
    {
        // Seed roles
        $this->seed(RoleSeeder::class);

        $required = [
            'Owner',
            'Super Admin',
            'Admin',
            'Finance Manager',
            'Purchasing',
            'Purchasing Manager',
            'Inventory Manager',
            'Admin Inventory',
            'Sales Manager',
            'Sales',
            'Kasir',
            'Checker',
            'Warehouse Staff',
            'Delivery Driver',
            'Customer Service',
            'Auditor',
            'IT Support',
        ];

        $found = Role::whereIn('name', $required)->pluck('name')->all();

        sort($required);
        sort($found);

        $this->assertEquals($required, $found, 'One or more required roles are missing.');
    }
}
