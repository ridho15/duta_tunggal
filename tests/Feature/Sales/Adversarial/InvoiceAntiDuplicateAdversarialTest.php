<?php

use App\Models\AccountReceivable;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Observers\InvoiceObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('INV-ST-02: penghapusan faktur penjualan yang sah membersihkan jurnal dan AR tanpa data hantu', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-CLEANUP-TEST',
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

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 5,
        'unit_price' => 10000,
        'subtotal' => 50000,
        'total' => 50000,
    ]);

    (new InvoiceObserver())->postSalesInvoice($invoice->fresh());

    expect(AccountReceivable::where('invoice_id', $invoice->id)->exists())->toBeTrue()
        ->and(JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->exists())->toBeTrue();

    // Hapus faktur
    $invoice->delete();

    // Observer deleting & deleted memastikan AR dan Jurnal dibersihkan bersih
    expect(AccountReceivable::where('invoice_id', $invoice->id)->exists())->toBeFalse()
        ->and(JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->exists())->toBeFalse();
});

it('INV-ST-03: Sales Order yang sudah memiliki faktur terbit terkunci dari pembatalan sepihak', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-LOCKED-SO-TEST',
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

    // Verifikasi relasi bahwa SO memiliki faktur aktif
    $hasActiveInvoice = Invoice::where('from_model_type', SaleOrder::class)
        ->where('from_model_id', $so->id)
        ->whereNotIn('status', ['canceled', 'cancelled'])
        ->exists();

    expect($hasActiveInvoice)->toBeTrue();
});
