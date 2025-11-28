<?php

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ViewProduct;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ChartOfAccountSeeder::class);

    $this->user = User::factory()->create();
    $this->cabang = Cabang::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->uom = UnitOfMeasure::factory()->create();
    $this->category = ProductCategory::factory()->create([
        'cabang_id' => $this->cabang->id,
    ]);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $permissions = [
        'view any product',
        'view product',
        'create product',
        'update product',
        'view any cabang',
        'view cabang',
        'view any supplier',
        'view supplier',
        'view any product category',
        'view product category',
        'view any unit of measure',
        'view unit of measure',
        'view any chart of account',
        'view chart of account',
    ];

    foreach ($permissions as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $this->user->givePermissionTo($permissions);

    Auth::login($this->user);
});

function productDefaultAccounts(): array
{
    return [
        'inventory_coa_id' => ChartOfAccount::where('code', '1140.10')->value('id'),
        'sales_coa_id' => ChartOfAccount::where('code', '4100.10')->value('id'),
        'sales_return_coa_id' => ChartOfAccount::where('code', '4120.10')->value('id'),
        'sales_discount_coa_id' => ChartOfAccount::where('code', '4110.10')->value('id'),
        'goods_delivery_coa_id' => ChartOfAccount::where('code', '1140.20')->value('id'),
        'cogs_coa_id' => ChartOfAccount::where('code', '5100.10')->value('id'),
        'purchase_return_coa_id' => ChartOfAccount::where('code', '5120.10')->value('id'),
        'unbilled_purchase_coa_id' => ChartOfAccount::where('code', '2190.10')->value('id'),
        'temporary_procurement_coa_id' => ChartOfAccount::where('code', '1400.01')->value('id'),
    ];
}

function productFormPayload(object $testCase, array $overrides = []): array
{
    return array_merge([
        'name' => 'Produk Form ' . uniqid(),
        'sku' => 'SKU-' . uniqid(),
        'cabang_id' => $testCase->cabang->id,
        'supplier_id' => $testCase->supplier->id,
        'product_category_id' => $testCase->category->id,
        'cost_price' => '15000',
        'sell_price' => '25000',
        'biaya' => '500',
        'harga_batas' => '0',
        'item_value' => '0',
        'tipe_pajak' => 'Inklusif', // Match form default
        'pajak' => '0',
        'jumlah_kelipatan_gudang_besar' => '0',
        'jumlah_jual_kategori_banyak' => '0',
        'kode_merk' => 'MRK-' . uniqid(),
        'uom_id' => $testCase->uom->id,
        'description' => 'Deskripsi produk pengujian',
        'is_manufacture' => false,
        'is_raw_material' => false,
        'is_active' => true,
    ], $overrides);
}

it('creates a product through the Filament create page', function () {
    // Test with minimal required fields only
    $minimalData = [
        'sku' => 'SKU-MIN-' . uniqid(),
        'name' => 'Minimal Product',
        'cabang_id' => $this->cabang->id,
        'product_category_id' => $this->category->id,
        'cost_price' => '10000',
        'sell_price' => '15000',
        'biaya' => '500',
        'kode_merk' => 'MRK-MIN-' . uniqid(),
        'uom_id' => $this->uom->id,
    ];

    Livewire::test(CreateProduct::class)
        ->fillForm($minimalData)
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::where('sku', $minimalData['sku'])->first();

    expect($product)
        ->not->toBeNull()
        ->and($product->name)->toBe('Minimal Product');
});

it('edits a product through the Filament edit page', function () {
    $initialAccounts = productDefaultAccounts();

    $existingProduct = Product::query()->create(array_merge(
        productFormPayload($this, [
            'name' => 'Produk Awal',
            'sku' => 'SKU-AWAL-' . uniqid(),
        ]),
        $initialAccounts,
    ));

    $customAccounts = productDefaultAccounts(); // Use existing accounts instead of non-existent ones

    $updateData = productFormPayload($this, [
        'name' => 'Produk Setelah Edit',
        'sku' => 'SKU-EDIT-' . uniqid(),
        'description' => 'Produk telah diperbarui',
        'tipe_pajak' => 'Eksklusif',
        'pajak' => '11',
    ]);

    Livewire::test(EditProduct::class, ['record' => $existingProduct->getKey()])
        ->fillForm(array_merge($updateData, $customAccounts))
        ->call('save')
        ->assertHasNoFormErrors();

    $existingProduct->refresh();

    expect($existingProduct->name)
        ->toBe('Produk Setelah Edit')
        ->and($existingProduct->sku)->toBe($updateData['sku'])
        ->and($existingProduct->pajak)->toBe('11.00');
});

it('displays product details on the Filament view page', function () {
    $product = Product::query()->create(array_merge(
        productFormPayload($this, [
            'name' => 'Produk View Lengkap',
            'sku' => 'SKU-VIEW-' . uniqid(),
            'description' => 'Detail produk lengkap',
        ]),
        productDefaultAccounts(),
    ));

    Livewire::test(ViewProduct::class, ['record' => $product->getKey()])
        ->assertFormExists()
        ->assertFormSet([
            'name' => $product->name,
            'sku' => $product->sku,
            'cabang_id' => $product->cabang_id,
            'product_category_id' => $product->product_category_id,
            'inventory_coa_id' => $product->inventory_coa_id,
            'sales_coa_id' => $product->sales_coa_id,
            'sales_return_coa_id' => $product->sales_return_coa_id,
        ]);
});

it('validates SKU uniqueness', function () {
    $existingProduct = Product::query()->create(array_merge(
        productFormPayload($this, [
            'name' => 'Produk Existing',
            'sku' => 'SKU-UNIQUE-TEST',
        ]),
        productDefaultAccounts(),
    ));

    $formData = productFormPayload($this, [
        'name' => 'Produk Duplicate SKU',
        'sku' => 'SKU-UNIQUE-TEST', // Same SKU
    ]);

    Livewire::test(CreateProduct::class)
        ->fillForm(array_merge($formData, productDefaultAccounts()))
        ->call('create')
        ->assertHasFormErrors(['sku']);
});

it('updates product pricing', function () {
    $existingProduct = Product::query()->create(array_merge(
        productFormPayload($this, [
            'name' => 'Produk Pricing Test',
            'sku' => 'SKU-PRICE-' . uniqid(),
            'sell_price' => '25000',
            'cost_price' => '15000',
        ]),
        productDefaultAccounts(),
    ));

    $updateData = productFormPayload($this, [
        'name' => 'Produk Pricing Updated',
        'sku' => 'SKU-PRICE-UPDATED-' . uniqid(),
        'sell_price' => '30000',
        'cost_price' => '18000',
    ]);

    Livewire::test(EditProduct::class, ['record' => $existingProduct->getKey()])
        ->fillForm(array_merge($updateData, productDefaultAccounts()))
        ->call('save')
        ->assertHasNoFormErrors();

    $existingProduct->refresh();

    expect($existingProduct->sell_price)->toBe('30000.00')
        ->and($existingProduct->cost_price)->toBe('18000.00');
});

it('tests UOM conversions functionality', function () {
    $uom2 = UnitOfMeasure::factory()->create(['name' => 'Box', 'abbreviation' => 'box']);
    $uom3 = UnitOfMeasure::factory()->create(['name' => 'Pack', 'abbreviation' => 'pack']);

    // Create product without using factory to avoid auto-generated unit conversions
    $product = Product::query()->create(array_merge(
        productFormPayload($this, [
            'name' => 'Produk dengan Konversi',
            'sku' => 'SKU-CONV-' . uniqid(),
        ]),
        productDefaultAccounts(),
    ));

    // Add unit conversions
    $product->unitConversions()->create([
        'uom_id' => $uom2->id,
        'nilai_konversi' => 12,
    ]);

    $product->unitConversions()->create([
        'uom_id' => $uom3->id,
        'nilai_konversi' => 24,
    ]);

    // Refresh product with relationships
    $product->refresh();
    $product->load('unitConversions');

    expect($product->unitConversions)->toHaveCount(2)
        ->and($product->unitConversions->first()->uom_id)->toBe($uom2->id)
        ->and($product->unitConversions->first()->nilai_konversi)->toBe('12.00')
        ->and($product->unitConversions->last()->uom_id)->toBe($uom3->id)
        ->and($product->unitConversions->last()->nilai_konversi)->toBe('24.00');

    // Test updating unit conversion
    $conversion = $product->unitConversions->first();
    $conversion->update(['nilai_konversi' => 15]);

    expect($conversion->fresh()->nilai_konversi)->toBe('15.00');
});
