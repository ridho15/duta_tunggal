<?php

/**
 * Catatan akunting 24 Nov 2026: "Penerimaan Barang Belum Tertagih" terbaca seperti aset.
 * Akar: penerimaan barang mengkredit akun belum-tertagih MILIK PRODUK (mis. 2190.10), sedangkan faktur pembelian selalu
 * mendebit 2100.10 → 2100.10 bersaldo debit dan akun produk tidak pernah nol. Faktur kini mendebit akun yang sama.
 */

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Invoice;
use App\Services\PurchaseReceiptService;

function ubpGl(string $code): float
{
    $id = ChartOfAccount::where('code', $code)->value('id');

    return round((float) JournalEntry::withoutGlobalScopes()->where('coa_id', $id)->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) n')->value('n'), 2);
}

it('produk memakai akun belum-tertagih 2190.10: penerimaan lalu faktur → 2190.10 dan 2100.10 sama-sama 0', function () {
    $ctx = purContext();
    $productAccount = ChartOfAccount::firstOrCreate(['code' => '2190.10'], ['name' => 'Pembelian Belum Tertagih - Default Produk', 'type' => 'Liability', 'is_active' => true, 'opening_balance' => 0]);
    $ctx['product']->forceFill(['unbilled_purchase_coa_id' => $productAccount->id])->saveQuietly();

    [$po] = purOrder($ctx, 10, 50000);
    [$receipt] = purReceipt($ctx, $po, 10);
    expect(app(PurchaseReceiptService::class)->postPurchaseReceipt($receipt->fresh())['status'])->toBe('posted');
    expect(ubpGl('2190.10'))->toBe(-500000.0);   // kredit saat barang diterima

    $num = 'SUP-UBP-' . uniqid();
    $this->actingAs($ctx['user'])->post(route('pembelian.invoices.store'), [
        'supplier_id' => $ctx['supplier']->id, 'purchase_receipts' => [$receipt->id], 'supplier_invoice_number' => $num,
        'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'action' => 'save_and_post', 'other_fee' => 0,
    ])->assertSessionHasNoErrors();
    expect(session('error'))->toBeNull();
    expect(Invoice::where('supplier_invoice_number', $num)->exists())->toBeTrue();

    expect(ubpGl('2190.10'))->toBe(0.0)     // tertutup oleh faktur
        ->and(ubpGl('2100.10'))->toBe(0.0)  // tidak lagi bersaldo debit ("seperti aset")
        ->and(ubpGl('2110'))->toBe(-500000.0);
});

it('perintah purchase:fix-unbilled-account memindahkan debit 2100.10 faktur lama ke akun produk (dry-run dulu, idempoten)', function () {
    $ctx = purContext();
    $productAccount = ChartOfAccount::firstOrCreate(['code' => '2190.10'], ['name' => 'Pembelian Belum Tertagih - Default Produk', 'type' => 'Liability', 'is_active' => true, 'opening_balance' => 0]);
    $ctx['product']->forceFill(['unbilled_purchase_coa_id' => $productAccount->id])->saveQuietly();

    [$po] = purOrder($ctx, 10, 50000);
    [$receipt] = purReceipt($ctx, $po, 10);
    app(PurchaseReceiptService::class)->postPurchaseReceipt($receipt->fresh());
    $num = 'SUP-OLD-' . uniqid();
    $this->actingAs($ctx['user'])->post(route('pembelian.invoices.store'), [
        'supplier_id' => $ctx['supplier']->id, 'purchase_receipts' => [$receipt->id], 'supplier_invoice_number' => $num,
        'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'action' => 'save_and_post', 'other_fee' => 0,
    ])->assertSessionHasNoErrors();
    $invoice = Invoice::where('supplier_invoice_number', $num)->firstOrFail();

    // Simulasikan posting versi lama: debit faktur jatuh ke 2100.10
    JournalEntry::withoutGlobalScopes()->where('source_type', Invoice::class)->where('source_id', $invoice->id)
        ->where('coa_id', $productAccount->id)->update(['coa_id' => ChartOfAccount::where('code', '2100.10')->value('id')]);
    expect(ubpGl('2100.10'))->toBe(500000.0)->and(ubpGl('2190.10'))->toBe(-500000.0);

    $this->artisan('purchase:fix-unbilled-account')->assertSuccessful();
    expect(ubpGl('2100.10'))->toBe(500000.0);   // dry-run tidak menulis

    $this->artisan('purchase:fix-unbilled-account', ['--apply' => true])->assertSuccessful();
    expect(ubpGl('2100.10'))->toBe(0.0)->and(ubpGl('2190.10'))->toBe(0.0);

    $this->artisan('purchase:fix-unbilled-account', ['--apply' => true])->assertSuccessful();   // idempoten
    expect(ubpGl('2190.10'))->toBe(0.0);
});
