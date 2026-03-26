<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Cabang;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::updateOrCreate([
            'email' => 'ralamzah@gmail.com',
        ], [
            'username' => 'ridho_al_amzah',
            'email' => 'ralamzah@gmail.com',
            'name' => 'Ridho Al Amzah',
            'first_name' => 'Ridho',
            'last_name' => 'Al Amzah',
            'kode_user' => 'ridho',
            'status' => true,
            'posisi' => 'Pemilik',
            'manage_type' => 'all',
            'cabang_id' => Cabang::inRandomOrder()->first()->id ?? 1,
            'password' => Hash::make('ridho123')
        ]);

        $superAdmin = User::updateOrCreate([
            'email' => 'superadmin@gmail.com',
        ], [
            'email' => 'superadmin@gmail.com',
            'username' => 'super_admin',
            'manage_type' => 'all',
            'first_name' => 'Super Admin',
            'status' => true,
            'kode_user' => 'super_admin',
            'posisi' => 'Super Admin',
            'name' => 'Super Admin',
            'cabang_id' => null, // Super admin can access all branches
            'password' => Hash::make('superadmin')
        ]);

        $user->syncRoles(Role::where('name', 'Super Admin')->first());
        $superAdmin->syncRoles(Role::where('name', 'Super Admin')->first());

        // Create an Owner account and assign Owner role
        $owner = User::updateOrCreate([
            'email' => 'owner@example.com',
        ], [
            'username' => 'owner',
            'email' => 'owner@example.com',
            'name' => 'Owner',
            'first_name' => 'Owner',
            'last_name' => '',
            'kode_user' => 'owner',
            'status' => true,
            'posisi' => 'Owner',
            'manage_type' => 'all',
            'cabang_id' => null, // Owner can access all branches
            'password' => Hash::make('owner123')
        ]);

        $owner->syncRoles(Role::where('name', 'Owner')->first());

        // Additional role-based sample accounts
        $accounts = [
            ['email' => 'admin@example.com', 'username' => 'admin', 'name' => 'Admin', 'role' => 'Admin', 'manage_type' => 'all', 'cabang' => null],
            ['email' => 'finance_manager@example.com', 'username' => 'finance_manager', 'name' => 'Finance Manager', 'role' => 'Finance Manager'],
            ['email' => 'admin_keuangan@example.com', 'username' => 'admin_keuangan', 'name' => 'Admin Keuangan', 'role' => 'Admin Keuangan'],
            ['email' => 'accounting@example.com', 'username' => 'accounting', 'name' => 'Accounting', 'role' => 'Accounting'],
            ['email' => 'purchasing@example.com', 'username' => 'purchasing', 'name' => 'Purchasing', 'role' => 'Purchasing'],
            ['email' => 'purchasing_manager@example.com', 'username' => 'purchasing_manager', 'name' => 'Purchasing Manager', 'role' => 'Purchasing Manager'],
            ['email' => 'inventory_manager@example.com', 'username' => 'inventory_manager', 'name' => 'Inventory Manager', 'role' => 'Inventory Manager'],
            ['email' => 'admin_inventory@example.com', 'username' => 'admin_inventory', 'name' => 'Admin Inventory', 'role' => 'Admin Inventory'],
            ['email' => 'warehouse_staff@example.com', 'username' => 'warehouse_staff', 'name' => 'Warehouse Staff', 'role' => 'Warehouse Staff'],
            ['email' => 'checker@example.com', 'username' => 'checker', 'name' => 'Checker', 'role' => 'Checker'],
            ['email' => 'sales_manager@example.com', 'username' => 'sales_manager', 'name' => 'Sales Manager', 'role' => 'Sales Manager'],
            ['email' => 'sales@example.com', 'username' => 'sales', 'name' => 'Sales', 'role' => 'Sales'],
            ['email' => 'kasir@example.com', 'username' => 'kasir', 'name' => 'Kasir', 'role' => 'Kasir'],
            ['email' => 'customer_service@example.com', 'username' => 'customer_service', 'name' => 'Customer Service', 'role' => 'Customer Service'],
            ['email' => 'delivery_driver@example.com', 'username' => 'delivery_driver', 'name' => 'Delivery Driver', 'role' => 'Delivery Driver'],
            ['email' => 'auditor@example.com', 'username' => 'auditor', 'name' => 'Auditor', 'role' => 'Auditor'],
            ['email' => 'it_support@example.com', 'username' => 'it_support', 'name' => 'IT Support', 'role' => 'IT Support'],
        ];

        foreach ($accounts as $acc) {
            $cabangId = $acc['cabang'] ?? Cabang::inRandomOrder()->first()->id ?? 1;
            $manageType = $acc['manage_type'] ?? 'branch';

            $u = User::updateOrCreate([
                'email' => $acc['email'],
            ], [
                'email' => $acc['email'],
                'username' => $acc['username'],
                'name' => $acc['name'],
                'first_name' => $acc['name'],
                'last_name' => '',
                'kode_user' => $acc['username'],
                'status' => true,
                'posisi' => $acc['name'],
                'manage_type' => $manageType,
                'cabang_id' => $cabangId,
                'password' => Hash::make('password'),
            ]);

            $role = Role::where('name', $acc['role'])->first();
            if ($role) {
                $u->syncRoles($role);
            }
        }
    }
}
