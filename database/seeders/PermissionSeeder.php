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
                'guard_name' => 'web'
            ]);
        }

        // Ensure warehouse approval permission exists for material issue tests
        Permission::updateOrCreate([
            'name' => 'approve warehouse'
        ], [
            'name' => 'approve warehouse',
            'guard_name' => 'web'
        ]);
    }
}
