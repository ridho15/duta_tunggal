<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            'email' => 'ralamzah@gmail.com',
            'name' => 'Ridho Al Amzah',
            'password' => Hash::make('ridho123')
        ]);

        $admin = User::updateOrCreate([
            'email' => 'admin@gmail.com',
        ], [
            'email' => 'admin@gmail.com',
            'name' => 'Admin',
            'password' => Hash::make('ridho123')
        ]);

        $gudang = User::updateOrCreate([
            'email' => 'gudang@gmail.com',
        ], [
            'email' => 'gudang@gmail.com',
            'name' => "Gudang",
            'password' => Hash::make('ridho123')
        ]);

        $owner = User::updateOrCreate([
            'email' => 'owner@gmail.com',
        ], [
            'email' => 'owner@gmail.com',
            'name' => "Owner",
            'password' => Hash::make('ridho123')
        ]);

        $user->syncRoles(Role::where('name', 'Super Admin')->first());
        $gudang->assignRole('Gudang');
        $owner->assignRole('Owner');
    }
}
