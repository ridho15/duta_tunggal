<?php

/**
 * Cek ulang 09 Nov 2026 butir 3: DO yang dibuat dengan aturan lama (stok fisik dipotong saat DO dibuat) dikonversi sekali menjadi
 * kunci stok — stok fisik kembali, kolom Terpesan terisi, stok bebas tidak berubah. DO yang sudah berangkat tidak disentuh.
 */

use App\Models\DeliveryOrder;
use App\Models\StockMovement;
use App\Models\StockReservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/** Ubah DO yang baru dibuat (terkunci) menjadi keadaan warisan aturan lama: stok fisik terpotong, tanpa reservasi DO. */
function legacyDeducted(DeliveryOrder $do, array $ctx, float $qty): void
{
    StockReservation::where('delivery_order_id', $do->id)->get()->each->delete();
    app(\App\Services\ProductService::class)->createStockMovement(
        product_id: $ctx['product']->id, warehouse_id: $ctx['warehouse']->id, quantity: $qty, type: 'sales', date: now()->toDateString(),
        notes: "Pemotongan stok saat DO {$do->do_number} dibuat", rak_id: null, fromModel: $do->deliveryOrderItem->first(), value: 0,
        meta: ['do_created_deduction' => true]
    );
}

it('dry-run melaporkan DO terbuka yang stoknya terpotong tanpa mengubah apa pun; --apply mengembalikan fisik dan mengunci', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);

    $open = stkDeliveryOrder($ctx, $so, $item, 5, 'request_stock');       // "Menunggu Konfirmasi Stok"
    legacyDeducted($open, $ctx, 5);
    $shipped = stkDeliveryOrder($ctx, $so, $item, 4, 'approved');
    $shipped->update(['status' => 'sent']);                                 // sudah berangkat: memang terpotong

    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 21.0, 'reserved' => 0.0, 'free' => 21.0]);
    $movements = StockMovement::count();

    Artisan::call('stock:convert-open-do-deductions', ['--no-csv' => true]);
    expect(Artisan::output())->toContain('[DRY-RUN')->toContain('1 DO')->toContain($open->do_number)->not->toContain($shipped->do_number)
        ->and(StockMovement::count())->toBe($movements)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(21.0);

    Artisan::call('stock:convert-open-do-deductions', ['--apply' => true, '--no-csv' => true]);

    // Fisik 30 − 4 (dikirim) = 26; DO terbuka mengunci 5 → bebas tetap 21
    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 26.0, 'reserved' => 5.0, 'free' => 21.0])
        ->and(stkReserved(null, $open))->toBe(5.0)
        ->and(StockMovement::where('type', 'adjustment_in')->count())->toBe(1);

    // Idempoten
    Artisan::call('stock:convert-open-do-deductions', ['--apply' => true, '--no-csv' => true]);
    expect(Artisan::output())->toContain('0 DO')
        ->and(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 26.0, 'reserved' => 5.0, 'free' => 21.0]);

    // Setelah konversi, DO dikirim memotong sekali
    $open->fresh()->update(['status' => 'approved']);
    $open->fresh()->update(['status' => 'sent']);
    expect(stkStock($ctx['product'], $ctx['warehouse']))->toBe(['available' => 21.0, 'reserved' => 0.0, 'free' => 21.0]);
});

it('--do membatasi konversi ke satu DO', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 20);
    $a = stkDeliveryOrder($ctx, $so, $item, 3);
    legacyDeducted($a, $ctx, 3);
    $b = stkDeliveryOrder($ctx, $so, $item, 2);
    legacyDeducted($b, $ctx, 2);

    Artisan::call('stock:convert-open-do-deductions', ['--apply' => true, '--no-csv' => true, '--do' => $a->do_number]);

    expect(stkReserved(null, $a))->toBe(3.0)
        ->and(stkReserved(null, $b))->toBe(0.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(28.0);
});
