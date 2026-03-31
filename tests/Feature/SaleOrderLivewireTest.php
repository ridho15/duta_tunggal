<?php

use App\Filament\Resources\SaleOrderResource\Pages\CreateSaleOrder;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Product;
use App\Models\TaxSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function grantSaleOrderLivewirePermissions(User $user): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([
        'create sales order',
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
        'create sales order',
        'view sales order',
        'view any sales order',
        'view any customer',
        'view any product',
        'view any warehouse',
    ]);
}

beforeEach(function () {
    $this->cabang = Cabang::factory()->create();
    $this->user = User::factory()->create(['cabang_id' => $this->cabang->id]);
    grantSaleOrderLivewirePermissions($this->user);
    $this->actingAs($this->user);

    $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 1]);
    $this->product = Product::factory()->create();

    TaxSetting::factory()->ppn()->create([
        'effective_date' => now()->subDay()->toDateString(),
        'status' => true,
    ]);
});

test('sale order item tax follows selected tipe pajak in the create flow', function () {
    $activeRate = TaxSetting::activeRate('PPN');

    Livewire::actingAs($this->user)
        ->test(CreateSaleOrder::class)
        ->assertSuccessful()
        ->assertFormExists()
        ->fillForm([
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'so_number' => 'SO-LIVE-001',
            'order_date' => now()->toDateString(),
            'delivery_date' => now()->addDays(3)->toDateString(),
            'shipped_to' => $this->customer->address,
            'total_amount' => 0,
            'tipe_pengiriman' => 'Kirim Langsung',
        ])
        ->set('data.saleOrderItem', [
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'unit_price' => 100000,
                'discount' => 0,
                'tax' => 0,
                'tipe_pajak' => 'None',
                'warehouseAllocations' => [
                    [
                        'warehouse_id' => $this->warehouse->id,
                        'quantity' => 1,
                    ],
                ],
            ],
        ])
        ->set('data.saleOrderItem.0.tipe_pajak', 'PPN Excluded')
        ->assertSet('data.saleOrderItem.0.tax', $activeRate)
        ->set('data.saleOrderItem.0.tax', 7)
        ->assertSet('data.saleOrderItem.0.tax', 7)
        ->set('data.saleOrderItem.0.tipe_pajak', 'None')
        ->assertSet('data.saleOrderItem.0.tax', 0)
        ->set('data.saleOrderItem.0.tipe_pajak', 'PPN Included')
        ->assertSet('data.saleOrderItem.0.tax', $activeRate);
});
