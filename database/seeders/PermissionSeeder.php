<?php

namespace Database\Seeders;

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
        // User
        Permission::updateOrCreate([
            'name' => 'view-any user'
        ], [
            'name' => 'view-any user',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view user'
        ], [
            'name' => 'view user',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create user'
        ], [
            'name' => 'create user',
            'guard_name' => 'web'
        ]);
        Permission::updateOrCreate([
            'name' => 'update user'
        ], [
            'name' => 'update user',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete user'
        ], [
            'name' => 'delete user',
            'guard_name' => 'web'
        ]);


        // Role
        Permission::updateOrCreate([
            'name' => 'view-any role'
        ], [
            'name' => 'view-any role',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view role'
        ], [
            'name' => 'view role',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create role'
        ], [
            'name' => 'create role',
            'guard_name' => 'web'
        ]);
        Permission::updateOrCreate([
            'name' => 'update role'
        ], [
            'name' => 'update role',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete role'
        ], [
            'name' => 'delete role',
            'guard_name' => 'web'
        ]);


        // Permission
        Permission::updateOrCreate([
            'name' => 'view-any permission'
        ], [
            'name' => 'view-any permission',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view permission'
        ], [
            'name' => 'view permission',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create permission'
        ], [
            'name' => 'create permission',
            'guard_name' => 'web'
        ]);
        Permission::updateOrCreate([
            'name' => 'update permission'
        ], [
            'name' => 'update permission',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete permission'
        ], [
            'name' => 'delete permission',
            'guard_name' => 'web'
        ]);

        // product
        Permission::updateOrCreate([
            'name' => 'view product'
        ], [
            'name' => 'view product',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create product'
        ], [
            'name' => 'create product',
            'guard_name' => 'web'
        ]);
        Permission::updateOrCreate([
            'name' => 'update product'
        ], [
            'name' => 'update product',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete product'
        ], [
            'name' => 'delete product',
            'guard_name' => 'web'
        ]);

        // customer
        Permission::updateOrCreate([
            'name' => 'view-any customer'
        ], [
            'name' => 'view-any customer',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view customer'
        ], [
            'name' => 'view customer',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create customer'
        ], [
            'name' => 'create customer',
            'guard_name' => 'web'
        ]);
        Permission::updateOrCreate([
            'name' => 'update customer'
        ], [
            'name' => 'update customer',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete customer'
        ], [
            'name' => 'delete customer',
            'guard_name' => 'web'
        ]);

        // manufacture
        Permission::updateOrCreate([
            'name' => 'view-any manufacture'
        ], [
            'name' => 'view-any manufacture',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view manufacture'
        ], [
            'name' => 'view manufacture',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create manufacture'
        ], [
            'name' => 'create manufacture',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update manufacture'
        ], [
            'name' => 'update manufacture',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete manufacture'
        ], [
            'name' => 'delete manufacture',
            'guard_name' => 'web'
        ]);

        // currency
        Permission::updateOrCreate([
            'name' => 'view-any currency'
        ], [
            'name' => 'view-any currency',
            'guard_name' => 'web'
        ]);
        Permission::updateOrCreate([
            'name' => 'view currency'
        ], [
            'name' => 'view currency',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create currency'
        ], [
            'name' => 'create currency',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update currency'
        ], [
            'name' => 'update currency',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete currency'
        ], [
            'name' => 'delete currency',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'restore currency'
        ], [
            'name' => 'restore currency',
            'guard_name' => 'web'
        ]);

        // supplier
        Permission::updateOrCreate([
            'name' => 'view-any supplier'
        ], [
            'name' => 'view-any supplier',
            'guard_name' => 'web'
        ]);
        Permission::updateOrCreate([
            'name' => 'view supplier'
        ], [
            'name' => 'view supplier',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create supplier'
        ], [
            'name' => 'create supplier',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update supplier'
        ], [
            'name' => 'update supplier',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete supplier'
        ], [
            'name' => 'delete supplier',
            'guard_name' => 'web'
        ]);

        // unit of measure
        Permission::updateOrCreate([
            'name' => 'view-any unit of measure'
        ], [
            'name' => 'view-any unit of measure',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view unit of measure'
        ], [
            'name' => 'view unit of measure',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create unit of measure'
        ], [
            'name' => 'create unit of measure',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update unit of measure'
        ], [
            'name' => 'update unit of measure',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete unit of measure'
        ], [
            'name' => 'delete unit of measure',
            'guard_name' => 'web'
        ]);


        // warehouse confirmation
        Permission::updateOrCreate([
            'name' => 'view-any warehouse confirmation'
        ], [
            'name' => 'view-any warehouse confirmation',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view warehouse confirmation'
        ], [
            'name' => 'view warehouse confirmation',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create warehouse confirmation'
        ], [
            'name' => 'create warehouse confirmation',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update warehouse confirmation'
        ], [
            'name' => 'update warehouse confirmation',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete warehouse confirmation'
        ], [
            'name' => 'delete warehouse confirmation',
            'guard_name' => 'web'
        ]);

        // vehicle
        Permission::updateOrCreate([
            'name' => 'view-any vehicle'
        ], [
            'name' => 'view-any vehicle',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view vehicle'
        ], [
            'name' => 'view vehicle',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create vehicle'
        ], [
            'name' => 'create vehicle',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update vehicle'
        ], [
            'name' => 'update vehicle',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete vehicle'
        ], [
            'name' => 'delete vehicle',
            'guard_name' => 'web'
        ]);

        // Purchase Order
        Permission::updateOrCreate([
            'name' => 'view-any purchase order'
        ], [
            'name' => 'view-any purchase order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view purchase order'
        ], [
            'name' => 'view purchase order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create purchase order'
        ], [
            'name' => 'create purchase order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update purchase order'
        ], [
            'name' => 'update purchase order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete purchase order'
        ], [
            'name' => 'delete purchase order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'approve purchase order'
        ], [
            'name' => 'approve purchase order',
            'guard_name' => 'web'
        ]);

        // Purchase Order Item
        Permission::updateOrCreate([
            'name' => 'view-any purchase order item'
        ], [
            'name' => 'view-any purchase order item',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view purchase order item'
        ], [
            'name' => 'view purchase order item',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create purchase order item'
        ], [
            'name' => 'create purchase order item',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update purchase order item'
        ], [
            'name' => 'update purchase order item',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete purchase order item'
        ], [
            'name' => 'delete purchase order item',
            'guard_name' => 'web'
        ]);

        // Sales Order
        Permission::updateOrCreate([
            'name' => 'view-any sales order'
        ], [
            'name' => 'view-any sales order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view sales order'
        ], [
            'name' => 'view sales order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create sales order'
        ], [
            'name' => 'create sales order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update sales order'
        ], [
            'name' => 'update sales order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete sales order'
        ], [
            'name' => 'delete sales order',
            'guard_name' => 'web'
        ]);

        // Sales Order Item
        Permission::updateOrCreate([
            'name' => 'view-any sales order item'
        ], [
            'name' => 'view-any sales order item',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view sales order item'
        ], [
            'name' => 'view sales order item',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create sales order item'
        ], [
            'name' => 'create sales order item',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update sales order item'
        ], [
            'name' => 'update sales order item',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete sales order item'
        ], [
            'name' => 'delete sales order item',
            'guard_name' => 'web'
        ]);

        // Delivery Order
        Permission::updateOrCreate([
            'name' => 'view-any delivery order'
        ], [
            'name' => 'view-any delivery order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'view delivery order'
        ], [
            'name' => 'view delivery order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'create delivery order'
        ], [
            'name' => 'create delivery order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'update delivery order'
        ], [
            'name' => 'update delivery order',
            'guard_name' => 'web'
        ]);

        Permission::updateOrCreate([
            'name' => 'delete delivery order'
        ], [
            'name' => 'delete delivery order',
            'guard_name' => 'web'
        ]);
    }
}
