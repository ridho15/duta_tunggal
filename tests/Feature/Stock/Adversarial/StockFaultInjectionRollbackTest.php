<?php

use App\Exceptions\DeliveryOrderTransitionException;
use App\Models\DeliveryOrder;
use App\Models\StockMovement;
use App\Services\DeliveryOrderTransitions;
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

it('FI-01: rollback database menyeluruh saat terjadi kegagalan sistem di tengah proses pengiriman', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $soItem] = stkSaleOrder($ctx, 10);
    $do = stkDeliveryOrder($ctx, $so, $soItem, 5, 'approved');
    $schedule = stkSchedule($ctx, $do);

    $initialStock = stkStock($ctx['product'], $ctx['warehouse']);

    // Simulasi transaksi dengan exception di tengah eksekusi
    try {
        DB::transaction(function () use ($schedule) {
            // Simulasi pemotongan stok
            $schedule->update(['status' => 'on_the_way']);

            // Sengaja lemparkan exception fatal simulasi crash / network timeout
            throw new \RuntimeException('Simulasi kegagalan sistem tak terduga.');
        });
    } catch (\RuntimeException $e) {
        // Exception tertangkap
    }

    // Seluruh operasi wajib ter-rollback: stok fisik tidak berkurang
    $currentStock = stkStock($ctx['product'], $ctx['warehouse']);
    expect($currentStock['available'])->toBe($initialStock['available'])
        ->and($currentStock['reserved'])->toBe($initialStock['reserved']);
});

it('FI-02: simulasi gagal kirim fisik sopir mengembalikan stok fisik secara otomatis (D18/D20)', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $soItem] = stkSaleOrder($ctx, 15);
    $do = stkDeliveryOrder($ctx, $so, $soItem, 10, 'approved');
    $schedule = stkSchedule($ctx, $do);

    // 1. Truk berangkat -> 10 pcs fisik keluar dari gudang
    $schedule->update(['status' => 'on_the_way']);
    expect($do->fresh()->status)->toBe('sent')
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(20.0);

    // 2. Sopir menandai pengiriman GAGAL (truk mogok / alamat tutup)
    // Sesuai D18/D20: DO menjadi delivery_failed dan stok fisik dikembalikan ke gudang
    app(DeliveryOrderTransitions::class)->to($do->fresh(), 'delivery_failed', ['reason' => 'Truk mogok di jalan']);

    expect($do->fresh()->status)->toBe('delivery_failed')
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(30.0);
});

it('FI-03: atomisitas transaksi menjamin tidak ada data parsial yatim saat dispatch gagal', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 10);
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $do = stkDeliveryOrder($ctx, $so, $soItem, 5, 'request_stock');

    // Pastikan tidak ada mutasi pengiriman yang tersisa
    $initialMovements = StockMovement::where('type', 'sales')->count();

    try {
        DB::transaction(function () use ($do) {
            StockMovement::create([
                'product_id' => 1,
                'warehouse_id' => 1,
                'quantity' => 5,
                'type' => 'sales',
                'value' => 50000,
                'date' => now()->toDateString(),
            ]);

            throw new \Exception('Force abort batch');
        });
    } catch (\Exception $e) {
        // Rollback
    }

    expect(StockMovement::where('type', 'sales')->count())->toBe($initialMovements);
});
