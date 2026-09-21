<?php

/**
 * T2.1 — buku besar reservasi: penulis tunggal, simetris, deterministik, tidak pernah negatif, tercatat,
 * dan reservasi DO DIKONSUMSI saat Dikirim (memperbaiki reservasi yatim X1).
 */

use App\Models\InventoryStock;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\StockReservationEvent;
use App\Services\StockReservationLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

function ledgerOn(bool $on = true): void
{
    config(['sales.stock.ledger' => $on]);
}

function ledgerReserve(array $ctx, float $qty, array $extra = []): StockReservation
{
    return app(StockReservationLedger::class)->reserve(array_merge([
        'product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => $qty,
    ], $extra), 'uji');
}

// ───────────────────────────── Buku besar: reserve / adjust / release / consume ─────────────────────────────

it('reserve menaikkan qty_reserved dan mencatat event; stok fisik tidak berubah', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);

    $reservation = ledgerReserve($ctx, 12);

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 12.0, 'free' => 18.0]);

    $event = StockReservationEvent::firstOrFail();
    expect($event->event)->toBe('reserved')->and($event->quantity)->toBe(12.0)->and($event->stock_reservation_id)->toBe($reservation->id)
        ->and($event->reason)->toBe('uji')->and($event->actor_id)->toBe($ctx['user']->id);
});

it('adjust naik dan turun keduanya memengaruhi qty_reserved (observer simetris); event membawa selisih bertanda', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $ledger = app(StockReservationLedger::class);
    $reservation = ledgerReserve($ctx, 10);

    $ledger->adjust($reservation, 15, 'naik');
    expect(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(15.0);

    $ledger->adjust($reservation->fresh(), 6, 'turun');
    expect(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(6.0);

    $ledger->adjust($reservation->fresh(), 6, 'sama');   // tanpa perubahan → tanpa event
    expect(StockReservationEvent::where('event', 'adjusted')->pluck('quantity')->map(fn ($q) => (float) $q)->all())->toBe([5.0, -9.0]);
});

it('adjust ke 0 melepas reservasi; release dan consume menghapus baris dan menurunkan qty_reserved; event berbeda', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $ledger = app(StockReservationLedger::class);

    $a = ledgerReserve($ctx, 5);
    $b = ledgerReserve($ctx, 4);
    $c = ledgerReserve($ctx, 3);

    $ledger->adjust($a, 0, 'nol');
    $ledger->release($b, 'lepas');
    $ledger->consume($c, 'kirim');

    expect(StockReservation::count())->toBe(0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(0.0)
        ->and(StockReservationEvent::whereIn('event', ['released', 'consumed'])->orderBy('id')->pluck('event')->all())->toBe(['released', 'released', 'consumed'])
        ->and(StockReservationEvent::where('event', 'consumed')->value('quantity'))->toEqual(3);
});

it('qty_reserved tidak pernah menjadi negatif walau reservasi lebih besar dari qty_reserved; peringatan dicatat', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $reservation = ledgerReserve($ctx, 10);
    InventoryStock::where('product_id', $ctx['product']->id)->update(['qty_reserved' => 4]);   // tercemar (mis. impor legacy)

    Log::spy();
    app(StockReservationLedger::class)->release($reservation, 'uji');

    expect(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(0.0);
    Log::shouldHaveReceived('warning')->withArgs(fn ($m) => str_contains($m, 'tidak dibuat negatif'))->once();
});

it('baris stok dipilih deterministik: rak cocok → rak null; pelepasan menguras baris yang sama; jumlah produk×gudang benar', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);   // baris rak null
    $rak = Rak::factory()->create(['warehouse_id' => $ctx['warehouse']->id]);
    $rakRow = InventoryStock::create(['product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'rak_id' => $rak->id, 'qty_available' => 10, 'qty_reserved' => 0, 'qty_min' => 0]);
    $nullRow = InventoryStock::where('product_id', $ctx['product']->id)->whereNull('rak_id')->firstOrFail();

    $withRak = ledgerReserve($ctx, 4, ['rak_id' => $rak->id]);
    $noRak = ledgerReserve($ctx, 3);

    expect((float) $rakRow->fresh()->qty_reserved)->toBe(4.0)
        ->and((float) $nullRow->fresh()->qty_reserved)->toBe(3.0);

    app(StockReservationLedger::class)->release($withRak, 'lepas rak');

    expect((float) $rakRow->fresh()->qty_reserved)->toBe(0.0)
        ->and((float) $nullRow->fresh()->qty_reserved)->toBe(3.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(3.0);

    $noRak->refresh();
});

it('releaseWhere hanya menyentuh reservasi penjualan, TIDAK menyentuh reservasi Material Issue', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    $materialIssue = \App\Models\MaterialIssue::factory()->create(['warehouse_id' => $ctx['warehouse']->id]);
    StockReservation::create(['material_issue_id' => $materialIssue->id, 'product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 7]);
    ledgerReserve($ctx, 5);

    $released = app(StockReservationLedger::class)->releaseWhere(fn ($q) => $q->where('product_id', $ctx['product']->id), 'semua');

    expect($released)->toBe(1)
        ->and(StockReservation::whereNotNull('material_issue_id')->count())->toBe(1)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(7.0);
});

// ───────────────────────────── Flag ledger: reservasi DO dikonsumsi saat Dikirim (X1) ─────────────────────────────

it('[ledger] DO Siap Kirim → reservasi DO terpetakan ke item SO; Dikirim → stok fisik keluar sekali dan reservasi DIKONSUMSI (bukan yatim)', function () {
    ledgerOn();
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);

    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    $reservation = StockReservation::where('delivery_order_id', $do->id)->firstOrFail();
    expect((float) $reservation->quantity)->toBe(12.0)->and($reservation->sale_order_item_id)->toBe($item->id)->and($reservation->sale_order_id)->toBe($so->id)
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 12.0, 'free' => 18.0]);

    $do->update(['status' => 'sent']);

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 0.0, 'free' => 18.0])   // dulu: free 6
        ->and(StockReservation::where('delivery_order_id', $do->id)->count())->toBe(0)
        ->and(StockReservationEvent::where('event', 'consumed')->where('delivery_order_id', $do->id)->count())->toBe(1);
});

it('[ledger] skenario probe: jadwal "Tandai Selesai" (strict_dispatch mati) kini meninggalkan qty_reserved = 0 dan stok bebas 18', function () {
    ledgerOn();
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    stkSchedule($ctx, $do)->update(['status' => 'delivered']);

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 0.0, 'free' => 18.0]);
});

it('[ledger] gerakan pengiriman IDEMPOTEN: DO gagal lalu dikirim ulang tidak memotong stok dua kali', function () {
    ledgerOn();
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    $do->update(['status' => 'sent']);
    $do->update(['status' => 'delivery_failed']);
    $do->update(['status' => 'sent']);   // penjadwalan ulang

    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0)
        ->and(StockMovement::where('type', 'sales')->count())->toBe(1);
});

it('[ledger] pengiriman ulang memotong SELISIH bila kuantitas item DO naik setelah pengiriman pertama', function () {
    ledgerOn();
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 10, 'approved');
    $do->update(['status' => 'sent']);

    // gerakan pengembalian (mis. T2.3) mengurangi "sudah keluar": 4 kembali → kirim ulang hanya menutup 4
    StockMovement::create(['product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 4, 'type' => 'adjustment_in', 'date' => now(),
        'from_model_type' => \App\Models\DeliveryOrderItem::class, 'from_model_id' => $do->deliveryOrderItem->first()->id]);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(24.0);

    $do->update(['status' => 'delivery_failed']);
    $do->update(['status' => 'sent']);

    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(20.0);
});

it('[ledger] DO ditutup, ditolak, atau dihapus melepas reservasinya; SO dibatalkan melepas semua', function () {
    ledgerOn();
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 60);
    [$so, $item] = stkSaleOrder($ctx, 40);

    foreach (['closed', 'reject'] as $status) {
        $do = stkDeliveryOrder($ctx, $so, $item, 5, 'approved');
        expect(stkReserved(null, $do))->toBe(5.0);
        $do->update(['status' => $status]);
        expect(stkReserved(null, $do))->toBe(0.0);
    }

    $deleted = stkDeliveryOrder($ctx, $so, $item, 6, 'approved');
    $deleted->delete();
    expect(stkReserved(null, $deleted))->toBe(0.0);

    $kept = stkDeliveryOrder($ctx, $so, $item, 7, 'approved');
    expect(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(7.0);

    $so->update(['status' => 'canceled']);

    expect(stkReserved($so))->toBe(0.0)->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(0.0)
        ->and(StockReservationEvent::where('reason', 'like', '%dibatalkan%')->count())->toBe(1);

    $kept->refresh();
});

it('[ledger] mengubah kuantitas item DO saat Siap Kirim (edit checker) menurunkan/menaikkan reservasi sesuai', function () {
    ledgerOn();
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');
    $doItem = $do->deliveryOrderItem->first();

    $doItem->update(['quantity' => 8]);
    expect(stkReserved(null, $do))->toBe(8.0)->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(8.0);

    $doItem->update(['quantity' => 12]);
    expect(stkReserved(null, $do))->toBe(12.0);
});

it('[ledger] DO dengan dua gudang sumber menahan per gudang; kuantitas item yang turun memotong sumber TERAKHIR lebih dulu', function () {
    ledgerOn();
    $ctx = stkContext();
    $second = stkWarehouse($ctx);
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    stkSetStock($ctx['product'], $second, 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 10, 'request_stock');
    $doItem = $do->deliveryOrderItem->first();
    $doItem->warehouseSources()->delete();
    \App\Models\DeliveryOrderItemWarehouseSource::create(['delivery_order_item_id' => $doItem->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 6]);
    \App\Models\DeliveryOrderItemWarehouseSource::create(['delivery_order_item_id' => $doItem->id, 'warehouse_id' => $second->id, 'quantity' => 4]);

    $do->update(['status' => 'approved']);

    expect(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(6.0)->and(stkStock($ctx['product'], $second)['reserved'])->toBe(4.0);

    $doItem->update(['quantity' => 7]);   // total 7: sumber pertama 6 tetap, sumber terakhir 4 → 1

    expect(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(6.0)->and(stkStock($ctx['product'], $second)['reserved'])->toBe(1.0);
});

it('[flag mati] reservasi DO/edit kuantitas berperilaku lama: tidak ada event, tidak ada konsumsi', function () {
    ledgerOn(false);
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    $do->update(['status' => 'sent']);

    expect(StockReservationEvent::count())->toBe(0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(12.0);   // yatim — perilaku lama
});
