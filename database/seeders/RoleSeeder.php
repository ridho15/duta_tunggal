<?php

namespace Database\Seeders;

use App\Http\Controllers\HelperController;
use App\Models\Customer;
use App\Models\Permission;
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
        $owner = Role::updateOrCreate([
            'name' => 'Owner',
        ], [
            'name' => 'Owner',
            'guard_name' => 'web'
        ]);

        $superAdmin = Role::updateOrCreate([
            'name' => 'Super Admin',
        ], [
            'name' => 'Super Admin',
            'guard_name' => 'web'
        ]);

        $admin = Role::updateOrCreate([
            'name' => 'Admin',
        ], [
            'name' => 'Admin',
            'guard_name' => 'web'
        ]);

        $salesManager = Role::updateOrCreate([
            'name' => 'Sales Manager',
        ], [
            'name' => 'Sales Manager',
            'guard_name' => 'web'
        ]);

        $sales = Role::updateOrCreate([
            'name' => 'Sales',
        ], [
            'name' => 'Sales',
            'guard_name' => 'web'
        ]);

        $kasir = Role::updateOrCreate([
            'name' => 'Kasir',
        ], [
            'name' => 'Kasir',
            'guard_name' => 'web'
        ]);

        $inventoryManager = Role::updateOrCreate([
            'name' => 'Inventory Manager',
        ], [
            'name' => 'Inventory Manager',
            'guard_name' => 'web'
        ]);

        $adminInventory = Role::updateOrCreate([
            'name' => 'Admin Inventory',
        ], [
            'name' => 'Admin Inventory',
            'guard_name' => 'web'
        ]);

        $checker = Role::updateOrCreate([
            'name' => 'Checker',
        ], [
            'name' => 'Checker',
            'guard_name' => 'web'
        ]);

        $financeManager = Role::updateOrCreate([
            'name' => 'Finance Manager',
        ], [
            'name' => 'Finance Manager',
            'guard_name' => 'web'
        ]);

        $adminKeuangan = Role::updateOrCreate([
            'name' => 'Admin Keuangan',
        ], [
            'name' => 'Admin Keuangan',
            'guard_name' => 'web'
        ]);

        $accounting = Role::updateOrCreate([
            'name' => 'Accounting',
        ], [
            'name' => 'Accounting',
            'guard_name' => 'web'
        ]);

        $purchasing = Role::updateOrCreate([
            'name' => 'Purchasing',
        ], [
            'name' => 'Purchasing',
            'guard_name' => 'web'
        ]);

        // Additional recommended roles
        $purchasingManager = Role::updateOrCreate([
            'name' => 'Purchasing Manager',
        ], [
            'name' => 'Purchasing Manager',
            'guard_name' => 'web'
        ]);

        $warehouseStaff = Role::updateOrCreate([
            'name' => 'Warehouse Staff',
        ], [
            'name' => 'Warehouse Staff',
            'guard_name' => 'web'
        ]);

        $deliveryDriver = Role::updateOrCreate([
            'name' => 'Delivery Driver',
        ], [
            'name' => 'Delivery Driver',
            'guard_name' => 'web'
        ]);

        $customerService = Role::updateOrCreate([
            'name' => 'Customer Service',
        ], [
            'name' => 'Customer Service',
            'guard_name' => 'web'
        ]);

        $auditor = Role::updateOrCreate([
            'name' => 'Auditor',
        ], [
            'name' => 'Auditor',
            'guard_name' => 'web'
        ]);

        $itSupport = Role::updateOrCreate([
            'name' => 'IT Support',
        ], [
            'name' => 'IT Support',
            'guard_name' => 'web'
        ]);


        $owner->syncPermissions(Permission::all());
        $superAdmin->syncPermissions(Permission::all());

        // Role -> resource mapping: each role gets ALL permissions for the listed resources
        $roleResourceMap = [
            'Admin' => [
                'user', 'role', 'permission', 'currency', 'chart of account', 'tax setting', 'cabang', 'asset', 'journal entry'
            ],
            'Finance Manager' => [
                'account payable', 'account receivable', 'vendor payment', 'vendor payment detail', 'customer receipt', 'customer receipt item', 'invoice', 'invoice item', 'deposit', 'deposit log', 'ageing schedule', 'voucher request', 'asset', 'asset depreciation', 'asset disposal', 'asset transfer', 'cash bank account', 'cash bank transaction detail', 'journal entry'
            ],
            'Admin Keuangan' => [
                'account payable', 'vendor payment', 'deposit', 'invoice', 'invoice item', 'voucher request', 'asset', 'asset depreciation', 'asset disposal', 'asset transfer', 'cash bank account', 'cash bank transaction detail', 'journal entry'
            ],
            'Accounting' => [
                'chart of account', 'account payable', 'account receivable', 'deposit', 'invoice', 'invoice item', 'ageing schedule', 'asset', 'asset depreciation', 'asset disposal', 'asset transfer', 'cash bank account', 'cash bank transaction detail', 'journal entry'
            ],
            'Purchasing' => [
                'purchase order', 'purchase order item', 'purchase receipt', 'purchase receipt item', 'purchase order biaya', 'purchase order currency', 'purchase return'
            ],
            'Purchasing Manager' => [
                'purchase order', 'purchase order item', 'purchase receipt', 'vendor payment', 'purchase return', 'purchase order biaya', 'asset'
            ],
            'Inventory Manager' => [
                'warehouse', 'warehouse confirmation', 'inventory stock', 'stock movement', 'stock transfer', 'stock transfer item', 'product', 'product category', 'rak', 'unit of measure', 'product unit conversion', 'quality control', 'asset transfer'
            ],
            'Admin Inventory' => [
                'warehouse', 'inventory stock', 'stock movement', 'product'
            ],
            'Warehouse Staff' => [
                'warehouse', 'warehouse confirmation', 'stock transfer', 'stock transfer item', 'inventory stock'
            ],
            'Checker' => [
                'warehouse confirmation', 'quality control', 'inventory stock'
            ],
            'Sales Manager' => [
                'sales order', 'sales order item', 'quotation', 'quotation item', 'invoice', 'customer', 'customer receipt'
            ],
            'Sales' => [
                'sales order', 'sales order item', 'quotation', 'customer'
            ],
            'Kasir' => [
                'customer receipt', 'customer receipt item', 'invoice'
            ],
            'Customer Service' => [
                'customer', 'quotation', 'sales order', 'delivery order', 'surat jalan'
            ],
            'Delivery Driver' => [
                'delivery order', 'delivery order item', 'vehicle', 'surat jalan'
            ],
            'Auditor' => array_keys(HelperController::listPermission()), // auditor can view many modules; we'll grant view access in test verification
            'IT Support' => [
                'user', 'role', 'permission', 'tax setting', 'currency'
            ],
        ];

        // Apply permissions per role based on mapping
        foreach ($roleResourceMap as $roleName => $resources) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            $permsToAssign = [];
            $allDefined = HelperController::listPermission();
            foreach ($resources as $res) {
                // If mapping contains full list key (like auditor), handle separately
                if (is_int($res)) {
                    continue;
                }

                if ($res === 'AUDITOR_ALL') {
                    continue;
                }

                // if resource exists in helper list, collect all action names
                    if (isset($allDefined[$res])) {
                        // Do not grant destructive actions to most roles. Only allow for these roles:
                        $allowedDestructiveRoles = [
                        'Owner',
                        'Super Admin',
                        'Purchasing Manager',
                        'Inventory Manager',
                        'Finance Manager',
                    ];

                        foreach ($allDefined[$res] as $action) {
                            if (in_array($action, ['delete', 'force-delete']) && !in_array($roleName, $allowedDestructiveRoles, true)) {
                                // skip destructive action for this role
                                continue;
                            }

                            $permsToAssign[] = $action . ' ' . $res;
                        }
                    }
            }

            // Special: Auditor — grant view any for all resources
            if ($roleName === 'Auditor') {
                foreach ($allDefined as $resName => $actions) {
                    if (in_array('view any', $actions)) {
                        $permsToAssign[] = 'view any ' . $resName;
                    }
                }
            }

            // Sync unique permissions that exist in DB
            $permsToAssign = array_unique($permsToAssign);
            $existingPerms = Permission::whereIn('name', $permsToAssign)->get();
            if ($existingPerms->count()) {
                $role->syncPermissions($existingPerms);
            }
        }
    }
}
