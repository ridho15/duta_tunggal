<?php

/**
 * Keputusan 22 Nov 2026: mutasi stok antar cabang dijurnal per cabang pada nilai HPP lewat akun perantara Rekening Antar Cabang
 * (1180.02). Sejak alur 4 tahap (24 Nov) jurnal dibuat saat barang DIKIRIM, sebesar qty yang keluar dari gudang asal.
 * Mutasi dalam satu cabang tidak dijurnal.
 */

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function ibtTransfer(array $ctx, $toWarehouse, float $qty): StockTransfer
{
    $st = StockTransfer::create(['transfer_number' => 'ST-IBT-'.uniqid(), 'from_warehouse_id' => $ctx['warehouse']->id, 'to_warehouse_id' => $toWarehouse->id,
        'transfer_date' => now()->toDateString(), 'status' => 'Draft']);
    StockTransferItem::create(['stock_transfer_id' => $st->id, 'product_id' => $ctx['product']->id, 'quantity' => $qty,
        'from_warehouse_id' => $ctx['warehouse']->id, 'to_warehouse_id' => $toWarehouse->id]);
    return stkCompleteTransfer($st);
}

/** Saldo bersih (debit − kredit) per kode akun per cabang dari jurnal mutasi. */
function ibtNet(StockTransfer $st, string $code, int $cabangId): float
{
    $coaId = ChartOfAccount::where('code', $code)->value('id');

    return round((float) JournalEntry::withoutGlobalScopes()->where('source_type', StockTransfer::class)->where('source_id', $st->id)
        ->where('coa_id', $coaId)->where('cabang_id', $cabangId)->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) n')->value('n'), 2);
}

beforeEach(function () {
    $this->ctx = stkContext();
    stkSetStock($this->ctx['product'], $this->ctx['warehouse'], 20);
    $this->cabangB = Cabang::factory()->create(['kode' => 'IBT-'.uniqid(), 'nama' => 'Cabang B', 'status' => 1]);
    $this->gudangB = stkWarehouse($this->ctx, 'Gudang Cabang B', $this->cabangB);
});

it('mutasi antar cabang disetujui: persediaan pindah cabang pada HPP lewat Rekening Antar Cabang, tiap cabang seimbang', function () {
    $st = ibtTransfer($this->ctx, $this->gudangB, 5);   // 5 × HPP 5.000
    $a = $this->ctx['cabang']->id;
    $b = $this->cabangB->id;

    expect(ibtNet($st, '1140.10', $a))->toBe(-25000.0)
        ->and(ibtNet($st, '1180.02', $a))->toBe(25000.0)
        ->and(ibtNet($st, '1140.10', $b))->toBe(25000.0)
        ->and(ibtNet($st, '1180.02', $b))->toBe(-25000.0);

    // Laporan gabungan: Rekening Antar Cabang saling meniadakan; total debit = total kredit
    $all = JournalEntry::withoutGlobalScopes()->where('source_type', StockTransfer::class)->where('source_id', $st->id);
    expect(round((float) (clone $all)->sum('debit'), 2))->toBe(round((float) (clone $all)->sum('credit'), 2));
});

it('mutasi dalam satu cabang tidak membuat jurnal', function () {
    $gudangA2 = stkWarehouse($this->ctx, 'Gudang A2');
    $st = ibtTransfer($this->ctx, $gudangA2, 5);

    expect(JournalEntry::withoutGlobalScopes()->where('source_type', StockTransfer::class)->where('source_id', $st->id)->count())->toBe(0);
});

it('disetujui belum dijurnal; kirim sebagian dijurnal sebesar qty yang dikirim, kirim sisa menambah jurnal', function () {
    $st = StockTransfer::create(['transfer_number' => 'ST-IBT-'.uniqid(), 'from_warehouse_id' => $this->ctx['warehouse']->id, 'to_warehouse_id' => $this->gudangB->id,
        'transfer_date' => now()->toDateString(), 'status' => 'Draft']);
    $item = StockTransferItem::create(['stock_transfer_id' => $st->id, 'product_id' => $this->ctx['product']->id, 'quantity' => 5,
        'from_warehouse_id' => $this->ctx['warehouse']->id, 'to_warehouse_id' => $this->gudangB->id]);
    $svc = app(StockTransferService::class);
    $svc->requestTransfer($st->fresh());
    $svc->approveStockTransfer($st->fresh());
    expect(JournalEntry::withoutGlobalScopes()->where('source_type', StockTransfer::class)->where('source_id', $st->id)->count())->toBe(0);

    $svc->shipTransfer($st->fresh(), [$item->id => 3]);
    expect(ibtNet($st, '1140.10', $this->cabangB->id))->toBe(15000.0)
        ->and(ibtNet($st, '1140.10', $this->ctx['cabang']->id))->toBe(-15000.0);

    $svc->shipTransfer($st->fresh(), [$item->id => 2]);
    expect(ibtNet($st, '1140.10', $this->cabangB->id))->toBe(25000.0)
        ->and(ibtNet($st, '1180.02', $this->cabangB->id))->toBe(-25000.0);
});

it('void mutasi antar cabang membalik jurnal (tidak menghapus) sehingga saldo bersih 0', function () {
    $st = ibtTransfer($this->ctx, $this->gudangB, 5);
    $before = JournalEntry::withoutGlobalScopes()->where('source_type', StockTransfer::class)->where('source_id', $st->id)->count();

    app(StockTransferService::class)->voidStockTransfer($st, 'Salah gudang tujuan');

    expect(JournalEntry::withoutGlobalScopes()->where('source_type', StockTransfer::class)->where('source_id', $st->id)->where('is_reversal', false)->count())->toBe($before)
        ->and(ibtNet($st, '1140.10', $this->cabangB->id))->toBe(0.0)
        ->and(ibtNet($st, '1180.02', $this->ctx['cabang']->id))->toBe(0.0);
});

it('akun Rekening Antar Cabang tidak ada: pengiriman ditolak dengan sebab & solusi, stok tidak berpindah', function () {
    ChartOfAccount::where('code', '1180.02')->delete();

    expect(fn () => ibtTransfer($this->ctx, $this->gudangB, 5))
        ->toThrow(\Illuminate\Validation\ValidationException::class, 'Rekening Antar Cabang');
    expect(stkStock($this->ctx['product'], $this->ctx['warehouse'])['available'])->toBe(20.0);
});
