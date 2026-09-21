<?php

/**
 * T2.0 — skenario probe audit (docs/AUDIT-20-IMPROVEMENT-PENJUALAN.md §2) sebagai regresi PERMANEN.
 *
 * Bagian 1 mengunci perilaku LAMA (semua flag `sales.stock.*` mati) — bukti bahwa flag mati = tidak ada perubahan perilaku.
 * Setiap tugas T2 menambahkan pasangan tes "flag hidup" pada berkas ini/berkas Stock lain.
 */

use App\Models\StockMovement;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => ['ledger' => false, 'strict_dispatch' => false, 'reserve_on_so_approve' => false, 'block_short_approval' => false]]);
});

it('[flag mati] semua flag stok default mati di berkas config', function () {
    $defaults = require config_path('sales.php');

    expect($defaults['stock'])->toBe(['ledger' => false, 'strict_dispatch' => false, 'reserve_on_so_approve' => false, 'block_short_approval' => false]);
});

it('[flag mati] SO qty 35 dengan stok 30 lolos approve tanpa peringatan dan tanpa reservasi (celah usulan 2)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so] = stkSaleOrder($ctx, 35, ['status' => 'request_approve']);

    expect(app(SalesOrderService::class)->approve($so))->toBeTrue();

    expect($so->fresh()->status)->toBe('approved')
        ->and(stkReserved($so))->toBe(0.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 30.0, 'reserved' => 0.0, 'free' => 30.0]);
});

it('[flag mati] reservasi baru terbentuk di tahap DO Approved, bukan SO (usulan 1)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);

    expect(stkReserved($so))->toBe(0.0);

    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    expect(stkReserved($so, $do))->toBe(12.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(12.0);
});

it('[flag mati] jadwal "Tandai Selesai" dari pending menyelesaikan DO dalam satu klik; item DO tetap requested; reservasi yatim (X1)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');
    $schedule = stkSchedule($ctx, $do);

    $schedule->update(['status' => 'delivered']);   // "Tandai Selesai" langsung dari Menunggu

    $do = $do->fresh();
    $stock = stkStock($ctx['product'], $ctx['warehouse']);

    expect($do->status)->toBe('completed')
        ->and($do->deliveryOrderItem->first()->status)->toBe('requested')   // item tak pernah diperbarui (usulan 3)
        ->and($stock['available'])->toBe(18.0)                              // stok fisik keluar sekali (benar)
        ->and($stock['reserved'])->toBe(12.0)                               // reservasi yatim: tidak pernah dilepas (X1)
        ->and($stock['free'])->toBe(6.0)                                    // seharusnya 18
        ->and(StockMovement::where('type', 'sales')->count())->toBe(1);
});

it('[flag mati] DO yang gagal setelah Dikirim tidak mengembalikan stok, dan dikirim ulang memotong stok DUA kali (F6)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $do = stkDeliveryOrder($ctx, $so, $item, 12, 'approved');

    $do->update(['status' => 'sent']);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0);

    $do->update(['status' => 'delivery_failed']);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0);   // tidak kembali

    $do->update(['status' => 'sent']);   // dijadwalkan ulang
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(6.0)     // terpotong ganda
        ->and(StockMovement::where('type', 'sales')->count())->toBe(2);
});
