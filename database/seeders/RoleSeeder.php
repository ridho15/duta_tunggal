<?php

namespace Database\Seeders;

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
        Role::updateOrCreate([
            'name' => 'Super Admin',
        ], [
            'name' => 'Super Admin',
            'guard_name' => 'web'
        ]);

        Role::updateOrCreate([
            'name' => 'Admin',
        ], [
            'name' => 'Admin',
            'guard_name' => 'web'
        ]);

        Role::updateOrCreate([
            'name' => 'Gudang',
        ], [
            'name' => 'Gudang',
            'guard_name' => 'web'
        ]);

        Role::updateOrCreate([
            'name' => 'Owner',
        ], [
            'name' => 'Owner',
            'guard_name' => 'web'
        ]);
    }
}
