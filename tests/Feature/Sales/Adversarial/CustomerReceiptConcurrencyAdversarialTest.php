<?php

use App\Models\AccountReceivable;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Services\CustomerReceiptAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('RCP-CC-01: menolak overpayment saat pembayaran pelanggan melebihi sisa piutang faktur', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-OVERPAY-TEST',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 50000,
        'tax' => 0,
        'total' => 50000,
    ]);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    expect((float) $ar->remaining)->toBe(50000.0);

    $allocator = app(CustomerReceiptAllocator::class);

    // Coba bayar Rp 70.000 (lebih Rp 20.000 dari sisa Rp 50.000) tanpa opsi deposit
    expect(function () use ($allocator, $ctx, $invoice) {
        $allocator->plan(
            $ctx['customer']->id,
            [$invoice->id => 70000],
            'Cash',
            false // allowDepositOverpayment = false
        );
    })->toThrow(ValidationException::class);

    // Sisa piutang tetap utuh dan tidak pernah minus
    expect((float) $ar->fresh()->remaining)->toBe(50000.0);
});

it('RCP-CC-02: balapan pembayaran simultan tidak boleh menghasilkan sisa piutang negatif', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 10);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-RACE-PAY-TEST',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 100000,
        'tax' => 0,
        'total' => 100000,
    ]);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    expect((float) $ar->remaining)->toBe(100000.0);

    // Pembayaran 1 sebesar 70.000 berhasil masuk
    $receipt1 = CustomerReceipt::create([
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Cash',
        'total_payment' => 70000,
        'total_payment_idr' => 70000,
        'status' => 'paid',
        'invoice_id' => $invoice->id,
        'selected_invoices' => [$invoice->id],
    ]);

    $arFresh = $ar->fresh();
    expect((float) $arFresh->remaining)->toBe(30000.0);

    // Pembayaran 2 masuk simultan mencoba membayar 70.000 lagi
    // Saat allocator mengevaluasi, sisa tagihan aktual hanya 30.000
    $allocator = app(CustomerReceiptAllocator::class);

    expect(function () use ($allocator, $ctx, $invoice) {
        $allocator->plan(
            $ctx['customer']->id,
            [$invoice->id => 70000],
            'Cash',
            false
        );
    })->toThrow(ValidationException::class);

    // Invarian: Saldo sisa piutang tidak boleh pernah negatif
    expect((float) $ar->fresh()->remaining)->toBeGreaterThanOrEqual(0.0);
});

it('RCP-CC-03: pemanggilan update berulang pada CustomerReceipt tidak menggandakan pemotongan piutang', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-IDEMPOTENT-PAY',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 50000,
        'tax' => 0,
        'total' => 50000,
    ]);

    $receipt = CustomerReceipt::create([
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Cash',
        'total_payment' => 20000,
        'total_payment_idr' => 20000,
        'status' => 'partial',
        'invoice_id' => $invoice->id,
        'selected_invoices' => [$invoice->id],
    ]);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    expect((float) $ar->paid)->toBe(20000.0)
        ->and((float) $ar->remaining)->toBe(30000.0);

    // Panggil update tanpa perubahan nominal (simulasi hook update terpanggil lagi)
    $receipt->update(['notes' => 'Catatan diperbarui']);

    // Nominal terbayar harus tetap tepat 20.000, tidak boleh berganda jadi 40.000
    expect((float) $ar->fresh()->paid)->toBe(20000.0)
        ->and((float) $ar->fresh()->remaining)->toBe(30000.0);
});
