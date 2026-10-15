<?php

/**
 * T2.6 — spesifikasi alur DO-sentris (menggantikan tiga tes lama InvoiceEditAndDeliveryOrderTest yang memanggil metode
 * "WC → DO otomatis" yang sudah dihapus): DO dibuat (driver/kendaraan boleh kosong), WC dibuat PER ITEM dari DO,
 * DO otomatis Siap Kirim bila semua WC dikonfirmasi dan Ditolak bila ada WC ditolak.
 */

use App\Models\DeliveryOrder;
use App\Models\WarehouseConfirmation;
use App\Services\DeliveryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('DO dapat dibuat tanpa driver/kendaraan (nullable) dan WC dibuat per item dari DO', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 5);
    $do = stkDeliveryOrder($ctx, $so, $item, 3);   // request_stock, tanpa driver & kendaraan

    expect($do->driver_id)->toBeNull()->and($do->vehicle_id)->toBeNull();

    $wcs = app(DeliveryOrderService::class)->createWarehouseConfirmationsForDeliveryOrder($do);

    expect($wcs)->toHaveCount(1);
    $wc = WarehouseConfirmation::where('confirmable_type', DeliveryOrder::class)->where('confirmable_id', $do->id)->firstOrFail();
    $wcItem = $wc->warehouseConfirmationItems()->firstOrFail();
    expect($wc->status)->toBe('request')
        ->and((float) $wcItem->requested_qty)->toBe(3.0)
        ->and($wcItem->warehouse_id)->toBe($ctx['warehouse']->id)
        ->and($do->fresh()->status)->toBe('request_stock');
});

it('semua WC dikonfirmasi → DO otomatis Siap Kirim (approved) tanpa reservasi DO (stok sudah terpotong); WC ditolak → DO Ditolak', function () {
    config(['sales.stock.ledger' => true]);
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);
    [$so, $item] = stkSaleOrder($ctx, 10);
    $do = stkDeliveryOrder($ctx, $so, $item, 4);
    app(DeliveryOrderService::class)->createWarehouseConfirmationsForDeliveryOrder($do);

    WarehouseConfirmation::where('confirmable_id', $do->id)->where('confirmable_type', DeliveryOrder::class)->get()
        ->each(fn ($wc) => $wc->update(['status' => 'confirmed']));

    // Tahap 3: stok fisik terpotong sejak DO dibuat (30 → 26); konfirmasi gudang tidak menahan reservasi DO lagi
    expect($do->fresh()->status)->toBe('approved')
        ->and(stkReserved($so, $do))->toBe(0.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['reserved'])->toBe(0.0);

    // DO lain: WC ditolak gudang → DO Ditolak
    $rejected = stkDeliveryOrder($ctx, $so, $item, 2);
    app(DeliveryOrderService::class)->createWarehouseConfirmationsForDeliveryOrder($rejected);
    WarehouseConfirmation::where('confirmable_id', $rejected->id)->where('confirmable_type', DeliveryOrder::class)->get()
        ->each(fn ($wc) => $wc->update(['status' => 'rejected']));

    expect($rejected->fresh()->status)->toBe('reject')->and(stkReserved($so, $rejected))->toBe(0.0);
});

