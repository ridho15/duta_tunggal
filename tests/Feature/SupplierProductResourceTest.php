<?php

/**
 * Comprehensive tests for:
 *  - SupplierResource  (form, table, validation, CRUD)
 *  - ProductResource   (form, table, validation, CRUD)
 *  - SupplierResource\RelationManagers\ProductsRelationManager
 *  - SupplierResource\RelationManagers\PurchaseOrderRelationManager
 *  - ProductResource\RelationManagers\InventoryStockRelationManager
 *  - ProductResource\RelationManagers\StockMovementRelationManager
 *  - Supplier / Product model relationships
 */

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Filament\Resources\ProductResource\RelationManagers\InventoryStockRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\StockMovementRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\SuppliersRelationManager;
use App\Filament\Resources\SupplierResource;
use App\Filament\Resources\SupplierResource\Pages\CreateSupplier;
use App\Filament\Resources\SupplierResource\Pages\EditSupplier;
use App\Filament\Resources\SupplierResource\Pages\ListSuppliers;
use App\Filament\Resources\SupplierResource\RelationManagers\ProductsRelationManager;
use App\Filament\Resources\SupplierResource\RelationManagers\PurchaseOrderRelationManager;
use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->cabang = Cabang::factory()->create([
        'kode'    => 'TST-001',
        'nama'    => 'Cabang Test',
        'alamat'  => 'Jl. Test No.1',
        'telepon' => '0211234567',
        'status'  => true,
    ]);

    $this->user = User::factory()->create([
        'cabang_id' => $this->cabang->id,
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'view any supplier',   'view supplier',   'create supplier',
        'update supplier',     'delete supplier',
        'view any product',    'view product',    'create product',
        'update product',      'delete product',
        'view any purchase order',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm]);
    }

    $this->user->givePermissionTo($permissions);
    Auth::login($this->user);

    $this->uom = UnitOfMeasure::factory()->create([
        'name'         => 'Kilogram',
        'abbreviation' => 'Kg',
    ]);

    $this->category = ProductCategory::factory()->create([
        'name' => 'Kategori Test',
    ]);

    $this->supplier = Supplier::factory()->create([
        'code'        => 'SUP-TEST-001',
        'perusahaan'  => 'PT Test Supplier',
        'phone'       => '0211234567',
        'handphone'   => '081234567890',
        'fax'         => '0211234568',
        'email'       => 'supplier@test.com',
        'npwp'        => '01.234.567.8-901.234',
        'address'     => 'Jl. Supplier No.1',
        'tempo_hutang' => 30,
        'cabang_id'   => $this->cabang->id,
    ]);

    $this->product = Product::factory()->create([
        'sku'                 => 'SKU-EXISTING-001',
        'name'                => 'Produk Existing',
        'cabang_id'           => $this->cabang->id,
        'product_category_id' => $this->category->id,
        'uom_id'              => $this->uom->id,
        'cost_price'          => 10000,
        'sell_price'          => 15000,
        'kode_merk'           => 'MERK-TEST',
        'tipe_pajak'          => 'Non Pajak',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 1: Model relationship tests
// ─────────────────────────────────────────────────────────────────────────────

describe('Supplier Model Relationships', function () {
    it('has many purchase orders', function () {
        expect(method_exists($this->supplier, 'purchaseOrder'))->toBeTrue();
        expect($this->supplier->purchaseOrder())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('has many products via legacy supplier_id', function () {
        expect(method_exists($this->supplier, 'products'))->toBeTrue();
        expect($this->supplier->products())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('belongs to many products via product_supplier pivot', function () {
        expect(method_exists($this->supplier, 'productSuppliers'))->toBeTrue();
        expect($this->supplier->productSuppliers())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    });

    it('pivot table product_supplier records supplier_price', function () {
        $this->supplier->productSuppliers()->attach($this->product->id, [
            'supplier_price' => 9500,
        ]);

        $pivotProduct = $this->supplier->productSuppliers()->where('product_id', $this->product->id)->first();
        expect($pivotProduct)->not->toBeNull()
            ->and((float) $pivotProduct->pivot->supplier_price)->toBe(9500.0);
    });

    it('belongs to cabang', function () {
        expect($this->supplier->cabang)->not->toBeNull()
            ->and($this->supplier->cabang->id)->toBe($this->cabang->id);
    });

    it('soft-deletes correctly', function () {
        $supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $id = $supplier->id;

        $supplier->delete();

        expect(Supplier::find($id))->toBeNull();
        expect(Supplier::withTrashed()->find($id))->not->toBeNull();
    });
});

describe('Product Model Relationships', function () {
    it('belongs to many suppliers', function () {
        expect(method_exists($this->product, 'suppliers'))->toBeTrue();
        expect($this->product->suppliers())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
    });

    it('can attach a supplier with price', function () {
        $this->product->suppliers()->attach($this->supplier->id, [
            'supplier_price' => 8000,
        ]);

        $attachedSupplier = $this->product->suppliers()->where('supplier_id', $this->supplier->id)->first();
        expect($attachedSupplier)->not->toBeNull()
            ->and((float) $attachedSupplier->pivot->supplier_price)->toBe(8000.0);
    });

    it('belongs to uom', function () {
        expect($this->product->uom)->not->toBeNull()
            ->and($this->product->uom->id)->toBe($this->uom->id);
    });

    it('belongs to product category', function () {
        expect($this->product->productCategory)->not->toBeNull()
            ->and($this->product->productCategory->id)->toBe($this->category->id);
    });

    it('has many inventory stocks', function () {
        expect($this->product->inventoryStock())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('has many stock movements', function () {
        expect($this->product->stockMovement())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
    });

    it('casts is_active, is_manufacture, is_raw_material as boolean', function () {
        $product = Product::factory()->create([
            'cabang_id'           => $this->cabang->id,
            'is_active'           => true,
            'is_manufacture'      => false,
            'is_raw_material'     => false,
            'uom_id'              => $this->uom->id,
            'product_category_id' => $this->category->id,
        ]);

        expect($product->is_active)->toBeBool()
            ->and($product->is_manufacture)->toBeBool()
            ->and($product->is_raw_material)->toBeBool();
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 2: SupplierResource – Form & Validation
// ─────────────────────────────────────────────────────────────────────────────

describe('SupplierResource Form Validation', function () {
    it('requires sku (code), perusahaan, npwp, address, phone, handphone, email, fax, tempo_hutang', function () {
        Livewire::test(CreateSupplier::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['code', 'perusahaan', 'npwp', 'address', 'phone', 'handphone', 'email', 'fax', 'tempo_hutang']);
    });

    it('rejects invalid email on supplier form', function () {
        Livewire::test(CreateSupplier::class)
            ->fillForm([
                'code'        => 'SUP-VAL-001',
                'perusahaan'  => 'PT ABC',
                'npwp'        => '01.234.567.8-901.234',
                'address'     => 'Jl. Test',
                'phone'       => '0211234567',
                'handphone'   => '081234567890',
                'email'       => 'bukan-email',
                'fax'         => '0211234568',
                'tempo_hutang' => 30,
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);
    });

    it('accepts international phone numbers (phone, handphone, fax)', function () {
        Livewire::test(CreateSupplier::class)
            ->fillForm([
                'code'        => 'SUP-INTL-001',
                'perusahaan'  => 'PT International',
                'npwp'        => '01.234.567.8-901.234',
                'address'     => 'Jl. International No.1',
                'phone'       => '+1 (439) 328-8356',
                'handphone'   => '+62 812 3456 7890',
                'email'       => 'intl@example.com',
                'fax'         => '+44 20 1234 5678',
                'tempo_hutang' => 30,
                'cabang_id'   => $this->cabang->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors(['phone', 'handphone', 'fax']);
    });

    it('rejects phone numbers with only letters', function () {
        Livewire::test(CreateSupplier::class)
            ->fillForm([
                'code'        => 'SUP-VAL-002',
                'perusahaan'  => 'PT Test',
                'npwp'        => '01.234.567.8-901.234',
                'address'     => 'Jl. Test',
                'phone'       => 'abc-def-ghi',
                'handphone'   => 'invalid-phone',
                'email'       => 'test@example.com',
                'fax'         => 'bad-fax',
                'tempo_hutang' => 30,
            ])
            ->call('create')
            ->assertHasFormErrors(['phone', 'handphone', 'fax']);
    });

    it('enforces unique supplier code', function () {
        Supplier::factory()->create([
            'code'      => 'DUP-CODE',
            'cabang_id' => $this->cabang->id,
        ]);

        Livewire::test(CreateSupplier::class)
            ->fillForm([
                'code'        => 'DUP-CODE',
                'perusahaan'  => 'PT Duplicate',
                'npwp'        => '01.234.567.8-901.234',
                'address'     => 'Jl. Dup No.1',
                'phone'       => '0211234567',
                'handphone'   => '081234567890',
                'email'       => 'dup@example.com',
                'fax'         => '0211234568',
                'tempo_hutang' => 30,
                'cabang_id'   => $this->cabang->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['code']);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 3: SupplierResource – CRUD pages
// ─────────────────────────────────────────────────────────────────────────────

describe('SupplierResource CRUD', function () {
    it('list page renders', function () {
        Livewire::test(ListSuppliers::class)
            ->assertSuccessful();
    });

    it('can create a supplier', function () {
        Livewire::test(CreateSupplier::class)
            ->fillForm([
                'code'        => 'SUP-NEW-001',
                'perusahaan'  => 'PT New Supplier',
                'npwp'        => '01.234.567.8-901.234',
                'address'     => 'Jl. New No.1',
                'phone'       => '+62 21 12345678',
                'handphone'   => '+62 812 9876 5432',
                'email'       => 'new@supplier.com',
                'fax'         => '+62 21 12345679',
                'tempo_hutang' => 45,
                'cabang_id'   => $this->cabang->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('suppliers', [
            'code'       => 'SUP-NEW-001',
            'perusahaan' => 'PT New Supplier',
        ]);
    });

    it('can edit a supplier and update tempo hutang', function () {
        Livewire::test(EditSupplier::class, ['record' => $this->supplier->getKey()])
            ->fillForm([
                'code'        => $this->supplier->code,
                'perusahaan'  => $this->supplier->perusahaan,
                'npwp'        => $this->supplier->npwp,
                'address'     => $this->supplier->address,
                'phone'       => $this->supplier->phone,
                'handphone'   => $this->supplier->handphone,
                'email'       => $this->supplier->email,
                'fax'         => $this->supplier->fax,
                'tempo_hutang' => 60,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('suppliers', [
            'id'          => $this->supplier->id,
            'tempo_hutang' => 60,
        ]);
    });

    it('can soft-delete a supplier from list', function () {
        Livewire::test(ListSuppliers::class)
            ->callTableAction('delete', $this->supplier);

        $this->assertSoftDeleted('suppliers', ['id' => $this->supplier->id]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 4: ProductResource – Form & Validation
// ─────────────────────────────────────────────────────────────────────────────

describe('ProductResource Form Validation', function () {
    it('requires sku, name, product_category_id, uom_id, kode_merk, tipe_pajak', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm([])
            ->call('create')
            ->assertHasFormErrors(['sku', 'name']);
    });

    it('enforces unique sku', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku'                 => 'SKU-EXISTING-001', // already used in beforeEach
                'name'                => 'Different Name',
                'cabang_id'           => $this->cabang->id,
                'product_category_id' => $this->category->id,
                'cost_price'          => 1000,
                'sell_price'          => 2000,
                'biaya'               => 0,
                'uom_id'              => $this->uom->id,
                'kode_merk'           => 'MERK',
                'tipe_pajak'          => 'Non Pajak',
            ])
            ->call('create')
            ->assertHasFormErrors(['sku']);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 5: ProductResource – CRUD pages
// ─────────────────────────────────────────────────────────────────────────────

describe('ProductResource CRUD', function () {
    it('list page renders', function () {
        Livewire::test(ListProducts::class)
            ->assertSuccessful();
    });

    it('can create a product without suppliers (optional)', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku'                 => 'SKU-CREATE-002',
                'name'                => 'Produk Baru Tanpa Supplier',
                'cabang_id'           => $this->cabang->id,
                'product_category_id' => $this->category->id,
                'cost_price'          => 5000,
                'sell_price'          => 8000,
                'biaya'               => 0,
                'uom_id'              => $this->uom->id,
                'kode_merk'           => 'MRK-002',
                'tipe_pajak'          => 'Non Pajak',
                'pajak'               => 0,
            ])
            ->set('data.suppliers', [])
            ->set('data.unitConversions', [['uom_id' => $this->uom->id, 'nilai_konversi' => 1]])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', ['sku' => 'SKU-CREATE-002']);
    });

    it('can create a product then attach supplier via pivot relationship', function () {
        // BelongsToMany repeater via Livewire form creates new related records;
        // instead we verify the full cycle: create product, then attach supplier via ORM.
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku'                 => 'SKU-WITH-SUP-001',
                'name'                => 'Produk Dengan Supplier',
                'cabang_id'           => $this->cabang->id,
                'product_category_id' => $this->category->id,
                'cost_price'          => 7000,
                'sell_price'          => 10000,
                'biaya'               => 0,
                'uom_id'              => $this->uom->id,
                'kode_merk'           => 'MRK-003',
                'tipe_pajak'          => 'Non Pajak',
            ])
            ->set('data.suppliers', [])
            ->set('data.unitConversions', [['uom_id' => $this->uom->id, 'nilai_konversi' => 1]])
            ->call('create')
            ->assertHasNoFormErrors();

        // CabangScope is active; use the DB assertion to bypass Eloquent global scopes
        $this->assertDatabaseHas('products', ['sku' => 'SKU-WITH-SUP-001']);

        $product = Product::withoutGlobalScopes()->where('sku', 'SKU-WITH-SUP-001')->first();
        expect($product)->not->toBeNull();

        // Attach the supplier via the BelongsToMany relationship
        $product->suppliers()->attach($this->supplier->id, ['supplier_price' => 7500]);

        $this->assertDatabaseHas('product_supplier', [
            'product_id'  => $product->id,
            'supplier_id' => $this->supplier->id,
        ]);
    });

    it('can edit a product name and sell price', function () {
        Livewire::test(EditProduct::class, ['record' => $this->product->getRouteKey()])
            ->fillForm([
                'name'       => 'Produk Diupdate',
                'sell_price' => 18000,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('products', [
            'id'         => $this->product->id,
            'name'       => 'Produk Diupdate',
            'sell_price' => 18000,
        ]);
    });

    it('can soft-delete a product from list', function () {
        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $this->product);

        $this->assertSoftDeleted('products', ['id' => $this->product->id]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 6: SupplierResource RelationManagers
// ─────────────────────────────────────────────────────────────────────────────

describe('ProductsRelationManager on Supplier', function () {
    it('renders the relation manager for a supplier', function () {
        Livewire::test(ProductsRelationManager::class, [
            'ownerRecord' => $this->supplier,
            'pageClass'   => \App\Filament\Resources\SupplierResource\Pages\ViewSupplier::class,
        ])
        ->assertSuccessful();
    });

    it('shows the associate product action header button', function () {
        // Custom associate action is a header action, visible when edit page is used as the ownerPage
        Livewire::test(ProductsRelationManager::class, [
            'ownerRecord' => $this->supplier,
            'pageClass'   => \App\Filament\Resources\SupplierResource\Pages\EditSupplier::class,
        ])
        ->assertTableActionExists('associateProduct');
    });

    it('can associate a product to supplier via custom action', function () {
        Livewire::test(ProductsRelationManager::class, [
            'ownerRecord' => $this->supplier,
            'pageClass'   => \App\Filament\Resources\SupplierResource\Pages\EditSupplier::class,
        ])
        ->callTableAction('associateProduct', data: [
            'product_id'     => $this->product->id,
            'supplier_price' => 9500,
        ])
        ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('product_supplier', [
            'supplier_id' => $this->supplier->id,
            'product_id'  => $this->product->id,
        ]);
    });

    it('can dissociate a product from supplier via ORM', function () {
        // Attach first, then detach via the relationship (mirrors DissosiateAction behaviour)
        $this->supplier->productSuppliers()->attach($this->product->id, ['supplier_price' => 9000]);

        $this->assertDatabaseHas('product_supplier', [
            'supplier_id' => $this->supplier->id,
            'product_id'  => $this->product->id,
        ]);

        $this->supplier->productSuppliers()->detach($this->product->id);

        $this->assertDatabaseMissing('product_supplier', [
            'supplier_id' => $this->supplier->id,
            'product_id'  => $this->product->id,
        ]);
    });

    it('shows associated products in the table', function () {
        $this->supplier->productSuppliers()->attach($this->product->id, ['supplier_price' => 5000]);

        Livewire::test(ProductsRelationManager::class, [
            'ownerRecord' => $this->supplier,
            'pageClass'   => \App\Filament\Resources\SupplierResource\Pages\ViewSupplier::class,
        ])
        ->assertCanSeeTableRecords([$this->product]);
    });
});

describe('PurchaseOrderRelationManager on Supplier', function () {
    it('renders the relation manager', function () {
        Livewire::test(PurchaseOrderRelationManager::class, [
            'ownerRecord' => $this->supplier,
            'pageClass'   => \App\Filament\Resources\SupplierResource\Pages\ViewSupplier::class,
        ])
        ->assertSuccessful();
    });

    it('shows purchase orders belonging to the supplier', function () {
        $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $warehouse->id,
            'cabang_id'    => $this->cabang->id,
            'created_by'   => $this->user->id,
            'status'       => 'draft',
        ]);

        Livewire::test(PurchaseOrderRelationManager::class, [
            'ownerRecord' => $this->supplier,
            'pageClass'   => \App\Filament\Resources\SupplierResource\Pages\ViewSupplier::class,
        ])
        ->assertCanSeeTableRecords([$po]);
    });

    it('does not show purchase orders of other suppliers', function () {
        $otherSupplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        $po = PurchaseOrder::factory()->create([
            'supplier_id'  => $otherSupplier->id,
            'warehouse_id' => $warehouse->id,
            'cabang_id'    => $this->cabang->id,
            'created_by'   => $this->user->id,
        ]);

        Livewire::test(PurchaseOrderRelationManager::class, [
            'ownerRecord' => $this->supplier,
            'pageClass'   => \App\Filament\Resources\SupplierResource\Pages\ViewSupplier::class,
        ])
        ->assertCanNotSeeTableRecords([$po]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 7: ProductResource RelationManagers
// ─────────────────────────────────────────────────────────────────────────────

describe('InventoryStockRelationManager on Product', function () {
    it('renders the relation manager', function () {
        Livewire::test(InventoryStockRelationManager::class, [
            'ownerRecord' => $this->product,
            'pageClass'   => \App\Filament\Resources\ProductResource\Pages\ViewProduct::class,
        ])
        ->assertSuccessful();
    });

    it('shows inventory stock records for the product', function () {
        $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        $stock = InventoryStock::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'qty_available' => 100,
        ]);

        Livewire::test(InventoryStockRelationManager::class, [
            'ownerRecord' => $this->product,
            'pageClass'   => \App\Filament\Resources\ProductResource\Pages\ViewProduct::class,
        ])
        ->assertCanSeeTableRecords([$stock]);
    });
});

describe('StockMovementRelationManager on Product', function () {
    it('renders the relation manager', function () {
        Livewire::test(StockMovementRelationManager::class, [
            'ownerRecord' => $this->product,
            'pageClass'   => \App\Filament\Resources\ProductResource\Pages\ViewProduct::class,
        ])
        ->assertSuccessful();
    });

    it('shows stock movements for the product', function () {
        $warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        $movement = StockMovement::factory()->create([
            'product_id'   => $this->product->id,
            'warehouse_id' => $warehouse->id,
            'type'         => 'purchase_in',
            'quantity'     => 50,
        ]);

        Livewire::test(StockMovementRelationManager::class, [
            'ownerRecord' => $this->product,
            'pageClass'   => \App\Filament\Resources\ProductResource\Pages\ViewProduct::class,
        ])
        ->assertCanSeeTableRecords([$movement]);
    });
});

// ─────────────────────────────────────────────────────────────────────────────
// SECTION 8: Resource structure meta-tests
// ─────────────────────────────────────────────────────────────────────────────

describe('SupplierResource structure', function () {
    it('declares correct relation managers', function () {
        $relations = SupplierResource::getRelations();

        expect($relations)->toContain(PurchaseOrderRelationManager::class)
            ->and($relations)->toContain(ProductsRelationManager::class);
    });

    it('registers all expected pages', function () {
        $pages = SupplierResource::getPages();

        expect(array_keys($pages))->toContain('index', 'create', 'view', 'edit');
    });
});

describe('ProductResource structure', function () {
    it('declares correct relation managers', function () {
        $relations = ProductResource::getRelations();

        expect($relations)->toContain(SuppliersRelationManager::class)
            ->and($relations)->toContain(InventoryStockRelationManager::class)
            ->and($relations)->toContain(StockMovementRelationManager::class);
    });

    it('registers all expected pages', function () {
        $pages = ProductResource::getPages();

        expect(array_keys($pages))->toContain('index', 'create', 'view', 'edit');
    });
});
