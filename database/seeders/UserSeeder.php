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

        $user->syncRoles(Role::where('name', 'Super Admin')->first());
    }
}
