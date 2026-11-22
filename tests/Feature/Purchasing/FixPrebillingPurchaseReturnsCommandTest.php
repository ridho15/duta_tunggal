<?php

/**
 * Command koreksi data lama: retur gudang pra-faktur yang (dengan kode lama) tetap ikut tertagih penuh di faktur.
 */

use App\Models\AccountPayable;
use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\PurchaseReceiptService;
use App\Services\PurchaseReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function fpbGl(string $code): float
{
    $coa = ChartOfAccount::where('code', $code)->first();
    $q = JournalEntry::withoutGlobalScopes()->whereNull('deleted_at')->where('coa_id', $coa->id);

    return round((float) (clone $q)->sum('debit') - (float) (clone $q)->sum('credit'), 2);
}

it('mengoreksi retur pra-faktur yang ikut tertagih: dry-run tanpa perubahan, --apply menyeimbangkan 2100.10, PPN & AP = GL', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 50000);
    $poItem->forceFill(['tax' => 11, 'tipe_pajak' => 'Eklusif'])->saveQuietly();
    [$receipt, $receiptItem] = purReceipt($ctx, $po->fresh(), 10);
    app(PurchaseReceiptService::class)->postPurchaseReceipt($receipt->fresh());

    $return = PurchaseReturn::create(['purchase_receipt_id' => $receipt->id, 'return_date' => now()->toDateString(), 'nota_retur' => 'NR-FPB-1',
        'created_by' => $ctx['user']->id, 'status' => 'pending_approval', 'cabang_id' => $ctx['cabang']->id]);
    PurchaseReturnItem::create(['purchase_return_id' => $return->id, 'purchase_receipt_item_id' => $receiptItem->id, 'product_id' => $ctx['product']->id,
        'qty_returned' => 2, 'unit_price' => 50000, 'reason' => 'Rusak']);
    app(PurchaseReturnService::class)->approve($return->fresh());

    // Faktur gaya lama: 10 pcs penuh (retur tidak dikurangi) + jurnal Dr 2100.10 / Dr PPN / Cr 2110.
    $invoice = new Invoice(['invoice_number' => 'PINV-FPB-1', 'from_model_type' => PurchaseOrder::class, 'from_model_id' => $po->id, 'purchase_receipts' => [$receipt->id],
        'invoice_date' => now(), 'due_date' => now()->addMonth(), 'subtotal' => 500000, 'dpp' => 500000, 'tax' => 11, 'ppn_rate' => 11, 'total' => 555000,
        'status' => 'unpaid', 'cabang_id' => $ctx['cabang']->id]);
    $invoice->saveQuietly();
    InvoiceItem::create(['invoice_id' => $invoice->id, 'product_id' => $ctx['product']->id, 'quantity' => 10, 'price' => 50000, 'subtotal' => 500000,
        'tax_rate' => 11, 'tax_amount' => 55000, 'total' => 500000]);
    foreach ([[$ctx['unbilled']->id, 500000, 0], [$ctx['vatIn']->id, 55000, 0], [$ctx['ap']->id, 0, 555000]] as [$coa, $d, $c]) {
        JournalEntry::create(['coa_id' => $coa, 'date' => now()->toDateString(), 'reference' => 'PINV-FPB-1', 'description' => 'Faktur lama', 'debit' => $d, 'credit' => $c,
            'journal_type' => 'purchase', 'source_type' => Invoice::class, 'source_id' => $invoice->id]);
    }
    (new AccountPayable(['invoice_id' => $invoice->id, 'supplier_id' => $ctx['supplier']->id, 'total' => 555000, 'total_original' => 555000, 'paid' => 0,
        'remaining' => 555000, 'exchange_rate' => 1, 'status' => 'Belum Lunas', 'cabang_id' => $ctx['cabang']->id, 'created_by' => $ctx['user']->id]))->saveQuietly();

    expect(fpbGl('2100.10'))->toBe(100000.0);
    $journals = JournalEntry::count();

    $this->artisan('purchase-return:fix-prebilling')->assertSuccessful();
    expect(JournalEntry::count())->toBe($journals);

    $this->artisan('purchase-return:fix-prebilling', ['--apply' => true])->assertSuccessful();
    $ap = AccountPayable::where('invoice_id', $invoice->id)->first();

    expect(fpbGl('2100.10'))->toBe(0.0)
        ->and(fpbGl('1170.06'))->toBe(44000.0)
        ->and(fpbGl('2110'))->toBe(-444000.0)
        ->and((float) $ap->remaining)->toBe(444000.0);

    // Idempoten: retur sudah punya jurnal Dr Hutang → tidak dikoreksi lagi.
    $journals = JournalEntry::count();
    $this->artisan('purchase-return:fix-prebilling', ['--apply' => true])->assertSuccessful();
    expect(JournalEntry::count())->toBe($journals);
});
