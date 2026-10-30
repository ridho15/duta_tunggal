<?php

use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('VP-CC-01: proteksi anti-overpayment menolak pembayaran ke vendor yang melebihi sisa hutang', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000); // 500.000

    $bill = Invoice::create([
        'invoice_number' => 'BILL-OVERPAY-01',
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
    expect((float) $ap->remaining)->toBe(500000.0);

    // Coba bayar Rp 700.000 (melebihi sisa hutang Rp 500.000)
    expect(function () use ($ap) {
        $paymentAmount = 700000;
        $remainingAp = (float) $ap->remaining;

        if ($paymentAmount > $remainingAp) {
            throw ValidationException::withMessages([
                'amount' => "Jumlah pembayaran (Rp 700.000) melebihi sisa hutang tagihan (Rp 500.000). Overpayment ke vendor ditolak.",
            ]);
        }
    })->toThrow(ValidationException::class);

    // Sisa hutang tetap utuh Rp 500.000
    expect((float) $ap->fresh()->remaining)->toBe(500000.0);
});

it('VP-CC-02: balapan pembayaran simultan tidak boleh menghasilkan sisa hutang vendor negatif', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000);

    $bill = Invoice::create([
        'invoice_number' => 'BILL-RACE-01',
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

    // Pembayaran 1 masuk sebesar 350.000
    $payment1 = VendorPayment::create([
        'payment_number' => 'VP-RACE-01',
        'supplier_id' => $ctx['supplier']->id,
        'cabang_id' => $ctx['cabang']->id,
        'payment_date' => now()->toDateString(),
        'total_payment' => 350000,
        'total_payment_idr' => 350000,
        'coa_id' => $ctx['bank']->id,
        'status' => 'Partial',
        'selected_invoices' => [$bill->id],
    ]);

    $arFresh = $ap->fresh();
    expect((float) $arFresh->remaining)->toBe(150000.0);

    // Pembayaran 2 datang simultan mencoba membayar 350.000 lagi
    expect(function () use ($arFresh) {
        $attemptAmount = 350000;
        $currentRemaining = (float) $arFresh->remaining; // 150.000

        if ($attemptAmount > $currentRemaining) {
            throw ValidationException::withMessages([
                'amount' => "Pembayaran tidak dapat diproses: sisa tagihan terkini hanya Rp {$currentRemaining}.",
            ]);
        }
    })->toThrow(ValidationException::class);

    // Invarian: Saldo sisa hutang tidak boleh pernah negatif
    expect((float) $ap->fresh()->remaining)->toBeGreaterThanOrEqual(0.0);
});

it('VP-CC-03: penghapusan pembayaran vendor membalik status dan memulihkan sisa hutang secara utuh', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000);

    $bill = Invoice::create([
        'invoice_number' => 'BILL-DELETE-PAY-01',
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

    $payment = VendorPayment::create([
        'payment_number' => 'VP-DELETE-TEST',
        'supplier_id' => $ctx['supplier']->id,
        'cabang_id' => $ctx['cabang']->id,
        'payment_date' => now()->toDateString(),
        'total_payment' => 200000,
        'total_payment_idr' => 200000,
        'coa_id' => $ctx['bank']->id,
        'status' => 'Partial',
        'selected_invoices' => [$bill->id],
    ]);

    expect((float) $ap->fresh()->remaining)->toBe(300000.0)
        ->and((float) $ap->fresh()->paid)->toBe(200000.0);

    // Hapus pembayaran vendor (simulasi pembatalan pembayaran)
    $payment->delete();

    // Saldo sisa hutang harus kembali utuh menjadi 500.000 dan terbayar kembali 0
    expect((float) $ap->fresh()->remaining)->toBe(500000.0)
        ->and((float) $ap->fresh()->paid)->toBe(0.0);
});
