<?php

use App\Exceptions\DeliveryOrderTransitionException;
use App\Models\DeliveryOrder;
use App\Models\InventoryStock;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Services\DeliveryOrderTransitions;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['sales.stock' => [
        'ledger' => false,
        'strict_dispatch' => true,
        'reserve_on_so_approve' => false,
        'block_short_approval' => true,
    ]]);
});

it('CC-01: rebutan stok terakhir tidak boleh menghasilkan stok fisik negatif', function () {
    $ctx = stkContext();
    // Hanya ada 5 pcs stok fisik
    stkSetStock($ctx['product'], $ctx['warehouse'], 5);
    [$so, $soItem] = stkSaleOrder($ctx, 10);

    // Dua DO bersaing, masing-masing meminta 5 pcs
    $doA = stkDeliveryOrder($ctx, $so, $soItem, 5, 'approved');
    $schA = stkSchedule($ctx, $doA);

    // DO A berhasil dikirim -> 5 pcs fisik keluar
    $schA->update(['status' => 'on_the_way']);
    expect($doA->fresh()->status)->toBe('sent')
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(0.0);

    // DO B mencoba dikirim saat stok fisik sudah 0
    $doB = stkDeliveryOrder($ctx, $so, $soItem, 5, 'request_stock');
    $schB = stkSchedule($ctx, $doB);

    // Pengiriman DO B wajib ditolak karena stok fisik habis / DO belum siap kirim
    expect(function () use ($schB) {
        $schB->update(['status' => 'on_the_way']);
    })->toThrow(\Exception::class);

    // Verifikasi juga jika DO B dipaksa transisi ke sent saat stok fisik 0, wajib dilempar exception
    expect(function () use ($doB) {
        app(DeliveryOrderTransitions::class)->to($doB->fresh(), 'sent');
    })->toThrow(\Exception::class);

    // Pastikan stok fisik TIDAK PERNAH bernilai negatif
    $finalStock = stkStock($ctx['product'], $ctx['warehouse']);
    expect($finalStock['available'])->toBeGreaterThanOrEqual(0.0);
});

it('CC-02: eksekusi persetujuan berulang (idempotensi) tidak menggandakan pemotongan atau reservasi', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $soItem] = stkSaleOrder($ctx, 10);

    $do = stkDeliveryOrder($ctx, $so, $soItem, 8, 'approved');

    $firstReserved = stkReserved(null, $do);
    expect($firstReserved)->toBe(8.0);

    // Panggil update status 'approved' sekali lagi (simulasi klik ganda)
    $do->update(['status' => 'approved']);

    // Jumlah cadangan tetap harus tepat 8, tidak boleh menjadi 16
    expect(stkReserved(null, $do->fresh()))->toBe(8.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(8.0);
});

it('CC-03: stok hasil retur pelanggan langsung tersedia aman untuk pengiriman DO berikutnya', function () {
    $ctx = stkContext();
    // Awalnya stok fisik 0
    stkSetStock($ctx['product'], $ctx['warehouse'], 0);

    // Simulasi penerimaan barang retur fisik ke gudang (10 pcs)
    $stock = InventoryStock::withoutGlobalScopes()
        ->where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->first();

    $stock->increment('qty_available', 10);
    StockMovement::create([
        'product_id' => $ctx['product']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'quantity' => 10,
        'type' => 'customer_return',
        'value' => 50000,
        'date' => now()->toDateString(),
        'notes' => 'Uji retur customer',
    ]);

    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(10.0);

    // Sekarang DO baru dapat mengalokasikan stok retur tersebut
    [$so, $soItem] = stkSaleOrder($ctx, 7);
    $do = stkDeliveryOrder($ctx, $so, $soItem, 7, 'approved');
    $schedule = stkSchedule($ctx, $do);

    $schedule->update(['status' => 'on_the_way']);
    expect($do->fresh()->status)->toBe('sent');

    // Sisa fisik harus tepat 3
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(3.0);
});
