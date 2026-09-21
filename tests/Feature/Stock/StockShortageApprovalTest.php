<?php

/**
 * T2.5 — peringatan stok, blokir approve (D2), backorder beralasan, isi ulang FIFO (D21), peringatan API.
 * Flag: sales.stock.block_short_approval (+ reserve_on_so_approve untuk reservasi parsial & isi ulang).
 */

use App\Models\SaleOrder;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\SaleOrderReservationSynchronizer;
use App\Services\SalesOrderService;
use App\Services\StockAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => ['ledger' => false, 'strict_dispatch' => false, 'reserve_on_so_approve' => true, 'block_short_approval' => true]]);
});

/** Pengguna dengan peran/izin persetujuan SO, opsional bukan pembuat SO. */
function bkoUser(array $ctx, string $role = 'Sales Manager'): User
{
    Permission::firstOrCreate(['name' => 'response sales order', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);

    $user = User::factory()->create(['cabang_id' => $ctx['cabang']->id, 'manage_type' => 'all']);
    $user->givePermissionTo('response sales order');
    $user->assignRole($role);

    return $user;
}

function bkoShortSo(array $ctx, float $qty = 35, array $so = [], array $item = []): SaleOrder
{
    [$saleOrder] = stkSaleOrder($ctx, $qty, array_merge(['status' => 'request_approve'], $so), $item);

    return $saleOrder;
}

it('D2: SO 35 dengan stok bebas 30 ditolak dengan rincian; status dan reservasi tidak berubah', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = bkoShortSo($ctx);

    try {
        app(SalesOrderService::class)->approve($so);
        $this->fail('seharusnya ditolak');
    } catch (ValidationException $e) {
        $msg = $e->errors()['stock'][0];
        expect($msg)->toContain('Alat Uji Stok: diminta 35')->and($msg)->toContain('stok bebas 30')->and($msg)->toContain('kurang 5')
            ->and($msg)->toContain('Gudang Utama')->and($msg)->toContain('Setujui sebagai Backorder');
    }

    expect($so->fresh()->status)->toBe('request_approve')->and(StockReservation::count())->toBe(0)->and($so->fresh()->is_backorder)->toBeFalse();
});

it('celah probe tertutup: item TANPA gudang/alokasi yang kurang stok juga ditolak', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = bkoShortSo($ctx, 35, [], ['warehouse_id' => null]);

    expect(fn () => app(SalesOrderService::class)->approve($so))->toThrow(ValidationException::class, 'Stok tidak mencukupi');
    expect($so->fresh()->status)->toBe('request_approve');
});

it('alokasi gudang yang kurang stok ditolak; alokasi yang tidak sama dengan qty item juga ditolak', function () {
    $ctx = stkContext();
    $w2 = stkWarehouse($ctx, 'Gudang Kedua');
    stkSetStock($ctx['product'], $ctx['warehouse'], 10);
    stkSetStock($ctx['product'], $w2, 4);

    [$so, $item] = stkSaleOrder($ctx, 20, ['status' => 'request_approve'], ['warehouse_id' => null]);
    $item->warehouseAllocations()->create(['warehouse_id' => $ctx['warehouse']->id, 'quantity' => 10]);
    $item->warehouseAllocations()->create(['warehouse_id' => $w2->id, 'quantity' => 10]);
    expect(fn () => app(SalesOrderService::class)->approve($so))->toThrow(ValidationException::class, 'Gudang Kedua');

    $item->warehouseAllocations()->where('warehouse_id', $w2->id)->update(['quantity' => 4]);   // total 14 ≠ 20
    expect(fn () => app(SalesOrderService::class)->approve($so->fresh()))->toThrow(ValidationException::class, 'alokasi');
});

it('stok cukup: approve lolos seperti biasa dan tidak ditandai backorder', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = bkoShortSo($ctx, 20);

    app(SalesOrderService::class)->approve($so);

    expect($so->fresh()->status)->toBe('approved')->and($so->fresh()->is_backorder)->toBeFalse();
});

it('SO yang sudah menahan stok tidak "kurang stok" karena reservasinya sendiri (sadar reservasi)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = bkoShortSo($ctx, 30);
    app(SalesOrderService::class)->approve($so);   // tertahan 30, bebas 0

    expect(app(StockAvailability::class)->check($so->fresh())['has_shortage'])->toBeFalse();
});

it('Backorder: manajer menyetujui dengan alasan → reservasi parsial 30, kekurangan 5, tercatat', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = bkoShortSo($ctx);
    $manager = bkoUser($ctx);
    Auth::login($manager);

    app(SalesOrderService::class)->approveAsBackorder($so, 'PO ke supplier sudah terbit, barang datang Kamis');

    $fresh = $so->fresh();
    $summary = app(SaleOrderReservationSynchronizer::class)->sync($fresh);

    expect($fresh->status)->toBe('approved')
        ->and($fresh->is_backorder)->toBeTrue()
        ->and($fresh->backorder_reason)->toContain('PO ke supplier')
        ->and((int) $fresh->backorder_approved_by)->toBe($manager->id)
        ->and($fresh->backorder_approved_at)->not->toBeNull()
        ->and((float) StockReservation::where('sale_order_id', $so->id)->sum('quantity'))->toBe(30.0)
        ->and(collect($summary)->sum('shortage'))->toBe(5.0);
});

it('Backorder: alasan wajib; Sales biasa dan pembuat SO sendiri ditolak (SoD); Owner pembuat boleh', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = bkoShortSo($ctx);
    $service = app(SalesOrderService::class);

    // tanpa alasan
    Auth::login(bkoUser($ctx));
    expect(fn () => $service->approveAsBackorder($so, '   '))->toThrow(ValidationException::class, 'Alasan backorder wajib');

    // Sales biasa (tanpa peran manajer): ditolak
    Auth::login(bkoUser($ctx, 'Sales'));
    expect(fn () => $service->approveAsBackorder($so, 'mendesak'))->toThrow(ValidationException::class, 'wewenang');

    // Sales Manager yang juga PEMBUAT SO: ditolak (pemisahan tugas)
    $creator = bkoUser($ctx);
    $so->update(['created_by' => $creator->id]);
    Auth::login($creator);
    expect(fn () => $service->approveAsBackorder($so->fresh(), 'mendesak'))->toThrow(ValidationException::class, 'Segregation of Duties');

    expect($so->fresh()->status)->toBe('request_approve');

    // Owner (pembuat) memiliki override darurat, sama seperti approve biasa
    $owner = bkoUser($ctx, 'Owner');
    $so->update(['created_by' => $owner->id]);
    Auth::login($owner);
    $service->approveAsBackorder($so->fresh(), 'keputusan direksi');

    expect($so->fresh()->status)->toBe('approved')->and($so->fresh()->is_backorder)->toBeTrue();
});

it('Backorder pada SO tanpa kekurangan disetujui biasa (bukan backorder)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = bkoShortSo($ctx, 10);
    Auth::login(bkoUser($ctx));

    app(SalesOrderService::class)->approveAsBackorder($so, 'tidak perlu');

    expect($so->fresh()->status)->toBe('approved')->and($so->fresh()->is_backorder)->toBeFalse();
});

it('isi ulang (top-up): stok masuk → SO backorder terisi FIFO menurut waktu approve; dry-run tidak mengubah; flag mati = tidak berbuat apa-apa', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    // Dibuat berurutan: SO1 menahan 25; SO2 dan SO3 (dibuat belakangan) hanya kebagian sisa 5 dan 0.
    [$so1] = stkSaleOrder($ctx, 25, ['approve_at' => now()->subDays(3)]);
    [$so2] = stkSaleOrder($ctx, 20, ['approve_at' => now()->subDay()]);
    [$so3] = stkSaleOrder($ctx, 20, ['approve_at' => now()->subDays(2)]);   // lebih TUA dari SO2 walau dibuat setelahnya

    expect((float) StockReservation::where('sale_order_id', $so2->id)->sum('quantity'))->toBe(5.0)
        ->and((float) StockReservation::where('sale_order_id', $so3->id)->sum('quantity'))->toBe(0.0);

    stkSetStock($ctx['product'], $ctx['warehouse'], 50, 30);   // pembelian 20 masuk → bebas 20

    $this->artisan('sales:top-up-reservations', ['--dry-run' => true])->expectsOutputToContain('DRY-RUN')->assertSuccessful();
    expect((float) StockReservation::where('sale_order_id', $so3->id)->sum('quantity'))->toBe(0.0);

    $this->artisan('sales:top-up-reservations')->assertSuccessful();

    // FIFO menurut approve_at: SO3 (lebih tua) mendapat 20 dari bebas 20; SO2 tetap 5
    expect((float) StockReservation::where('sale_order_id', $so3->id)->sum('quantity'))->toBe(20.0)
        ->and((float) StockReservation::where('sale_order_id', $so2->id)->sum('quantity'))->toBe(5.0)
        ->and((float) StockReservation::where('sale_order_id', $so1->id)->sum('quantity'))->toBe(25.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['free'])->toBe(0.0);

    config(['sales.stock.reserve_on_so_approve' => false]);
    $this->artisan('sales:top-up-reservations')->expectsOutputToContain('mati')->assertSuccessful();
});

it('tombol "Coba reservasi ulang" (sinkron manual) menutup kekurangan setelah stok masuk', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so] = stkSaleOrder($ctx, 35);

    expect((float) StockReservation::where('sale_order_id', $so->id)->sum('quantity'))->toBe(30.0);

    stkSetStock($ctx['product'], $ctx['warehouse'], 40, 30);
    $summary = app(SaleOrderReservationSynchronizer::class)->sync($so, 'Coba reservasi ulang');

    expect((float) StockReservation::where('sale_order_id', $so->id)->sum('quantity'))->toBe(35.0)->and(collect($summary)->sum('shortage'))->toBe(0.0);
});

it('describeShortages: kalimat kekurangan per item dengan nama gudang', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = bkoShortSo($ctx);
    $availability = app(StockAvailability::class);

    $lines = $availability->describeShortages($availability->check($so));

    expect($lines)->toBe(['Alat Uji Stok: diminta 35, stok bebas 30 (kurang 5) di Gudang Utama']);
});

it('penjadwalan: sales:top-up-reservations terdaftar tiap 30 menit tanpa tumpang tindih', function () {
    $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($e) => str_contains((string) $e->command, 'sales:top-up-reservations'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('*/30 * * * *')->and($event->withoutOverlapping)->toBeTrue();
});

it('API: simpan SO melebihi stok mengembalikan warnings[] (tidak memblokir simpan draf); stok cukup = warnings kosong', function () {
    $ctx = stkContext();
    $ctx['user']->givePermissionTo(['create sales order', 'view any sales order', 'view sales order', 'update sales order']);
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);

    $payload = fn (string $number, float $qty) => [
        'header' => [
            'so_number' => $number, 'customer_id' => $ctx['customer']->id, 'cabang_id' => $ctx['cabang']->id, 'order_date' => now()->format('Y-m-d'),
            'tipe_pengiriman' => 'Kirim Langsung', 'currency_id' => $ctx['idr']->id, 'exchange_rate' => 1, 'shipped_to' => 'Jl. Uji',
        ],
        'items' => [['product_id' => $ctx['product']->id, 'quantity' => $qty, 'unit_price' => 10000, 'discount' => 0, 'tax_type' => 'eksklusif', 'tax' => 11]],
    ];

    $short = $this->actingAs($ctx['user'])->postJson('/api/v1/sales-orders', $payload('SO-API-KURANG', 35))->assertOk();
    expect($short->json('warnings'))->toHaveCount(1)->and($short->json('warnings.0'))->toContain('diminta 35')->and($short->json('warnings.0'))->toContain('kurang 5');

    $ok = $this->actingAs($ctx['user'])->postJson('/api/v1/sales-orders', $payload('SO-API-CUKUP', 10))->assertOk();
    expect($ok->json('warnings'))->toBe([]);

    $id = $short->json('data.id');
    $update = $this->actingAs($ctx['user'])->putJson("/api/v1/sales-orders/{$id}", $payload('SO-API-KURANG', 12))->assertOk();
    expect($update->json('warnings'))->toBe([]);
});

it('[flag mati] approve SO stok kurang tetap lolos (perilaku lama) dan tidak ditandai backorder', function () {
    config(['sales.stock.block_short_approval' => false, 'sales.stock.reserve_on_so_approve' => false]);
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $so = bkoShortSo($ctx);

    expect(app(SalesOrderService::class)->approve($so))->toBeTrue();

    expect($so->fresh()->status)->toBe('approved')->and($so->fresh()->is_backorder)->toBeFalse();
});

it('UI: aksi Setujui sebagai Backorder tampil di halaman SO hanya untuk penyetuju dan hanya saat stok kurang', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $short = bkoShortSo($ctx, 35);
    $enough = bkoShortSo($ctx, 10);
    $manager = bkoUser($ctx);
    $regular = bkoUser($ctx, 'Sales');

    $view = fn ($user, $so) => \Livewire\Livewire::actingAs($user)->test(\App\Filament\Resources\SaleOrderResource\Pages\ViewSaleOrder::class, ['record' => $so->getKey()]);

    $view($manager, $short)->assertActionVisible('approve_backorder');
    $view($manager, $enough)->assertActionHidden('approve_backorder');
    $view($regular, $short)->assertActionHidden('approve_backorder');

    config(['sales.stock.block_short_approval' => false]);
    $view($manager, $short)->assertActionHidden('approve_backorder');
});
