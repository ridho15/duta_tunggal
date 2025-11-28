<?php

use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use function Pest\Laravel\actingAs;

describe('Product account mapping form defaults', function () {
    uses(RefreshDatabase::class);

    beforeEach(function () {
        $this->seed(ChartOfAccountSeeder::class);

        /** @var User $user */
        $user = User::factory()->create();
        $this->user = $user;

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view any product',
            'view product',
            'create product',
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

        $user->givePermissionTo($permissions);

        $this->cabang = Cabang::factory()->create();
        $this->uom = UnitOfMeasure::factory()->create();
        $this->category = ProductCategory::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        actingAs($user);
    });

    it('applies default coa values and persists manual overrides through the create form', function () {
        $defaultAccountState = [
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.10')->value('id'),
            'sales_coa_id' => ChartOfAccount::where('code', '4100.10')->value('id'),
            'sales_return_coa_id' => ChartOfAccount::where('code', '4120.10')->value('id'),
            'sales_discount_coa_id' => ChartOfAccount::where('code', '4110.10')->value('id'),
            'goods_delivery_coa_id' => ChartOfAccount::where('code', '1140.20')->value('id'),
            'cogs_coa_id' => ChartOfAccount::where('code', '5100.10')->value('id'),
            'purchase_return_coa_id' => ChartOfAccount::where('code', '5120.10')->value('id'),
            'unbilled_purchase_coa_id' => ChartOfAccount::where('code', '2190.10')->value('id'),
        ];

        $baseFormData = [
            'name' => 'Produk Default COA',
            'sku' => 'SKU-DEFAULT-' . uniqid(),
            'cabang_id' => $this->cabang->id,
            'supplier_id' => null,
            'product_category_id' => $this->category->id,
            'cost_price' => 12345,
            'sell_price' => 23456,
            'biaya' => 1000,
            'harga_batas' => 0,
            'item_value' => 0,
            'tipe_pajak' => 'Non Pajak',
            'pajak' => 0,
            'jumlah_kelipatan_gudang_besar' => 0,
            'jumlah_jual_kategori_banyak' => 0,
            'kode_merk' => 'MRK-DEF',
            'uom_id' => $this->uom->id,
            'description' => 'Produk default mapping',
            'unitConversions' => [],
            'is_manufacture' => false,
            'is_raw_material' => false,
            'is_active' => true,
        ];

        $component = Livewire::test(CreateProduct::class);

        $component->assertFormSet($defaultAccountState);

        $component
            ->fillForm(array_merge($baseFormData, $defaultAccountState))
            ->call('create')
            ->assertHasNoFormErrors();

        $defaultProduct = Product::latest('id')->first();

        expect($defaultProduct)
            ->not->toBeNull()
            ->and($defaultProduct->inventory_coa_id)->toBe($defaultAccountState['inventory_coa_id'])
            ->and($defaultProduct->sales_coa_id)->toBe($defaultAccountState['sales_coa_id'])
            ->and($defaultProduct->sales_return_coa_id)->toBe($defaultAccountState['sales_return_coa_id'])
            ->and($defaultProduct->sales_discount_coa_id)->toBe($defaultAccountState['sales_discount_coa_id'])
            ->and($defaultProduct->goods_delivery_coa_id)->toBe($defaultAccountState['goods_delivery_coa_id'])
            ->and($defaultProduct->cogs_coa_id)->toBe($defaultAccountState['cogs_coa_id'])
            ->and($defaultProduct->purchase_return_coa_id)->toBe($defaultAccountState['purchase_return_coa_id'])
            ->and($defaultProduct->unbilled_purchase_coa_id)->toBe($defaultAccountState['unbilled_purchase_coa_id']);

        $customAccounts = [
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.02')->value('id'),
            'sales_coa_id' => ChartOfAccount::where('code', '4100')->value('id'),
            'sales_return_coa_id' => ChartOfAccount::where('code', '4120')->value('id'),
            'sales_discount_coa_id' => ChartOfAccount::where('code', '4110')->value('id'),
            'goods_delivery_coa_id' => ChartOfAccount::where('code', '1180.01')->value('id'),
            'cogs_coa_id' => ChartOfAccount::where('code', '5100')->value('id'),
            'purchase_return_coa_id' => ChartOfAccount::where('code', '5120')->value('id'),
            'unbilled_purchase_coa_id' => ChartOfAccount::where('code', '2190')->value('id'),
        ];

        $overrideFormData = array_merge($baseFormData, [
            'name' => 'Produk Override COA',
            'sku' => 'SKU-OVR-' . uniqid(),
        ], $customAccounts);

        Livewire::test(CreateProduct::class)
            ->fillForm($overrideFormData)
            ->call('create')
            ->assertHasNoFormErrors();

        $overrideProduct = Product::latest('id')->first();

        expect($overrideProduct)
            ->not->toBeNull()
            ->and($overrideProduct->inventory_coa_id)->toBe($customAccounts['inventory_coa_id'])
            ->and($overrideProduct->sales_coa_id)->toBe($customAccounts['sales_coa_id'])
            ->and($overrideProduct->sales_return_coa_id)->toBe($customAccounts['sales_return_coa_id'])
            ->and($overrideProduct->sales_discount_coa_id)->toBe($customAccounts['sales_discount_coa_id'])
            ->and($overrideProduct->goods_delivery_coa_id)->toBe($customAccounts['goods_delivery_coa_id'])
            ->and($overrideProduct->cogs_coa_id)->toBe($customAccounts['cogs_coa_id'])
            ->and($overrideProduct->purchase_return_coa_id)->toBe($customAccounts['purchase_return_coa_id'])
            ->and($overrideProduct->unbilled_purchase_coa_id)->toBe($customAccounts['unbilled_purchase_coa_id']);
    });
});
