<?php

use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => [
        'ledger' => false,
        'strict_dispatch' => true,
        'reserve_on_so_approve' => false,
        'block_short_approval' => false,
    ]]);
});

it('RC-01: invarian stok fisik vs mutasi kartu persediaan bernilai tepat 0 (tanpa selisih)', function () {
    $ctx = stkContext();
    // Saldo awal 50 unit
    stkSetStock($ctx['product'], $ctx['warehouse'], 50);

    // Transaksi 1: DO kirim 15 pcs
    [$so1, $soItem1] = stkSaleOrder($ctx, 15);
    $do1 = stkDeliveryOrder($ctx, $so1, $soItem1, 15, 'approved');
    $sch1 = stkSchedule($ctx, $do1);
    $sch1->update(['status' => 'on_the_way']);

    // Transaksi 2: DO kirim 20 pcs
    [$so2, $soItem2] = stkSaleOrder($ctx, 20);
    $do2 = stkDeliveryOrder($ctx, $so2, $soItem2, 20, 'approved');
    $sch2 = stkSchedule($ctx, $do2);
    $sch2->update(['status' => 'on_the_way']);

    // Stok fisik di tabel inventory_stocks
    $currentAvailable = (float) InventoryStock::withoutGlobalScopes()
        ->where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->value('qty_available');

    // Total mutasi keluar dari StockMovement
    $totalOut = (float) StockMovement::where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->where('type', 'sales')
        ->sum('quantity');

    // Invarian matematika: Stok Awal (50) - Total Keluar (35) == Stok Fisik Aktual (15)
    $expectedStock = 50.0 - $totalOut;
    expect(abs($currentAvailable - $expectedStock))->toBeLessThan(0.0001)
        ->and($currentAvailable)->toBe(15.0);
});

it('RC-02: invarian cadangan stok menjamin nol reservasi phantom di setiap siklus DO', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 40);

    // Buat 2 DO yang disetujui
    [$so1, $soItem1] = stkSaleOrder($ctx, 12);
    $do1 = stkDeliveryOrder($ctx, $so1, $soItem1, 12, 'approved');

    [$so2, $soItem2] = stkSaleOrder($ctx, 8);
    $do2 = stkDeliveryOrder($ctx, $so2, $soItem2, 8, 'approved');

    // Invarian 1: qty_reserved di inventory_stocks harus sama dengan total reservasi aktif (20)
    $reservedInStock = (float) InventoryStock::withoutGlobalScopes()
        ->where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->value('qty_reserved');

    $activeReservations = (float) StockReservation::where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->sum('quantity');

    expect($reservedInStock)->toBe(20.0)
        ->and($activeReservations)->toBe(20.0)
        ->and(abs($reservedInStock - $activeReservations))->toBe(0.0);

    // DO 1 dikirim -> reservasi DO 1 dilepas/dikonsumsi
    $sch1 = stkSchedule($ctx, $do1);
    $sch1->update(['status' => 'on_the_way']);

    $reservedAfterSend = (float) InventoryStock::withoutGlobalScopes()
        ->where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->value('qty_reserved');

    $activeAfterSend = (float) StockReservation::where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->sum('quantity');

    expect($reservedAfterSend)->toBe(8.0)
        ->and($activeAfterSend)->toBe(8.0)
        ->and(abs($reservedAfterSend - $activeAfterSend))->toBe(0.0);

    // DO 2 dibatalkan (closed) -> cadangan harus kembali menjadi 0
    app(\App\Services\DeliveryOrderTransitions::class)->to($do2, 'closed', ['reason' => 'Batal DO 2']);

    $finalReserved = (float) InventoryStock::withoutGlobalScopes()
        ->where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->value('qty_reserved');

    $finalActive = (float) StockReservation::where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->sum('quantity');

    expect($finalReserved)->toBe(0.0)
        ->and($finalActive)->toBe(0.0)
        ->and(abs($finalReserved - $finalActive))->toBe(0.0);
});

it('RC-03: setiap penerbitan jurnal HPP pengiriman memiliki saldo debit dan credit yang balance', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $soItem] = stkSaleOrder($ctx, 7);
    $do = stkDeliveryOrder($ctx, $so, $soItem, 7, 'approved');
    $sch = stkSchedule($ctx, $do);

    // Kirim barang dan selesaikan pengiriman (DO completed) -> memicu pencatatan jurnal HPP & pengurangan persediaan
    $sch->update(['status' => 'on_the_way']);
    $sch->update(['status' => 'delivered']);

    $entries = DB::table('journal_entries')->where('reference', $do->do_number)->get();
    expect($entries)->isNotEmpty();

    $sumDebit = (float) $entries->sum('debit');
    $sumCredit = (float) $entries->sum('credit');

    expect(abs($sumDebit - $sumCredit))->toBeLessThan(0.01)
        ->and($sumDebit)->toBeGreaterThan(0);
});
