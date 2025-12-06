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
    }
}
