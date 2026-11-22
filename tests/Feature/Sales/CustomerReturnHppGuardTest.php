<?php

/**
 * Retur penjualan tanpa HPP sama sekali (snapshot invoice kosong & cost_price master 0) harus ditolak dengan pesan jelas,
 * bukan dijurnal dengan harga jual / timpang. Retur perbaikan tidak membalik sisi keuangan dan tidak menyentuh kartu stok.
 */

use App\Models\AccountReceivable;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Models\StockMovement;
use App\Services\CustomerReturnService;

function crgSetup(float $productCost, ?float $snapshotCost): array
{
    $ctx = stkContext();
    $ctx['product']->forceFill(['cost_price' => $productCost])->saveQuietly();
    $so = SaleOrder::create([
        'so_number' => 'SO-CRG-'.uniqid(), 'customer_id' => $ctx['customer']->id, 'order_date' => now()->subMonth(), 'status' => 'completed',
        'total_amount' => 1110000, 'cabang_id' => $ctx['cabang']->id, 'created_by' => $ctx['user']->id, 'tipe_pengiriman' => 'Kirim Langsung',
    ]);
    $invoice = new Invoice([
        'invoice_number' => 'INV-CRG-'.uniqid(), 'from_model_type' => SaleOrder::class, 'from_model_id' => $so->id, 'invoice_date' => now()->subMonth(),
        'due_date' => now()->addMonth(), 'subtotal' => 1000000, 'tax' => 110000, 'dpp' => 1000000, 'total' => 1110000, 'status' => 'sent',
        'customer_name' => $ctx['customer']->name, 'cabang_id' => $ctx['cabang']->id, 'ppn_rate' => 11,
    ]);
    $invoice->saveQuietly();
    $invoiceItem = InvoiceItem::create([
        'invoice_id' => $invoice->id, 'product_id' => $ctx['product']->id, 'quantity' => 10, 'price' => 100000, 'discount' => 0,
        'tax_rate' => 11, 'tax_amount' => 110000, 'subtotal' => 1000000, 'total' => 1110000,
    ]);
    InvoiceItem::withoutEvents(fn () => $invoiceItem->forceFill(['cost_price' => $snapshotCost, 'cogs_amount' => $snapshotCost ? $snapshotCost * 10 : null])->save());
    $ar = new AccountReceivable([
        'invoice_id' => $invoice->id, 'customer_id' => $ctx['customer']->id, 'total' => 1110000, 'paid' => 0, 'remaining' => 1110000,
        'status' => 'Belum Lunas', 'cabang_id' => $ctx['cabang']->id, 'created_by' => $ctx['user']->id,
    ]);
    $ar->saveQuietly();
    stkSetStock($ctx['product'], $ctx['warehouse'], 0);

    return array_merge($ctx, compact('invoice', 'invoiceItem', 'ar'));
}

function crgReturn(array $ctx, string $decision): CustomerReturn
{
    $cr = CustomerReturn::create([
        'return_number' => CustomerReturn::generateReturnNumber(), 'invoice_id' => $ctx['invoice']->id, 'customer_id' => $ctx['customer']->id,
        'cabang_id' => $ctx['cabang']->id, 'warehouse_id' => $ctx['warehouse']->id, 'return_date' => now(), 'reason' => 'Uji',
        'status' => CustomerReturn::STATUS_APPROVED, 'received_by' => $ctx['user']->id, 'received_at' => now(), 'qc_inspected_by' => $ctx['user']->id,
        'qc_inspected_at' => now(), 'approved_by' => $ctx['user']->id, 'approved_at' => now(),
    ]);
    CustomerReturnItem::create([
        'customer_return_id' => $cr->id, 'product_id' => $ctx['product']->id, 'invoice_item_id' => $ctx['invoiceItem']->id, 'quantity' => 3,
        'problem_description' => 'Rusak', 'qc_result' => CustomerReturnItem::QC_RESULT_FAIL, 'decision' => $decision,
    ]);

    return $cr->fresh();
}

it('menolak retur bila HPP invoice dan HPP master sama-sama tidak tersedia', function () {
    $ctx = crgSetup(0, null);
    $cr = crgReturn($ctx, CustomerReturnItem::DECISION_REPLACE);

    expect(fn () => app(CustomerReturnService::class)->processCompletion($cr))
        ->toThrow(\Exception::class, 'HPP produk');

    expect(JournalEntry::where('source_type', CustomerReturn::class)->where('source_id', $cr->id)->count())->toBe(0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(0.0)
        ->and((float) $ctx['ar']->fresh()->remaining)->toBe(1110000.0)
        ->and($cr->fresh()->status)->toBe(CustomerReturn::STATUS_APPROVED);
});

it('retur perbaikan: tanpa jurnal, tanpa kartu stok, piutang tetap', function () {
    $ctx = crgSetup(60000, 60000);
    $cr = crgReturn($ctx, CustomerReturnItem::DECISION_REPAIR);

    $summary = app(CustomerReturnService::class)->processCompletion($cr);

    expect($summary['repair_qty'])->toBe(3.0)
        ->and(JournalEntry::where('source_type', CustomerReturn::class)->where('source_id', $cr->id)->count())->toBe(0)
        ->and(StockMovement::where('from_model_type', CustomerReturn::class)->where('from_model_id', $cr->id)->count())->toBe(0)
        ->and(stkStock($ctx['product'], $ctx['warehouse'])['available'])->toBe(0.0)
        ->and((float) $ctx['ar']->fresh()->remaining)->toBe(1110000.0)
        ->and($cr->fresh()->status)->toBe(CustomerReturn::STATUS_COMPLETED);
});
