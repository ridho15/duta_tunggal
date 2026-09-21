<?php

/**
 * T2.6 — SPESIFIKASI reservasi stok penjualan (menggantikan StockReservationFlowTest & TC-SR-003..005 yang memakai
 * SalesOrderService::confirm() dan semantik lama "qty_available berkurang saat reservasi").
 *
 * Semantik sekarang: qty_available = stok FISIK (tetap saat reservasi), qty_reserved = tertahan, bebas = selisihnya;
 * reservasi baru dibuat saat SO Approved (flag reserve_on_so_approve), dikonsumsi saat DO Dikirim.
 */

use App\Models\InventoryStock;
use App\Models\SaleOrder;
use App\Models\StockReservation;
use App\Services\DeliveryOrderTransitions;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => ['ledger' => false, 'strict_dispatch' => true, 'reserve_on_so_approve' => true, 'block_short_approval' => false]]);
});

it('spec: SO Approved menahan stok — qty_reserved naik, qty_available (stok fisik) tetap', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 100);
    [$so] = stkSaleOrder($ctx, 10);

    $this->assertDatabaseHas('stock_reservations', ['sale_order_id' => $so->id, 'product_id' => $ctx['product']->id, 'warehouse_id' => $ctx['warehouse']->id, 'quantity' => 10]);
    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 100.0, 'reserved' => 10.0, 'free' => 90.0]);
});

it('spec (TC-SR-003): reservasi tidak pernah melebihi stok bebas — SO 20 dengan stok 5 menahan 5, kekurangan dilaporkan', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 5);
    [$so, $item] = stkSaleOrder($ctx, 20);

    expect((float) StockReservation::where('sale_order_id', $so->id)->sum('quantity'))->toBe(5.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['free'])->toBe(0.0)
        ->and(app(\App\Services\SaleOrderReservationSynchronizer::class)->sync($so)[$item->id]['shortage'])->toBe(15.0);
});

it('spec (TC-SR-004): stok yang sudah ditahan SO pertama tidak ditahan lagi oleh SO kedua; stok bebas tidak pernah negatif', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 10);
    [$so1] = stkSaleOrder($ctx, 10);
    [$so2] = stkSaleOrder($ctx, 5);

    expect((float) StockReservation::where('sale_order_id', $so1->id)->sum('quantity'))->toBe(10.0)
        ->and((float) StockReservation::where('sale_order_id', $so2->id)->sum('quantity'))->toBe(0.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['free'])->toBe(0.0);
});

it('spec (TC-SR-005): membatalkan SO melepas reservasi; stok bebas pulih penuh', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 50);
    [$so] = stkSaleOrder($ctx, 15);
    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 50.0, 'reserved' => 15.0, 'free' => 35.0]);

    app(SalesOrderService::class)->cancel($so->fresh());

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 50.0, 'reserved' => 0.0, 'free' => 50.0]);
    $this->assertDatabaseMissing('stock_reservations', ['sale_order_id' => $so->id]);
    $this->assertDatabaseHas('stock_reservation_events', ['sale_order_id' => $so->id, 'event' => 'released']);   // tercatat di buku besar
    expect($so->fresh()->status)->toBe('canceled');
});

it('spec: DO Dikirim mengonsumsi reservasi; DO Selesai tidak menahan apa pun dan stok fisik berkurang sekali', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 12);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');
    $transitions = app(DeliveryOrderTransitions::class);

    $transitions->to($do, 'sent');
    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 0.0, 'free' => 18.0]);

    $transitions->complete($do);

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 0.0, 'free' => 18.0])
        ->and(StockReservation::count())->toBe(0)
        ->and($so->fresh()->status)->toBe('completed');
});

it('spec: pengiriman parsial — DO1 12 dikirim menyisakan tertahan 8; DO2 8 dikirim menghabiskannya dan SO selesai', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $transitions = app(DeliveryOrderTransitions::class);

    $do1 = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');
    $transitions->to($do1, 'sent');

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 18.0, 'reserved' => 8.0, 'free' => 10.0])
        ->and($so->fresh()->status)->toBe('partially_delivered');

    $do2 = stkDeliveryOrder($ctx, $so, $item, 8, 'approved');
    $transitions->to($do2, 'sent');

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 10.0, 'reserved' => 0.0, 'free' => 10.0])
        ->and(StockReservation::count())->toBe(0)
        ->and($so->fresh()->status)->toBe('completed');
});

it('spec: tidak ada double reservasi — DO Siap Kirim memindahkan reservasi SO, total tertahan tetap sama', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$soA, $itemA] = stkSaleOrder($ctx, 20);
    [$soB] = stkSaleOrder($ctx, 20);

    expect(InventoryStock::where('product_id', $ctx['product']->id)->sum('qty_reserved'))->toEqual(30);   // A 20 + B 10 (parsial)

    stkDeliveryOrder($ctx, $soA, $itemA, 12, 'approved');

    expect(InventoryStock::where('product_id', $ctx['product']->id)->sum('qty_reserved'))->toEqual(30)   // tidak berubah
        ->and(SaleOrder::count())->toBe(2);
});
