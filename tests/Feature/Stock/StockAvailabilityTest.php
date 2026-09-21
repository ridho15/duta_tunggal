<?php

/**
 * T2.2 — StockAvailability: stok bebas sadar reservasi (add-back reservasi milik SO sendiri) + kebijakan cabang (D3).
 * Kunci: SO yang menahan stok TIDAK boleh terhalang oleh reservasinya sendiri (temuan F2).
 */

use App\Filament\Resources\SaleOrderResource\Pages\ListSaleOrders;
use App\Models\Cabang;
use App\Models\SaleOrderItemWarehouseAllocation;
use App\Services\StockAvailability;
use App\Services\StockReservationLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function availability(): StockAvailability
{
    return app(StockAvailability::class);
}

function holdFor($so, $item, array $ctx, float $qty, ?int $warehouseId = null): void
{
    app(StockReservationLedger::class)->reserve([
        'product_id' => $item->product_id, 'warehouse_id' => $warehouseId ?? $ctx['warehouse']->id, 'quantity' => $qty,
        'sale_order_id' => $so->id, 'sale_order_item_id' => $item->id,
    ], 'uji');
}

// ───────────────────────────── Kebijakan cabang (D3) ─────────────────────────────

it('D3: gudang yang boleh dipakai = gudang aktif cabang SO; + cabang lain hanya bila lihat_stok_cabang_lain; gudang nonaktif/terhapus tidak', function () {
    $ctx = stkContext();
    $own2 = stkWarehouse($ctx, 'Gudang Cabang 2');
    $other = Cabang::factory()->create(['kode' => 'OTH-'.strtoupper(substr(uniqid(), -4)), 'nama' => 'Cabang Lain', 'status' => 1]);
    $foreign = stkWarehouse($ctx, 'Gudang Cabang Lain', $other);
    $inactive = stkWarehouse($ctx, 'Gudang Nonaktif');
    $inactive->update(['status' => 0]);
    $deleted = stkWarehouse($ctx, 'Gudang Terhapus');
    $deleted->delete();

    expect(availability()->accessibleWarehouseIds($ctx['cabang']->id))->toBe([$ctx['warehouse']->id, $own2->id]);

    $ctx['cabang']->update(['lihat_stok_cabang_lain' => true]);

    expect(availability()->accessibleWarehouseIds($ctx['cabang']->id))->toBe([$ctx['warehouse']->id, $own2->id, $foreign->id])
        ->and(availability()->accessibleWarehouseIds(null))->toBe([$ctx['warehouse']->id, $own2->id, $foreign->id]);
});

it('D3: item tanpa gudang menghitung stok hanya dari gudang cabang SO — stok cabang lain baru ikut bila diizinkan', function () {
    $ctx = stkContext();
    $other = Cabang::factory()->create(['kode' => 'OTH-'.strtoupper(substr(uniqid(), -4)), 'nama' => 'Cabang Lain', 'status' => 1]);
    $foreign = stkWarehouse($ctx, 'Gudang Cabang Lain', $other);
    stkSetStock($ctx['product'], $ctx['warehouse'], 10);
    stkSetStock($ctx['product'], $foreign, 50);
    [$so] = stkSaleOrder($ctx, 30, [], ['warehouse_id' => null]);

    $check = availability()->check($so);

    expect($check['has_shortage'])->toBeTrue()
        ->and($check['items'][0]['available'])->toBe(10.0)
        ->and($check['items'][0]['shortage'])->toBe(20.0);

    $ctx['cabang']->update(['lihat_stok_cabang_lain' => true]);

    expect(availability()->check($so->fresh())['has_shortage'])->toBeFalse()
        ->and(availability()->freeForCabang($ctx['product']->id, $ctx['cabang']->id))->toBe(60.0);
});

it('D3: gudang yang DIPILIH pada item tetap dihormati walau milik cabang lain', function () {
    $ctx = stkContext();
    $other = Cabang::factory()->create(['kode' => 'OTH-'.strtoupper(substr(uniqid(), -4)), 'nama' => 'Cabang Lain', 'status' => 1]);
    $foreign = stkWarehouse($ctx, 'Gudang Cabang Lain', $other);
    stkSetStock($ctx['product'], $foreign, 50);
    [$so] = stkSaleOrder($ctx, 30, [], ['warehouse_id' => $foreign->id]);

    expect(availability()->check($so)['has_shortage'])->toBeFalse();
});

it('freeByProductForCabang: satu kueri untuk semua produk, hanya gudang yang boleh dipakai', function () {
    $ctx = stkContext();
    $second = stkProduct($ctx);
    $other = Cabang::factory()->create(['kode' => 'OTH-'.strtoupper(substr(uniqid(), -4)), 'nama' => 'Cabang Lain', 'status' => 1]);
    $foreign = stkWarehouse($ctx, 'Gudang Cabang Lain', $other);
    stkSetStock($ctx['product'], $ctx['warehouse'], 30, 12);
    stkSetStock($second, $ctx['warehouse'], 5);
    stkSetStock($ctx['product'], $foreign, 99);

    $free = availability()->freeByProductForCabang($ctx['cabang']->id);

    expect($free[$ctx['product']->id])->toBe(18.0)->and($free[$second->id])->toBe(5.0);
});

// ───────────────────────────── Add-back reservasi milik SO sendiri (F2) ─────────────────────────────

it('F2: stok bebas untuk SO = bebas + reservasi milik SO itu; SO lain tidak mendapat add-back', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$soA, $itemA] = stkSaleOrder($ctx, 20);
    [$soB] = stkSaleOrder($ctx, 15);
    holdFor($soA, $itemA, $ctx, 20);

    expect(availability()->freeInWarehouse($ctx['product']->id, $ctx['warehouse']->id))->toBe(10.0)
        ->and(availability()->freeForSaleOrders($ctx['product']->id, $ctx['warehouse']->id, [$soA->id]))->toBe(30.0)
        ->and(availability()->freeForSaleOrders($ctx['product']->id, $ctx['warehouse']->id, [$soB->id]))->toBe(10.0)
        ->and(availability()->freeForSaleOrderItem($ctx['product']->id, $ctx['warehouse']->id, $itemA->id))->toBe(30.0);
});

it('F2: SO yang menahan stok tidak dianggap kurang stok karena reservasinya sendiri; SO lain yang menuntut lebih dari sisa bebas tetap kurang', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$soA, $itemA] = stkSaleOrder($ctx, 20);
    [$soB] = stkSaleOrder($ctx, 15);
    holdFor($soA, $itemA, $ctx, 20);

    expect(availability()->check($soA)['has_shortage'])->toBeFalse()
        ->and($soA->fresh()->hasInsufficientStock())->toBeFalse();

    $checkB = availability()->check($soB);
    expect($checkB['has_shortage'])->toBeTrue()
        ->and($checkB['items'][0]['available'])->toBe(10.0)
        ->and($checkB['items'][0]['shortage'])->toBe(5.0)
        ->and($soB->fresh()->hasInsufficientStock())->toBeTrue()
        ->and($soB->fresh()->getInsufficientStockItems()[0]['shortage'])->toBe(5.0);
});

it('reservasi Material Issue tidak pernah dianggap milik SO (tidak ada add-back)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so] = stkSaleOrder($ctx, 20);
    $mi = \App\Models\MaterialIssue::factory()->create(['warehouse_id' => $ctx['warehouse']->id]);
    \App\Models\StockReservation::create(['material_issue_id' => $mi->id, 'sale_order_id' => $so->id, 'product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 25]);

    expect(availability()->check($so)['has_shortage'])->toBeTrue();   // 30 − 25 = 5 bebas, butuh 20
});

// ───────────────────────────── Alokasi & rak ─────────────────────────────

it('alokasi multi-gudang: tiap alokasi diperiksa pada gudangnya; alokasi tidak lengkap dianggap kurang', function () {
    $ctx = stkContext();
    $second = stkWarehouse($ctx, 'Gudang Kedua');
    stkSetStock($ctx['product'], $ctx['warehouse'], 10);
    stkSetStock($ctx['product'], $second, 3);
    [$so, $item] = stkSaleOrder($ctx, 12);
    SaleOrderItemWarehouseAllocation::create(['sale_order_item_id' => $item->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 8]);
    SaleOrderItemWarehouseAllocation::create(['sale_order_item_id' => $item->id, 'warehouse_id' => $second->id, 'quantity' => 4]);

    $check = availability()->check($so->fresh());
    expect($check['has_shortage'])->toBeTrue()               // gudang kedua hanya 3 dari 4
        ->and($check['items'][0]['available'])->toBe(11.0)
        ->and($check['items'][0]['shortage'])->toBe(1.0);

    stkSetStock($ctx['product'], $second, 4);
    expect(availability()->check($so->fresh())['has_shortage'])->toBeFalse();

    $item->warehouseAllocations()->where('warehouse_id', $second->id)->delete();   // Σ alokasi 8 ≠ 12
    $partial = availability()->check($so->fresh());
    expect($partial['has_shortage'])->toBeTrue()
        ->and($partial['items'][0]['allocation_mismatch'])->toBeTrue()
        ->and($partial['items'][0]['note'])->toContain('alokasi');
});

it('item dengan rak terpilih diperiksa pada rak itu saja', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 50);   // baris rak null
    $rak = \App\Models\Rak::factory()->create(['warehouse_id' => $ctx['warehouse']->id]);
    \App\Models\InventoryStock::create(['product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'rak_id' => $rak->id, 'qty_available' => 4, 'qty_reserved' => 0, 'qty_min' => 0]);
    [$so] = stkSaleOrder($ctx, 10, [], ['rak_id' => $rak->id]);

    $check = availability()->check($so->fresh());
    expect($check['has_shortage'])->toBeTrue()->and($check['items'][0]['available'])->toBe(4.0);
});

it('SO tanpa item tidak pernah kurang stok', function () {
    $ctx = stkContext();
    [$so, $item] = stkSaleOrder($ctx, 5);
    $item->delete();

    expect(availability()->check($so->fresh())['has_shortage'])->toBeFalse();
});

// ───────────────────────────── Batch: parity & jumlah query tetap ─────────────────────────────

it('checkMany menghasilkan hasil identik dengan check per SO', function () {
    $ctx = stkContext();
    $second = stkProduct($ctx);
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    stkSetStock($second, $ctx['warehouse'], 2);
    [$a, $ai] = stkSaleOrder($ctx, 20);
    holdFor($a, $ai, $ctx, 20);
    [$b] = stkSaleOrder($ctx, 15);
    [$c] = stkSaleOrder($ctx, 5, [], ['product_id' => $second->id]);

    $batch = availability()->checkMany([$a->fresh(), $b->fresh(), $c->fresh()]);
    $summary = fn (array $r) => [$r['has_shortage'], array_map(fn ($i) => [$i['needed'], $i['available'], $i['shortage']], $r['items'])];

    foreach ([$a, $b, $c] as $order) {
        expect($summary($batch[$order->id]))->toBe($summary(availability()->check($order->fresh())));
    }
});

it('checkMany: jumlah query sama untuk 3 maupun 25 SO', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 1000);

    $queries = function (int $count) use ($ctx) {
        $orders = collect(range(1, $count))->map(fn () => stkSaleOrder($ctx, 2)[0]);
        $fresh = \App\Models\SaleOrder::whereIn('id', $orders->pluck('id'))->get();
        DB::flushQueryLog();
        DB::enableQueryLog();
        availability()->checkMany($fresh);
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    };

    $few = $queries(3);
    $many = $queries(25);

    expect($few)->toBeGreaterThan(0)->and($many)->toBe($few);
});

// ───────────────────────────── Daftar SO: badge & filter ─────────────────────────────

function listContext(): array
{
    $ctx = stkContext();
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['view any sales order', 'view sales order'] as $name) {
        Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }
    $ctx['user']->givePermissionTo(['view any sales order', 'view sales order']);
    Auth::login($ctx['user']);

    return $ctx;
}

it('daftar SO: badge STOK KURANG/READY sadar reservasi sendiri; filter Status Stok konsisten', function () {
    $ctx = listContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$holder, $holderItem] = stkSaleOrder($ctx, 20);
    holdFor($holder, $holderItem, $ctx, 20);   // menahan 20 → tidak boleh terlihat "kurang" karena reservasinya sendiri
    [$short] = stkSaleOrder($ctx, 15);         // sisa bebas 10 → kurang

    Livewire::actingAs($ctx['user'])
        ->test(ListSaleOrders::class)
        ->assertTableColumnStateSet('stock_status', 'STOK READY', $holder)
        ->assertTableColumnStateSet('stock_status', 'STOK KURANG', $short)
        ->filterTable('stock_status', 'insufficient')
        ->assertCanSeeTableRecords([$short])
        ->assertCanNotSeeTableRecords([$holder])
        ->filterTable('stock_status', 'sufficient')
        ->assertCanSeeTableRecords([$holder])
        ->assertCanNotSeeTableRecords([$short]);
});

it('daftar SO: jumlah query pemeriksaan stok tetap sama untuk 3 maupun 25 SO', function () {
    $ctx = listContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 1000);

    $stockQueries = function () use ($ctx) {
        DB::flushQueryLog();
        DB::enableQueryLog();
        Livewire::actingAs($ctx['user'])->test(ListSaleOrders::class);
        $count = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'inventory_stocks'))->count();
        DB::disableQueryLog();

        return $count;
    };

    foreach (range(1, 3) as $i) {
        stkSaleOrder($ctx, 2);
    }
    $few = $stockQueries();
    foreach (range(1, 22) as $i) {
        stkSaleOrder($ctx, 2);
    }
    $many = $stockQueries();

    expect($few)->toBeGreaterThan(0)->and($many)->toBe($few);
});

// ───────────────────────────── Pemindai: tidak ada lagi freeQtyFor tanpa add-back di jalur penjualan ─────────────────────────────

it('pemindai: jalur DO/SO tidak lagi memakai InventoryStock::freeQtyFor (harus reservasi-sadar)', function () {
    $files = [
        'app/Filament/Resources/DeliveryOrderResource.php',
        'app/Filament/Resources/DeliveryOrderResource/Pages/CreateDeliveryOrder.php',
        'app/Filament/Resources/DeliveryOrderResource/Pages/EditDeliveryOrder.php',
        'app/Models/SaleOrder.php',
        'app/Services/SalesOrderService.php',
    ];

    foreach ($files as $file) {
        expect(file_get_contents(base_path($file)))->not->toContain('freeQtyFor(');
    }
});
