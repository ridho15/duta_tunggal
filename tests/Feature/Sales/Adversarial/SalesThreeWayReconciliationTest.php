<?php

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Observers\InvoiceObserver;
use App\Services\CustomerReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('SALES-RC-01: invarian jurnal faktur penjualan menjamin keseimbangan debit piutang vs kredit penjualan dan PPN', function () {
    $ctx = stkContext();
    [$so, $soItem] = stkSaleOrder($ctx, 10, [
        'tipe_pajak' => 'exclude',
        'ppn_rate' => 11.0,
    ], [
        'unit_price' => 10000,
        'tipe_pajak' => 'exclude',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-RECON-01',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $so->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 100000,
        'tax' => 11000,
        'ppn_rate' => 11.0,
        'tipe_pajak' => 'exclude',
        'total' => 111000,
    ]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 10,
        'unit_price' => 10000,
        'subtotal' => 100000,
        'tax' => 11000,
        'total' => 111000,
    ]);

    (new InvoiceObserver())->postSalesInvoice($invoice->fresh());

    $entries = JournalEntry::where('source_type', Invoice::class)
        ->where('source_id', $invoice->id)
        ->get();

    expect($entries)->isNotEmpty();

    $sumDebit = (float) $entries->sum('debit');
    $sumCredit = (float) $entries->sum('credit');

    // 1. Debit = Credit mutlak (Penjualan Rp 100k + PPN Rp 11k + HPP Rp 50k = Rp 161.000)
    expect(abs($sumDebit - $sumCredit))->toBeLessThan(0.01)
        ->and($sumDebit)->toBe(161000.0);

    // 2. Debit pada akun Piutang Dagang (1120) tepat 111.000 dan Debit HPP (5100.10) tepat 50.000
    $piutangDebit = (float) $entries->filter(fn ($e) => $e->coa && $e->coa->code === '1120')->sum('debit');
    $hppDebit = (float) $entries->filter(fn ($e) => $e->coa && $e->coa->code === '5100.10')->sum('debit');
    expect($piutangDebit)->toBe(111000.0)
        ->and($hppDebit)->toBe(50000.0);

    // 3. Credit pada Penjualan (4000) tepat 100.000, PPN Keluaran (2120.06) tepat 11.000, dan Barang Terkirim (1140.20) tepat 50.000
    $penjualanCredit = (float) $entries->filter(fn ($e) => $e->coa && $e->coa->code === '4000')->sum('credit');
    $ppnCredit = (float) $entries->filter(fn ($e) => $e->coa && $e->coa->code === '2120.06')->sum('credit');
    $goodsCredit = (float) $entries->filter(fn ($e) => $e->coa && $e->coa->code === '1140.20')->sum('credit');

    expect($penjualanCredit)->toBe(100000.0)
        ->and($ppnCredit)->toBe(11000.0)
        ->and($goodsCredit)->toBe(50000.0);
});

it('SALES-RC-02: invarian jurnal pembayaran pelanggan menjamin saldo kas masuk seimbang dengan piutang berkurang', function () {
    $ctx = stkContext();

    // Pastikan akun Kas Kecil (1111.01) atau Kas Default tersedia
    ChartOfAccount::firstOrCreate(['code' => '1111.01'], [
        'name' => 'Kas Kecil',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    [$so, $soItem] = stkSaleOrder($ctx, 5);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-RECON-PAY-01',
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

    $entries = JournalEntry::where('source_type', CustomerReceipt::class)
        ->where('source_id', $receipt->id)
        ->get();

    expect($entries)->isNotEmpty();

    $sumDebit = (float) $entries->sum('debit');
    $sumCredit = (float) $entries->sum('credit');

    // Invarian akuntansi: Debit Kas == Credit Piutang, selisih 0.00
    expect(abs($sumDebit - $sumCredit))->toBeLessThan(0.01)
        ->and($sumDebit)->toBe(50000.0);
});

it('SALES-RC-03: rekonsiliasi tiga arah sub-ledger AR bernilai tepat terhadap agregat invoice, payment, dan return', function () {
    $ctx = stkContext();
    stkSetStock($ctx['product'], $ctx['warehouse'], 30);

    // Faktur A: Rp 100.000, dibayar Rp 40.000 (sisa Rp 60.000)
    [$soA, $soItemA] = stkSaleOrder($ctx, 10);
    $invA = Invoice::create([
        'invoice_number' => 'INV-RECON-3WAY-A',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $soA->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 100000,
        'tax' => 0,
        'total' => 100000,
    ]);

    $receiptA = CustomerReceipt::create([
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'Cash',
        'total_payment' => 40000,
        'total_payment_idr' => 40000,
        'status' => 'partial',
        'invoice_id' => $invA->id,
        'selected_invoices' => [$invA->id],
    ]);

    // Faktur B: Rp 50.000, diretur 2 pcs @ Rp 10.000 = Rp 20.000 (sisa Rp 30.000)
    [$soB, $soItemB] = stkSaleOrder($ctx, 5);
    $invB = Invoice::create([
        'invoice_number' => 'INV-RECON-3WAY-B',
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'from_model_type' => SaleOrder::class,
        'from_model_id' => $soB->id,
        'status' => Invoice::STATUS_SENT,
        'subtotal' => 50000,
        'tax' => 0,
        'total' => 50000,
    ]);

    $invItemB = InvoiceItem::create([
        'invoice_id' => $invB->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 5,
        'unit_price' => 10000,
        'subtotal' => 50000,
        'total' => 50000,
    ]);

    $returnB = CustomerReturn::create([
        'return_number' => 'RET-RECON-B',
        'return_date' => now()->toDateString(),
        'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'invoice_id' => $invB->id,
        'reason' => 'Barang rusak retur potongan tagihan',
        'status' => CustomerReturn::STATUS_APPROVED,
    ]);

    CustomerReturnItem::create([
        'customer_return_id' => $returnB->id,
        'invoice_item_id' => $invItemB->id,
        'product_id' => $ctx['product']->id,
        'quantity' => 2,
        'decision' => CustomerReturnItem::DECISION_CREDIT,
    ]);

    app(CustomerReturnService::class)->processCompletion($returnB->fresh());

    // Rekonsiliasi Matematika:
    // Saldo Total AR Customer = Sisa Inv A (60.000) + Sisa Inv B (30.000) = 90.000
    $totalArRemaining = (float) AccountReceivable::where('customer_id', $ctx['customer']->id)->sum('remaining');
    $expectedTotal = 60000.0 + 30000.0;

    expect(abs($totalArRemaining - $expectedTotal))->toBeLessThan(0.0001)
        ->and($totalArRemaining)->toBe(90000.0);
});
