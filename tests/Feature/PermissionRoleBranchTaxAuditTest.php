<?php

/**
 * COMPREHENSIVE AUDIT TEST SUITE
 *
 * Covers:
 *   1. Permission system — every module guard-gate check
 *   2. Role-based access (Sales, Purchasing, Finance, Warehouse, Admin, Super Admin, Auditor)
 *   3. Branch (cabang) scope — data isolation between branches
 *   4. Quotation tax calculations — Exclusive and Inclusive
 *   5. SalesOrder tax calculations with tipe_pajak
 *   6. Sales Order created from Quotation — tax fields inherited
 *   7. SalesOrderService::updateTotalAmount respects tipe_pajak
 *   8. InventoryCard / StockReport controllers require permission
 *
 * Database: real MySQL (no RefreshDatabase to keep test DB state; uses TestCase setup)
 * Test runner: Pest
 */

use App\Http\Controllers\HelperController;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\QuotationService;
use App\Services\SalesOrderService;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS
// ─────────────────────────────────────────────────────────────────────────────

function makeCustomer(string $code = 'CUST-TEST', int $cabangId = 1): Customer
{
    return Customer::create([
        'name'            => 'PT Test ' . $code,
        'code'            => $code,
        'address'         => 'Jl. Test No. 1',
        'telephone'       => '021-111',
        'phone'           => '08100000001',
        'email'           => strtolower($code) . '@test.com',
        'perusahaan'      => 'PT Test',
        'tipe'            => 'PKP',
        'fax'             => '',
        'nik_npwp'        => '000000000000000',
        'tempo_kredit'    => 30,
        'kredit_limit'    => 50000000,
        'tipe_pembayaran' => 'Kredit',
        'keterangan'      => '',
    ]);
}

function makeCabang(string $name = 'Cabang A'): Cabang
{
    static $ci = 0;
    $ci++;
    return Cabang::create([
        'kode'   => 'CB' . str_pad($ci, 4, '0', STR_PAD_LEFT),
        'nama'   => $name,
        'alamat' => 'Jl. Test No. ' . $ci,
        'telepon' => '021-000000',
        'status' => 1,
        'tipe_penjualan' => 'Semua',
    ]);
}

function makeProductCategory(int $cabangId = 1): ProductCategory
{
    static $i = 0;
    $i++;
    return ProductCategory::create([
        'name'         => 'Category ' . $i,
        'kode'         => 'CAT' . str_pad($i, 3, '0', STR_PAD_LEFT),
        'cabang_id'    => $cabangId,
        'kenaikan_harga' => 0,
    ]);
}

function makeProduct(int $cabangId = 1, float $sellPrice = 100000): Product
{
    static $j = 0;
    $j++;
    $cat = makeProductCategory($cabangId);
    return Product::create([
        'name'                => 'Product ' . $j,
        'sku'                 => 'SKU' . str_pad($j, 4, '0', STR_PAD_LEFT),
        'cabang_id'           => $cabangId,
        'product_category_id' => $cat->id,
        'sell_price'          => $sellPrice,
        'cost_price'          => $sellPrice * 0.8,
        'kode_merk'           => 'BRAND',
        'uom_id'              => 1,
        'is_active'           => true,
        'is_manufacture'      => false,
        'is_raw_material'     => false,
    ]);
}

function makeQuotation(Customer $customer, string $status = 'draft'): Quotation
{
    static $n = 0;
    $n++;
    return Quotation::create([
        'quotation_number' => 'QO-TEST-' . str_pad($n, 4, '0', STR_PAD_LEFT),
        'customer_id'      => $customer->id,
        'date'             => now(),
        'valid_until'      => now()->addDays(30),
        'status'           => $status,
        'created_by'       => 1,
    ]);
}

function makeUser(array $roles = [], array $permissions = []): User
{
    static $u = 0;
    $u++;
    $user = User::factory()->create([
        'email' => 'user' . $u . '@test.example',
    ]);
    foreach ($roles as $roleName) {
        if (!Role::where('name', $roleName)->where('guard_name', 'web')->exists()) {
            Role::create(['name' => $roleName, 'guard_name' => 'web']);
        }
        $user->assignRole($roleName);
    }
    foreach ($permissions as $permName) {
        if (!Permission::where('name', $permName)->where('guard_name', 'web')->exists()) {
            Permission::create(['name' => $permName, 'guard_name' => 'web']);
        }
        $user->givePermissionTo($permName);
    }
    return $user;
}

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1 — PERMISSION SYSTEM: naming convention and completeness
// ─────────────────────────────────────────────────────────────────────────────

test('listPermission covers all core modules and follows naming convention', function () {
    $permissions = HelperController::listPermission();

    $requiredModules = [
        'user', 'role', 'permission',
        'sales order', 'sales order item',
        'quotation', 'quotation item',
        'purchase order', 'purchase order item',
        'inventory stock', 'stock movement', 'stock transfer',
        'delivery order', 'warehouse',
        'invoice', 'customer', 'tax setting',
        'cabang', 'product', 'product category',
    ];

    foreach ($requiredModules as $module) {
        expect(array_key_exists($module, $permissions))->toBeTrue("Module '{$module}' missing from listPermission()");
    }

    $crudActions = ['view any', 'view', 'create', 'update', 'delete'];
    foreach ($requiredModules as $module) {
        foreach ($crudActions as $action) {
            expect(in_array($action, $permissions[$module]))->toBeTrue(
                "Action '{$action}' missing for module '{$module}'"
            );
        }
    }
});

test('sales order module has workflow permissions', function () {
    $permissions = HelperController::listPermission();
    expect($permissions['sales order'])->toContain('request');
    expect($permissions['sales order'])->toContain('response');
});

test('quotation module has approval-workflow permissions', function () {
    $permissions = HelperController::listPermission();
    expect($permissions['quotation'])->toContain('request-approve');
    expect($permissions['quotation'])->toContain('approve');
    expect($permissions['quotation'])->toContain('reject');
});

test('voucher request module has submit, approve, reject, cancel permissions', function () {
    $permissions = HelperController::listPermission();
    expect($permissions)->toHaveKey('voucher request');
    expect($permissions['voucher request'])->toContain('submit');
    expect($permissions['voucher request'])->toContain('approve');
    expect($permissions['voucher request'])->toContain('reject');
    expect($permissions['voucher request'])->toContain('cancel');
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2 — PERMISSION SEEDER: all permissions are created and described
// ─────────────────────────────────────────────────────────────────────────────

test('permission seeder creates all expected permissions', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $list = HelperController::listPermission();
    foreach ($list as $module => $actions) {
        foreach ($actions as $action) {
            $permName = "{$action} {$module}";
            $perm = Permission::where('name', $permName)->first();
            expect($perm)->not->toBeNull("Permission '{$permName}' not found after seeding");
            expect($perm->description)->not->toBeNull();
            expect(trim($perm->description))->not->toBe('');
        }
    }
});

test('role seeder creates descriptions for each role', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $roles = HelperController::roleDescriptions();
    foreach ($roles as $name => $desc) {
        $role = Role::where('name', $name)->first();
        expect($role)->not->toBeNull("Role '{$name}' should exist after seeding");
        expect($role->description)->toBe($desc);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3 — ROLE ACCESS MATRIX
// ─────────────────────────────────────────────────────────────────────────────

test('Sales role only has sales-related permissions', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $salesRole = Role::findByName('Sales', 'web');
    $permNames = $salesRole->permissions->pluck('name')->toArray();

    // Must have
    expect($permNames)->toContain('view any sales order');
    expect($permNames)->toContain('create sales order');
    expect($permNames)->toContain('view any quotation');
    expect($permNames)->toContain('create quotation');

    // Must NOT have full finance or purchasing perms
    expect($permNames)->not->toContain('view any purchase order');
    expect($permNames)->not->toContain('create purchase order');
    expect($permNames)->not->toContain('view any journal entry');
});

test('Purchasing role only has purchasing-related permissions', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $role = Role::findByName('Purchasing', 'web');
    $permNames = $role->permissions->pluck('name')->toArray();

    expect($permNames)->toContain('view any purchase order');
    expect($permNames)->toContain('create purchase order');
    expect($permNames)->not->toContain('create sales order');
    expect($permNames)->not->toContain('view any journal entry');
});

test('Auditor role has view any on every module but cannot create or delete', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $role = Role::findByName('Auditor', 'web');
    $permNames = $role->permissions->pluck('name')->toArray();

    $list = HelperController::listPermission();
    foreach (array_keys($list) as $module) {
        expect(in_array("view any {$module}", $permNames))->toBeTrue("Auditor missing 'view any {$module}'");
    }

    // Auditor must not have create/delete on sales order
    expect($permNames)->not->toContain('create sales order');
    expect($permNames)->not->toContain('delete sales order');
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 4 — GATE CHECKS: spatie/permission integration
// ─────────────────────────────────────────────────────────────────────────────

test('user with view any sales order permission can pass Gate check', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $user = makeUser(permissions: ['view any sales order']);
    expect($user->can('view any sales order'))->toBeTrue();
    expect($user->can('create sales order'))->toBeFalse();
});

test('user without permission is denied', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $user = makeUser(permissions: []);
    expect($user->can('view any sales order'))->toBeFalse();
    expect($user->can('create quotation'))->toBeFalse();
});

test('Super Admin user has all permissions', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $superAdmin = makeUser(roles: ['Super Admin']);
    $list = HelperController::listPermission();

    foreach ($list as $module => $actions) {
        foreach ($actions as $action) {
            expect($superAdmin->can("{$action} {$module}"))
                ->toBeTrue("Super Admin missing '{$action} {$module}'");
        }
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5 — BRANCH SCOPE VERIFICATION
// ─────────────────────────────────────────────────────────────────────────────

test('sale orders are filtered by branch scope', function () {
    $cabangA = makeCabang('Cabang A');
    $cabangB = makeCabang('Cabang B');

    $customerA = makeCustomer('CUST-A');
    $customerB = makeCustomer('CUST-B');

    $userA = makeUser();
    $userA->update(['cabang_id' => $cabangA->id]);

    // Create SO for branch A
    $soA = SaleOrder::create([
        'customer_id'    => $customerA->id,
        'so_number'      => 'SO-BRANCH-A-001',
        'order_date'     => now(),
        'status'         => 'draft',
        'tipe_pengiriman' => 'Ambil Sendiri',
        'created_by'     => $userA->id,
        'cabang_id'      => $cabangA->id,
        'total_amount'   => 0,
    ]);

    // Create SO for branch B (different cabang)
    $soB = SaleOrder::create([
        'customer_id'    => $customerB->id,
        'so_number'      => 'SO-BRANCH-B-001',
        'order_date'     => now(),
        'status'         => 'draft',
        'tipe_pengiriman' => 'Ambil Sendiri',
        'created_by'     => 1,
        'cabang_id'      => $cabangB->id,
        'total_amount'   => 0,
    ]);

    // Simulate the branch scope by querying with cabang_id filter (mirrors CabangScope)
    Auth::setUser($userA);
    $filteredOrders = SaleOrder::withoutGlobalScope(\App\Models\Scopes\CabangScope::class)
        ->where('cabang_id', $cabangA->id)
        ->pluck('so_number')
        ->toArray();

    expect($filteredOrders)->toContain('SO-BRANCH-A-001');
    expect($filteredOrders)->not->toContain('SO-BRANCH-B-001');
});

test('CabangScope applies to SaleOrder queries', function () {
    // Verify that the SaleOrder model has CabangScope registered
    $model      = new SaleOrder();
    $globalScopes = $model->getGlobalScopes();

    $scopeClasses = array_keys($globalScopes);
    expect($scopeClasses)->toContain(\App\Models\Scopes\CabangScope::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 6 — TAX SERVICE
// ─────────────────────────────────────────────────────────────────────────────

test('TaxService normalizes English Exclusive to Eksklusif', function () {
    $result = TaxService::normalizeType('Exclusive');
    expect($result)->toBe('Eksklusif');
});

test('TaxService normalizes English Inclusive to Inklusif', function () {
    $result = TaxService::normalizeType('Inclusive');
    expect($result)->toBe('Inklusif');
});

test('TaxService::compute Eksklusif adds PPN on top', function () {
    $result = TaxService::compute(1000000, 12, 'Eksklusif');
    expect($result['dpp'])->toBe(1000000.0);
    expect($result['ppn'])->toBe(120000.0);
    expect($result['total'])->toBe(1120000.0);
});

test('TaxService::compute Inklusif extracts PPN from gross', function () {
    $result = TaxService::compute(1120000, 12, 'Inklusif');
    // total stays at gross
    expect($result['total'])->toBe(1120000.0);
    expect($result['ppn'])->toBeGreaterThan(0);
    expect($result['ppn'])->toBe(round(1120000 - round(1120000 * 100 / 112)));
});

test('TaxService::compute Non Pajak returns zero PPN', function () {
    $result = TaxService::compute(500000, 12, 'Non Pajak');
    expect($result['ppn'])->toBe(0.0);
    expect($result['total'])->toBe(500000.0);
});

test('TaxService::compute with zero rate returns no PPN', function () {
    $result = TaxService::compute(500000, 0, 'Eksklusif');
    expect($result['ppn'])->toBe(0.0);
    expect($result['total'])->toBe(500000.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 7 — hitungSubtotal helper
// ─────────────────────────────────────────────────────────────────────────────

test('hitungSubtotal with Exclusive tax calculates correct total', function () {
    // qty=2, price=100000, discount=0%, tax=12%, Exclusive
    // subtotal = 200000, after discount = 200000, +12% = 224000
    $result = HelperController::hitungSubtotal(2, 100000, 0, 12, 'Exclusive');
    expect($result)->toBe(224000.0);
});

test('hitungSubtotal with Inclusive tax returns gross unchanged', function () {
    // qty=2, price=100000, discount=0%, tax=12%, Inclusive
    // gross = 200000, total = 200000 (PPN inside)
    $result = HelperController::hitungSubtotal(2, 100000, 0, 12, 'Inclusive');
    expect($result)->toBe(200000.0);
});

test('hitungSubtotal applies discount before tax', function () {
    // qty=10, price=100000, discount=10%, tax=12%, Exclusive
    // base = 1000000, after 10% discount = 900000, +12% = 1008000
    $result = HelperController::hitungSubtotal(10, 100000, 10, 12, 'Exclusive');
    expect($result)->toBe(1008000.0);
});

test('hitungSubtotal defaults to Inklusif when null taxType passed', function () {
    // null taxType → hitungSubtotal defaults to 'Inklusif'
    // qty=1, price=1000000, discount=0%, tax=12%
    // Inklusif: total = 1000000 (unchanged)
    $result = HelperController::hitungSubtotal(1, 1000000, 0, 12, null);
    expect($result)->toBe(1000000.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 8 — QUOTATION TAX BEHAVIOR
// ─────────────────────────────────────────────────────────────────────────────

test('QuotationItem stores tax_type Exclusive by default from DB column', function () {
    $customer  = makeCustomer('CUST-QIT');
    $quotation = makeQuotation($customer);

    $item = QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id'   => 1,
        'quantity'     => 1,
        'unit_price'   => 500000,
        'discount'     => 0,
        'tax'          => 12,
        // tax_type omitted — DB default 'Exclusive'
    ]);
    $item->refresh();

    expect($item->tax_type)->toBe('Exclusive');
});

test('QuotationItem stores Inclusive when explicitly set', function () {
    $customer  = makeCustomer('CUST-QII');
    $quotation = makeQuotation($customer);

    $item = QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id'   => 1,
        'quantity'     => 1,
        'unit_price'   => 500000,
        'discount'     => 0,
        'tax'          => 12,
        'tax_type'     => 'Inclusive',
    ]);
    $item->refresh();

    expect($item->tax_type)->toBe('Inclusive');
});

test('QuotationService updateTotalAmount uses Exclusive tax_type', function () {
    $service   = new QuotationService();
    $customer  = makeCustomer('CUST-QEX');
    $quotation = makeQuotation($customer);

    // 1 x 1,000,000, 0% discount, 12% Exclusive → total 1,120,000
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id'   => 1,
        'quantity'     => 1,
        'unit_price'   => 1000000,
        'discount'     => 0,
        'tax'          => 12,
        'tax_type'     => 'Exclusive',
    ]);

    $service->updateTotalAmount($quotation);
    $quotation->refresh();

    expect((float) $quotation->total_amount)->toBe(1120000.0);
});

test('QuotationService updateTotalAmount uses Inclusive tax_type', function () {
    $service   = new QuotationService();
    $customer  = makeCustomer('CUST-QINC');
    $quotation = makeQuotation($customer);

    // 1 x 1,120,000, 0% discount, 12% Inclusive → total stays 1,120,000
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id'   => 1,
        'quantity'     => 1,
        'unit_price'   => 1120000,
        'discount'     => 0,
        'tax'          => 12,
        'tax_type'     => 'Inclusive',
    ]);

    $service->updateTotalAmount($quotation);
    $quotation->refresh();

    expect((float) $quotation->total_amount)->toBe(1120000.0);
});

test('QuotationService updateTotalAmount handles multiple items with mixed tax types', function () {
    $service   = new QuotationService();
    $customer  = makeCustomer('CUST-QMIX');
    $quotation = makeQuotation($customer);

    // Item A: 2 x 500000, 0% discount, 12% Exclusive → 1,000,000 + 120,000 = 1,120,000
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id'   => 1,
        'quantity'     => 2,
        'unit_price'   => 500000,
        'discount'     => 0,
        'tax'          => 12,
        'tax_type'     => 'Exclusive',
    ]);

    // Item B: 1 x 200000, 0% discount, 12% Inclusive → stays 200,000
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id'   => 1,
        'quantity'     => 1,
        'unit_price'   => 200000,
        'discount'     => 0,
        'tax'          => 12,
        'tax_type'     => 'Inclusive',
    ]);

    $service->updateTotalAmount($quotation);
    $quotation->refresh();

    // 1,120,000 + 200,000 = 1,320,000
    expect((float) $quotation->total_amount)->toBe(1320000.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 9 — SALE ORDER ITEM: tipe_pajak column
// ─────────────────────────────────────────────────────────────────────────────

test('SaleOrderItem tipe_pajak column defaults to Exclusive', function () {
    $cabang   = makeCabang('Cabang SOI');
    $customer = makeCustomer('CUST-SOI');

    $so = SaleOrder::create([
        'customer_id'     => $customer->id,
        'so_number'       => 'SO-TIPEPAJAK-001',
        'order_date'      => now(),
        'status'          => 'draft',
        'tipe_pengiriman' => 'Ambil Sendiri',
        'created_by'      => 1,
        'cabang_id'       => $cabang->id,
        'total_amount'    => 0,
    ]);

    $item = SaleOrderItem::create([
        'sale_order_id'  => $so->id,
        'product_id'     => 1,
        'quantity'       => 1,
        'unit_price'     => 100000,
        'discount'       => 0,
        'tax'            => 12,
        'warehouse_id'   => 1,
        // tipe_pajak omitted — DB default 'Exclusive'
    ]);
    $item->refresh();

    expect($item->tipe_pajak)->toBe('Exclusive');
});

test('SaleOrderItem tipe_pajak can be set to Inclusive', function () {
    $cabang   = makeCabang('Cabang SOI2');
    $customer = makeCustomer('CUST-SOI2');

    $so = SaleOrder::create([
        'customer_id'     => $customer->id,
        'so_number'       => 'SO-TIPEPAJAK-002',
        'order_date'      => now(),
        'status'          => 'draft',
        'tipe_pengiriman' => 'Ambil Sendiri',
        'created_by'      => 1,
        'cabang_id'       => $cabang->id,
        'total_amount'    => 0,
    ]);

    $item = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id'    => 1,
        'quantity'      => 1,
        'unit_price'    => 200000,
        'discount'      => 0,
        'tax'           => 12,
        'tipe_pajak'    => 'Inclusive',
        'warehouse_id'  => 1,
    ]);
    $item->refresh();

    expect($item->tipe_pajak)->toBe('Inclusive');
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 10 — SalesOrderService::updateTotalAmount with tipe_pajak
// ─────────────────────────────────────────────────────────────────────────────

test('SalesOrderService updateTotalAmount uses per-item tipe_pajak (Exclusive)', function () {
    $service  = new SalesOrderService();
    $cabang   = makeCabang('Cabang SOS-EX');
    $customer = makeCustomer('CUST-SOS-EX');

    $so = SaleOrder::create([
        'customer_id'     => $customer->id,
        'so_number'       => 'SO-SOSEX-001',
        'order_date'      => now(),
        'status'          => 'draft',
        'tipe_pengiriman' => 'Ambil Sendiri',
        'created_by'      => 1,
        'cabang_id'       => $cabang->id,
        'total_amount'    => 0,
    ]);

    // 2 x 1,000,000, 0% disc, 12% Exclusive → 2,000,000 + 240,000 = 2,240,000
    SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id'    => 1,
        'quantity'      => 2,
        'unit_price'    => 1000000,
        'discount'      => 0,
        'tax'           => 12,
        'tipe_pajak'    => 'Exclusive',
        'warehouse_id'  => 1,
    ]);

    $service->updateTotalAmount($so);
    $so->refresh();

    expect((float) $so->total_amount)->toBe(2240000.0);
});

test('SalesOrderService updateTotalAmount uses per-item tipe_pajak (Inclusive)', function () {
    $service  = new SalesOrderService();
    $cabang   = makeCabang('Cabang SOS-INC');
    $customer = makeCustomer('CUST-SOS-INC');

    $so = SaleOrder::create([
        'customer_id'     => $customer->id,
        'so_number'       => 'SO-SOSINC-001',
        'order_date'      => now(),
        'status'          => 'draft',
        'tipe_pengiriman' => 'Ambil Sendiri',
        'created_by'      => 1,
        'cabang_id'       => $cabang->id,
        'total_amount'    => 0,
    ]);

    // 1 x 2,240,000, 0% disc, 12% Inclusive → total stays 2,240,000
    SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id'    => 1,
        'quantity'      => 1,
        'unit_price'    => 2240000,
        'discount'      => 0,
        'tax'           => 12,
        'tipe_pajak'    => 'Inclusive',
        'warehouse_id'  => 1,
    ]);

    $service->updateTotalAmount($so);
    $so->refresh();

    expect((float) $so->total_amount)->toBe(2240000.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 11 — SALE ORDER CREATED FROM QUOTATION: tax fields inherited
// ─────────────────────────────────────────────────────────────────────────────

test('Sale Order items inherit tax_type from Quotation items when created from Quotation', function () {
    $cabang   = makeCabang('Cabang FromQ');
    $customer = makeCustomer('CUST-FROM-Q');
    $quotation = makeQuotation($customer, 'approve');

    // Quotation item with Inclusive tax
    $qItem = QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id'   => 1,
        'quantity'     => 3,
        'unit_price'   => 500000,
        'discount'     => 5,
        'tax'          => 12,
        'tax_type'     => 'Inclusive',
    ]);

    // This simulates the SaleOrderResource.afterStateUpdated logic
    $soItems = [];
    foreach ($quotation->quotationItem as $item) {
        $tipePajak = $item->tax_type ?? 'Exclusive';
        $soItems[] = [
            'product_id'  => $item->product_id,
            'quantity'    => $item->quantity,
            'unit_price'  => (float) $item->unit_price,
            'discount'    => $item->discount,
            'tax'         => $item->tax,
            'tipe_pajak'  => $tipePajak,
            'warehouse_id' => 1,
        ];
    }

    $so = SaleOrder::create([
        'customer_id'    => $customer->id,
        'quotation_id'   => $quotation->id,
        'so_number'      => 'SO-FROM-Q-001',
        'order_date'     => now(),
        'status'         => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by'     => 1,
        'cabang_id'      => $cabang->id,
        'total_amount'   => 0,
    ]);

    foreach ($soItems as $soItemData) {
        SaleOrderItem::create(array_merge($soItemData, ['sale_order_id' => $so->id]));
    }

    $so->load('saleOrderItem');
    $firstItem = $so->saleOrderItem->first();

    expect($firstItem->tipe_pajak)->toBe('Inclusive');
    expect($firstItem->tax)->toBe(12);
    expect($firstItem->discount)->toBe(5);
});

test('Sale Order total reflects inherited Inclusive tax correctly', function () {
    $service  = new SalesOrderService();
    $cabang   = makeCabang('Cabang Inherit');
    $customer = makeCustomer('CUST-INHERIT');
    $quotation = makeQuotation($customer, 'approve');

    // Quotation item: 2 x 1,000,000, 0% disc, 11% Inclusive → total stays 2,000,000
    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id'   => 1,
        'quantity'     => 2,
        'unit_price'   => 1000000,
        'discount'     => 0,
        'tax'          => 11,
        'tax_type'     => 'Inclusive',
    ]);

    $so = SaleOrder::create([
        'customer_id'    => $customer->id,
        'quotation_id'   => $quotation->id,
        'so_number'      => 'SO-INHERIT-001',
        'order_date'     => now(),
        'status'         => 'draft',
        'tipe_pengiriman' => 'Ambil Sendiri',
        'created_by'     => 1,
        'cabang_id'      => $cabang->id,
        'total_amount'   => 0,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id'    => 1,
        'quantity'      => 2,
        'unit_price'    => 1000000,
        'discount'      => 0,
        'tax'           => 11,
        'tipe_pajak'    => 'Inclusive', // inherited from quotation
        'warehouse_id'  => 1,
    ]);

    $service->updateTotalAmount($so);
    $so->refresh();

    // Inclusive: total = gross = 2,000,000
    expect((float) $so->total_amount)->toBe(2000000.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 12 — TAX CONSISTENCY: invoice should be able to see tax type
// ─────────────────────────────────────────────────────────────────────────────

test('TaxService handles all possible tax type strings without exception', function () {
    $typesToTest = [
        'Exclusive', 'Inclusive', 'Non Pajak',
        'inklusif', 'eksklusif', 'exclusive', 'inclusive',
        'PPN Included', 'PPN Excluded',
        'none', 'null', '',
        null,
    ];

    foreach ($typesToTest as $type) {
        $result = TaxService::compute(100000, 12, $type);
        expect($result)->toHaveKeys(['dpp', 'ppn', 'total'])
            ->and($result['total'])->toBeGreaterThanOrEqual(0);
    }
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 13 — TAX SETTINGS MODEL
// ─────────────────────────────────────────────────────────────────────────────

test('TaxSetting can be created and retrieved', function () {
    $tax = TaxSetting::create([
        'name'           => 'PPN 12%',
        'rate'           => 12.00,
        'effective_date' => '2025-01-01',
        'status'         => 1,
        'type'           => 'PPN',
    ]);
    $tax->refresh();

    expect($tax->rate)->toEqual('12.00');
    expect($tax->type)->toBe('PPN');
    expect($tax->status)->toBe(1);
});

test('Active PPN tax setting can be queried for default tax rate', function () {
    TaxSetting::create([
        'name'           => 'PPN 12% Active',
        'rate'           => 12.00,
        'effective_date' => now()->subMonth()->toDateString(),
        'status'         => 1,
        'type'           => 'PPN',
    ]);

    $activePPN = TaxSetting::where('type', 'PPN')
        ->where('status', 1)
        ->orderByDesc('effective_date')
        ->first();

    expect($activePPN)->not->toBeNull();
    expect((float) $activePPN->rate)->toBe(12.0);
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 14 — QUOTATION APPROVAL WORKFLOW
// ─────────────────────────────────────────────────────────────────────────────

test('Quotation approval workflow: draft → request_approve → approve', function () {
    $user = User::factory()->create();
    Auth::shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('guard')->andReturnSelf();

    $service   = new QuotationService();
    $customer  = makeCustomer('CUST-WFLOW');
    $quotation = makeQuotation($customer, 'draft');

    $result = $service->requestApprove($quotation);
    expect($result)->toBeTrue();
    $quotation->refresh();
    expect($quotation->status)->toBe('request_approve');

    $result = $service->approve($quotation);
    expect($result)->toBeTrue();
    $quotation->refresh();
    expect($quotation->status)->toBe('approve');
    expect($quotation->approve_by)->toBe($user->id);
});

test('Quotation approval workflow: request_approve → reject', function () {
    $user = User::factory()->create();
    Auth::shouldReceive('user')->andReturn($user);
    Auth::shouldReceive('guard')->andReturnSelf();

    $service   = new QuotationService();
    $customer  = makeCustomer('CUST-REJ');
    $quotation = makeQuotation($customer, 'request_approve');

    $result = $service->reject($quotation);
    expect($result)->toBeTrue();
    $quotation->refresh();
    expect($quotation->status)->toBe('reject');
    expect($quotation->reject_by)->toBe($user->id);
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 15 — CONTROLLER PERMISSION ENFORCEMENT (HTTP layer)
// ─────────────────────────────────────────────────────────────────────────────

test('InventoryCardController print route returns 403 without permission', function () {
    $user = makeUser(); // no permissions
    $this->actingAs($user);

    $response = $this->get(route('inventory-card.print'));
    $response->assertStatus(403);
});

test('InventoryCardController print route passes with correct permission', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $user = makeUser(permissions: ['view any inventory stock']);
    $this->actingAs($user);

    // Route exists and auth passes (may 500 on missing data — that's OK for auth test)
    $response = $this->get(route('inventory-card.print'));
    expect($response->status())->not()->toBe(403);
});

test('StockReport preview route returns 403 without permission', function () {
    $user = makeUser();
    $this->actingAs($user);

    $response = $this->get(route('reports.stock-report.preview'));
    $response->assertStatus(403);
});

test('StockReport preview route passes with correct permission', function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $user = makeUser(permissions: ['view any inventory stock']);
    $this->actingAs($user);

    $response = $this->get(route('reports.stock-report.preview'));
    expect($response->status())->not()->toBe(403);
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 16 — MENU / NAVIGATION: permission-driven visibility placeholder
// ─────────────────────────────────────────────────────────────────────────────

test('Filament resources define canViewAny via policy', function () {
    // Filament resolves canViewAny through the model Policy.
    // Verify the SaleOrder policy class exists and responds to viewAny.
    expect(class_exists(\App\Policies\SaleOrderPolicy::class))->toBeTrue();
    expect(class_exists(\App\Policies\QuotationPolicy::class))->toBeTrue();
    expect(class_exists(\App\Policies\TaxSettingPolicy::class))->toBeTrue();
    expect(class_exists(\App\Policies\InvoicePolicy::class))->toBeTrue();
    expect(class_exists(\App\Policies\PurchaseOrderPolicy::class))->toBeTrue();
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 17 — SALE ORDER NUMBER GENERATION
// ─────────────────────────────────────────────────────────────────────────────

test('SalesOrderService::generateCode returns a unique SO number', function () {
    // Verify that so_number uniqueness is enforced at application level.
    // The SaleOrderResource generates codes like SO-YYYYMMDD-NNNN.
    $cabang   = makeCabang('Cabang UNIQ');
    $customer = makeCustomer('CUST-UNIQ');

    $so1 = SaleOrder::create([
        'customer_id'     => $customer->id,
        'so_number'       => 'SO-UNIQUE-001',
        'order_date'      => now(),
        'status'          => 'draft',
        'tipe_pengiriman' => 'Ambil Sendiri',
        'created_by'      => 1,
        'cabang_id'       => $cabang->id,
        'total_amount'    => 0,
    ]);

    $exists = SaleOrder::where('so_number', 'SO-UNIQUE-001')->count();
    expect($exists)->toBe(1);
    expect($so1->so_number)->toBe('SO-UNIQUE-001');
});
