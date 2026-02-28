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

use App\Filament\Resources\CabangResource\Pages\CreateCabang;
use App\Filament\Resources\CabangResource\Pages\EditCabang;
use App\Filament\Resources\CabangResource\Pages\ListCabangs;
use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Filament\Resources\ProductCategoryResource\Pages\CreateProductCategory;
use App\Filament\Resources\ProductCategoryResource\Pages\EditProductCategory;
use App\Filament\Resources\ProductCategoryResource\Pages\ListProductCategories;
use App\Filament\Resources\UnitOfMeasureResource\Pages\CreateUnitOfMeasure;
use App\Filament\Resources\UnitOfMeasureResource\Pages\EditUnitOfMeasure;
use App\Filament\Resources\UnitOfMeasureResource\Pages\ListUnitOfMeasures;
use App\Filament\Resources\CurrencyResource\Pages\CreateCurrency;
use App\Filament\Resources\CurrencyResource\Pages\EditCurrency;
use App\Filament\Resources\CurrencyResource\Pages\ListCurrencies;
use App\Filament\Resources\TaxSettingResource\Pages\CreateTaxSetting;
use App\Filament\Resources\TaxSettingResource\Pages\EditTaxSetting;
use App\Filament\Resources\TaxSettingResource\Pages\ListTaxSettings;
use App\Filament\Resources\WarehouseResource\Pages\CreateWarehouse;
use App\Filament\Resources\WarehouseResource\Pages\EditWarehouse;
use App\Filament\Resources\WarehouseResource\Pages\ListWarehouses;
use App\Filament\Resources\DriverResource\Pages\CreateDriver;
use App\Filament\Resources\DriverResource\Pages\EditDriver;
use App\Filament\Resources\DriverResource\Pages\ListDrivers;
use App\Filament\Resources\VehicleResource\Pages\CreateVehicle;
use App\Filament\Resources\VehicleResource\Pages\EditVehicle;
use App\Filament\Resources\VehicleResource\Pages\ListVehicles;
use App\Filament\Resources\RakResource\Pages\CreateRak;
use App\Filament\Resources\RakResource\Pages\EditRak;
use App\Filament\Resources\RakResource\Pages\ListRaks;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;

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

describe('Cabang (Branch) CRUD', function () {
    beforeEach(function () {
        $this->user = setupDataMasterUser();
    });

    it('can render list page', function () {
        Livewire::test(ListCabangs::class)->assertSuccessful();
    });

    it('can create cabang', function () {
        Livewire::test(CreateCabang::class)
            ->fillForm([
                'kode'                  => 'CBG-TEST01',
                'nama'                  => 'Cabang Test Jakarta',
                'alamat'                => 'Jl. Test No. 1, Jakarta',
                'telepon'               => '0212345678',
                'kenaikan_harga'        => 0,
                'tipe_penjualan'        => 'Semua',
                'warna_background'      => '#3b82f6',
                'status'                => true,
                'kode_invoice_pajak'        => 'INV-PJK-001',
                'kode_invoice_non_pajak'    => 'INV-NPJK-001',
                'kode_invoice_pajak_walkin' => 'INV-WPJK-001',
                'nama_kwitansi'         => 'Kwitansi Test',
                'label_invoice_pajak'   => 'Label Pajak',
                'label_invoice_non_pajak' => 'Label Non Pajak',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cabangs', [
            'kode' => 'CBG-TEST01',
            'nama' => 'Cabang Test Jakarta',
        ]);
    });

    it('validates required fields when creating cabang', function () {
        Livewire::test(CreateCabang::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['kode', 'nama', 'alamat']);
    });

    it('can edit cabang', function () {
        $cabang = Cabang::factory()->create([
            'telepon'          => '0212345678',
            'warna_background' => '#3b82f6',
            'tipe_penjualan'   => 'Semua',
        ]);

        Livewire::test(EditCabang::class, ['record' => $cabang->getRouteKey()])
            ->fillForm([
                'nama'             => 'Cabang Diperbarui',
                'alamat'           => 'Jl. Baru No. 99',
                'telepon'          => '0219999999',
                'warna_background' => '#ef4444',
                'tipe_penjualan'   => 'Semua',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cabangs', [
            'id'   => $cabang->id,
            'nama' => 'Cabang Diperbarui',
        ]);
    });

    it('can delete cabang', function () {
        $cabang = Cabang::factory()->create();

        Livewire::test(ListCabangs::class)
            ->callTableAction('delete', $cabang);

        $this->assertSoftDeleted('cabangs', ['id' => $cabang->id]);
    });
});

// =====================================================================
// 2. CUSTOMER CRUD
// =====================================================================

describe('Customer CRUD', function () {
    beforeEach(function () {
        $this->user = setupDataMasterUser();
        $this->cabang = Cabang::factory()->create();
    });

    it('can render list page', function () {
        Livewire::test(ListCustomers::class)->assertSuccessful();
    });

    it('can create customer', function () {
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'code'            => 'CUST-TEST001',
                'name'            => 'PT Test Customer',
                'perusahaan'      => 'PT Test Customer',
                'nik_npwp'        => '1234567890123456',
                'address'         => 'Jl. Customer No. 1',
                'telephone'       => '0212345678',
                'phone'           => '08111111111',
                'email'           => 'customer@test.com',
                'fax'             => '0212345679',
                'tempo_kredit'    => 30,
                'kredit_limit'    => 50000000,
                'tipe_pembayaran' => 'Kredit',
                'tipe'            => 'PKP',
                'isSpecial'       => false,
                'cabang_id'       => $this->cabang->id,
                'keterangan'      => 'Test customer created in automated test',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'code'    => 'CUST-TEST001',
            'perusahaan' => 'PT Test Customer',
        ]);
    });

    it('validates required fields when creating customer', function () {
        Livewire::test(CreateCustomer::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['code', 'perusahaan']);
    });

    it('can edit customer', function () {
        $customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);

        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm([
                'perusahaan' => 'PT Customer Diperbarui',
                'email'      => 'updated@test.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'id'         => $customer->id,
            'perusahaan' => 'PT Customer Diperbarui',
        ]);
    });

    it('can delete customer', function () {
        $customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);

        Livewire::test(ListCustomers::class)
            ->callTableAction('delete', $customer);

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    });
});

// =====================================================================
// 3. SUPPLIER CRUD
// =====================================================================

describe('Supplier CRUD', function () {
    beforeEach(function () {
        $this->user = setupDataMasterUser();
        $this->cabang = Cabang::factory()->create();
    });

    it('can render list page', function () {
        Livewire::test(ListSuppliers::class)->assertSuccessful();
    });

    it('can create supplier', function () {
        Livewire::test(CreateSupplier::class)
            ->fillForm([
                'cabang_id'      => $this->cabang->id,
                'code'           => 'SUP-TEST001',
                'perusahaan'     => 'PT Test Supplier',
                'kontak_person'  => 'Budi Santoso',
                'npwp'           => '01.234.567.8-901.000',
                'address'        => 'Jl. Supplier No. 1',
                'phone'          => '0213333333',
                'handphone'      => '08133333333',
                'email'          => 'supplier@test.com',
                'fax'            => '021333001',
                'tempo_hutang'   => 45,
                'keterangan'     => 'Test supplier',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('suppliers', [
            'code'       => 'SUP-TEST001',
            'perusahaan' => 'PT Test Supplier',
        ]);
    });

    it('validates required fields when creating supplier', function () {
        Livewire::test(CreateSupplier::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['code', 'perusahaan']);
    });

    it('can edit supplier', function () {
        $supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);

        Livewire::test(EditSupplier::class, ['record' => $supplier->getRouteKey()])
            ->fillForm([
                'perusahaan' => 'PT Supplier Diperbarui',
                'tempo_hutang' => 60,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('suppliers', [
            'id'         => $supplier->id,
            'perusahaan' => 'PT Supplier Diperbarui',
        ]);
    });

    it('can delete supplier', function () {
        $supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);

        Livewire::test(ListSuppliers::class)
            ->callTableAction('delete', $supplier);

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    });
});

// =====================================================================
// 4. PRODUCT CATEGORY CRUD
// =====================================================================

describe('Product Category CRUD', function () {
    beforeEach(function () {
        $this->user = setupDataMasterUser();
    });

    it('can render list page', function () {
        Livewire::test(ListProductCategories::class)->assertSuccessful();
    });

    it('can create product category', function () {
        Livewire::test(CreateProductCategory::class)
            ->fillForm([
                'kode'           => 'CAT-TEST01',
                'name'           => 'Kategori Test',
                'kenaikan_harga' => 5,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('product_categories', [
            'kode' => 'CAT-TEST01',
            'name' => 'Kategori Test',
        ]);
    });

    it('validates required fields when creating product category', function () {
        Livewire::test(CreateProductCategory::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['name']);
    });

    it('can edit product category', function () {
        $category = ProductCategory::factory()->create();

        Livewire::test(EditProductCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm([
                'name'           => 'Kategori Diperbarui',
                'kenaikan_harga' => 10,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('product_categories', [
            'id'   => $category->id,
            'name' => 'Kategori Diperbarui',
        ]);
    });

    it('can delete product category', function () {
        $category = ProductCategory::factory()->create();

        Livewire::test(ListProductCategories::class)
            ->callTableAction('delete', $category);

        $this->assertDatabaseMissing('product_categories', ['id' => $category->id, 'deleted_at' => null]);
    });
});

// =====================================================================
// 5. UNIT OF MEASURE (UOM) CRUD
// =====================================================================

describe('Unit of Measure CRUD', function () {
    beforeEach(function () {
        $this->user = setupDataMasterUser();
    });

    it('can render list page', function () {
        Livewire::test(ListUnitOfMeasures::class)->assertSuccessful();
    });

    it('can create unit of measure', function () {
        Livewire::test(CreateUnitOfMeasure::class)
            ->fillForm([
                'name'         => 'Karton',
                'abbreviation' => 'ktn',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('unit_of_measures', [
            'name'         => 'Karton',
            'abbreviation' => 'ktn',
        ]);
    });

    it('validates required fields when creating unit of measure', function () {
        Livewire::test(CreateUnitOfMeasure::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['name', 'abbreviation']);
    });

    it('can edit unit of measure', function () {
        $uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pc']);

        Livewire::test(EditUnitOfMeasure::class, ['record' => $uom->getRouteKey()])
            ->fillForm([
                'name'         => 'Pieces Updated',
                'abbreviation' => 'pcs',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('unit_of_measures', [
            'id'           => $uom->id,
            'name'         => 'Pieces Updated',
            'abbreviation' => 'pcs',
        ]);
    });

    it('can delete unit of measure', function () {
        $uom = UnitOfMeasure::factory()->create(['name' => 'DeleteMe', 'abbreviation' => 'dm']);

        Livewire::test(ListUnitOfMeasures::class)
            ->callTableAction('delete', $uom);

        $this->assertSoftDeleted('unit_of_measures', ['id' => $uom->id]);
    });
});

// =====================================================================
// 6. CURRENCY CRUD
// =====================================================================

describe('Currency CRUD', function () {
    beforeEach(function () {
        $this->user = setupDataMasterUser();
    });

    it('can render list page', function () {
        Livewire::test(ListCurrencies::class)->assertSuccessful();
    });

    it('can create currency', function () {
        Livewire::test(CreateCurrency::class)
            ->fillForm([
                'name'      => 'Singapore Dollar',
                'symbol'    => 'S$',
                'code'      => 'SGD',
                'to_rupiah' => 11600,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('currencies', [
            'code' => 'SGD',
            'name' => 'Singapore Dollar',
        ]);
    });

    it('validates required fields when creating currency', function () {
        Livewire::test(CreateCurrency::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['name', 'to_rupiah']);
    });

    it('can edit currency', function () {
        $currency = Currency::factory()->create(['name' => 'US Dollar', 'symbol' => '$', 'code' => 'USD', 'to_rupiah' => 15000]);

        Livewire::test(EditCurrency::class, ['record' => $currency->getRouteKey()])
            ->fillForm([
                'to_rupiah' => 16000,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('currencies', [
            'id'        => $currency->id,
            'to_rupiah' => 16000,
        ]);
    });

    it('can delete currency', function () {
        $currency = Currency::factory()->create(['name' => 'Test Coin', 'symbol' => 'TC', 'code' => 'TST', 'to_rupiah' => 1]);

        Livewire::test(ListCurrencies::class)
            ->callTableAction('delete', $currency);

        $this->assertSoftDeleted('currencies', ['id' => $currency->id]);
    });
});

// =====================================================================
// 7. TAX SETTING CRUD
// =====================================================================

describe('Tax Setting CRUD', function () {
    beforeEach(function () {
        $this->user = setupDataMasterUser();
    });

    it('can render list page', function () {
        Livewire::test(ListTaxSettings::class)->assertSuccessful();
    });

    it('can create tax setting PPN', function () {
        Livewire::test(CreateTaxSetting::class)
            ->fillForm([
                'name'           => 'PPN 11%',
                'rate'           => 11,
                'effective_date' => '2024-01-01',
                'status'         => true,
                'type'           => 'PPN',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tax_settings', [
            'name' => 'PPN 11%',
            'rate' => 11,
            'type' => 'PPN',
        ]);
    });

    it('can create tax setting PPH', function () {
        Livewire::test(CreateTaxSetting::class)
            ->fillForm([
                'name'           => 'PPH 2%',
                'rate'           => 2,
                'effective_date' => '2024-01-01',
                'status'         => true,
                'type'           => 'PPH',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tax_settings', [
            'name' => 'PPH 2%',
            'type' => 'PPH',
        ]);
    });

    it('validates required fields when creating tax setting', function () {
        Livewire::test(CreateTaxSetting::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['name', 'rate']);
    });

    it('can edit tax setting', function () {
        $taxSetting = TaxSetting::factory()->create([
            'name'           => 'PPN Test',
            'rate'           => 10,
            'effective_date' => now(),
            'status'         => true,
            'type'           => 'PPN',
        ]);

        Livewire::test(EditTaxSetting::class, ['record' => $taxSetting->getRouteKey()])
            ->fillForm([
                'name' => 'PPN 11% Updated',
                'rate' => 11,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('tax_settings', [
            'id'   => $taxSetting->id,
            'name' => 'PPN 11% Updated',
            'rate' => 11,
        ]);
    });

    it('can delete tax setting', function () {
        $taxSetting = TaxSetting::factory()->create([
            'name'           => 'Delete Me Tax',
            'rate'           => 5,
            'effective_date' => now(),
            'status'         => true,
            'type'           => 'CUSTOM',
        ]);

        Livewire::test(ListTaxSettings::class)
            ->callTableAction('delete', $taxSetting);

        $this->assertSoftDeleted('tax_settings', ['id' => $taxSetting->id]);
    });
});

// =====================================================================
// 8. WAREHOUSE CRUD
// =====================================================================

describe('Warehouse CRUD', function () {
    beforeEach(function () {
        $this->cabang = Cabang::factory()->create();
        $this->user = setupDataMasterUser($this->cabang->id);
    });

    it('can render list page', function () {
        Livewire::test(ListWarehouses::class)->assertSuccessful();
    });

    it('can create warehouse', function () {
        Livewire::test(CreateWarehouse::class)
            ->fillForm([
                'kode'             => 'GDG-TEST01',
                'name'             => 'Gudang Test Utama',
                'cabang_id'        => $this->cabang->id,
                'tipe'             => 'Besar',
                'location'         => 'Jl. Gudang No. 1',
                'telepon'          => '0214444444',
                'status'           => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('warehouses', [
            'kode' => 'GDG-TEST01',
            'name' => 'Gudang Test Utama',
        ]);
    });

    it('validates required fields when creating warehouse', function () {
        Livewire::test(CreateWarehouse::class)
            ->fillForm([])
            ->call('create')
            // cabang_id uses ->visible() condition so may not be validated
            ->assertHasFormErrors(['kode', 'name']);
    });

    it('can edit warehouse', function () {
        $warehouse = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'telepon'   => '0212345678',
        ]);

        Livewire::test(EditWarehouse::class, ['record' => $warehouse->getRouteKey()])
            ->fillForm([
                'name'     => 'Gudang Diperbarui',
                'location' => 'Jl. Baru Gudang No. 2',
                'telepon'  => '0219876543',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('warehouses', [
            'id'   => $warehouse->id,
            'name' => 'Gudang Diperbarui',
        ]);
    });

    it('can delete warehouse', function () {
        $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        Livewire::test(ListWarehouses::class)
            ->callTableAction('delete', $warehouse);

        $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    });
});

// =====================================================================
// 9. DRIVER CRUD
// =====================================================================

describe('Driver CRUD', function () {
    beforeEach(function () {
        $this->cabang = Cabang::factory()->create();
        $this->user = setupDataMasterUser($this->cabang->id);
    });

    it('can render list page', function () {
        Livewire::test(ListDrivers::class)->assertSuccessful();
    });

    it('can create driver', function () {
        Livewire::test(CreateDriver::class)
            ->fillForm([
                'name'      => 'Ahmad Sopir',
                'phone'     => '08199999999',
                'license'   => 'SIM A No. 1234567',
                'cabang_id' => $this->cabang->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('drivers', [
            'name'  => 'Ahmad Sopir',
            'phone' => '08199999999',
        ]);
    });

    it('validates required fields when creating driver', function () {
        Livewire::test(CreateDriver::class)
            ->fillForm([])
            ->call('create')
            // phone and license are optional; only name is required (cabang_id auto-set)
            ->assertHasFormErrors(['name']);
    });

    it('can edit driver', function () {
        $driver = Driver::factory()->create(['cabang_id' => $this->cabang->id]);

        Livewire::test(EditDriver::class, ['record' => $driver->getRouteKey()])
            ->fillForm([
                'name'  => 'Budi Sopir Updated',
                'phone' => '08177777777',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('drivers', [
            'id'   => $driver->id,
            'name' => 'Budi Sopir Updated',
        ]);
    });

    it('can delete driver', function () {
        $driver = Driver::factory()->create(['cabang_id' => $this->cabang->id]);

        Livewire::test(ListDrivers::class)
            ->callTableAction('delete', $driver);

        $this->assertSoftDeleted('drivers', ['id' => $driver->id]);
    });
});

// =====================================================================
// 10. VEHICLE CRUD
// =====================================================================

describe('Vehicle CRUD', function () {
    beforeEach(function () {
        $this->cabang = Cabang::factory()->create();
        $this->user = setupDataMasterUser($this->cabang->id);
    });

    it('can render list page', function () {
        Livewire::test(ListVehicles::class)->assertSuccessful();
    });

    it('can create vehicle', function () {
        Livewire::test(CreateVehicle::class)
            ->fillForm([
                'plate'     => 'B 1234 XYZ',
                'type'      => 'Truck',
                'capacity'  => '5 Ton',
                'cabang_id' => $this->cabang->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vehicles', [
            'plate' => 'B 1234 XYZ',
            'type'  => 'Truck',
        ]);
    });

    it('validates required fields when creating vehicle', function () {
        Livewire::test(CreateVehicle::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['plate', 'type', 'capacity']);
    });

    it('can edit vehicle', function () {
        $vehicle = Vehicle::factory()->create(['cabang_id' => $this->cabang->id]);

        Livewire::test(EditVehicle::class, ['record' => $vehicle->getRouteKey()])
            ->fillForm([
                'plate'    => 'D 9999 ABC',
                'capacity' => '3 Ton',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vehicles', [
            'id'    => $vehicle->id,
            'plate' => 'D 9999 ABC',
        ]);
    });

    it('can delete vehicle', function () {
        $vehicle = Vehicle::factory()->create(['cabang_id' => $this->cabang->id]);

        Livewire::test(ListVehicles::class)
            ->callTableAction('delete', $vehicle);

        $this->assertSoftDeleted('vehicles', ['id' => $vehicle->id]);
    });
});

// =====================================================================
// 11. RAK (SHELF/RACK) CRUD
// =====================================================================

describe('Rak (Shelf) CRUD', function () {
    beforeEach(function () {
        $this->cabang = Cabang::factory()->create();
        $this->user = setupDataMasterUser($this->cabang->id);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
    });

    it('can render list page', function () {
        Livewire::test(ListRaks::class)->assertSuccessful();
    });

    it('can create rak', function () {
        Livewire::test(CreateRak::class)
            ->fillForm([
                'name'         => 'Rak Besi A1',
                'code'         => 'RAK-A001',
                'warehouse_id' => $this->warehouse->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('raks', [
            'code' => 'RAK-A001',
            'name' => 'Rak Besi A1',
        ]);
    });

    it('validates required fields when creating rak', function () {
        Livewire::test(CreateRak::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['name', 'code', 'warehouse_id']);
    });

    it('can edit rak', function () {
        $rak = Rak::factory()->create(['warehouse_id' => $this->warehouse->id]);

        Livewire::test(EditRak::class, ['record' => $rak->getRouteKey()])
            ->fillForm([
                'name' => 'Rak Kayu B2 Updated',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('raks', [
            'id'   => $rak->id,
            'name' => 'Rak Kayu B2 Updated',
        ]);
    });

    it('can delete rak', function () {
        $rak = Rak::factory()->create(['warehouse_id' => $this->warehouse->id]);

        Livewire::test(ListRaks::class)
            ->callTableAction('delete', $rak);

        $this->assertSoftDeleted('raks', ['id' => $rak->id]);
    });
});

// =====================================================================
// 12. PRODUCT CRUD
// =====================================================================

describe('Product CRUD', function () {
    beforeEach(function () {
        $this->seed(ChartOfAccountSeeder::class);
        $this->user = setupDataMasterUser();
        $this->cabang = Cabang::factory()->create();
        $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->uom = UnitOfMeasure::factory()->create(['name' => 'Piece', 'abbreviation' => 'pcs']);
        $this->category = ProductCategory::factory()->create();
    });

    it('can render list page', function () {
        Livewire::test(ListProducts::class)->assertSuccessful();
    });

    it('can create product', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku'                => 'SKU-TEST-001',
                'name'               => 'Produk Test Baru',
                'cabang_id'          => $this->cabang->id,
                'product_category_id' => $this->category->id,
                'cost_price'         => 10000,
                'sell_price'         => 15000,
                'biaya'              => 0,
                'uom_id'             => $this->uom->id,
                'kode_merk'          => 'MERK-TEST',
                'tipe_pajak'         => 'Non Pajak',
                'pajak'              => 0,
            ])
            // Clear auto-generated empty repeater item then set valid conversion
            ->set('data.unitConversions', [['uom_id' => $this->uom->id, 'nilai_konversi' => 1]])
            // Ensure supplier repeater starts empty (optional field)
            ->set('data.suppliers', [])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'sku'  => 'SKU-TEST-001',
            'name' => 'Produk Test Baru',
        ]);
    });

    it('validates required fields when creating product', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['sku', 'name']);
    });

    it('can edit product', function () {
        $product = Product::factory()->create([
            'cabang_id'          => $this->cabang->id,
            'supplier_id'        => $this->supplier->id,
            'product_category_id' => $this->category->id,
            'uom_id'             => $this->uom->id,
        ]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm([
                'name'       => 'Produk Diperbarui',
                'sell_price' => 20000,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'id'         => $product->id,
            'name'       => 'Produk Diperbarui',
            'sell_price' => 20000,
        ]);
    });

    it('can delete product', function () {
        $product = Product::factory()->create([
            'cabang_id'          => $this->cabang->id,
            'supplier_id'        => $this->supplier->id,
            'product_category_id' => $this->category->id,
            'uom_id'             => $this->uom->id,
        ]);

        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $product);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    });
});

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
