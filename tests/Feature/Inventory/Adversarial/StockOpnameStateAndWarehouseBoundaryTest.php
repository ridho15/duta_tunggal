<?php

use App\Models\Rak;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\StockOpnameService;
use Illuminate\Validation\ValidationException;

test('OPN-ST-01: menolak persetujuan stock opname jika status bukan completed', function () {
    $ctx = opnContext();
    $service = app(StockOpnameService::class);

    // Status draft
    $opnameDraft = opnCreate($ctx, ['status' => 'draft']);
    opnItem($opnameDraft, $ctx);

    expect(fn () => $service->approveStockOpname($opnameDraft, $ctx['user']->id))
        ->toThrow(ValidationException::class);

    // Status in_progress
    $opnameProgress = opnCreate($ctx, ['status' => 'in_progress']);
    opnItem($opnameProgress, $ctx);

    expect(fn () => $service->approveStockOpname($opnameProgress, $ctx['user']->id))
        ->toThrow(ValidationException::class);

    // Status approved
    $opnameApproved = opnCreate($ctx, ['status' => 'approved']);
    opnItem($opnameApproved, $ctx);

    expect(fn () => $service->approveStockOpname($opnameApproved, $ctx['user']->id))
        ->toThrow(ValidationException::class);
});

test('OPN-ST-02: menolak persetujuan stock opname tanpa item', function () {
    $ctx = opnContext();
    $service = app(StockOpnameService::class);

    $opnameEmpty = opnCreate($ctx, ['status' => 'completed']);
    // Tanpa opnItem

    expect(fn () => $service->approveStockOpname($opnameEmpty, $ctx['user']->id))
        ->toThrow(ValidationException::class);
});

test('OPN-ST-03: menolak persetujuan jika rak item tidak sesuai dengan gudang stock opname', function () {
    $ctx = opnContext();
    $service = app(StockOpnameService::class);

    $opname = opnCreate($ctx, [
        'status' => 'completed',
        'warehouse_id' => $ctx['warehouse']->id,
    ]);

    // Pasang rakTarget yang dimiliki oleh warehouseTarget (berbeda dari $ctx['warehouse'])
    opnItem($opname, $ctx, [
        'rak_id' => $ctx['rakTarget']->id,
    ]);

    expect(fn () => $service->approveStockOpname($opname, $ctx['user']->id))
        ->toThrow(ValidationException::class);
});

test('OPN-ST-04: menolak item opname tanpa produk atau tanpa rak', function () {
    $ctx = opnContext();
    $service = app(StockOpnameService::class);

    $opname = opnCreate($ctx, ['status' => 'completed']);

    // Item tanpa rak
    $itemWithoutRak = StockOpnameItem::create([
        'stock_opname_id' => $opname->id,
        'product_id' => $ctx['product']->id,
        'rak_id' => null,
        'system_qty' => 10,
        'physical_qty' => 12,
        'difference_qty' => 2,
        'unit_cost' => 50000,
        'average_cost' => 50000,
        'difference_value' => 100000,
        'total_value' => 600000,
    ]);

    expect(fn () => $service->approveStockOpname($opname, $ctx['user']->id))
        ->toThrow(ValidationException::class);
});
