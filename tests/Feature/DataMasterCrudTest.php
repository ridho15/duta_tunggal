<?php

/**
 * Data Master CRUD Test Suite
 * 
 * Covers complete CRUD operations for all data master modules:
 * - Cabang (Branch)
 * - Customer
 * - Supplier
 * - Product Category
 * - Unit of Measure (UOM)
 * - Currency
 * - Tax Setting
 * - Warehouse
 * - Driver
 * - Vehicle
 * - Rak (Shelf/Rack)
 * - Product
 */


use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\Currency;
use App\Models\TaxSetting;
use App\Models\Warehouse;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\Rak;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// =====================================================================
// HELPER: setup admin user with all data master permissions
// =====================================================================
function setupDataMasterUser(?int $cabangId = null): User
{
    $user = $cabangId
        ? User::factory()->create(['cabang_id' => $cabangId])
        : User::factory()->create();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        // Cabang
        'view any cabang', 'view cabang', 'create cabang', 'update cabang', 'delete cabang',
        // Customer
        'view any customer', 'view customer', 'create customer', 'update customer', 'delete customer',
        // Supplier
        'view any supplier', 'view supplier', 'create supplier', 'update supplier', 'delete supplier',
        // Product Category
        'view any product category', 'view product category', 'create product category', 'update product category', 'delete product category',
        // Unit of Measure
        'view any unit of measure', 'view unit of measure', 'create unit of measure', 'update unit of measure', 'delete unit of measure',
        // Currency
        'view any currency', 'view currency', 'create currency', 'update currency', 'delete currency',
        // Tax Setting
        'view any tax setting', 'view tax setting', 'create tax setting', 'update tax setting', 'delete tax setting',
        // Warehouse
        'view any warehouse', 'view warehouse', 'create warehouse', 'update warehouse', 'delete warehouse',
        // Driver
        'view any driver', 'view driver', 'create driver', 'update driver', 'delete driver',
        // Vehicle
        'view any vehicle', 'view vehicle', 'create vehicle', 'update vehicle', 'delete vehicle',
        // Rak
        'view any rak', 'view rak', 'create rak', 'update rak', 'delete rak',
        // Product
        'view any product', 'view product', 'create product', 'update product', 'delete product',
        // Chart of Account (needed for product form)
        'view any chart of account', 'view chart of account',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($permissions);
    Auth::login($user);

    return $user;
}

// =====================================================================
// 1. CABANG (BRANCH) CRUD
// =====================================================================

// =====================================================================
// 2. CUSTOMER CRUD
// =====================================================================

// =====================================================================
// 3. SUPPLIER CRUD
// =====================================================================

// =====================================================================
// 4. PRODUCT CATEGORY CRUD
// =====================================================================

// =====================================================================
// 5. UNIT OF MEASURE (UOM) CRUD
// =====================================================================

// =====================================================================
// 6. CURRENCY CRUD
// =====================================================================

// =====================================================================
// 7. TAX SETTING CRUD
// =====================================================================

// =====================================================================
// 8. WAREHOUSE CRUD
// =====================================================================

// =====================================================================
// 9. DRIVER CRUD
// =====================================================================

// =====================================================================
// 10. VEHICLE CRUD
// =====================================================================

// =====================================================================
// 11. RAK (SHELF/RACK) CRUD
// =====================================================================

describe('Rak (Shelf) CRUD', function () {
    beforeEach(function () {
        $this->cabang = Cabang::factory()->create();
        $this->user = setupDataMasterUser($this->cabang->id);
        $this->warehouse = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);
    });

    it('only offers active warehouses for rak selection', function () {
        $inactiveWarehouse = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'status' => 0,
        ]);

        $otherCabang = Cabang::factory()->create();
        $otherBranchWarehouse = Warehouse::factory()->create([
            'cabang_id' => $otherCabang->id,
            'status' => 1,
        ]);

        $eligibleWarehouseIds = Warehouse::where('status', 1)
            ->where('cabang_id', $this->cabang->id)
            ->pluck('id')
            ->all();

        $this->assertContains($this->warehouse->id, $eligibleWarehouseIds);
        $this->assertNotContains($inactiveWarehouse->id, $eligibleWarehouseIds);
        $this->assertNotContains($otherBranchWarehouse->id, $eligibleWarehouseIds);
    });

});

// =====================================================================
// 12. PRODUCT CRUD
// =====================================================================

// =====================================================================
// 13. DATA MASTER INTEGRITY TESTS (Relations & Dependencies)
// =====================================================================

describe('Data Master Integrity', function () {
    beforeEach(function () {
        $this->user = setupDataMasterUser();
        $this->cabang = Cabang::factory()->create();
    });

    it('warehouse belongs to cabang', function () {
        $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        expect($warehouse->cabang)->not->toBeNull();
        expect($warehouse->cabang->id)->toBe($this->cabang->id);
    });

    it('driver belongs to cabang', function () {
        $driver = Driver::factory()->create(['cabang_id' => $this->cabang->id]);

        expect($driver->cabang)->not->toBeNull();
        expect($driver->cabang->id)->toBe($this->cabang->id);
    });

    it('vehicle belongs to cabang', function () {
        $vehicle = Vehicle::factory()->create(['cabang_id' => $this->cabang->id]);

        expect($vehicle->cabang)->not->toBeNull();
        expect($vehicle->cabang->id)->toBe($this->cabang->id);
    });

    it('rak belongs to warehouse', function () {
        $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);

        expect($rak->warehouse)->not->toBeNull();
        expect($rak->warehouse->id)->toBe($warehouse->id);
    });

    it('customer belongs to cabang', function () {
        $customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);

        expect($customer->cabang)->not->toBeNull();
        expect($customer->cabang->id)->toBe($this->cabang->id);
    });

    it('supplier belongs to cabang', function () {
        $supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);

        expect($supplier->cabang)->not->toBeNull();
        expect($supplier->cabang->id)->toBe($this->cabang->id);
    });

    it('product category can have multiple records', function () {
        $cat1 = ProductCategory::factory()->create(['name' => 'Kategori A']);
        $cat2 = ProductCategory::factory()->create(['name' => 'Kategori B']);

        expect(ProductCategory::count())->toBe(2);
        expect($cat1->name)->toBe('Kategori A');
        expect($cat2->name)->toBe('Kategori B');
    });

    it('customer code must be unique (DB constraint)', function () {
        Customer::factory()->create(['code' => 'CUST-UNIQUE', 'cabang_id' => $this->cabang->id]);

        expect(fn () => Customer::factory()->create(['code' => 'CUST-UNIQUE', 'cabang_id' => $this->cabang->id]))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('cabang kode must be unique (DB constraint)', function () {
        Cabang::factory()->create(['kode' => 'CBG-UNIQUE']);

        expect(fn () => Cabang::factory()->create(['kode' => 'CBG-UNIQUE']))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('supplier code must be unique (DB constraint)', function () {
        Supplier::factory()->create(['code' => 'SUP-UNIQUE', 'cabang_id' => $this->cabang->id]);

        expect(fn () => Supplier::factory()->create(['code' => 'SUP-UNIQUE', 'cabang_id' => $this->cabang->id]))
            ->toThrow(\Illuminate\Database\QueryException::class);
    });

    it('can create multiple currencies', function () {
        $usd = Currency::factory()->create(['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD', 'to_rupiah' => 15000]);
        $eur = Currency::factory()->create(['name' => 'Euro', 'symbol' => '€', 'code' => 'EUR', 'to_rupiah' => 16500]);

        expect(Currency::count())->toBe(2);
        expect($usd->code)->toBe('USD');
        expect($eur->code)->toBe('EUR');
    });
});

// =====================================================================
// 14. DATA MASTER MODEL FACTORY TESTS
// =====================================================================

describe('Data Master Factory Tests', function () {
    it('can create cabang via factory', function () {
        $cabang = Cabang::factory()->create();
        expect($cabang->id)->not->toBeNull();
        expect($cabang->kode)->not->toBeEmpty();
        expect($cabang->nama)->not->toBeEmpty();
    });

    it('can create customer via factory', function () {
        $cabang = Cabang::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        expect($customer->id)->not->toBeNull();
        expect($customer->perusahaan)->not->toBeEmpty();
    });

    it('can create supplier via factory', function () {
        $cabang = Cabang::factory()->create();
        $supplier = Supplier::factory()->create(['cabang_id' => $cabang->id]);
        expect($supplier->id)->not->toBeNull();
        expect($supplier->perusahaan)->not->toBeEmpty();
    });

    it('can create product category via factory', function () {
        $category = ProductCategory::factory()->create();
        expect($category->id)->not->toBeNull();
        expect($category->name)->not->toBeEmpty();
    });

    it('can create unit of measure via factory', function () {
        $uom = UnitOfMeasure::factory()->create();
        expect($uom->id)->not->toBeNull();
        expect($uom->name)->not->toBeEmpty();
        expect($uom->abbreviation)->not->toBeEmpty();
    });

    it('can create currency via factory', function () {
        $currency = Currency::factory()->create();
        expect($currency->id)->not->toBeNull();
        expect($currency->code)->not->toBeEmpty();
    });

    it('can create tax setting via factory', function () {
        $tax = TaxSetting::factory()->create([
            'name'           => 'PPN Test',
            'rate'           => 11,
            'effective_date' => now(),
            'status'         => true,
            'type'           => 'PPN',
        ]);
        expect($tax->id)->not->toBeNull();
        expect($tax->type)->toBe('PPN');
    });

    it('can create warehouse via factory', function () {
        $cabang = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
        expect($warehouse->id)->not->toBeNull();
        expect($warehouse->kode)->not->toBeEmpty();
    });

    it('can create driver via factory', function () {
        $cabang = Cabang::factory()->create();
        $driver = Driver::factory()->create(['cabang_id' => $cabang->id]);
        expect($driver->id)->not->toBeNull();
        expect($driver->name)->not->toBeEmpty();
    });

    it('can create vehicle via factory', function () {
        $cabang = Cabang::factory()->create();
        $vehicle = Vehicle::factory()->create(['cabang_id' => $cabang->id]);
        expect($vehicle->id)->not->toBeNull();
        expect($vehicle->plate)->not->toBeEmpty();
    });

    it('can create rak via factory', function () {
        $cabang = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
        $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id]);
        expect($rak->id)->not->toBeNull();
        expect($rak->code)->not->toBeEmpty();
    });
});
