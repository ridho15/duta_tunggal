<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::updateOrCreate([
            'email' => 'ralamzah@gmail.com'
        ], [
            'email' => 'ralamzah@gmail.com',
            'name' => 'Ridho Al Amzah',
            'password' => Hash::make('ridho123')
        ]);

        User::updateOrCreate([
            'email' => 'superadmin@gmail.com'
        ], [
            'email' => 'superadmin@gmail.com',
            'name' => 'Super Admin',
            'password' => Hash::make('adminsuper')
        ]);
    }
}
