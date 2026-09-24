<?php

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\StockTransferService;
use Illuminate\Validation\ValidationException;

test('TRF-ST-01: requestTransfer hanya menerima transfer berstatus Draft dan mengubah ke Request', function () {
    $ctx = opnContext();
    $service = app(StockTransferService::class);

    // Draft -> Request berhasil
    $transferDraft = stfCreate($ctx, ['status' => 'Draft']);
    stfItem($transferDraft, $ctx);

    $requested = $service->requestTransfer($transferDraft);
    expect($requested->status)->toBe('Request');

    // Coba requestTransfer lagi pada status yang sudah Request -> ditolak
    expect(fn () => $service->requestTransfer($requested))
        ->toThrow(ValidationException::class);

    // Transfer berstatus Approved -> ditolak
    $transferApproved = stfCreate($ctx, ['status' => 'Approved']);
    stfItem($transferApproved, $ctx);
    expect(fn () => $service->requestTransfer($transferApproved))
        ->toThrow(ValidationException::class);
});

test('TRF-ST-02: approveStockTransfer menolak transfer yang bukan berstatus Request', function () {
    $ctx = opnContext();
    $service = app(StockTransferService::class);

    // Transfer berstatus Draft (belum diajukan)
    $transferDraft = stfCreate($ctx, ['status' => 'Draft']);
    stfItem($transferDraft, $ctx);
    expect(fn () => $service->approveStockTransfer($transferDraft))
        ->toThrow(ValidationException::class);

    // Transfer berstatus Approved (double approval)
    $transferApproved = stfCreate($ctx, ['status' => 'Approved']);
    stfItem($transferApproved, $ctx);
    expect(fn () => $service->approveStockTransfer($transferApproved))
        ->toThrow(ValidationException::class);
});

test('TRF-ST-03: menolak persetujuan transfer jika stok fisik sumber di rak asal kurang dari kuantitas transfer', function () {
    $ctx = opnContext();
    $service = app(StockTransferService::class);

    // Stok sumber hanya ada 3, tapi transfer meminta 10
    opnSetStock($ctx['product'], $ctx['warehouse'], $ctx['rakSource'], 3.0);

    $transfer = stfCreate($ctx, ['status' => 'Draft']);
    stfItem($transfer, $ctx, ['quantity' => 10.0]);

    $service->requestTransfer($transfer);

    expect(fn () => $service->approveStockTransfer($transfer))
        ->toThrow(ValidationException::class);
});

test('TRF-ST-04: menolak persetujuan transfer jika baris stok sumber di rak asal belum terdaftar', function () {
    $ctx = opnContext();
    $service = app(StockTransferService::class);

    // Tanpa opnSetStock sama sekali pada rakSource
    $transfer = stfCreate($ctx, ['status' => 'Draft']);
    stfItem($transfer, $ctx, ['quantity' => 5.0]);

    $service->requestTransfer($transfer);

    expect(fn () => $service->approveStockTransfer($transfer))
        ->toThrow(ValidationException::class);
});
