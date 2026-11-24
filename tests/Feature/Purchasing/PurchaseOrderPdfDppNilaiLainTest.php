<?php

/**
 * Catatan Ko Handy 24 Nov 2026 (PO-20261124-0001): PO 10 × Rp20.000 PPN inklusif tampil Tax 11% & DPP 180.180,
 * padahal Faktur Pajak memakai 12% × DPP Nilai Lain 165.165. PDF PO kini disajikan seperti Faktur Pajak:
 * Harga Jual 180.180 → DPP Nilai Lain 165.165 → PPN 12% 19.820 → Grand Total 200.000 (nilai uang tidak berubah).
 */

it('PDF PO PPN inklusif 11%: Tax tampil 12%, Harga Jual 180.180, DPP Nilai Lain 165.165, PPN 19.820, total 200.000', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 20000);
    $poItem->forceFill(['tax' => 11, 'tipe_pajak' => 'Inklusif'])->save();

    $html = view('pdf.purchase-order', ['purchaseOrder' => $po->fresh(['supplier', 'cabang', 'purchaseOrderItem.product.uom', 'purchaseOrderCurrency.currency'])])->render();
    $text = preg_replace('/\s+/', ' ', strip_tags($html));

    expect($text)->toContain('12,00%')->not->toContain('11,00%')
        ->toContain('Harga Jual (DPP)')->toContain('DPP Nilai Lain')
        ->toContain('180.180')->toContain('165.165')->toContain('19.820')
        ->toContain('PPN 12% × DPP Nilai Lain')
        ->toContain('Sub Total setelah diskon (termasuk PPN)')
        ->toContain('200.000');
});

it('PDF PO tanpa pajak: tidak menampilkan DPP Nilai Lain di ringkasan', function () {
    $ctx = purContext();
    [$po] = purOrder($ctx, 10, 20000);

    $html = view('pdf.purchase-order', ['purchaseOrder' => $po->fresh(['supplier', 'cabang', 'purchaseOrderItem.product.uom', 'purchaseOrderCurrency.currency'])])->render();

    expect($html)->not->toContain('DPP Nilai Lain (11/12');
});

it('layar detail PO menampilkan ringkasan pajak yang sama: Harga Jual, DPP Nilai Lain, PPN 12%', function () {
    $ctx = purContext();
    [$po, $poItem] = purOrder($ctx, 10, 20000);
    $poItem->forceFill(['tax' => 11, 'tipe_pajak' => 'Inklusif'])->save();

    $ctx['user']->assignRole(\Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']));   // harga beli hanya untuk PUR/FIN/Owner
    $this->actingAs($ctx['user'])->get(route('pembelian.po.show', $po))->assertOk()
        ->assertSee('Inklusif · 12%')
        ->assertSee('DPP Nilai Lain (11/12)')->assertSee('165.165')
        ->assertSee('PPN 12% × DPP Nilai Lain')->assertSee('19.820');
});
