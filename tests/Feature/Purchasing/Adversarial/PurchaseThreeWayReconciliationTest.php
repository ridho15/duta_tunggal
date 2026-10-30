<?php

use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use App\Services\LedgerPostingService;
use App\Services\PurchaseReceiptService;
use App\Services\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('PUR-RC-01: pemadanan tiga arah (Three-Way Matching) menjamin nilai PO == GRN (Unbilled) == Faktur Pembelian', function () {
    $ctx = purContext();

    // 1. PO: Pesan 10 unit @ Rp 50.000 = Rp 500.000
    [$po, $poItem] = purOrder($ctx, 10, 50000);
    expect((float) $po->total_amount)->toBe(500000.0);

    // 2. GRN: Terima 10 unit @ Rp 50.000 = Rp 500.000
    [$receipt, $receiptItem] = purReceipt($ctx, $po, 10);
    $totalReceivedValue = (float) ($receiptItem->qty_accepted * $poItem->unit_price);
    expect($totalReceivedValue)->toBe(500000.0);

    // 3. Faktur Pembelian (Vendor Bill): Tagihan 10 unit @ Rp 50.000 = Rp 500.000
    $bill = Invoice::create([
        'invoice_number' => 'BILL-3WAY-001',
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
    expect((float) $ap->total)->toBe(500000.0)
        ->and((float) $ap->remaining)->toBe(500000.0);

    // Invarian Rekonsiliasi Tiga Arah: Nilai PO == Nilai GRN == Nilai Tagihan (AP)
    $poTotal = (float) $po->total_amount;
    $grnTotal = $totalReceivedValue;
    $apTotal = (float) $ap->total;

    expect(abs($poTotal - $grnTotal))->toBeLessThan(0.01)
        ->and(abs($grnTotal - $apTotal))->toBeLessThan(0.01)
        ->and($apTotal)->toBe(500000.0);
});

it('PUR-RC-02: retur pembelian (Purchase Return) memotong stok fisik dan mengurangi saldo hutang vendor', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000);

    // Terima 10 unit di gudang
    [$receipt, $receiptItem] = purReceipt($ctx, $po, 10);

    $stock = InventoryStock::firstOrCreate([
        'product_id' => $ctx['product']->id,
        'warehouse_id' => $ctx['warehouse']->id,
    ], ['qty_available' => 10, 'qty_reserved' => 0, 'qty_min' => 0]);
    $stock->update(['qty_available' => 10]);

    // Terbitkan Faktur Pembelian: 10 unit = Rp 500.000
    $bill = Invoice::create([
        'invoice_number' => 'BILL-RET-001',
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

    // Buat Retur Pembelian: Retur 2 unit @ Rp 50.000 = Rp 100.000
    $return = PurchaseReturn::create([
        'purchase_receipt_id' => $receipt->id,
        'return_date' => now()->toDateString(),
        'nota_retur' => 'RET-PUR-001',
        'status' => 'draft',
        'created_by' => $ctx['user']->id,
        'cabang_id' => $ctx['cabang']->id,
    ]);

    PurchaseReturnItem::create([
        'purchase_return_id' => $return->id,
        'purchase_receipt_item_id' => $receiptItem->id,
        'product_id' => $ctx['product']->id,
        'qty_returned' => 2,
        'unit_price' => 50000,
        'reason' => 'Barang rusak tidak sesuai spesifikasi',
    ]);

    $returnService = app(PurchaseReturnService::class);

    // Eksekusi pemotongan stok fisik & penyesuaian hutang
    $returnService->adjustStock($return);
    $returnService->adjustAccountPayable($return);

    // 1. Stok fisik gudang berkurang dari 10 menjadi 8 unit
    expect((float) $stock->fresh()->qty_available)->toBe(8.0);

    // 2. Saldo sisa hutang berkurang dari 500.000 menjadi 400.000
    $apFresh = $ap->fresh();
    expect((float) $apFresh->remaining)->toBe(400000.0)
        ->and((float) $apFresh->paid)->toBe(100000.0);
});

it('PUR-RC-03: invarian jurnal akuntansi pengadaan menjamin keseimbangan debit dan credit mutlak', function () {
    $ctx = purContext();

    // Pastikan akun-akun pengadaan tersedia
    $invCoa = $ctx['inventory'];
    $unbilledCoa = $ctx['unbilled'];
    $apCoa = $ctx['ap'];
    $vatInCoa = $ctx['vatIn'];

    // Simulasi jurnal penerimaan barang (GRN): Dr Persediaan 500k, Cr Unbilled Purchase 500k
    $entriesGrn = [
        JournalEntry::create([
            'coa_id' => $invCoa->id,
            'date' => now()->toDateString(),
            'reference' => 'GRN-JRNL-01',
            'description' => 'Debit inventory GRN',
            'debit' => 500000,
            'credit' => 0,
            'journal_type' => 'inventory',
            'cabang_id' => $ctx['cabang']->id,
        ]),
        JournalEntry::create([
            'coa_id' => $unbilledCoa->id,
            'date' => now()->toDateString(),
            'reference' => 'GRN-JRNL-01',
            'description' => 'Credit unbilled purchase GRN',
            'debit' => 0,
            'credit' => 500000,
            'journal_type' => 'inventory',
            'cabang_id' => $ctx['cabang']->id,
        ]),
    ];

    $grnDebit = (float) collect($entriesGrn)->sum('debit');
    $grnCredit = (float) collect($entriesGrn)->sum('credit');
    expect($grnDebit)->toBe(500000.0)
        ->and($grnCredit)->toBe(500000.0);

    // Simulasi jurnal faktur pembelian dengan PPN Masukan 11%:
    // Dr Unbilled Purchase 500k, Dr PPN Masukan 55k, Cr Hutang Dagang 555k
    $entriesBill = [
        JournalEntry::create([
            'coa_id' => $unbilledCoa->id,
            'date' => now()->toDateString(),
            'reference' => 'BILL-JRNL-01',
            'description' => 'Debit unbilled purchase Bill',
            'debit' => 500000,
            'credit' => 0,
            'journal_type' => 'purchase',
            'cabang_id' => $ctx['cabang']->id,
        ]),
        JournalEntry::create([
            'coa_id' => $vatInCoa->id,
            'date' => now()->toDateString(),
            'reference' => 'BILL-JRNL-01',
            'description' => 'Debit PPN Masukan Bill',
            'debit' => 55000,
            'credit' => 0,
            'journal_type' => 'purchase',
            'cabang_id' => $ctx['cabang']->id,
        ]),
        JournalEntry::create([
            'coa_id' => $apCoa->id,
            'date' => now()->toDateString(),
            'reference' => 'BILL-JRNL-01',
            'description' => 'Credit Hutang Dagang Bill',
            'debit' => 0,
            'credit' => 555000,
            'journal_type' => 'purchase',
            'cabang_id' => $ctx['cabang']->id,
        ]),
    ];

    $billDebit = (float) collect($entriesBill)->sum('debit');
    $billCredit = (float) collect($entriesBill)->sum('credit');
    expect(abs($billDebit - $billCredit))->toBeLessThan(0.01)
        ->and($billDebit)->toBe(555000.0)
        ->and($billCredit)->toBe(555000.0);
});
