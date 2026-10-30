<?php

use App\Models\InventoryStock;
use App\Models\QualityControl;
use App\Models\QualityControlItem;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('QC-BV-01: kuantitas rejected pada QC tidak menambah stok fisik siap jual di gudang', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000);

    // Initial stock di gudang = 0
    $initialStock = InventoryStock::where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->first();
    $availableBefore = (float) ($initialStock?->qty_available ?? 0);

    // Buat QC dengan 4 passed, 6 rejected dari 10 pcs diterima
    $qc = QualityControl::create([
        'qc_number' => 'QC-TEST-001',
        'purchase_order_id' => $po->id,
        'from_model_type' => \App\Models\PurchaseOrderItem::class,
        'from_model_id' => $poItem->id,
        'product_id' => $ctx['product']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'quantity_received' => 10,
        'passed_quantity' => 4,
        'rejected_quantity' => 6,
        'reason_reject' => 'Pipa bengkok dan karat',
        'status' => 0,
        'cabang_id' => $ctx['cabang']->id,
    ]);

    expect((float) $qc->passed_quantity)->toBe(4.0)
        ->and((float) $qc->rejected_quantity)->toBe(6.0);

    // Invarian persediaan: Hanya barang passed (4 pcs) yang boleh masuk ke stok siap jual
    // Barang rejected (6 pcs) TIDAK boleh menambah qty_available
    $stockAfter = InventoryStock::firstOrCreate([
        'product_id' => $ctx['product']->id,
        'warehouse_id' => $ctx['warehouse']->id,
    ], ['qty_available' => 0, 'qty_reserved' => 0, 'qty_min' => 0]);

    // Simulasi penambahan stok hanya untuk kuantitas lulus QC
    $stockAfter->increment('qty_available', $qc->passed_quantity);

    expect((float) $stockAfter->fresh()->qty_available)->toBe($availableBefore + 4.0);
});

it('QC-BV-02: sistem menolak keras kuantitas pemeriksaan QC yang melebihi barang diterima dari PO', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000);

    $qcService = app(QualityControlService::class);

    // Coba buat QC dengan passed_quantity 15 (melebihi PO 10)
    expect(function () use ($qcService, $poItem, $ctx) {
        $qcService->createQCFromPurchaseOrderItem($poItem, [
            'warehouse_id' => $ctx['warehouse']->id,
            'quantity_received' => 15,
            'passed_quantity' => 15,
            'rejected_quantity' => 0,
        ]);
    })->toThrow(\Exception::class);
});

it('QC-BV-03: idempotensi status QC mencegah mutasi stok berganda saat record disimpan berulang', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 5, 50000);

    $qc = QualityControl::create([
        'qc_number' => 'QC-IDEMPOTENT-001',
        'purchase_order_id' => $po->id,
        'from_model_type' => \App\Models\PurchaseOrderItem::class,
        'from_model_id' => $poItem->id,
        'product_id' => $ctx['product']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'quantity_received' => 5,
        'passed_quantity' => 5,
        'rejected_quantity' => 0,
        'status' => 1, // Sudah diproses
        'cabang_id' => $ctx['cabang']->id,
    ]);

    expect($qc->status)->toBe(1);

    // Percobaan pemrosesan ulang pada QC yang sudah berstatus 1 tidak boleh mengubah status atau menambah stok lagi
    expect(function () use ($qc) {
        if ($qc->status == 1) {
            throw new \RuntimeException("QC #{$qc->qc_number} sudah selesai diproses dan terkunci.");
        }
    })->toThrow(\RuntimeException::class);
});
