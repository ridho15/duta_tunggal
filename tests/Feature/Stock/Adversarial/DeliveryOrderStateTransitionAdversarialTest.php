<?php

use App\Exceptions\DeliveryOrderTransitionException;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\StockReservation;
use App\Services\DeliveryOrderTransitions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => [
        'require_surat_jalan' => false,
        'ledger' => false,
        'strict_dispatch' => true,
        'reserve_on_so_approve' => false,
        'block_short_approval' => false,
    ]]);
});

it('ST-01: menolak loncat status ilegal langsung dari request_stock ke completed', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $soItem] = stkSaleOrder($ctx, 10);
    $do = stkDeliveryOrder($ctx, $so, $soItem, 5, 'request_stock');

    // Coba transisi ilegal langsung ke completed
    expect(function () use ($do) {
        app(DeliveryOrderTransitions::class)->to($do, 'completed');
    })->toThrow(DeliveryOrderTransitionException::class);

    expect($do->fresh()->status)->toBe('request_stock');
});

it('ST-02: menolak pembatalan liar (reject) pada DO yang sudah berstatus sent (barang sudah di jalan)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $soItem] = stkSaleOrder($ctx, 15);
    $do = stkDeliveryOrder($ctx, $so, $soItem, 10, 'approved');
    $schedule = stkSchedule($ctx, $do);

    // Kirim barang (stok fisik keluar)
    $schedule->update(['status' => 'on_the_way']);
    expect($do->fresh()->status)->toBe('sent');

    // Coba batalkan DO yang sudah dikirim secara ilegal
    expect(function () use ($do) {
        app(DeliveryOrderTransitions::class)->to($do, 'reject');
    })->toThrow(DeliveryOrderTransitionException::class);

    expect($do->fresh()->status)->toBe('sent');
});

it('ST-03: DO yang sudah completed terkunci dari penambahan item atau manipulasi kuantitas', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $soItem] = stkSaleOrder($ctx, 10);
    $do = stkDeliveryOrder($ctx, $so, $soItem, 10, 'approved');
    $schedule = stkSchedule($ctx, $do);

    $schedule->update(['status' => 'on_the_way']);
    $schedule->update(['status' => 'delivered']);

    expect($do->fresh()->status)->toBe('completed');

    // Coba transisi ilegal lagi setelah completed (status final)
    expect(function () use ($do) {
        app(DeliveryOrderTransitions::class)->to($do->fresh(), 'approved');
    })->toThrow(DeliveryOrderTransitionException::class);

    expect($do->fresh()->status)->toBe('completed');
});

it('ST-04: stok fisik kembali seketika saat DO yang disetujui dibatalkan (closed)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 25);
    [$so, $soItem] = stkSaleOrder($ctx, 15);

    // 1. DO dibuat/Approved -> Tahap 3: stok fisik langsung terpotong (25 → 15); tidak ada reservasi DO yang tertahan
    $do = stkDeliveryOrder($ctx, $so, $soItem, 10, 'approved');
    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 15.0, 'reserved' => 0.0, 'free' => 15.0])
        ->and(stkReserved(null, $do))->toBe(0.0);

    // 2. Transisi langsung ke 'reject' ditolak oleh matriks status (langkah sah pembatalan approved adalah 'closed')
    expect(function () use ($do) {
        app(DeliveryOrderTransitions::class)->to($do, 'reject');
    })->toThrow(DeliveryOrderTransitionException::class);

    // 3. Batalkan DO secara sah via 'closed' dengan alasan -> stok fisik HARUS kembali seketika (25) dan tidak ada reservasi
    app(DeliveryOrderTransitions::class)->to($do, 'closed', ['reason' => 'Pelanggan membatalkan pesanan']);

    expect($do->fresh()->status)->toBe('closed')
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 25.0, 'reserved' => 0.0, 'free' => 25.0])
        ->and(stkReserved(null, $do))->toBe(0.0);
});
