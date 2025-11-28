<?php

namespace Tests\Unit;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Http\Controllers\HelperController;

class RolePermissionMappingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function roles_have_expected_permissions_based_on_mapping()
    {
        // Seed permissions and roles
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        // Define same mapping as RoleSeeder (subset used to verify)
        $roleResourceMap = [
            'Admin' => [
                'user', 'role', 'permission', 'currency', 'chart of account', 'tax setting', 'cabang'
            ],
            'Finance Manager' => [
                'account payable', 'account receivable', 'vendor payment', 'vendor payment detail', 'customer receipt', 'customer receipt item', 'invoice', 'deposit', 'deposit log', 'ageing schedule', 'voucher request'
            ],
            'Purchasing' => [
                'purchase order', 'purchase order item', 'purchase receipt'
            ],
            'Inventory Manager' => [
                'warehouse', 'warehouse confirmation', 'inventory stock'
            ],
            'Sales' => [
                'sales order', 'sales order item', 'quotation'
            ],
            'Kasir' => [
                'customer receipt', 'customer receipt item', 'invoice'
            ],
            'Auditor' => array_keys(HelperController::listPermission()),
        ];

        foreach ($roleResourceMap as $roleName => $resources) {
            $role = Role::where('name', $roleName)->first();
            $this->assertNotNull($role, "Role $roleName should exist");

            foreach ($resources as $res) {
                // prefer 'view any' permission check if exists
                $viewAny = 'view any ' . $res;
                if (Permission::where('name', $viewAny)->exists()) {
                    $this->assertTrue($role->hasPermissionTo($viewAny), "Role $roleName must have permission: $viewAny");
                    continue;
                }

                // fallback: check role has at least one permission related to the resource
                $related = Permission::where('name', 'like', "% $res")->pluck('name')->all();
                $hasAny = false;
                foreach ($related as $permName) {
                    if ($role->hasPermissionTo($permName)) {
                        $hasAny = true;
                        break;
                    }
                }

                $this->assertTrue($hasAny, "Role $roleName should have at least one permission for resource: $res");
            }
        }

        // Ensure non-privileged roles do NOT have destructive permissions
        $allowedDestructive = [
            'Owner',
            'Super Admin',
            'Admin',
            'Purchasing Manager',
            'Inventory Manager',
            'Finance Manager',
        ];

        $rolesWithDestructive = \Spatie\Permission\Models\Role::whereNotIn('name', $allowedDestructive)
            ->whereHas('permissions', function($q) {
                $q->where('name', 'like', 'delete %')
                  ->orWhere('name', 'like', 'force-delete %');
            })->pluck('name')->all();

        $this->assertEmpty($rolesWithDestructive, 'Non-allowed roles have destructive permissions: ' . implode(', ', $rolesWithDestructive));
    }
}
