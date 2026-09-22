<?php

/**
 * T1.5 — kolom "Sisa Belum Dikirim" di daftar SO: benar dan dihitung batch (jumlah query tetap, bukan per baris).
 */

use App\Filament\Resources\SaleOrderResource\Pages\ListSaleOrders;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\User;
use App\Services\SaleOrderDeliveryProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function rqContext(): array
{
    $cabang = Cabang::factory()->create(['kode' => 'RQ-'.strtoupper(substr(uniqid(), -5)), 'nama' => 'Cabang RQ', 'status' => 1]);

    $permissions = ['view any sales order', 'view sales order'];
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach ($permissions as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $user->givePermissionTo($permissions);
    Auth::login($user);

    $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
    $product = Product::factory()->create();

    return compact('cabang', 'user', 'customer', 'product');
}

function rqSaleOrder(array $ctx, string $status = 'approved', int $qty = 20): array
{
    $saleOrder = SaleOrder::create([
        'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'so_number' => 'SO-RQ-'.strtoupper(substr(uniqid(), -7)),
        'order_date' => now(), 'status' => $status, 'tipe_pengiriman' => 'Kirim Langsung', 'exchange_rate' => 1.0, 'tempo_pembayaran' => 30,
        'shipped_to' => 'Jl. Uji No. 1',
    ]);
    $item = SaleOrderItem::create([
        'sale_order_id' => $saleOrder->id, 'product_id' => $ctx['product']->id, 'quantity' => $qty, 'delivered_quantity' => 0,
        'unit_price' => 1000, 'discount' => 0, 'tax' => 0, 'tipe_pajak' => 'none',
    ]);

    return [$saleOrder, $item];
}

function rqDeliver(array $ctx, SaleOrder $saleOrder, SaleOrderItem $item, float $qty, string $status): DeliveryOrder
{
    $deliveryOrder = DeliveryOrder::create([
        'do_number' => 'DO-RQ-'.strtoupper(substr(uniqid(), -7)), 'delivery_date' => now(), 'status' => $status, 'cabang_id' => $ctx['cabang']->id,
    ]);
    $deliveryOrder->salesOrders()->attach($saleOrder->id);
    DeliveryOrderItem::create(['delivery_order_id' => $deliveryOrder->id, 'sale_order_item_id' => $item->id, 'product_id' => $ctx['product']->id, 'quantity' => $qty]);

    return $deliveryOrder;
}

it('SO 20 dengan DO 12 terkirim menampilkan sisa 8; SO tanpa DO menampilkan 20; draft/selesai tanpa angka', function () {
    $ctx = rqContext();

    [$partial, $partialItem] = rqSaleOrder($ctx);
    rqDeliver($ctx, $partial, $partialItem, 12, 'completed');

    [$untouched] = rqSaleOrder($ctx);
    [$draft] = rqSaleOrder($ctx, 'draft');
    [$done, $doneItem] = rqSaleOrder($ctx, 'completed');
    rqDeliver($ctx, $done, $doneItem, 20, 'completed');

    Livewire::actingAs($ctx['user'])
        ->test(ListSaleOrders::class)
        ->assertTableColumnStateSet('remaining_qty', 8.0, $partial->fresh())
        ->assertTableColumnStateSet('remaining_qty', 20.0, $untouched)
        ->assertTableColumnStateSet('remaining_qty', null, $draft)
        ->assertTableColumnStateSet('remaining_qty', null, $done)
        ->assertTableColumnFormattedStateSet('remaining_qty', '8', $partial->fresh())
        ->assertTableColumnFormattedStateSet('remaining_qty', '–', $draft);
});

it('DO yang belum berangkat (draft) tidak mengurangi sisa belum dikirim', function () {
    $ctx = rqContext();
    [$saleOrder, $item] = rqSaleOrder($ctx);
    rqDeliver($ctx, $saleOrder, $item, 12, 'draft');   // terikat ke SO, tetapi belum keluar gudang

    Livewire::actingAs($ctx['user'])
        ->test(ListSaleOrders::class)
        ->assertTableColumnStateSet('remaining_qty', 20.0, $saleOrder);
});

it('forSaleOrders sama persis dengan forSaleOrder per SO', function () {
    $ctx = rqContext();
    [$a, $itemA] = rqSaleOrder($ctx);
    rqDeliver($ctx, $a, $itemA, 5, 'completed');
    [$b, $itemB] = rqSaleOrder($ctx, 'approved', 30);
    rqDeliver($ctx, $b, $itemB, 10, 'sent');
    rqDeliver($ctx, $b, $itemB, 4, 'draft');

    $service = app(SaleOrderDeliveryProgress::class);
    $batch = $service->forSaleOrders([$a->id, $b->id]);

    expect($batch[$a->id])->toBe($service->forSaleOrder($a)['totals'])
        ->and($batch[$b->id])->toBe($service->forSaleOrder($b)['totals'])
        ->and($batch[$b->id]['remaining'])->toBe(20.0)
        ->and($batch[$b->id]['in_process'])->toBe(4.0)
        ->and($service->forSaleOrders([]))->toBe([]);
});

it('jumlah query progres pengiriman di daftar SO tetap sama untuk 3 maupun 25 SO', function () {
    $ctx = rqContext();

    $progressQueries = function () use ($ctx) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::actingAs($ctx['user'])->test(ListSaleOrders::class);
        $count = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'delivery_order_items'))->count();
        DB::disableQueryLog();

        return $count;
    };

    foreach (range(1, 3) as $i) {
        [$so, $item] = rqSaleOrder($ctx);
        rqDeliver($ctx, $so, $item, 2, 'sent');
    }
    $few = $progressQueries();

    foreach (range(1, 22) as $i) {
        [$so, $item] = rqSaleOrder($ctx);
        rqDeliver($ctx, $so, $item, 2, 'sent');
    }
    $many = $progressQueries();

    expect($few)->toBeGreaterThan(0)->and($many)->toBe($few);
});
