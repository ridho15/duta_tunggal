<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrderItem;
use App\Models\SaleOrder;
use App\Models\TaxSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

function seedSaleOrderPostingCoas(): void
{
    ChartOfAccount::firstOrCreate(['code' => '1120'], ['name' => 'Piutang Dagang', 'type' => 'Asset']);
    ChartOfAccount::firstOrCreate(['code' => '4000'], ['name' => 'Penjualan', 'type' => 'Revenue']);
    ChartOfAccount::firstOrCreate(['code' => '4111'], ['name' => 'Penjualan Jasa', 'type' => 'Revenue']);
    ChartOfAccount::firstOrCreate(['code' => '2120.06'], ['name' => 'PPN Keluaran', 'type' => 'Liability']);
    ChartOfAccount::firstOrCreate(['code' => '1140.20'], ['name' => 'Barang Terkirim', 'type' => 'Asset']);
    ChartOfAccount::firstOrCreate(['code' => '5100.10'], ['name' => 'HPP Barang', 'type' => 'Expense']);
    ChartOfAccount::firstOrCreate(['code' => '4100.01'], ['name' => 'Diskon Penjualan', 'type' => 'Expense']);
    ChartOfAccount::firstOrCreate(['code' => '6100.02'], ['name' => 'Biaya Pengiriman', 'type' => 'Expense']);
}

beforeEach(function () {
    $this->cabang = Cabang::factory()->create();
    $this->user = User::factory()->create(['cabang_id' => $this->cabang->id]);
    grantSaleOrderLivewirePermissions($this->user);
    $this->actingAs($this->user);

    seedSaleOrderPostingCoas();

    $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
    $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id, 'status' => 1]);
    $this->product = Product::factory()->create([
        'cost_price' => 50000,
        'cogs_coa_id' => ChartOfAccount::where('code', '5100.10')->value('id'),
        'goods_delivery_coa_id' => ChartOfAccount::where('code', '1140.20')->value('id'),
        'sales_coa_id' => ChartOfAccount::where('code', '4000')->value('id'),
    ]);

    TaxSetting::factory()->ppn()->create([
        'effective_date' => now()->subDay()->toDateString(),
        'status' => true,
    ]);
});

test('sale order item tax follows global setting and canonical tipe pajak', function () {
    $activeRate = TaxSetting::activeRate('PPN');

    $saleOrder = SaleOrder::withoutGlobalScopes()->create([
        'so_number' => 'SO-LIVE-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(3)->toDateString(),
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'created_by' => $this->user->id,
    ]);

    $item = SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 100000,
        'discount' => 0,
        'tax' => 7,
        'tipe_pajak' => 'PPN Included',
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
    ]);

    expect($saleOrder)->not->toBeNull();
    expect($item)->not->toBeNull();
    expect((float) $item->tax)->toBe((float) $activeRate);
    expect($item->tipe_pajak)->toBe('inklusif');
});

test('sale order currency snapshot converts completed invoice totals to rupiah', function () {
    seedSaleOrderPostingCoas();

    $usd = Currency::factory()->create([
        'name' => 'US Dollar',
        'symbol' => '$',
        'code' => 'USD',
        'to_rupiah' => 16000,
    ]);

    $saleOrder = SaleOrder::withoutGlobalScopes()->create([
        'so_number' => 'SO-LIVE-USD-001',
        'customer_id' => $this->customer->id,
        'cabang_id' => $this->cabang->id,
        'order_date' => now()->toDateString(),
        'delivery_date' => now()->addDays(3)->toDateString(),
        'status' => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'currency_id' => $usd->id,
        'exchange_rate' => 16000,
        'created_by' => $this->user->id,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'none',
        'warehouse_id' => $this->warehouse->id,
        'rak_id' => null,
    ]);

    expect($saleOrder)->not->toBeNull();
    expect((int) $saleOrder->currency_id)->toBe($usd->id);
    expect((float) $saleOrder->exchange_rate)->toBe(16000.0);

    $saleOrder->update(['status' => 'completed']);

    $invoice = \App\Models\Invoice::withoutGlobalScopes()
        ->where('from_model_type', SaleOrder::class)
        ->where('from_model_id', $saleOrder->id)
        ->latest('id')
        ->first();

    expect($invoice)->not->toBeNull();
    expect((float) $invoice->total)->toBe(1600000.0);
    expect((float) $invoice->subtotal)->toBe(1600000.0);
    expect((float) $invoice->dpp)->toBe(1600000.0);
});
