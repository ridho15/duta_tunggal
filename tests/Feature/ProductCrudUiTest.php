<?php

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

    ChartOfAccount::firstOrCreate(
        ['code' => '2100.10'],
        ['name' => 'Pembelian Belum Tertagih', 'type' => 'Liability', 'is_active' => true]
    );

    $this->user = User::factory()->create();
    $this->cabang = Cabang::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->uom = UnitOfMeasure::factory()->create();
    $this->category = ProductCategory::factory()->create();

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
        'inventory_coa_id' => ChartOfAccount::where('code', '1140.01')->value('id'),
        'sales_coa_id' => ChartOfAccount::where('code', '4100.10')->value('id'),
        'sales_return_coa_id' => ChartOfAccount::where('code', '4120.10')->value('id'),
        'sales_discount_coa_id' => ChartOfAccount::where('code', '4110.10')->value('id'),
        'goods_delivery_coa_id' => ChartOfAccount::where('code', '1140.20')->value('id'),
        'cogs_coa_id' => ChartOfAccount::where('code', '5100.10')->value('id'),
        'purchase_return_coa_id' => ChartOfAccount::where('code', '5120.10')->value('id'),
        'unbilled_purchase_coa_id' => ChartOfAccount::where('code', '2100.10')->value('id'),
        'temporary_procurement_coa_id' => ChartOfAccount::where('code', '1400.01')->value('id'),
    ];
}

function productFormPayload(object $testCase, array $overrides = []): array
{
    return array_merge([
        'name' => 'Produk Form ' . uniqid(),
        'sku' => 'SKU-' . uniqid(),
        'supplier_id' => $testCase->supplier->id,
        'product_category_id' => $testCase->category->id,
        'cabang_id' => $testCase->cabang->id,
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
