<?php

/**
 * T2.4 — reservasi stok sejak SO Approved (flag sales.stock.reserve_on_so_approve): keadaan-yang-diinginkan, idempoten,
 * perpindahan SO→DO tanpa selisih, penempatan otomatis (D22), parsial/backorder (D21), backfill FIFO.
 */

use App\Models\SaleOrder;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\StockReservationEvent;
use App\Services\SaleOrderReservationSynchronizer;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => ['ledger' => false, 'strict_dispatch' => false, 'reserve_on_so_approve' => true, 'block_short_approval' => false]]);
});

/** SO $qty diajukan lalu disetujui lewat layanan (jalur nyata) sehingga observer menyusun reservasi. */
function rsvApprove(array $ctx, float $qty, array $so = [], array $item = []): array
{
    [$saleOrder, $saleOrderItem] = stkSaleOrder($ctx, $qty, array_merge(['status' => 'request_approve'], $so), $item);
    app(SalesOrderService::class)->approve($saleOrder);

    return [$saleOrder->fresh(), $saleOrderItem];
}

/** Reservasi level-SO (bukan baris DO) sebuah SO. */
function rsvSoLevel(SaleOrder $so): float
{
    return (float) StockReservation::where('sale_order_id', $so->id)->whereNull('delivery_order_id')->sum('quantity');
}

/** Σ reservasi (SO + DO) untuk satu item SO. */
function rsvTotalForItem($item): float
{
    return (float) StockReservation::where('sale_order_item_id', $item->id)->sum('quantity');
}

it('approve SO menahan stok: SO 20 / stok 30 → tertahan 20, bebas 10; tercatat event reserved', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);

    [$so, $item] = rsvApprove($ctx, 20);

    expect(rsvSoLevel($so))->toBe(20.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 20.0, 'free' => 10.0])
        ->and(StockReservation::where('sale_order_id', $so->id)->first()->sale_order_item_id)->toBe($item->id)
        ->and(StockReservationEvent::where('event', 'reserved')->where('sale_order_id', $so->id)->count())->toBe(1);
});

it('sinkron dipanggil berulang tidak mengubah apa pun dan tidak menambah event (idempoten)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so] = rsvApprove($ctx, 20);
    $events = StockReservationEvent::count();

    $sync = app(SaleOrderReservationSynchronizer::class);
    $sync->sync($so);
    $sync->sync($so->id);
    $sync->sync($so);

    expect(StockReservationEvent::count())->toBe($events)
        ->and(rsvSoLevel($so))->toBe(20.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(20.0);
});

it('D21: SO kedua yang melebihi sisa bebas ditahan PARSIAL; ringkasan melaporkan kekurangan; isi ulang setelah stok masuk', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$soA] = rsvApprove($ctx, 20);
    [$soB, $itemB] = rsvApprove($ctx, 15);

    expect(rsvSoLevel($soB))->toBe(10.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['free'])->toBe(0.0);

    $summary = app(SaleOrderReservationSynchronizer::class)->sync($soB);
    expect($summary[$itemB->id])->toMatchArray(['needed' => 15.0, 'target' => 15.0, 'held' => 10.0, 'shortage' => 5.0]);

    stkSetStock($ctx['product'], $ctx['warehouse'], 40, 30);   // pembelian 10 masuk
    $summary = app(SaleOrderReservationSynchronizer::class)->sync($soB);

    expect(rsvSoLevel($soB))->toBe(15.0)->and($summary[$itemB->id]['shortage'])->toBe(0.0)->and(rsvSoLevel($soA))->toBe(20.0);
});

it('SO→DO tanpa selisih: DO 12 disetujui → total tertahan tetap 20 (DO 12 + SO 8); Dikirim → tertahan 8, fisik 18, bebas 10', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = rsvApprove($ctx, 20);

    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    expect(stkReserved($so, $do))->toBe(12.0)
        ->and(rsvSoLevel($so))->toBe(8.0)
        ->and(rsvTotalForItem($item))->toBe(20.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 20.0, 'free' => 10.0]);

    $do->update(['status' => 'sent']);

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 8.0, 'free' => 10.0])
        ->and(rsvSoLevel($so->fresh()))->toBe(8.0)
        ->and(StockMovement::where('type', 'sales')->count())->toBe(1);
});

it('DO Siap Kirim yang dibatalkan/ditutup mengembalikan kebutuhan ke SO: SO menahan 20 lagi', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = rsvApprove($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    $do->update(['status' => 'closed']);

    expect(stkReserved($so, $do))->toBe(0.0)
        ->and(rsvSoLevel($so))->toBe(20.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(20.0);
});

it('mengedit kuantitas item DO (checker) saat Siap Kirim menjaga total tertahan = kebutuhan', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = rsvApprove($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    $do->deliveryOrderItem->first()->update(['quantity' => 5]);

    expect(stkReserved($so, $do))->toBe(5.0)->and(rsvSoLevel($so))->toBe(15.0)->and(rsvTotalForItem($item))->toBe(20.0);
});

it('gagal kirim setelah Dikirim (alur ketat): stok kembali dan total tertahan tetap = kebutuhan', function () {
    config(['sales.stock.strict_dispatch' => true]);
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = rsvApprove($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');
    $transitions = app(\App\Services\DeliveryOrderTransitions::class);

    $transitions->to($do, 'sent');
    expect(rsvTotalForItem($item))->toBe(8.0);

    $transitions->to($do, 'delivery_failed', ['reason' => 'Customer tutup']);

    expect(rsvTotalForItem($item))->toBe(20.0)
        ->and(stkReserved($so, $do))->toBe(12.0)
        ->and(rsvSoLevel($so))->toBe(8.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 20.0, 'free' => 10.0]);
});

it('SO dibatalkan melepas semua reservasi (SO dan DO); ditolak/ditutup melepas reservasi level-SO; selesai penuh tidak menyisakan apa pun', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 60);
    [$soCancel, $itemCancel] = rsvApprove($ctx, 20);
    $doCancel = stkDeliveryOrder($ctx, $soCancel, $itemCancel, 12, 'approved');
    [$soClose] = rsvApprove($ctx, 10);
    [$soReject] = rsvApprove($ctx, 10);

    $soCancel->update(['status' => 'canceled']);
    $soClose->update(['status' => 'closed']);
    $soReject->update(['status' => 'reject']);

    expect(StockReservation::where('sale_order_id', $soCancel->id)->count())->toBe(0)
        ->and(stkReserved($soCancel, $doCancel))->toBe(0.0)
        ->and(rsvSoLevel($soClose))->toBe(0.0)
        ->and(rsvSoLevel($soReject))->toBe(0.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(0.0);
});

it('SO draft dan menunggu persetujuan tidak menahan stok', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);

    stkSaleOrder($ctx, 20, ['status' => 'draft']);
    stkSaleOrder($ctx, 20, ['status' => 'request_approve']);

    expect(StockReservation::count())->toBe(0)->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(0.0);
});

it('D22: item tanpa gudang ditempatkan otomatis di gudang cabang dengan stok bebas terbesar, lalu gudang berikutnya', function () {
    $ctx = stkContext();
    $small = $ctx['warehouse'];
    $big = stkWarehouse($ctx, 'Gudang Besar');
    stkSetStock($ctx['product'], $small, 5);
    stkSetStock($ctx['product'], $big, 12);

    [$so, $item] = rsvApprove($ctx, 15, [], ['warehouse_id' => null]);

    expect(stkStock($ctx['product'], $big)['reserved'])->toBe(12.0)
        ->and(stkStock($ctx['product'], $small)['reserved'])->toBe(3.0)
        ->and(rsvSoLevel($so))->toBe(15.0)
        ->and($item->fresh()->warehouse_id)->toBeNull();   // data item tidak diubah
});

it('D22: stok gudang cabang lain tidak dipakai penempatan otomatis (D3)', function () {
    $ctx = stkContext();
    $otherCabang = \App\Models\Cabang::factory()->create(['kode' => 'LN-'.strtoupper(substr(uniqid(), -4)), 'nama' => 'Cabang Lain', 'status' => 1]);
    $foreign = stkWarehouse($ctx, 'Gudang Cabang Lain', $otherCabang);
    stkSetStock($ctx['product'], $ctx['warehouse'], 3);
    stkSetStock($ctx['product'], $foreign, 50);

    [$so] = rsvApprove($ctx, 10, [], ['warehouse_id' => null]);

    expect(rsvSoLevel($so))->toBe(3.0)->and(stkStock($ctx['product'], $foreign)['reserved'])->toBe(0.0);
});

it('alokasi multi-gudang: reservasi per alokasi, dibatasi stok bebas tiap gudang', function () {
    $ctx = stkContext();
    $w1 = $ctx['warehouse'];
    $w2 = stkWarehouse($ctx, 'Gudang Kedua');
    stkSetStock($ctx['product'], $w1, 10);
    stkSetStock($ctx['product'], $w2, 4);

    [$saleOrder, $item] = stkSaleOrder($ctx, 20, ['status' => 'request_approve'], ['warehouse_id' => null]);
    $item->warehouseAllocations()->create(['warehouse_id' => $w1->id, 'quantity' => 10]);
    $item->warehouseAllocations()->create(['warehouse_id' => $w2->id, 'quantity' => 10]);
    $saleOrder->update(['status' => 'approved']);

    expect(stkStock($ctx['product'], $w1)['reserved'])->toBe(10.0)
        ->and(stkStock($ctx['product'], $w2)['reserved'])->toBe(4.0)
        ->and(rsvSoLevel($saleOrder))->toBe(14.0);
});

it('mengubah kuantitas item SO menyesuaikan reservasi; menghapus item melepasnya', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 50);
    [$so, $item] = rsvApprove($ctx, 20);
    $second = stkProduct($ctx);
    stkSetStock($second, $ctx['warehouse'], 50);
    $other = \App\Models\SaleOrderItem::create([
        'sale_order_id' => $so->id, 'product_id' => $second->id, 'quantity' => 7, 'delivered_quantity' => 0, 'unit_price' => 10000, 'discount' => 0,
        'tax' => 0, 'tipe_pajak' => 'none', 'currency_id' => $ctx['idr']->id, 'warehouse_id' => $ctx['warehouse']->id,
    ]);
    expect(rsvTotalForItem($other))->toBe(7.0);

    $item->update(['quantity' => 25]);
    expect(rsvTotalForItem($item))->toBe(25.0);

    $other->delete();
    expect(StockReservation::where('product_id', $second->id)->count())->toBe(0)
        ->and(stkStock($second, $ctx['warehouse'])['reserved'])->toBe(0.0);
});

it('Ambil Sendiri: reservasi SO dikonsumsi saat selesai — stok fisik keluar dan tidak ada reservasi yatim', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so] = rsvApprove($ctx, 10, ['tipe_pengiriman' => 'Ambil Sendiri']);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(10.0);

    $so->update(['status' => 'completed']);

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 20.0, 'reserved' => 0.0, 'free' => 20.0])
        ->and(StockReservationEvent::where('event', 'consumed')->where('sale_order_id', $so->id)->count())->toBe(1);
});

it('reservasi Material Issue tidak pernah disentuh sinkronisasi SO', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $materialIssue = \App\Models\MaterialIssue::factory()->create(['warehouse_id' => $ctx['warehouse']->id]);
    $mi = StockReservation::create(['product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 6, 'material_issue_id' => $materialIssue->id]);

    [$so] = rsvApprove($ctx, 20);
    $so->update(['status' => 'canceled']);

    expect(StockReservation::whereKey($mi->id)->exists())->toBeTrue()
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(6.0);
});

it('INVARIAN: sepanjang alur acak, Σ tertahan per item ≤ kebutuhan dan qty_reserved = Σ baris reservasi', function () {
    config(['sales.stock.strict_dispatch' => true]);
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 40);
    $transitions = app(\App\Services\DeliveryOrderTransitions::class);

    $check = function (string $when) use ($ctx) {
        $reserved = stkStock($ctx['product'], $ctx['warehouse'])['reserved'];
        $rows = (float) StockReservation::where('product_id', $ctx['product']->id)->sum('quantity');
        expect($reserved)->toBe($rows, "qty_reserved ≠ Σ baris: {$when}");

        foreach (SaleOrder::with('saleOrderItem')->get() as $so) {
            foreach ($so->saleOrderItem as $item) {
                $progress = app(\App\Services\SaleOrderDeliveryProgress::class)->forItems([$item->id])[$item->id];
                expect(rsvTotalForItem($item))->toBeLessThanOrEqual($progress['remaining'] + 0.0001, "Σ tertahan > kebutuhan: {$when}");
            }
        }
    };

    [$so1, $item1] = rsvApprove($ctx, 20);
    $check('approve SO1');
    [$so2, $item2] = rsvApprove($ctx, 25);
    $check('approve SO2 (parsial)');

    $do1 = stkDeliveryOrder($ctx, $so1, $item1, 12, 'approved');
    $check('DO1 approved');
    $do2 = stkDeliveryOrder($ctx, $so1, $item1, 8, 'approved');
    $check('DO2 approved');

    $transitions->to($do1, 'sent');
    $check('DO1 sent');
    $transitions->to($do2, 'delivery_failed', ['reason' => 'x']);
    $check('DO2 failed');
    $transitions->to($do1, 'delivery_failed', ['reason' => 'y']);
    $check('DO1 failed setelah sent');
    $transitions->to($do1, 'sent');
    $check('DO1 sent ulang');
    $transitions->to($do2, 'closed', ['reason' => 'batal']);
    $check('DO2 closed');
    $so2->update(['status' => 'canceled']);
    $check('SO2 canceled');
    $transitions->complete($do1);
    $check('DO1 selesai');
});

// ------------------------------------------------------------------ backfill

it('sales:backfill-so-reservations: dry-run tidak mengubah apa pun; --apply menahan FIFO menurut waktu approve dan menulis CSV', function () {
    config(['sales.stock.reserve_on_so_approve' => false]);   // data lama: dibuat sebelum flag hidup
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$newer] = stkSaleOrder($ctx, 20, ['approve_at' => now()->subHour()]);
    [$older] = stkSaleOrder($ctx, 20, ['approve_at' => now()->subDay()]);
    expect(StockReservation::count())->toBe(0);

    $dir = sys_get_temp_dir().'/rsv-backfill-'.uniqid();

    $this->artisan('sales:backfill-so-reservations', ['--out-dir' => $dir])->expectsOutputToContain('DRY-RUN')->assertSuccessful();
    expect(StockReservation::count())->toBe(0)
        ->and(StockReservationEvent::count())->toBe(0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(0.0);

    $this->artisan('sales:backfill-so-reservations', ['--apply' => true, '--out-dir' => $dir])->expectsOutputToContain('reservasi ditulis')->assertSuccessful();

    expect(rsvSoLevel($older))->toBe(20.0)          // lebih lama → dapat penuh
        ->and(rsvSoLevel($newer))->toBe(10.0)        // lebih baru → sisa 10 (backorder 10)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(30.0)
        ->and(glob($dir.'/backfill-reservasi-so-*.csv'))->not->toBeEmpty();

    // dijalankan ulang = tidak ada perubahan
    $events = StockReservationEvent::count();
    $this->artisan('sales:backfill-so-reservations', ['--apply' => true, '--no-csv' => true])->assertSuccessful();
    expect(StockReservationEvent::count())->toBe($events);
});

it('[flag mati] approve SO tidak menahan stok dan DO memakai jalur reservasi lama', function () {
    config(['sales.stock.reserve_on_so_approve' => false]);
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);

    [$so, $item] = rsvApprove($ctx, 20);

    expect(StockReservation::count())->toBe(0);

    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');
    expect(stkReserved($so, $do))->toBe(12.0)->and(StockReservationEvent::count())->toBe(0);   // tanpa buku besar
});

it('F2: DO tidak terblokir oleh reservasi SO-nya sendiri; reservasi SO lain tetap mengurangi stok bebas', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$soA, $itemA] = rsvApprove($ctx, 20);
    [$soB, $itemB] = rsvApprove($ctx, 10);
    $availability = app(\App\Services\StockAvailability::class);

    expect(stkStock($ctx['product'], $ctx['warehouse'])['free'])->toBe(0.0)
        ->and($availability->freeForSaleOrderItem($ctx['product']->id, $ctx['warehouse']->id, $itemA->id))->toBe(20.0)
        ->and($availability->freeForSaleOrderItem($ctx['product']->id, $ctx['warehouse']->id, $itemB->id))->toBe(10.0);

    // DO 12 untuk SO A lolos jalur persetujuan (tidak "kekurangan stok" karena reservasinya sendiri)
    $do = stkDeliveryOrder($ctx, $soA, $itemA, 12, 'approved');
    expect($do->status)->toBe('approved')->and(stkReserved($soA, $do))->toBe(12.0);
});
