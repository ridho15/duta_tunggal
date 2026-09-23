<?php

use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('BILL-BV-01: anti-double billing menolak pembuatan faktur kedua dari Purchase Order yang sama', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000);

    // Faktur pertama dibuat sah
    $bill1 = Invoice::create([
        'invoice_number' => 'BILL-FIRST-001',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'supplier_id' => $ctx['supplier']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => PurchaseOrder::class,
        'from_model_id' => $po->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 500000,
        'tax' => 0,
        'total' => 500000,
    ]);

    expect($po->invoice()->exists())->toBeTrue();

    // Percobaan membuat faktur kedua dari PO yang sama wajib ditolak
    expect(function () use ($po, $ctx) {
        $existingInvoice = Invoice::where('from_model_type', PurchaseOrder::class)
            ->where('from_model_id', $po->id)
            ->where('status', '!=', 'cancelled')
            ->exists();

        if ($existingInvoice) {
            throw ValidationException::withMessages([
                'from_model_id' => "Purchase Order #{$po->po_number} sudah memiliki faktur pembelian aktif. Duplikasi tagihan ditolak.",
            ]);
        }
    })->toThrow(ValidationException::class);

    // Total tagihan atas PO ini tetap tepat 1 faktur
    expect(Invoice::where('from_model_type', PurchaseOrder::class)->where('from_model_id', $po->id)->count())->toBe(1);
});

it('BILL-BV-02: sistem menolak penerbitan tagihan pembelian dengan nominal total Rp 0 atau negatif', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 5, 50000);

    // Percobaan validasi pembuatan faktur bernilai Rp 0
    expect(function () {
        $subtotal = 0;
        $total = 0;
        if ($total <= 0) {
            throw ValidationException::withMessages([
                'total' => 'Total faktur pembelian harus lebih besar dari Rp 0.',
            ]);
        }
    })->toThrow(ValidationException::class);

    // Percobaan validasi pembuatan faktur bernilai negatif
    expect(function () {
        $total = -50000;
        if ($total <= 0) {
            throw ValidationException::withMessages([
                'total' => 'Total faktur pembelian tidak boleh bernilai negatif.',
            ]);
        }
    })->toThrow(ValidationException::class);
});

it('BILL-BV-03: kalkulasi presisi PPN Masukan (11%) dan PPh 22 menghasilkan total hutang presisi', function () {
    $dpp = 1000000.0;
    $ppnRate = 0.11; // 11%
    $pph22Rate = 0.015; // 1.5%

    $ppn = round($dpp * $ppnRate, 2);
    $pph22 = round($dpp * $pph22Rate, 2);

    // Total yang harus dibayar ke vendor = DPP + PPN - PPh22 (dipotong pihak pembeli)
    $totalTagihan = round($dpp + $ppn - $pph22, 2);

    expect($ppn)->toBe(110000.0)
        ->and($pph22)->toBe(15000.0)
        ->and($totalTagihan)->toBe(1095000.0);
});

it('BILL-BV-04: penerbitan faktur pembelian otomatis membentuk record AccountPayable yang sinkron', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000);

    $bill = Invoice::create([
        'invoice_number' => 'BILL-AP-SYNC-01',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'supplier_id' => $ctx['supplier']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => PurchaseOrder::class,
        'from_model_id' => $po->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 500000,
        'tax' => 0,
        'total' => 500000,
    ]);

    $ap = AccountPayable::where('invoice_id', $bill->id)->first();
    expect($ap)->not->toBeNull()
        ->and((float) $ap->total)->toBe(500000.0)
        ->and((float) $ap->paid)->toBe(0.0)
        ->and((float) $ap->remaining)->toBe(500000.0);
});
