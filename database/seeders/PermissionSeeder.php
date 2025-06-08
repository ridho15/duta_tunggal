<?php

namespace Database\Seeders;

use App\Http\Controllers\HelperController;
use App\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (HelperController::listPermission() as $index => $permission) {
            foreach ($permission as $item) {
                Permission::updateOrCreate([
                    'name' => $item . ' ' . $index
                ], [
                    'name' => $item . ' ' . $index,
                    'guard_name' => 'web'
                ]);
            }
        }
    }
}
