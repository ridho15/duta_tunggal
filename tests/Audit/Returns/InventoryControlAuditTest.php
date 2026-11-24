<?php

/**
 * AUDIT AKUNTANSI — Mutasi antar gudang, Penyesuaian stok, Stock Opname, dan rekonsiliasi Σ(qty × HPP) vs GL persediaan.
 * HPP rata-rata (products.cost_price) = Rp5.000. Keputusan #4B: lebih → Dr Persediaan / Cr HPP; kurang → Dr HPP / Cr Persediaan.
 */

use App\Models\ChartOfAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\StockAdjustmentService;
use App\Services\StockOpnameService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

function audIcLog(string $label, mixed $data): void
{
    fwrite(STDERR, "\n[AUDIT-IC] {$label}: ".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");
}

function audIcJ(string $sourceType, int $id): array
{
    $rows = JournalEntry::withoutGlobalScopes()->with('coa')->where('source_type', $sourceType)->where('source_id', $id)->get();
    $out = $rows->groupBy(fn ($r) => $r->coa->code.' '.$r->coa->name)->map(fn ($g) => ['debit' => round((float) $g->sum('debit'), 2), 'credit' => round((float) $g->sum('credit'), 2)])->all();
    $out['_TOTAL'] = ['debit' => round((float) $rows->sum('debit'), 2), 'credit' => round((float) $rows->sum('credit'), 2)];

    return $out;
}

/** Saldo awal GL persediaan sesuai stok awal (agar rekonsiliasi bermakna). */
function audIcOpening(array $ctx, float $qty): void
{
    stkSetStock($ctx['product'], $ctx['warehouse'], $qty);
    $inv = ChartOfAccount::where('code', '1140.10')->first();
    $eq = ChartOfAccount::firstOrCreate(['code' => '3100'], ['name' => 'Modal', 'type' => 'Equity', 'is_active' => true, 'opening_balance' => 0]);
    foreach ([[$inv, $qty * 5000, 0], [$eq, 0, $qty * 5000]] as [$coa, $d, $c]) {
        JournalEntry::create(['coa_id' => $coa->id, 'date' => now()->subDay()->toDateString(), 'reference' => 'OPENING', 'description' => 'Saldo awal persediaan',
            'debit' => $d, 'credit' => $c, 'journal_type' => 'opening', 'cabang_id' => $ctx['cabang']->id]);
    }
}

function audIcAdjust(array $ctx, string $type, float $diff, ?float $unitCost = null): StockAdjustment
{
    $adj = StockAdjustment::create([
        'adjustment_number' => 'SA-AUD-'.uniqid(), 'adjustment_date' => now()->toDateString(), 'warehouse_id' => $ctx['warehouse']->id,
        'adjustment_type' => $type, 'reason' => 'Audit', 'status' => 'draft', 'created_by' => $ctx['user']->id,
    ]);
    $cur = stkStock($ctx['product'], $ctx['warehouse'])['available'];
    $uc = $unitCost ?? (float) $ctx['product']->fresh()->cost_price;
    StockAdjustmentItem::create([
        'stock_adjustment_id' => $adj->id, 'product_id' => $ctx['product']->id, 'rak_id' => null, 'current_qty' => $cur, 'adjusted_qty' => $cur + $diff,
        'difference_qty' => $diff, 'unit_cost' => $uc, 'difference_value' => round($diff * $uc, 2),   // sama seperti StockAdjustmentController::store
    ]);

    return app(StockAdjustmentService::class)->approveStockAdjustment($adj, $ctx['user']->id);
}

function audIcOpname(array $ctx, float $physical): StockOpname
{
    $op = StockOpname::create(['opname_number' => StockOpname::generateOpnameNumber(), 'opname_date' => now()->toDateString(), 'warehouse_id' => $ctx['warehouse']->id,
        'status' => 'draft', 'created_by' => $ctx['user']->id]);
    $svc = app(StockOpnameService::class);
    $svc->startPhysicalCount($op);
    foreach ($op->items()->get() as $item) {
        $item->update(['physical_qty' => $item->product_id === $ctx['product']->id ? $physical : $item->system_qty]);
    }
    $svc->completePhysicalCount($op->fresh());

    return $svc->approveStockOpname($op->fresh(), $ctx['user']->id);
}

it('ST-1 mutasi antar gudang (satu cabang): qty total tetap, tanpa dampak GL, nilai mutasi = HPP', function () {
    $ctx = stkContext();
    $w2 = stkWarehouse($ctx, 'Gudang Kedua');
    audIcOpening($ctx, 20);
    $st = StockTransfer::create(['transfer_number' => 'ST-AUD-'.uniqid(), 'from_warehouse_id' => $ctx['warehouse']->id, 'to_warehouse_id' => $w2->id,
        'transfer_date' => now()->toDateString(), 'status' => 'Draft']);
    StockTransferItem::create(['stock_transfer_id' => $st->id, 'product_id' => $ctx['product']->id, 'quantity' => 5, 'from_warehouse_id' => $ctx['warehouse']->id,
        'to_warehouse_id' => $w2->id]);
    $svc = app(StockTransferService::class);
    $svc->requestTransfer($st->fresh());
    $svc->approveStockTransfer($st->fresh());
    stkCompleteTransfer($st);   // alur 4 tahap: kirim semua & terima

    $mv = StockMovement::where('from_model_type', StockTransfer::class)->where('from_model_id', $st->id)->get(['type', 'warehouse_id', 'quantity', 'value']);
    audIcLog('ST-1 mutasi', $mv->toArray());
    audIcLog('ST-1 stok', ['G1' => stkStock($ctx['product'], $ctx['warehouse']), 'G2' => stkStock($ctx['product'], $w2)]);
    audIcLog('ST-1 jurnal', audIcJ(StockTransfer::class, $st->id));

    expect(stkStock($ctx['product'], $ctx['warehouse'])['available'] + stkStock($ctx['product'], $w2)['available'])->toBe(20.0)
        ->and(audIcJ(StockTransfer::class, $st->id)['_TOTAL']['debit'])->toBe(0.0)
        ->and($mv->pluck('value')->map(fn ($v) => (float) $v)->all())->toBe([25000.0, 25000.0, 25000.0, 25000.0]);   // asal → transit → tujuan
});

it('ST-2 mutasi antar CABANG: persediaan GL per cabang harus ikut berpindah', function () {
    $ctx = stkContext();
    $cabangB = \App\Models\Cabang::factory()->create(['kode' => 'CB-'.uniqid(), 'nama' => 'Cabang B', 'status' => 1]);
    $wB = stkWarehouse($ctx, 'Gudang Cabang B', $cabangB);
    audIcOpening($ctx, 20);
    $st = StockTransfer::create(['transfer_number' => 'ST-AUD-'.uniqid(), 'from_warehouse_id' => $ctx['warehouse']->id, 'to_warehouse_id' => $wB->id,
        'transfer_date' => now()->toDateString(), 'status' => 'Draft']);
    StockTransferItem::create(['stock_transfer_id' => $st->id, 'product_id' => $ctx['product']->id, 'quantity' => 5, 'from_warehouse_id' => $ctx['warehouse']->id,
        'to_warehouse_id' => $wB->id]);
    $svc = app(StockTransferService::class);
    $svc->requestTransfer($st->fresh());
    $svc->approveStockTransfer($st->fresh());
    stkCompleteTransfer($st);   // alur 4 tahap: kirim semua & terima

    $inv = ChartOfAccount::where('code', '1140.10')->first();
    $glA = (float) JournalEntry::withoutGlobalScopes()->where('coa_id', $inv->id)->where('cabang_id', $ctx['cabang']->id)->selectRaw('SUM(debit-credit) s')->value('s');
    $glB = (float) JournalEntry::withoutGlobalScopes()->where('coa_id', $inv->id)->where('cabang_id', $cabangB->id)->selectRaw('SUM(debit-credit) s')->value('s');
    audIcLog('ST-2 GL persediaan per cabang vs stok', ['GL_cabang_A' => $glA, 'stok_A_x_HPP' => stkStock($ctx['product'], $ctx['warehouse'])['available'] * 5000,
        'GL_cabang_B' => $glB, 'stok_B_x_HPP' => stkStock($ctx['product'], $wB)['available'] * 5000]);
    expect($glB)->toBe(stkStock($ctx['product'], $wB)['available'] * 5000);
});

it('ADJ-1 penyesuaian lebih 5 & kurang 3 @HPP: arah #4B, nilai = qty × HPP, seimbang', function () {
    $ctx = stkContext();
    audIcOpening($ctx, 20);
    $up = audIcAdjust($ctx, 'increase', 5);
    $down = audIcAdjust($ctx, 'decrease', -3);
    $jUp = audIcJ(StockAdjustment::class, $up->id);
    $jDown = audIcJ(StockAdjustment::class, $down->id);
    audIcLog('ADJ-1 lebih', $jUp);
    audIcLog('ADJ-1 kurang', $jDown);
    expect($jUp['1140.10 Persediaan'])->toBe(['debit' => 25000.0, 'credit' => 0.0])
        ->and($jUp['5100.10 HPP'])->toBe(['debit' => 0.0, 'credit' => 25000.0])
        ->and($jDown['5100.10 HPP'])->toBe(['debit' => 15000.0, 'credit' => 0.0])
        ->and($jDown['1140.10 Persediaan'])->toBe(['debit' => 0.0, 'credit' => 15000.0])
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(22.0);
});

it('ADJ-2 unit_cost input user ≠ HPP rata-rata: nilai jurnal harus tetap qty × HPP (5.000)', function () {
    $ctx = stkContext();
    audIcOpening($ctx, 20);
    $up = audIcAdjust($ctx, 'increase', 5, 8000);
    $j = audIcJ(StockAdjustment::class, $up->id);
    audIcLog('ADJ-2 jurnal (unit_cost 8.000, HPP 5.000)', $j);
    expect($j['1140.10 Persediaan']['debit'])->toBe(25000.0);
});

it('OPN-1 opname kurang 2 (bagan akun standar ada 1100 AKTIVA LANCAR): Dr HPP / Cr Persediaan produk 10.000', function () {
    $ctx = stkContext();
    ChartOfAccount::firstOrCreate(['code' => '1100'], ['name' => 'AKTIVA LANCAR', 'type' => 'Asset', 'is_active' => true, 'opening_balance' => 0]);
    audIcOpening($ctx, 20);
    $op = audIcOpname($ctx, 18);
    $j = audIcJ(StockOpname::class, $op->id);
    audIcLog('OPN-1 item', $op->items()->where('product_id', $ctx['product']->id)->first()->only(['system_qty', 'physical_qty', 'unit_cost', 'difference_value']));
    audIcLog('OPN-1 jurnal', $j);
    expect($j['_TOTAL']['debit'])->toBe($j['_TOTAL']['credit'])
        ->and($j['5100.10 HPP'] ?? null)->toBe(['debit' => 10000.0, 'credit' => 0.0])
        ->and($j['1140.10 Persediaan'] ?? null)->toBe(['debit' => 0.0, 'credit' => 10000.0])
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(18.0);
});

it('OPN-2 produk punya riwayat penerimaan pembelian: nilai opname harus HPP rata-rata (5.000), jurnal tidak boleh hilang', function () {
    $ctx = stkContext();
    audIcOpening($ctx, 20);
    $receipt = \App\Models\PurchaseReceipt::withoutEvents(fn () => \App\Models\PurchaseReceipt::create([
        'receipt_number' => 'GRN-AUD-'.uniqid(), 'receipt_date' => now()->subDays(3), 'received_by' => $ctx['user']->id, 'status' => 'completed',
        'cabang_id' => $ctx['cabang']->id, 'currency_id' => $ctx['idr']->id,
    ]));
    \App\Models\PurchaseReceiptItem::withoutEvents(fn () => \App\Models\PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt->id, 'product_id' => $ctx['product']->id, 'qty_received' => 20, 'qty_accepted' => 20, 'qty_rejected' => 0,
        'warehouse_id' => $ctx['warehouse']->id, 'status' => 'completed',
    ]));
    $op = audIcOpname($ctx, 18);
    $item = $op->items()->where('product_id', $ctx['product']->id)->first();
    $j = audIcJ(StockOpname::class, $op->id);
    audIcLog('OPN-2 item', $item->only(['system_qty', 'physical_qty', 'unit_cost', 'average_cost', 'difference_value']));
    audIcLog('OPN-2 jurnal', $j);
    audIcLog('OPN-2 stok', stkStock($ctx['product'], $ctx['warehouse']));
    expect((float) $item->unit_cost)->toBe(5000.0)->and($j['_TOTAL']['debit'])->toBe(10000.0);
});

it('REKON Σ(qty × HPP) semua gudang = saldo GL persediaan setelah mutasi, penyesuaian, opname', function () {
    $ctx = stkContext();
    $w2 = stkWarehouse($ctx, 'Gudang Kedua');
    audIcOpening($ctx, 20);
    // mutasi 5 ke gudang 2
    $st = StockTransfer::create(['transfer_number' => 'ST-AUD-'.uniqid(), 'from_warehouse_id' => $ctx['warehouse']->id, 'to_warehouse_id' => $w2->id,
        'transfer_date' => now()->toDateString(), 'status' => 'Draft']);
    StockTransferItem::create(['stock_transfer_id' => $st->id, 'product_id' => $ctx['product']->id, 'quantity' => 5, 'from_warehouse_id' => $ctx['warehouse']->id, 'to_warehouse_id' => $w2->id]);
    app(StockTransferService::class)->requestTransfer($st->fresh());
    app(StockTransferService::class)->approveStockTransfer($st->fresh());
    stkCompleteTransfer($st);
    audIcAdjust($ctx, 'increase', 4);         // G1: 15 → 19
    audIcAdjust($ctx, 'decrease', -2);        // G1: 19 → 17
    audIcOpname($ctx, 16);                    // G1: 17 → 16 (kurang 1)

    $qty = (float) InventoryStock::withoutGlobalScopes()->where('product_id', $ctx['product']->id)->sum('qty_available');
    $nilaiStok = $qty * (float) Product::find($ctx['product']->id)->cost_price;
    $invCoaIds = ChartOfAccount::whereIn('code', ['1140.10', '1140.01', '1100'])->pluck('id');
    $gl = JournalEntry::withoutGlobalScopes()->with('coa')->whereIn('coa_id', $invCoaIds)->get()->groupBy(fn ($r) => $r->coa->code)
        ->map(fn ($g) => round((float) $g->sum('debit') - (float) $g->sum('credit'), 2))->all();
    audIcLog('REKON', ['qty_total' => $qty, 'nilai_stok_qty_x_HPP' => $nilaiStok, 'GL_persediaan_per_akun' => $gl, 'GL_total' => array_sum($gl)]);
    expect($qty)->toBe(21.0)->and((float) array_sum($gl))->toBe($nilaiStok);
});
