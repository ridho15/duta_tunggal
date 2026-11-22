<?php

/**
 * AUDIT AKUNTANSI — Retur by DO (sebelum Surat Jalan). Stok awal 20 @ HPP 5.000; DO 8 pcs; checker: kurang 1, rusak 2.
 * Sebelum SJ belum ada pengakuan GL apa pun, jadi retur DO tidak boleh menimbulkan jurnal; setelah SJ DO pengganti hanya 5 pcs terjurnal.
 */

use App\Models\DeliveryOrder;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\ReturnProduct;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(Tests\TestCase::class, RefreshDatabase::class);

function audDoLog(string $label, mixed $data): void
{
    fwrite(STDERR, "\n[AUDIT-DO] {$label}: ".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n");
}

/** Saldo per kode COA (debit − kredit) dari seluruh jurnal. */
function audDoGl(): array
{
    return JournalEntry::withoutGlobalScopes()->with('coa')->get()
        ->groupBy(fn ($r) => $r->coa->code)
        ->map(fn ($g) => round((float) $g->sum('debit') - (float) $g->sum('credit'), 2))
        ->all();
}

function audDoStock(int $productId): array
{
    return InventoryStock::withoutGlobalScopes()->where('product_id', $productId)->get()
        ->groupBy(fn ($s) => Warehouse::withoutGlobalScopes()->find($s->warehouse_id)?->name)
        ->map(fn ($g) => ['available' => (float) $g->sum('qty_available'), 'reserved' => (float) $g->sum('qty_reserved')])->all();
}

it('DO-1 retur DO sebelum SJ: tanpa dampak GL, rusak ke karantina, DO pengganti tidak dobel HPP/stok', function () {
    $ctx = stkContext();
    Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
    $ctx['user']->assignRole('Super Admin');
    stkSetStock($ctx['product'], $ctx['warehouse'], 20);
    [$so, $item] = stkSaleOrder($ctx, 8);
    $do = stkDeliveryOrder($ctx, $so, $item, 8, 'approved');
    $doItem = $do->deliveryOrderItem()->first();

    $glBefore = audDoGl();
    $this->actingAs($ctx['user'])->post(route('gudang.kiriman.submitRetur', $do->id), [
        'items' => [$doItem->id => ['kurang' => 1, 'reject' => 2]],
    ])->assertSessionHas('success');
    $retur = ReturnProduct::where('from_model_id', $do->id)->sole();
    $this->post(route('gudang.retur-do.approve', $retur))->assertSessionHas('success');

    $glAfterReturn = audDoGl();
    $returnJournals = JournalEntry::withoutGlobalScopes()->with('coa')->where('source_type', ReturnProduct::class)->where('source_id', $retur->id)->get()
        ->map(fn ($r) => [$r->coa->code, (float) $r->debit, (float) $r->credit, $r->description])->all();
    $stockAfterReturn = audDoStock($ctx['product']->id);
    $quarantine = Warehouse::withoutGlobalScopes()->where('is_quarantine', true)->first();
    $qMove = StockMovement::where('warehouse_id', $quarantine?->id)->where('product_id', $ctx['product']->id)->get(['type', 'quantity', 'value']);
    $replacement = DeliveryOrder::withoutGlobalScopes()->where('replacement_for_delivery_order_id', $do->id)->sole();

    audDoLog('GL sebelum retur', $glBefore);
    audDoLog('Jurnal ReturnProduct', $returnJournals);
    audDoLog('GL setelah approve retur', $glAfterReturn);
    audDoLog('Stok setelah retur', $stockAfterReturn);
    audDoLog('Mutasi karantina', $qMove->toArray());
    audDoLog('DO pengganti qty', (float) $replacement->deliveryOrderItem()->sum('quantity'));

    // SJ DO pengganti
    $replacement->update(['status' => 'approved']);
    $this->post(route('penjualan.sj.store'), [
        'delivery_order_ids' => [$replacement->id], 'issued_at' => now()->toDateString(),
        'delivery_method' => 'ekspedisi', 'expedition_name' => 'JNE Uji', 'cabang_id' => $ctx['cabang']->id,
    ])->assertSessionHasNoErrors();
    $replacement->refresh();
    if (! in_array($replacement->status, ['sent', 'completed'], true)) {
        $replacement->update(['status' => 'sent']);
    }

    $glFinal = audDoGl();
    $stockFinal = audDoStock($ctx['product']->id);
    $totalQty = array_sum(array_column($stockFinal, 'available'));
    audDoLog('Status DO asal / pengganti', [$do->fresh()->status, $replacement->fresh()->status]);
    audDoLog('GL akhir', $glFinal);
    audDoLog('Stok akhir', $stockFinal);
    audDoLog('Rekonsiliasi', ['stok_qty_total' => $totalQty, 'nilai_stok' => $totalQty * 5000, 'GL_1140.10_mutasi' => $glFinal['1140.10'] ?? 0,
        'seharusnya_GL_mutasi' => -5 * 5000]);

    expect($returnJournals)->toBe([])                                         // sebelum SJ tidak ada yang dibalik
        ->and((float) ($glAfterReturn['1140.10'] ?? 0))->toBe(0.0)
        ->and($stockAfterReturn[$quarantine->name]['available'])->toBe(2.0)
        ->and((float) $replacement->deliveryOrderItem()->sum('quantity'))->toBe(5.0)
        ->and($totalQty)->toBe(15.0)
        ->and((float) ($glFinal['1140.10'] ?? 0))->toBe(-25000.0)              // hanya 5 pcs DO pengganti keluar
        ->and((float) ($glFinal['1140.20'] ?? 0))->toBe(25000.0);
});
