<?php

use App\Models\AccountReceivable;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Services\CustomerReceiptCancellation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('SALES-FI-01: rollback menyeluruh saat terjadi kegagalan di tengah proses alokasi multi-invoice', function () {
    $ctx = stkContext();
    [$so1, $soItem1] = stkSaleOrder($ctx, 5);
    [$so2, $soItem2] = stkSaleOrder($ctx, 5);

    $inv1 = Invoice::create([
        'invoice_number' => 'INV-MULTI-01',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so1->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 50000,
        'tax' => 0,
        'total' => 50000,
    ]);

    $inv2 = Invoice::create([
        'invoice_number' => 'INV-MULTI-02',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so2->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 50000,
        'tax' => 0,
        'total' => 50000,
    ]);

    $ar1 = AccountReceivable::where('invoice_id', $inv1->id)->first();
    $ar2 = AccountReceivable::where('invoice_id', $inv2->id)->first();

    expect((float) $ar1->remaining)->toBe(50000.0)
        ->and((float) $ar2->remaining)->toBe(50000.0);

    // Simulasi transaksi alokasi yang melempar exception di tengah jalan (pada invoice ke-2)
    try {
        DB::transaction(function () use ($inv1, $inv2, $ar1, $ar2) {
            // Potong invoice 1
            $ar1->paid = 50000;
            $ar1->remaining = 0;
            $ar1->save();

            // Sengaja lemparkan kegagalan jaringan / crash sebelum invoice 2 selesai
            throw new \RuntimeException('Crash fatal saat alokasi invoice kedua');
        });
    } catch (\RuntimeException $e) {
        // Tangkap exception
    }

    // Invarian atomisitas: seluruh transaksi ter-rollback; saldo invoice 1 TIDAK boleh terpotong
    expect((float) $ar1->fresh()->remaining)->toBe(50000.0)
        ->and((float) $ar1->fresh()->paid)->toBe(0.0)
        ->and((float) $ar2->fresh()->remaining)->toBe(50000.0);
});

it('SALES-FI-02: pembatalan penerimaan pembayaran (Customer Receipt Cancellation) memulihkan piutang', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-CANCEL-PAY-01',
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
        'total_payment' => 50000,
        'total_payment_idr' => 50000,
        'status' => 'paid',
        'invoice_id' => $invoice->id,
        'selected_invoices' => [$invoice->id],
    ]);

    CustomerReceiptItem::create([
        'customer_receipt_id' => $receipt->id,
        'invoice_id' => $invoice->id,
        'amount' => 50000,
        'amount_idr' => 50000,
        'method' => 'Cash',
    ]);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    expect((float) $ar->remaining)->toBe(0.0);

    // Batalkan pembayaran via service resmi CustomerReceiptCancellation
    $cancellation = app(CustomerReceiptCancellation::class);
    expect($cancellation->blocker($receipt))->toBeNull();

    $cancellation->cancel($receipt, 'Pembatalan transaksi salah setor pelanggan');

    // Piutang harus kembali terutang (remaining 50.000, paid 0)
    $arFresh = $ar->fresh();
    expect((float) $arFresh->remaining)->toBe(50000.0)
        ->and((float) $arFresh->paid)->toBe(0.0);
});

it('SALES-FI-03: kegagalan posting transaksi tidak meninggalkan baris parsial yatim', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $initialArCount = AccountReceivable::count();
    $initialJournalCount = JournalEntry::count();

    try {
        DB::transaction(function () use ($ctx, $so) {
            $inv = Invoice::create([
                'invoice_number' => 'INV-FAIL-ATOM-01',
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

            throw new \Exception('Simulasi kegagalan sistem saat batch create');
        });
    } catch (\Exception $e) {
        // Rollback
    }

    expect(AccountReceivable::count())->toBe($initialArCount)
        ->and(JournalEntry::count())->toBe($initialJournalCount);
});
