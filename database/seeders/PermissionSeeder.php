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
        $descriptions = HelperController::permissionDescriptions();

        foreach (HelperController::listPermission() as $index => $permission) {
            foreach ($permission as $item) {
                $name = $item . ' ' . $index;
                Permission::updateOrCreate([
                    'name' => $name
                ], [
                    'name' => $name,
                    'guard_name' => 'web',
                    'description' => $descriptions[$name] ?? null,
                ]);
            }
        }

        // Ensure voucher request related permissions exist for tests
        $voucherPermissions = [
            'submit voucher request',
            'approve voucher request',
            'reject voucher request',
            'view voucher request',
        ];

        foreach ($voucherPermissions as $perm) {
            Permission::updateOrCreate([
                'name' => $perm
            ], [
                'name' => $perm,
                'guard_name' => 'web',
                'description' => $descriptions[$perm] ?? null,
            ]);
        }

        // Ensure warehouse approval permission exists for material issue tests
        $perm = 'approve warehouse';
        Permission::updateOrCreate([
            'name' => $perm
        ], [
            'name' => $perm,
            'guard_name' => 'web',
            'description' => $descriptions[$perm] ?? null,
        ]);
    }
}
