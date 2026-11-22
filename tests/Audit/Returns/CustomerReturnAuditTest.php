<?php

/**
 * AUDIT AKUNTANSI — Retur Penjualan (CustomerReturn). Hanya membaca/menjalankan alur; tidak mengubah kode aplikasi.
 * Skenario: invoice 10 pcs @ Rp100.000 (DPP 1.000.000, PPN 11% 110.000), HPP snapshot invoice Rp60.000/pcs.
 */

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Models\StockMovement;
use App\Services\CustomerReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function audCrLog(string $label, mixed $data): void
{
    fwrite(STDERR, "\n[AUDIT-CR] {$label}: ".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");
}

/** @return array<string, mixed> */
function audCrSetup(string $invoiceStatus = 'sent', float $arPaid = 0, array $ctxOverrides = []): array
{
    $ctx = stkContext();
    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]);
    $coa('2160.04', 'Deposit Konsumen', 'Liability');
    $coa('1101.02', 'Barang Dalam Perbaikan', 'Asset');
    $ctx['product']->forceFill(['cost_price' => 60000])->saveQuietly();

    $so = SaleOrder::create([
        'so_number' => 'SO-AUD-'.uniqid(), 'customer_id' => $ctx['customer']->id, 'order_date' => now()->subMonth(), 'status' => 'completed',
        'total_amount' => 1110000, 'cabang_id' => $ctx['cabang']->id, 'created_by' => $ctx['user']->id, 'tipe_pengiriman' => 'Kirim Langsung',
    ]);
    $invoice = new Invoice([
        'invoice_number' => 'INV-AUD-'.uniqid(), 'from_model_type' => SaleOrder::class, 'from_model_id' => $so->id,
        'invoice_date' => now()->subMonth(), 'due_date' => now()->addMonth(), 'subtotal' => 1000000, 'tax' => 110000, 'dpp' => 1000000,
        'total' => 1110000, 'status' => $invoiceStatus, 'customer_name' => $ctx['customer']->name, 'cabang_id' => $ctx['cabang']->id, 'ppn_rate' => 11,
    ]);
    $invoice->saveQuietly();
    $invoiceItem = InvoiceItem::create([
        'invoice_id' => $invoice->id, 'product_id' => $ctx['product']->id, 'quantity' => 10, 'price' => 100000, 'discount' => 0,
        'tax_rate' => 11, 'tax_amount' => 110000, 'subtotal' => 1000000, 'total' => 1110000,
    ]);
    // Snapshot HPP saat invoice diposting (InvoiceObserver menyimpan cost_price produk saat itu)
    InvoiceItem::withoutEvents(fn () => $invoiceItem->forceFill(['cost_price' => 60000, 'cogs_amount' => 600000])->save());

    $ar = new AccountReceivable([
        'invoice_id' => $invoice->id, 'customer_id' => $ctx['customer']->id, 'total' => 1110000, 'paid' => $arPaid, 'remaining' => 1110000 - $arPaid,
        'status' => $arPaid >= 1110000 ? 'Lunas' : 'Belum Lunas', 'cabang_id' => $ctx['cabang']->id, 'created_by' => $ctx['user']->id,
    ]);
    $ar->saveQuietly();

    stkSetStock($ctx['product'], $ctx['warehouse'], 0);

    return array_merge($ctx, compact('invoice', 'invoiceItem', 'ar'));
}

function audCrReturn(array $ctx, string $decision, float $qty = 3): CustomerReturn
{
    $cr = CustomerReturn::create([
        'return_number' => CustomerReturn::generateReturnNumber(), 'invoice_id' => $ctx['invoice']->id, 'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id, 'warehouse_id' => $ctx['warehouse']->id, 'return_date' => now(), 'reason' => 'Audit',
        'status' => CustomerReturn::STATUS_APPROVED, 'received_by' => $ctx['user']->id, 'received_at' => now(), 'qc_inspected_by' => $ctx['user']->id,
        'qc_inspected_at' => now(), 'approved_by' => $ctx['user']->id, 'approved_at' => now(),
    ]);
    CustomerReturnItem::create([
        'customer_return_id' => $cr->id, 'product_id' => $ctx['product']->id, 'invoice_item_id' => $ctx['invoiceItem']->id, 'quantity' => $qty,
        'problem_description' => 'Rusak', 'qc_result' => CustomerReturnItem::QC_RESULT_FAIL, 'decision' => $decision,
    ]);

    return $cr->fresh();
}

/** @return array<string, array{debit: float, credit: float}> */
function audCrJournal(CustomerReturn $cr): array
{
    $rows = JournalEntry::withoutGlobalScopes()->with('coa')->where('source_type', CustomerReturn::class)->where('source_id', $cr->id)->get();
    $out = [];
    foreach ($rows as $r) {
        $k = $r->coa->code;
        $out[$k]['debit'] = round(($out[$k]['debit'] ?? 0) + (float) $r->debit, 2);
        $out[$k]['credit'] = round(($out[$k]['credit'] ?? 0) + (float) $r->credit, 2);
    }
    $out['_TOTAL'] = ['debit' => round($rows->sum('debit'), 2), 'credit' => round($rows->sum('credit'), 2)];

    return $out;
}

it('CR-1 replace, invoice belum lunas: jurnal seimbang, HPP/DPP/PPN/piutang benar, stok +3', function () {
    $ctx = audCrSetup();
    $cr = audCrReturn($ctx, CustomerReturnItem::DECISION_REPLACE);
    app(CustomerReturnService::class)->processCompletion($cr);

    $j = audCrJournal($cr);
    audCrLog('CR-1 jurnal', $j);
    audCrLog('CR-1 AR', $ctx['ar']->fresh()->only(['total', 'paid', 'remaining', 'status']));

    expect($j['_TOTAL']['debit'])->toBe($j['_TOTAL']['credit'])
        ->and($j['1140.10']['debit'])->toBe(180000.0)
        ->and($j['5100.10']['credit'])->toBe(180000.0)
        ->and($j['4120.10']['debit'])->toBe(300000.0)
        ->and($j['2120.06']['debit'])->toBe(33000.0)
        ->and($j['1120']['credit'])->toBe(333000.0)
        ->and((float) $ctx['ar']->fresh()->remaining)->toBe(777000.0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(3.0);

    $mv = StockMovement::where('from_model_type', CustomerReturn::class)->where('from_model_id', $cr->id)->get();
    expect($mv)->toHaveCount(1)->and((float) $mv->first()->value)->toBe(180000.0);
});

it('CR-2 HPP pembalikan harus = HPP snapshot invoice (60.000), bukan cost_price master terkini (75.000)', function () {
    $ctx = audCrSetup();
    // Setelah invoice, rata-rata bergerak naik karena pembelian baru
    $ctx['product']->forceFill(['cost_price' => 75000])->saveQuietly();
    $cr = audCrReturn($ctx, CustomerReturnItem::DECISION_REPLACE);
    app(CustomerReturnService::class)->processCompletion($cr);

    $j = audCrJournal($cr);
    audCrLog('CR-2 jurnal (HPP master 75rb, snapshot invoice 60rb)', $j);
    // HPP yang diakui saat jual = 3 × 60.000 = 180.000
    expect($j['5100.10']['credit'])->toBe(180000.0);
});

it('CR-3 produk tanpa cost_price: jurnal persediaan tetap harus seimbang', function () {
    $ctx = audCrSetup();
    $ctx['product']->forceFill(['cost_price' => 0])->saveQuietly();
    $cr = audCrReturn($ctx, CustomerReturnItem::DECISION_REPLACE);
    app(CustomerReturnService::class)->processCompletion($cr);

    $j = audCrJournal($cr);
    audCrLog('CR-3 jurnal (cost_price 0)', $j);
    expect($j['_TOTAL']['debit'])->toBe($j['_TOTAL']['credit']);
});

it('CR-4 repair: kartu stok vs stok fisik, akun WIP, dan pembalikan pendapatan', function () {
    $ctx = audCrSetup();
    $cr = audCrReturn($ctx, CustomerReturnItem::DECISION_REPAIR);
    app(CustomerReturnService::class)->processCompletion($cr);

    $j = audCrJournal($cr);
    $mvQty = (float) StockMovement::where('from_model_type', CustomerReturn::class)->where('from_model_id', $cr->id)->sum('quantity');
    $phys = stkStock($ctx['product'], $ctx['warehouse'])['available'];
    audCrLog('CR-4 jurnal repair', $j);
    audCrLog('CR-4 kartu stok vs fisik', ['movement_in_qty' => $mvQty, 'qty_available' => $phys, 'AR' => $ctx['ar']->fresh()->only(['total', 'remaining'])]);

    expect($j['_TOTAL']['debit'])->toBe($j['_TOTAL']['credit'])
        ->and($mvQty)->toBe($phys);   // kartu barang harus sama dengan stok fisik gudang
});

it('CR-5 reject: tidak ada jurnal, tidak ada stok, piutang tetap', function () {
    $ctx = audCrSetup();
    $cr = audCrReturn($ctx, CustomerReturnItem::DECISION_REJECT);
    app(CustomerReturnService::class)->processCompletion($cr);

    expect(audCrJournal($cr)['_TOTAL'])->toBe(['debit' => 0.0, 'credit' => 0.0])
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(0.0)
        ->and((float) $ctx['ar']->fresh()->remaining)->toBe(1110000.0);
});

it('CR-6 invoice lunas: kelebihan ke Deposit Konsumen, jurnal seimbang, subledger deposit = GL', function () {
    $ctx = audCrSetup('paid', 1110000);
    $cr = audCrReturn($ctx, CustomerReturnItem::DECISION_CREDIT);
    app(CustomerReturnService::class)->processCompletion($cr);

    $j = audCrJournal($cr);
    $dep = Deposit::where('from_model_id', $ctx['customer']->id)->first();
    audCrLog('CR-6 jurnal paid', $j);
    audCrLog('CR-6 deposit/AR', ['deposit' => $dep?->only(['amount', 'remaining_amount']), 'ar' => $ctx['ar']->fresh()->only(['total', 'paid', 'remaining'])]);
    expect($j['_TOTAL']['debit'])->toBe($j['_TOTAL']['credit'])
        ->and($j['2160.04']['credit'])->toBe(333000.0)
        ->and((float) $dep->remaining_amount)->toBe(333000.0);
});

it('CR-7 COA deposit tidak ada: jurnal tidak boleh diposting timpang', function () {
    $ctx = audCrSetup('paid', 1110000);
    ChartOfAccount::where('code', '2160.04')->forceDelete();
    $cr = audCrReturn($ctx, CustomerReturnItem::DECISION_CREDIT);
    try {
        app(CustomerReturnService::class)->processCompletion($cr);
    } catch (\Throwable $e) {
        audCrLog('CR-7 exception', $e->getMessage());
    }
    $j = audCrJournal($cr);
    audCrLog('CR-7 jurnal tanpa COA deposit', $j);
    expect($j['_TOTAL']['debit'])->toBe($j['_TOTAL']['credit']);
});

it('CR-8 Nota Kredit aktif + keputusan credit: sisi keuangan hanya sekali (tidak dobel)', function () {
    config(['sales.controls.credit_notes' => true]);
    $ctx = audCrSetup();
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'approve credit note', 'guard_name' => 'web']);
    $ctx['user']->givePermissionTo('approve credit note');
    $cr = audCrReturn($ctx, CustomerReturnItem::DECISION_CREDIT);
    app(CustomerReturnService::class)->processCompletion($cr);
    $jReturn = audCrJournal($cr);

    $svc = app(\App\Services\CreditNoteService::class);
    $cn = $svc->draftFromReturn($cr->fresh(), $ctx['user']);
    $cn = $svc->issue($cn, $ctx['user']);
    $cnRows = JournalEntry::withoutGlobalScopes()->with('coa')->where('source_type', \App\Models\CreditNote::class)->where('source_id', $cn->id)->get();
    $cnJ = $cnRows->groupBy(fn ($r) => $r->coa->code)->map(fn ($g) => ['d' => (float) $g->sum('debit'), 'c' => (float) $g->sum('credit')]);
    audCrLog('CR-8 jurnal retur (CN aktif)', $jReturn);
    audCrLog('CR-8 jurnal Nota Kredit', $cnJ);
    audCrLog('CR-8 AR', $ctx['ar']->fresh()->only(['total', 'paid', 'remaining']));

    $salesReturnDebit = ($jReturn['4120.10']['debit'] ?? 0) + (float) $cnRows->filter(fn ($r) => $r->debit > 0 && ! in_array($r->coa->code, ['2120.06'], true))->sum('debit');
    expect($jReturn['4120.10'] ?? null)->toBeNull()
        ->and(round($cnRows->sum('debit'), 2))->toBe(round($cnRows->sum('credit'), 2))
        ->and((float) $ctx['ar']->fresh()->remaining)->toBe(777000.0);
});

it('CR-7b COA PPN Keluaran tidak ditemukan: jurnal tidak boleh diposting timpang', function () {
    $ctx = audCrSetup();
    ChartOfAccount::where('code', '2120.06')->forceDelete();
    $cr = audCrReturn($ctx, CustomerReturnItem::DECISION_REPLACE);
    try {
        app(CustomerReturnService::class)->processCompletion($cr);
    } catch (\Throwable $e) {
        audCrLog('CR-7b exception', $e->getMessage());
    }
    $j = audCrJournal($cr);
    audCrLog('CR-7b jurnal tanpa COA PPN', $j);
    expect($j['_TOTAL']['debit'])->toBe($j['_TOTAL']['credit']);
});
