<?php

use App\Filament\Resources\PurchaseOrderResource\Pages\ViewPurchaseOrder;
use App\Models\Asset;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('shows success notification after confirming asset purchase order', function () {
    // Create test user with signature
    $user = User::factory()->create([
        'signature' => 'test-signature-path.png'
    ]);

    // Create test data
    $cabang = Cabang::factory()->create();
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $currency = Currency::factory()->create(['code' => 'IDR', 'name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    $category = ProductCategory::factory()->create(['cabang_id' => $cabang->id]);

    // Create COAs for asset
    $assetCoa = ChartOfAccount::factory()->create(['type' => 'Asset', 'code' => '1140.01']);
    $accumulatedDepreciationCoa = ChartOfAccount::factory()->create(['type' => 'Contra Asset', 'code' => '1140.02']);
    $depreciationExpenseCoa = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '6000.01']);

    // Create product with COA
    $product = Product::factory()->create([
        'cabang_id' => $cabang->id,
        'supplier_id' => $supplier->id,
        'product_category_id' => $category->id,
        'inventory_coa_id' => $assetCoa->id,
        'sales_coa_id' => ChartOfAccount::factory()->create(['code' => '4100.01'])->id,
        'temporary_procurement_coa_id' => ChartOfAccount::factory()->create(['code' => '1400.01'])->id,
        'unbilled_purchase_coa_id' => ChartOfAccount::factory()->create(['code' => '2190.10'])->id,
    ]);

    // Give user the required permissions
    $permissions = ['view purchase order', 'view any purchase order', 'update purchase order', 'view any purchase order item', 'response purchase order'];
    foreach ($permissions as $perm) {
        \Spatie\Permission\Models\Permission::firstOrCreate([
            'name' => $perm,
            'guard_name' => 'web'
        ]);
    }
    $user->givePermissionTo($permissions);

    test()->actingAs($user);

    // Create purchase order with asset
    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-ASSET-TEST-' . now()->format('YmdHis'),
        'order_date' => now()->format('Y-m-d'),
        'expected_date' => now()->addDays(7)->format('Y-m-d'),
        'warehouse_id' => $warehouse->id,
        'tempo_hutang' => 30,
        'note' => 'Test asset purchase order',
        'is_asset' => true,
        'status' => 'request_approval',
        'created_by' => $user->id,
    ]);

    // Create purchase order item
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 1000000,
        'currency_id' => $currency->id,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Inklusif',
    ]);

    // Test the action through Livewire
    Livewire::test(ViewPurchaseOrder::class, ['record' => $purchaseOrder->id])
        ->mountAction('konfirmasi')
        ->setActionData([
            'usage_date' => now()->format('Y-m-d'),
            'useful_life_years' => 5,
            'salvage_value' => 0,
            'asset_coa_id' => $assetCoa->id,
            'accumulated_depreciation_coa_id' => $accumulatedDepreciationCoa->id,
            'depreciation_expense_coa_id' => $depreciationExpenseCoa->id,
        ])
        ->callMountedAction()
        ->assertNotified('Purchase Order Berhasil Dikonfirmasi');

    // Verify asset was created
    expect(Asset::count())->toBe(1);
    $asset = Asset::first();
    expect($asset->name)->toBe($product->name);
    expect($asset->purchase_cost)->toBe('1000000.00');
    expect($asset->useful_life_years)->toBe(5);
});