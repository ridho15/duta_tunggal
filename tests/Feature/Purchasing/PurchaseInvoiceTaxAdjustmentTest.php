<?php

/**
 * Keputusan Ko Handy 24 Nov 2026: DPP/PPN Purchase Invoice boleh disamakan dengan Faktur Pajak supplier.
 * Contoh nyata: PO 100 × Rp17.500 PPN 12% inklusif → sistem DPP 1.576.576,58 / PPN 173.423,42;
 * Faktur Pajak supplier (harga satuan 15.766 dibulatkan) DPP 1.576.600 / PPN 173.426 → total 1.750.026.
 */

use App\Models\ChartOfAccount;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\PurchaseReceiptService;
use Spatie\Permission\Models\Role;

function ptaGl(string $code): float
{
    $id = ChartOfAccount::where('code', $code)->value('id');

    return round((float) JournalEntry::withoutGlobalScopes()->where('coa_id', $id)->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) n')->value('n'), 2);
}

/** PO 12% inklusif + penerimaan barang terposting. */
function ptaSetup(): array
{
    $ctx = purContext();
    ChartOfAccount::firstOrCreate(['code' => '5160'], ['name' => 'Selisih Pembelian', 'type' => 'Expense', 'is_active' => true, 'opening_balance' => 0]);
    [$po, $poItem] = purOrder($ctx, 100, 17500);
    $poItem->forceFill(['tax' => 12, 'tipe_pajak' => 'Inklusif'])->save();
    [$receipt] = purReceipt($ctx, $po->fresh(), 100);
    expect(app(PurchaseReceiptService::class)->postPurchaseReceipt($receipt->fresh())['status'])->toBe('posted');

    return [$ctx, $receipt];
}

function ptaPost($test, array $ctx, $receipt, array $extra = [])
{
    return $test->actingAs($ctx['user'])->post(route('pembelian.invoices.store'), array_merge([
        'supplier_id' => $ctx['supplier']->id, 'purchase_receipts' => [$receipt->id], 'supplier_invoice_number' => 'SUP-PTA-' . uniqid(),
        'invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'action' => 'save_and_post', 'other_fee' => 0,
    ], $extra));
}

function ptaFinance(array $ctx): void
{
    $ctx['user']->assignRole(Role::firstOrCreate(['name' => 'Finance Manager', 'guard_name' => 'web']));
}

it('tanpa penyesuaian: PPN 12% dihitung 11% efektif (sama dengan PO), persediaan & hutang sesuai PO', function () {
    [$ctx, $receipt] = ptaSetup();
    ptaPost($this, $ctx, $receipt)->assertSessionHasNoErrors();
    $invoice = Invoice::latest('id')->firstOrFail();

    expect(round((float) $invoice->subtotal, 2))->toBe(1576576.58)
        ->and(round((float) $invoice->invoiceItem()->sum('tax_amount'), 2))->toBe(173423.42)
        ->and(round((float) $invoice->total, 2))->toBe(1750000.0)
        ->and($invoice->tax_adjustment)->toBeNull()
        ->and(ptaGl('2100.10'))->toBe(0.0)
        ->and(ptaGl('2110'))->toBe(-1750000.0);
});

it('Finance menyamakan DPP/PPN dengan Faktur Pajak: PPN Masukan & hutang = faktur, selisih DPP ke 5160, 2100.10 tetap bersih', function () {
    [$ctx, $receipt] = ptaSetup();
    ptaFinance($ctx);
    ptaPost($this, $ctx, $receipt, ['faktur_dpp' => '1576600', 'faktur_ppn' => '173426'])->assertSessionHasNoErrors();
    $invoice = Invoice::latest('id')->firstOrFail();

    expect(round((float) $invoice->total, 2))->toBe(1750026.0)
        ->and($invoice->tax_adjustment['selisih_dpp'])->toBe(23.42)
        ->and($invoice->tax_adjustment['selisih_ppn'])->toBe(2.58)
        ->and(ptaGl('1170.06'))->toBe(173426.0)     // PPN Masukan = Faktur Pajak (bisa dikreditkan di Coretax)
        ->and(ptaGl('5160'))->toBe(23.42)           // selisih DPP → Selisih Pembelian
        ->and(ptaGl('2100.10'))->toBe(0.0)          // barang belum tertagih tertutup sebesar nilai penerimaan
        ->and(ptaGl('2110'))->toBe(-1750026.0);     // hutang = tagihan supplier
    expect(session('success'))->toContain('Finance Manager');
});

it('selisih lebih dari Rp500 ditolak (kemungkinan harga satuan salah) dan faktur tidak tersimpan', function () {
    [$ctx, $receipt] = ptaSetup();
    ptaFinance($ctx);
    ptaPost($this, $ctx, $receipt, ['faktur_dpp' => '1577200', 'faktur_ppn' => '173426'])
        ->assertSessionHasErrors('faktur_dpp');
    expect(session('errors')->first('faktur_dpp'))->toContain('Selisih DPP Rp 623')->toContain('harga satuan');

    expect(Invoice::count())->toBe(0);
});

it('selain Finance tidak boleh mengubah DPP/PPN faktur', function () {
    [$ctx, $receipt] = ptaSetup();
    ptaPost($this, $ctx, $receipt, ['faktur_ppn' => '173426'])->assertSessionHasErrors('faktur_ppn');

    expect(Invoice::count())->toBe(0);
});

it('draft: penyesuaian bisa diubah ulang lalu dikembalikan ke hitungan sistem tanpa menumpuk', function () {
    [$ctx, $receipt] = ptaSetup();
    ptaFinance($ctx);
    ptaPost($this, $ctx, $receipt, ['action' => 'save_draft', 'faktur_dpp' => '1576600', 'faktur_ppn' => '173426'])->assertSessionHasNoErrors();
    $invoice = Invoice::latest('id')->firstOrFail();
    expect(round((float) $invoice->total, 2))->toBe(1750026.0);

    $base = ['invoice_date' => now()->toDateString(), 'due_date' => now()->addDays(30)->toDateString(), 'action' => 'update_draft'];
    $this->put(route('pembelian.invoices.update', $invoice), $base + ['faktur_dpp' => '1576577', 'faktur_ppn' => '173423'])->assertSessionHasNoErrors();
    expect(round((float) $invoice->fresh()->total, 2))->toBe(1750000.0)
        ->and(round((float) $invoice->fresh()->invoiceItem()->sum('tax_amount'), 2))->toBe(173423.0);

    $this->put(route('pembelian.invoices.update', $invoice), $base + ['faktur_dpp' => '', 'faktur_ppn' => ''])->assertSessionHasNoErrors();
    $fresh = $invoice->fresh();
    expect($fresh->tax_adjustment)->toBeNull()
        ->and(round((float) $fresh->total, 2))->toBe(1750000.0)
        ->and(round((float) $fresh->subtotal, 2))->toBe(1576576.58)
        ->and(round((float) $fresh->price_variance_amount, 2))->toBe(0.0);
});
