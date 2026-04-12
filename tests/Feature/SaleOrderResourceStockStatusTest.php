<?php

use App\Filament\Resources\SaleOrderResource\Pages\ListSaleOrders;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantSaleOrderListPermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'view sales order',
        'view any sales order',
        'view any customer',
        'view any product',
        'view any warehouse',
    ] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'view sales order',
        'view any sales order',
        'view any customer',
        'view any product',
        'view any warehouse',
    ]);
}

function createSaleOrderForListStockStatusTest(User $user, string $status): SaleOrder
{
    $customer = Customer::factory()->create(['cabang_id' => $user->cabang_id]);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $user->cabang_id, 'status' => 1]);
    $category = ProductCategory::factory()->create();
    $uom = UnitOfMeasure::factory()->create();
    $product = Product::factory()->create([
        'cabang_id' => $user->cabang_id,
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_active' => true,
    ]);

    InventoryStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'rak_id' => null,
        'qty_available' => 0,
        'qty_reserved' => 0,
        'qty_min' => 0,
    ]);

    $saleOrder = SaleOrder::create([
        'so_number' => 'SO-LIST-' . uniqid(),
        'customer_id' => $customer->id,
        'cabang_id' => $user->cabang_id,
        'order_date' => now(),
        'delivery_date' => now()->addDays(3),
        'status' => $status,
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => $user->id,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $product->id,
        'quantity' => 5,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 0,
        'warehouse_id' => $warehouse->id,
        'rak_id' => null,
    ]);

    return $saleOrder->fresh(['saleOrderItem.product', 'saleOrderItem.warehouseAllocations']);
}

it('shows completed sale orders as neutral on the list page', function () {
    $cabang = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $cabang->id]);
    grantSaleOrderListPermissions($user);

    $saleOrder = createSaleOrderForListStockStatusTest($user, 'completed');

    Livewire::actingAs($user)
        ->test(ListSaleOrders::class)
        ->assertSuccessful()
        ->assertSee($saleOrder->so_number)
        ->assertSee('SELESAI')
        ->assertDontSee('STOK KURANG')
        ->assertDontSeeHtml('insufficient-stock-row');
});

it('still shows insufficient stock for unfinished sale orders on the list page', function () {
    $cabang = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $cabang->id]);
    grantSaleOrderListPermissions($user);

    $saleOrder = createSaleOrderForListStockStatusTest($user, 'draft');

    Livewire::actingAs($user)
        ->test(ListSaleOrders::class)
        ->assertSuccessful()
        ->assertSee($saleOrder->so_number)
        ->assertSee('STOK KURANG')
        ->assertSeeHtml('insufficient-stock-row');
});
