<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
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
            'password' => Hash::make('superadmin')
        ]);

        $user->syncRoles(Role::where('name', 'Super Admin')->first());
        $superAdmin->syncRoles(Role::where('name', 'Super Admin')->first());
    }
}
