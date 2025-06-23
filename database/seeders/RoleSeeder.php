<?php

namespace Database\Seeders;

use App\Http\Controllers\HelperController;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = Role::updateOrCreate([
            'name' => 'Owner',
        ], [
            'name' => 'Owner',
            'guard_name' => 'web'
        ]);

        $superAdmin = Role::updateOrCreate([
            'name' => 'Super Admin',
        ], [
            'name' => 'Super Admin',
            'guard_name' => 'web'
        ]);

        $admin = Role::updateOrCreate([
            'name' => 'Admin',
        ], [
            'name' => 'Admin',
            'guard_name' => 'web'
        ]);

        $salesManager = Role::updateOrCreate([
            'name' => 'Sales Manager',
        ], [
            'name' => 'Sales Manager',
            'guard_name' => 'web'
        ]);

        $sales = Role::updateOrCreate([
            'name' => 'Sales',
        ], [
            'name' => 'Sales',
            'guard_name' => 'web'
        ]);

        $kasir = Role::updateOrCreate([
            'name' => 'Kasir',
        ], [
            'name' => 'Kasir',
            'guard_name' => 'web'
        ]);

        $inventoryManager = Role::updateOrCreate([
            'name' => 'Inventory Manager',
        ], [
            'name' => 'Inventory Manager',
            'guard_name' => 'web'
        ]);

        $adminInventory = Role::updateOrCreate([
            'name' => 'Admin Inventory',
        ], [
            'name' => 'Admin Inventory',
            'guard_name' => 'web'
        ]);

        $checker = Role::updateOrCreate([
            'name' => 'Checker',
        ], [
            'name' => 'Checker',
            'guard_name' => 'web'
        ]);

        $financeManager = Role::updateOrCreate([
            'name' => 'Finance Manager',
        ], [
            'name' => 'Finance Manager',
            'guard_name' => 'web'
        ]);

        $adminKeuangan = Role::updateOrCreate([
            'name' => 'Admin Keuangan',
        ], [
            'name' => 'Admin Keuangan',
            'guard_name' => 'web'
        ]);

        $accounting = Role::updateOrCreate([
            'name' => 'Accounting',
        ], [
            'name' => 'Accounting',
            'guard_name' => 'web'
        ]);


        $owner->syncPermissions(Permission::all());
        $superAdmin->syncPermissions(Permission::all());
    }
}
