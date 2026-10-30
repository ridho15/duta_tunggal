<?php

use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Services\StockAdjustmentService;
use Illuminate\Validation\ValidationException;

test('ADJ-BV-01: menolak persetujuan stock adjustment jika status bukan draft', function () {
    $ctx = opnContext();
    $service = app(StockAdjustmentService::class);

    // Status approved
    $adjApproved = adjCreate($ctx, ['status' => 'approved']);
    adjItem($adjApproved, $ctx);

    expect(fn () => $service->approveStockAdjustment($adjApproved, $ctx['user']->id))
        ->toThrow(ValidationException::class);

    // Status rejected
    $adjRejected = adjCreate($ctx, ['status' => 'rejected']);
    adjItem($adjRejected, $ctx);

    expect(fn () => $service->approveStockAdjustment($adjRejected, $ctx['user']->id))
        ->toThrow(ValidationException::class);
});

test('ADJ-BV-02: menolak item adjustment dengan selisih kuantitas nol atau hasil akhir negatif', function () {
    $ctx = opnContext();
    $service = app(StockAdjustmentService::class);

    // Kasus 1: difference_qty == 0
    $adjZero = adjCreate($ctx, ['adjustment_type' => 'increase']);
    adjItem($adjZero, $ctx, [
        'current_qty' => 10,
        'adjusted_qty' => 10,
        'difference_qty' => 0.0,
    ]);

    expect(fn () => $service->approveStockAdjustment($adjZero, $ctx['user']->id))
        ->toThrow(ValidationException::class);

    // Kasus 2: adjusted_qty < 0
    $adjNeg = adjCreate($ctx, ['adjustment_type' => 'decrease']);
    adjItem($adjNeg, $ctx, [
        'current_qty' => 5,
        'adjusted_qty' => -2,
        'difference_qty' => -7,
    ]);

    expect(fn () => $service->approveStockAdjustment($adjNeg, $ctx['user']->id))
        ->toThrow(ValidationException::class);
});

test('ADJ-BV-03: menolak ketidaksesuaian arah antara tipe adjustment dan tanda selisih kuantitas', function () {
    $ctx = opnContext();
    $service = app(StockAdjustmentService::class);

    // Tipe increase tapi difference_qty negatif
    $adjIncWithNeg = adjCreate($ctx, ['adjustment_type' => 'increase']);
    adjItem($adjIncWithNeg, $ctx, [
        'current_qty' => 10,
        'adjusted_qty' => 8,
        'difference_qty' => -2,
    ]);

    expect(fn () => $service->approveStockAdjustment($adjIncWithNeg, $ctx['user']->id))
        ->toThrow(ValidationException::class);

    // Tipe decrease tapi difference_qty positif
    $adjDecWithPos = adjCreate($ctx, ['adjustment_type' => 'decrease']);
    adjItem($adjDecWithPos, $ctx, [
        'current_qty' => 10,
        'adjusted_qty' => 15,
        'difference_qty' => 5,
    ]);

    expect(fn () => $service->approveStockAdjustment($adjDecWithPos, $ctx['user']->id))
        ->toThrow(ValidationException::class);
});

test('ADJ-BV-04: menolak pengurangan kuantitas jika stok tidak ditemukan atau stok bebas tidak cukup', function () {
    $ctx = opnContext();
    $service = app(StockAdjustmentService::class);

    // Sub-kasus A: Baris stok tidak ada sama sekali di rak tersebut
    $adjMissingStock = adjCreate($ctx, ['adjustment_type' => 'decrease']);
    adjItem($adjMissingStock, $ctx, [
        'current_qty' => 10,
        'adjusted_qty' => 5,
        'difference_qty' => -5,
    ]);

    expect(fn () => $service->approveStockAdjustment($adjMissingStock, $ctx['user']->id))
        ->toThrow(ValidationException::class);

    // Sub-kasus B: Stok ada 10, tetapi reserved 8 (free_qty hanya 2). Diminta decrease 5!
    opnSetStock($ctx['product'], $ctx['warehouse'], $ctx['rakSource'], 10.0, 8.0);

    $adjInsufficientFree = adjCreate($ctx, ['adjustment_type' => 'decrease']);
    adjItem($adjInsufficientFree, $ctx, [
        'current_qty' => 10,
        'adjusted_qty' => 5,
        'difference_qty' => -5,
    ]);

    expect(fn () => $service->approveStockAdjustment($adjInsufficientFree, $ctx['user']->id))
        ->toThrow(ValidationException::class);
});
