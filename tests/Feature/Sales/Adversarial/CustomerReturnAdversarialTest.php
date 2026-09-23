<?php

use App\Models\AccountReceivable;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SaleOrder;
use App\Services\CustomerReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('RET-BV-01: menolak kuantitas retur pelanggan yang melebihi jumlah pembelian pada faktur', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-RET-OVER-TEST',
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

    $invItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 5,
        'unit_price' => 10000,
        'subtotal' => 50000,
        'total' => 50000,
    ]);

    // Buat Customer Return dengan kuantitas 8 pcs (melebihi 5 pcs)
    $return = CustomerReturn::create([
        'return_number' => 'RET-OVER-001',
        'return_date' => now()->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'invoice_id' => $invoice->id,
        'reason' => 'Barang rusak saat pengiriman',
        'status' => CustomerReturn::STATUS_PENDING,
    ]);

    CustomerReturnItem::create([
        'customer_return_id' => $return->id,
        'invoice_item_id' => $invItem->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 8,
        'decision' => CustomerReturnItem::DECISION_REPLACE,
    ]);

    $service = app(CustomerReturnService::class);

    expect(function () use ($service, $return) {
        $service->processCompletion($return->fresh());
    })->toThrow(\Exception::class);
});

it('RET-BV-02: proteksi anti-double execution menolak pemrosesan ulang pada retur yang sudah selesai', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-RET-DOUBLE-TEST',
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

    $invItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'reason' => 'Barang rusak saat pengiriman',
        'product_id' => $ctx['product']->id,
        'quantity' => 5,
        'unit_price' => 10000,
        'subtotal' => 50000,
        'total' => 50000,
    ]);

    $return = CustomerReturn::create([
        'return_number' => 'RET-DOUBLE-001',
        'return_date' => now()->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'invoice_id' => $invoice->id,
        'reason' => 'Barang rusak saat pengiriman',
        'status' => CustomerReturn::STATUS_COMPLETED,
        'stock_restored_at' => now(), // sudah selesai
    ]);

    CustomerReturnItem::create([
        'customer_return_id' => $return->id,
        'invoice_item_id' => $invItem->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 2,
        'decision' => CustomerReturnItem::DECISION_REPLACE,
    ]);

    $service = app(CustomerReturnService::class);

    // Percobaan proses ulang wajib ditolak keras
    expect(function () use ($service, $return) {
        $service->processCompletion($return->fresh());
    })->toThrow(\Exception::class);
});

it('RET-BV-03: pemrosesan retur pelanggan dengan keputusan credit memotong saldo piutang secara akurat', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-RET-CREDIT-TEST',
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

    $invItem = InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'reason' => 'Barang rusak saat pengiriman',
        'product_id' => $ctx['product']->id,
        'quantity' => 5,
        'unit_price' => 10000,
        'subtotal' => 50000,
        'total' => 50000,
    ]);

    $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
    expect((float) $ar->remaining)->toBe(50000.0);

    $return = CustomerReturn::create([
        'return_number' => 'RET-CREDIT-001',
        'return_date' => now()->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'invoice_id' => $invoice->id,
        'reason' => 'Barang rusak saat pengiriman',
        'status' => CustomerReturn::STATUS_APPROVED,
    ]);

    CustomerReturnItem::create([
        'customer_return_id' => $return->id,
        'invoice_item_id' => $invItem->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 2,
        'decision' => CustomerReturnItem::DECISION_CREDIT, // goods returned & credited against AR
    ]);

    $service = app(CustomerReturnService::class);
    $service->processCompletion($return->fresh());

    expect($return->fresh()->stock_restored_at)->not->toBeNull();

    // Piutang berkurang sebesar 2 * 10.000 = 20.000 -> sisa menjadi 30.000
    $arFresh = $ar->fresh();
    expect((float) $arFresh->remaining)->toBe(30000.0);
});
