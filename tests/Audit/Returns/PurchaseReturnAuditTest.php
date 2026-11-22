<?php

/**
 * AUDIT AKUNTANSI — Retur Pembelian. PO 10 @ Rp50.000 (PPN 11%), faktur pembelian 555.000; stok gudang 20 pcs, HPP rata-rata 45.000
 * (10 pcs lama @40.000 + 10 pcs baru @50.000). Retur 2 pcs ke supplier.
 */

use App\Models\AccountPayable;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\StockMovement;
use App\Services\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function audPrLog(string $label, mixed $data): void
{
    fwrite(STDERR, "\n[AUDIT-PR] {$label}: ".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");
}

function audPrSetup(float $apPaid = 0, float $discountPct = 0): array
{
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000);
    if ($discountPct > 0) {
        $poItem->forceFill(['discount' => $discountPct])->saveQuietly();
    }
    [$receipt, $receiptItem] = purReceipt($ctx, $po, 10);
    $ctx['product']->forceFill(['cost_price' => 45000])->saveQuietly();
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);

    $net = 50000 * (1 - $discountPct / 100);
    $dpp = 10 * $net;
    $invoice = new Invoice([
        'invoice_number' => 'PINV-AUD-'.uniqid(), 'from_model_type' => PurchaseReceipt::class, 'from_model_id' => $receipt->id,
        'invoice_date' => now(), 'due_date' => now()->addMonth(), 'subtotal' => $dpp, 'tax' => $dpp * 0.11, 'dpp' => $dpp, 'total' => $dpp * 1.11,
        'status' => $apPaid > 0 ? 'paid' : 'unpaid', 'cabang_id' => $ctx['cabang']->id, 'ppn_rate' => 11,
    ]);
    $invoice->saveQuietly();
    $ap = new AccountPayable([
        'invoice_id' => $invoice->id, 'supplier_id' => $ctx['supplier']->id, 'total' => $dpp * 1.11, 'paid' => $apPaid, 'remaining' => $dpp * 1.11 - $apPaid,
        'status' => $apPaid > 0 ? 'Lunas' : 'Belum Lunas', 'cabang_id' => $ctx['cabang']->id, 'created_by' => $ctx['user']->id,
    ]);
    $ap->saveQuietly();

    return array_merge($ctx, compact('po', 'poItem', 'receipt', 'receiptItem', 'invoice', 'ap'));
}

function audPrReturn(array $ctx, float $qty = 2, ?string $notes = null): PurchaseReturn
{
    $pr = PurchaseReturn::create([
        'purchase_receipt_id' => $ctx['receipt']->id, 'return_date' => now()->toDateString(), 'nota_retur' => 'NR-AUD-'.uniqid(),
        'created_by' => $ctx['user']->id, 'status' => 'pending_approval', 'cabang_id' => $ctx['cabang']->id, 'notes' => $notes,
    ]);
    PurchaseReturnItem::create([
        'purchase_return_id' => $pr->id, 'purchase_receipt_item_id' => $ctx['receiptItem']->id, 'product_id' => $ctx['product']->id,
        'qty_returned' => $qty, 'unit_price' => 50000, 'reason' => 'Cacat',
    ]);

    return $pr->fresh();
}

function audPrJournal(PurchaseReturn $pr): array
{
    $rows = JournalEntry::withoutGlobalScopes()->with('coa')->where('source_type', PurchaseReturn::class)->where('source_id', $pr->id)->get();
    $out = $rows->groupBy(fn ($r) => $r->coa->code)->map(fn ($g) => ['debit' => round((float) $g->sum('debit'), 2), 'credit' => round((float) $g->sum('credit'), 2)])->all();
    $out['_TOTAL'] = ['debit' => round((float) $rows->sum('debit'), 2), 'credit' => round((float) $rows->sum('credit'), 2)];

    return $out;
}

it('PR-1 retur gudang, hutang belum lunas: jurnal seimbang, PPN masukan dibalik, stok −2, hutang turun', function () {
    $ctx = audPrSetup();
    $pr = audPrReturn($ctx);
    app(PurchaseReturnService::class)->approve($pr);

    $j = audPrJournal($pr);
    $ap = $ctx['ap']->fresh();
    audPrLog('PR-1 jurnal', $j);
    audPrLog('PR-1 AP', $ap->only(['total', 'paid', 'remaining', 'status']));
    audPrLog('PR-1 stok', stkStock($ctx['product'], $ctx['warehouse']));
    $qty = stkStock($ctx['product'], $ctx['warehouse'])['available'];
    audPrLog('PR-1 rekonsiliasi HPP rata-rata', [
        'GL_persediaan_awal' => 20 * 45000, 'GL_persediaan_akhir' => 20 * 45000 - ($j['1140.10']['credit'] ?? 0),
        'qty_x_cost_price' => $qty * (float) $ctx['product']->fresh()->cost_price, 'cost_price_setelah_retur' => (float) $ctx['product']->fresh()->cost_price,
    ]);

    expect($j['_TOTAL']['debit'])->toBe($j['_TOTAL']['credit'])
        ->and($j['2110']['debit'])->toBe(111000.0)
        ->and($j['1140.10']['credit'])->toBe(100000.0)
        ->and($j['1170.06']['credit'])->toBe(11000.0)
        ->and($qty)->toBe(18.0)
        ->and((float) $ap->remaining)->toBe(444000.0)
        // Rata-rata bergerak: setelah barang @50.000 keluar, nilai buku stok (qty × cost_price) harus = saldo GL persediaan.
        // HPP 800.000/18 = 44.444,44… tidak bisa disimpan eksak (kolom 4 desimal) → dibandingkan pada presisi rupiah-sen.
        ->and(round($qty * (float) $ctx['product']->fresh()->cost_price, 2))->toBe((float) (20 * 45000 - $j['1140.10']['credit']));
});

it('PR-2 hutang sudah lunas: nota debit/piutang supplier harus tercatat di subledger (GL = subledger)', function () {
    $ctx = audPrSetup(555000);
    $pr = audPrReturn($ctx);
    app(PurchaseReturnService::class)->approve($pr);

    $j = audPrJournal($pr);
    $ap = $ctx['ap']->fresh();
    // Saldo GL hutang untuk faktur ini: Cr 555.000 (faktur) − Dr 555.000 (bayar) − Dr retur
    $glApBalance = 555000 - 555000 - $j['2110']['debit'];
    audPrLog('PR-2 jurnal', $j);
    audPrLog('PR-2 AP subledger vs GL', ['subledger_remaining' => (float) $ap->remaining, 'subledger_paid' => (float) $ap->paid, 'GL_saldo_hutang_faktur' => $glApBalance]);
    expect((float) $ap->remaining)->toBe((float) $glApBalance);
});

it('PR-3 PO berdiskon 10%: nilai retur & PPN harus pakai harga neto 45.000, bukan bruto 50.000', function () {
    $ctx = audPrSetup(0, 10);
    $pr = audPrReturn($ctx);
    app(PurchaseReturnService::class)->approve($pr);
    $j = audPrJournal($pr);
    audPrLog('PR-3 jurnal (diskon 10%)', $j);
    expect($j['1140.10']['credit'])->toBe(90000.0)->and($j['1170.06']['credit'])->toBe(9900.0);
});

it('PR-4 retur gudang yang catatannya menyebut "QC" tetap harus menjurnal & mengurangi stok', function () {
    $ctx = audPrSetup();
    $pr = audPrReturn($ctx, 2, 'Cacat ditemukan saat QC ulang di gudang');
    app(PurchaseReturnService::class)->approve($pr);
    $j = audPrJournal($pr);
    audPrLog('PR-4 jurnal (notes mengandung QC)', $j);
    audPrLog('PR-4 stok', stkStock($ctx['product'], $ctx['warehouse']));
    expect($j['_TOTAL']['debit'])->toBe(111000.0)->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0);
});

it('PR-5 retur melebihi stok fisik: tidak boleh membuat stok minus', function () {
    $ctx = audPrSetup();
    stkSetStock($ctx['product'], $ctx['warehouse'], 1);
    $pr = audPrReturn($ctx, 2);
    $err = null;
    try {
        app(PurchaseReturnService::class)->approve($pr);
    } catch (\Throwable $e) {
        $err = $e->getMessage();
    }
    audPrLog('PR-5 hasil', ['error' => $err, 'stok' => stkStock($ctx['product'], $ctx['warehouse']), 'status' => $pr->fresh()->status]);
    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBeGreaterThanOrEqual(0.0);
});

it('PR-6 retur QC (barang belum masuk stok): tanpa jurnal & tanpa mutasi stok', function () {
    $ctx = audPrSetup();
    $pr = audPrReturn($ctx);
    $pr->forceFill(['failed_qc_action' => PurchaseReturn::QC_ACTION_REDUCE_STOCK])->saveQuietly();
    try {
        app(PurchaseReturnService::class)->approve($pr->fresh());
    } catch (\Throwable $e) {
        audPrLog('PR-6 exception', $e->getMessage());
    }
    expect(audPrJournal($pr)['_TOTAL'])->toBe(['debit' => 0.0, 'credit' => 0.0])
        ->and(StockMovement::where('from_model_type', PurchaseReturn::class)->where('from_model_id', $pr->id)->count())->toBe(0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(20.0);
});
