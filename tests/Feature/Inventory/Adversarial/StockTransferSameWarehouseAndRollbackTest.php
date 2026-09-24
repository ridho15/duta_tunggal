<?php

use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\StockTransferService;
use Illuminate\Validation\ValidationException;

test('TRF-BV-01: menolak transfer jika gudang dan rak asal sama persis dengan tujuan', function () {
    $ctx = opnContext();
    $service = app(StockTransferService::class);

    $transfer = stfCreate($ctx, [
        'from_warehouse_id' => $ctx['warehouse']->id,
        'to_warehouse_id' => $ctx['warehouse']->id, // Gudang sama
    ]);

    // Rak juga sama persis
    stfItem($transfer, $ctx, [
        'from_warehouse_id' => $ctx['warehouse']->id,
        'to_warehouse_id' => $ctx['warehouse']->id,
        'from_rak_id' => $ctx['rakSource']->id,
        'to_rak_id' => $ctx['rakSource']->id,
    ]);

    expect(fn () => $service->requestTransfer($transfer))
        ->toThrow(ValidationException::class);
});

test('TRF-BV-02: menolak transfer jika kuantitas non-positif atau spesifikasi rak tidak lengkap', function () {
    $ctx = opnContext();
    $service = app(StockTransferService::class);

    // Kasus 1: quantity <= 0
    $transferZero = stfCreate($ctx);
    stfItem($transferZero, $ctx, ['quantity' => 0.0]);
    expect(fn () => $service->requestTransfer($transferZero))
        ->toThrow(ValidationException::class);

    // Kasus 2: transfer tanpa item sama sekali
    $transferEmpty = stfCreate($ctx);
    expect(fn () => $service->requestTransfer($transferEmpty))
        ->toThrow(ValidationException::class);

    // Kasus 3: spesifikasi rak tujuan tidak lengkap (null) pada item in-memory
    $transferNoTargetRak = stfCreate($ctx);
    $itemWithoutRak = new StockTransferItem([
        'stock_transfer_id' => $transferNoTargetRak->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 5.0,
        'from_warehouse_id' => $ctx['warehouse']->id,
        'from_rak_id' => $ctx['rakSource']->id,
        'to_warehouse_id' => $ctx['warehouseTarget']->id,
        'to_rak_id' => null,
    ]);
    $transferNoTargetRak->setRelation('stockTransferItem', collect([$itemWithoutRak]));

    expect(fn () => $service->requestTransfer($transferNoTargetRak))
        ->toThrow(ValidationException::class);
});

test('TRF-BV-03: observer auto-sync memperbarui movement saat item diubah dan membersihkan saat dihapus', function () {
    $ctx = opnContext();
    $service = app(StockTransferService::class);

    // Persiapkan stok sumber yang memadai
    opnSetStock($ctx['product'], $ctx['warehouse'], $ctx['rakSource'], 100.0);

    $transfer = stfCreate($ctx);
    $item = stfItem($transfer, $ctx, ['quantity' => 10.0]);

    $service->requestTransfer($transfer);
    $approvedTransfer = $service->approveStockTransfer($transfer);

    expect($approvedTransfer->status)->toBe('Approved');

    // Pastikan 2 StockMovement terbit: transfer_out dan transfer_in
    $movements = StockMovement::where('from_model_type', StockTransfer::class)
        ->where('from_model_id', $transfer->id)
        ->get();

    expect($movements)->toHaveCount(2);
    expect($movements->where('type', 'transfer_out')->first()->quantity)->toBe(10.0);
    expect($movements->where('type', 'transfer_in')->first()->quantity)->toBe(10.0);

    // Update kuantitas item: Observer otomatis memperbarui mutasi
    $item->update(['quantity' => 15.0]);

    $updatedMovements = StockMovement::where('from_model_type', StockTransfer::class)
        ->where('from_model_id', $transfer->id)
        ->get();

    expect($updatedMovements->where('type', 'transfer_out')->first()->quantity)->toBe(15.0);
    expect($updatedMovements->where('type', 'transfer_in')->first()->quantity)->toBe(15.0);

    // Hapus item: Observer otomatis menghapus mutasi
    $item->delete();

    $remainingMovements = StockMovement::where('from_model_type', StockTransfer::class)
        ->where('from_model_id', $transfer->id)
        ->get();

    expect($remainingMovements)->toHaveCount(0);
});
