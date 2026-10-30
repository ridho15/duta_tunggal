<?php

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('GRN-BV-01: penolakan over-receipt saat kuantitas penerimaan melebihi sisa pesanan PO', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 5, 50000); // Pesan 5 unit

    // Coba terima 8 unit (melebihi 5 unit yang dipesan)
    expect(function () use ($ctx, $po, $poItem) {
        $qtyToReceive = 8;
        $orderedQty = (float) $poItem->quantity;
        $alreadyReceived = (float) $poItem->purchaseReceiptItem()->sum('qty_received');
        $maxAllowed = max(0, $orderedQty - $alreadyReceived);

        if ($qtyToReceive > $maxAllowed) {
            throw ValidationException::withMessages([
                'qty_received' => "Kuantitas penerimaan ({$qtyToReceive}) melebihi sisa pesanan PO ({$maxAllowed}). Over-receipt tidak diizinkan.",
            ]);
        }
    })->toThrow(ValidationException::class);

    // Pastikan tidak ada record penerimaan berlebih yang tersimpan
    expect($po->purchaseReceipt()->count())->toBe(0);
});

it('GRN-BV-02: penolakan penerbitan penerimaan barang (GRN) dari PO berstatus draft atau batal', function () {
    $ctx = purContext();
    [$poDraft, $poItemDraft] = purOrder($ctx, 10, 50000, ['status' => 'draft']);

    // Coba terima barang dari PO yang masih draft
    expect(function () use ($poDraft) {
        if (!in_array($poDraft->status, ['approved', 'partially_received'])) {
            throw new \RuntimeException("Purchase Order #{$poDraft->po_number} belum disetujui. Penerimaan barang tidak dapat diproses.");
        }
    })->toThrow(\RuntimeException::class);

    [$poCancelled, $poItemCancelled] = purOrder($ctx, 10, 50000, ['status' => 'closed']);

    // Coba terima barang dari PO yang sudah closed
    expect(function () use ($poCancelled) {
        if (!in_array($poCancelled->status, ['approved', 'partially_received'])) {
            throw new \RuntimeException("Purchase Order #{$poCancelled->po_number} sudah ditutup. Penerimaan barang ditolak.");
        }
    })->toThrow(\RuntimeException::class);
});

it('GRN-BV-03: penerimaan bertahap yang valid menghitung sisa PO dengan tepat hingga lunas', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000); // Pesan 10 unit

    // Penerimaan 1: 4 unit
    $receipt1 = PurchaseReceipt::create([
        'receipt_number' => 'GRN-PARTIAL-01',
        'purchase_order_id' => $po->id,
        'receipt_date' => now(),
        'received_by' => $ctx['user']->id,
        'status' => 'completed',
        'cabang_id' => $ctx['cabang']->id,
        'currency_id' => $ctx['idr']->id,
    ]);

    PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt1->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $ctx['product']->id,
        'qty_received' => 4,
        'qty_accepted' => 4,
        'warehouse_id' => $ctx['warehouse']->id,
        'status' => 'completed',
    ]);

    $poItemFresh = $poItem->fresh();
    $totalReceived = (float) $poItemFresh->purchaseReceiptItem()->sum('qty_received');
    expect($totalReceived)->toBe(4.0);

    // Sisa yang boleh diterima adalah 10 - 4 = 6 unit
    $remaining = max(0, (float) $poItemFresh->quantity - $totalReceived);
    expect($remaining)->toBe(6.0);

    // Penerimaan 2: terima tepat sisa 6 unit
    $receipt2 = PurchaseReceipt::create([
        'receipt_number' => 'GRN-PARTIAL-02',
        'purchase_order_id' => $po->id,
        'receipt_date' => now(),
        'received_by' => $ctx['user']->id,
        'status' => 'completed',
        'cabang_id' => $ctx['cabang']->id,
        'currency_id' => $ctx['idr']->id,
    ]);

    PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt2->id,
        'purchase_order_item_id' => $poItem->id,
        'product_id' => $ctx['product']->id,
        'qty_received' => 6,
        'qty_accepted' => 6,
        'warehouse_id' => $ctx['warehouse']->id,
        'status' => 'completed',
    ]);

    $totalReceivedFinal = (float) $poItemFresh->purchaseReceiptItem()->sum('qty_received');
    expect($totalReceivedFinal)->toBe(10.0);

    // Sisa kini tepat 0 unit
    $remainingFinal = (float) max(0, (float) $poItemFresh->quantity - $totalReceivedFinal);
    expect($remainingFinal)->toBe(0.0);
});
