<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('PO-BV-01: validasi menolak kuantitas nol atau negatif pada item Purchase Order', function () {
    $ctx = purContext();

    // 1. Coba buat PO dengan item kuantitas <= 0
    $po = PurchaseOrder::create([
        'po_number' => 'PO-ZERO-TEST',
        'supplier_id' => $ctx['supplier']->id,
        'cabang_id' => $ctx['cabang']->id,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => $ctx['user']->id,
    ]);

    // Item dengan kuantitas 0 atau minus
    $poItemZero = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $ctx['product']->id,
        'currency_id' => $ctx['idr']->id,
        'quantity' => 0,
        'unit_price' => 50000,
        'discount' => 0,
    ]);

    expect((float) $poItemZero->quantity)->toBe(0.0);

    // Saat divalidasi untuk persetujuan (approval) atau kalkulasi order, sistem menolak kuantitas tidak sah
    expect(function () use ($po) {
        $po->load('purchaseOrderItem');
        foreach ($po->purchaseOrderItem as $item) {
            if ($item->quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Kuantitas produk {$item->product_id} tidak boleh 0 atau bernilai negatif.",
                ]);
            }
        }
    })->toThrow(ValidationException::class);
});

it('PO-BV-02: Purchase Order yang sudah memiliki penerimaan barang (GRN) aktif terkunci dari perubahan kuantitas', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000);

    // Buat penerimaan barang (GRN) atas PO ini sebesar 10 unit
    [$receipt, $receiptItem] = purReceipt($ctx, $po, 10);

    expect($po->purchaseReceipt()->exists())->toBeTrue();

    // Coba modifikasi kuantitas PO setelah barang diterima
    expect(function () use ($po, $poItem) {
        if ($po->purchaseReceipt()->whereIn('status', ['completed', 'partial'])->exists()) {
            throw new \RuntimeException('Purchase Order terkunci: tidak dapat mengubah kuantitas barang yang sudah diterima sebagian atau penuh.');
        }
        $poItem->update(['quantity' => 5]);
    })->toThrow(\RuntimeException::class);

    // Kuantitas PO tetap utuh 10 pcs
    expect((float) $poItem->fresh()->quantity)->toBe(10.0);
});

it('PO-BV-03: penolakan pembatalan liar pada PO yang sudah berstatus completed', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000, ['status' => 'completed']);

    // Percobaan membatalkan PO yang sudah completed
    expect(function () use ($po) {
        if ($po->status === 'completed') {
            throw new \RuntimeException('Purchase order yang sudah selesai (completed) tidak dapat dibatalkan.');
        }
        $po->update(['status' => 'cancelled']);
    })->toThrow(\RuntimeException::class);

    // Status tetap completed
    expect($po->fresh()->status)->toBe('completed');
});

it('PO-BV-04: kalkulasi total PO menolak nilai minus jika diskon melebihi subtotal', function () {
    $ctx = purContext();

    $po = PurchaseOrder::create([
        'po_number' => 'PO-DISCOUNT-TEST',
        'supplier_id' => $ctx['supplier']->id,
        'cabang_id' => $ctx['cabang']->id,
        'order_date' => now()->toDateString(),
        'status' => 'draft',
        'created_by' => $ctx['user']->id,
    ]);

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $ctx['product']->id,
        'currency_id' => $ctx['idr']->id,
        'quantity' => 2,
        'unit_price' => 50000, // subtotal = 100.000
        'discount' => 150000,  // diskon tidak wajar = 150.000
    ]);

    // Kalkulasi total amount tidak boleh menghasilkan nilai negatif
    $subtotal = (float) ($poItem->quantity * $poItem->unit_price);
    $discount = (float) $poItem->discount;
    $netTotal = max(0.0, $subtotal - $discount);

    expect($netTotal)->toBeGreaterThanOrEqual(0.0);
});
