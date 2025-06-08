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

        $gudang = Role::updateOrCreate([
            'name' => 'Gudang',
        ], [
            'name' => 'Gudang',
            'guard_name' => 'web'
        ]);

        $owner = Role::updateOrCreate([
            'name' => 'Owner',
        ], [
            'name' => 'Owner',
            'guard_name' => 'web'
        ]);

        $superAdmin->syncPermissions(Permission::all());
    }
}
