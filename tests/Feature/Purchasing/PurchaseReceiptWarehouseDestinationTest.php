<?php

/**
 * Prioritas 03 Nov 2026 butir 3: gudang terima GRN mengikuti gudang tujuan PO; beda gudang wajib beralasan
 * dan tampil berdampingan (Gudang Tujuan PO | Gudang Terima) di detail dan PDF.
 */

use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** PO approved 10 pcs dengan gudang tujuan $poWarehouse (null = PO tanpa gudang tujuan). */
function grnPo(array $ctx, ?\App\Models\Warehouse $poWarehouse): PurchaseOrder
{
    $idr = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    $supplier = Supplier::first() ?? Supplier::create(['code' => 'SUP-GRN', 'perusahaan' => 'PT Pemasok Uji', 'address' => 'Jl. Uji No. 1', 'status' => 1, 'cabang_id' => $ctx['cabang']->id]);

    $po = PurchaseOrder::create([
        'po_number' => 'PO-GRN-'.strtoupper(substr(uniqid(), -6)), 'supplier_id' => $supplier->id, 'warehouse_id' => $poWarehouse?->id,
        'cabang_id' => $ctx['cabang']->id, 'order_date' => now()->toDateString(), 'total_amount' => 100000, 'status' => 'approved',
        'approved_by' => $ctx['user']->id, 'approved_at' => now(),
    ]);
    PurchaseOrderCurrency::create(['purchase_order_id' => $po->id, 'currency_id' => $idr->id, 'nominal' => 1.0, 'type' => 'base']);
    PurchaseOrderItem::create([
        'purchase_order_id' => $po->id, 'product_id' => $ctx['product']->id, 'quantity' => 10, 'unit_price' => 10000, 'original_unit_price' => 10000,
        'discount' => 0, 'tax' => 0, 'tipe_pajak' => 'none', 'currency_id' => $idr->id, 'subtotal' => 100000,
    ]);

    return $po->fresh(['purchaseOrderItem']);
}

function grnPayload(PurchaseOrder $po, int $warehouseId, array $extra = []): array
{
    return array_merge([
        'purchase_order_id' => $po->id, 'receipt_date' => now()->toDateString(), 'warehouse_id' => $warehouseId, 'action' => 'save_draft',
        'items' => [[
            'purchase_order_item_id' => $po->purchaseOrderItem->first()->id, 'product_id' => $po->purchaseOrderItem->first()->product_id,
            'qty_received' => 10, 'qty_accepted' => 10, 'qty_rejected' => 0, 'warehouse_id' => $warehouseId,
        ]],
    ], $extra);
}

it('form GRN mewarisi gudang tujuan PO, bukan gudang pertama', function () {
    $ctx = stkContext();
    $first = stkWarehouse($ctx, 'AAA Gudang Pertama');           // urut nama paling awal → dulu jadi default diam-diam
    $target = stkWarehouse($ctx, 'ZZZ Gudang Tujuan PO');
    $po = grnPo($ctx, $target);

    $html = $this->actingAs($ctx['user'])->get(route('gudang.penerimaan.create', ['po_id' => $po->id]))->assertOk()->getContent();

    expect($html)->toContain("poWarehouseId: '{$target->id}'")
        ->and($html)->toContain("defaultWarehouseId: '{$target->id}'")
        ->and($html)->not->toContain("defaultWarehouseId: '{$first->id}'")
        ->and($html)->toContain('name="warehouse_override_reason"');
});

it('gudang terima sama dengan tujuan PO: tersimpan tanpa alasan', function () {
    $ctx = stkContext();
    $target = stkWarehouse($ctx, 'Gudang Tujuan');
    $po = grnPo($ctx, $target);

    $this->actingAs($ctx['user'])->post(route('gudang.penerimaan.store'), grnPayload($po, $target->id))->assertSessionHasNoErrors();

    $grn = PurchaseReceipt::latest('id')->firstOrFail();
    expect($grn->warehouse_override_reason)->toBeNull()->and($grn->warehouse_differs_from_po)->toBeFalse();
});

it('gudang terima berbeda tanpa alasan ditolak; dengan alasan tersimpan dan tampil berdampingan di detail & PDF', function () {
    $ctx = stkContext();
    $target = stkWarehouse($ctx, 'Gudang Tujuan');
    $other = stkWarehouse($ctx, 'Gudang Titipan');
    $po = grnPo($ctx, $target);

    $before = PurchaseReceipt::count();   // relatif: tes lama tanpa isolasi dapat menyisakan GRN
    $this->actingAs($ctx['user'])->post(route('gudang.penerimaan.store'), grnPayload($po, $other->id))
        ->assertSessionHasErrors('warehouse_override_reason');
    $this->post(route('gudang.penerimaan.store'), grnPayload($po, $other->id, ['warehouse_override_reason' => '   ']))
        ->assertSessionHasErrors('warehouse_override_reason');
    expect(PurchaseReceipt::count())->toBe($before);

    $this->post(route('gudang.penerimaan.store'), grnPayload($po, $other->id, ['warehouse_override_reason' => 'Gudang tujuan penuh']))
        ->assertSessionHasNoErrors();

    $grn = PurchaseReceipt::latest('id')->firstOrFail();
    expect($grn->warehouse_override_reason)->toBe('Gudang tujuan penuh')->and($grn->warehouse_differs_from_po)->toBeTrue();

    $this->get(route('gudang.penerimaan.show', $grn))->assertOk()
        ->assertSeeInOrder(['Gudang Tujuan PO', 'Gudang Tujuan', 'Gudang Terima', 'Gudang Titipan', 'Gudang tujuan penuh']);
    $this->get(route('gudang.penerimaan.pdf', $grn))->assertOk();
});

it('PO tanpa gudang tujuan: tidak ada pembanding, alasan tidak diminta dan tidak disimpan', function () {
    $ctx = stkContext();
    $any = stkWarehouse($ctx, 'Gudang Bebas');
    $po = grnPo($ctx, null);

    $this->actingAs($ctx['user'])->post(route('gudang.penerimaan.store'), grnPayload($po, $any->id, ['warehouse_override_reason' => 'tidak perlu']))
        ->assertSessionHasNoErrors();

    expect(PurchaseReceipt::latest('id')->firstOrFail()->warehouse_override_reason)->toBeNull();
});

it('edit GRN yang memindah ke gudang lain juga wajib beralasan', function () {
    $ctx = stkContext();
    $target = stkWarehouse($ctx, 'Gudang Tujuan');
    $other = stkWarehouse($ctx, 'Gudang Titipan');
    $po = grnPo($ctx, $target);
    $this->actingAs($ctx['user'])->post(route('gudang.penerimaan.store'), grnPayload($po, $target->id));
    $grn = PurchaseReceipt::latest('id')->firstOrFail();
    $item = $grn->purchaseReceiptItem()->firstOrFail();

    $payload = grnPayload($po, $other->id);
    unset($payload['purchase_order_id']);
    $payload['items'][0]['id'] = $item->id;

    $this->put(route('gudang.penerimaan.update', $grn), $payload)->assertSessionHasErrors('warehouse_override_reason');
    $this->put(route('gudang.penerimaan.update', $grn), $payload + ['warehouse_override_reason' => 'Dititipkan'])->assertSessionHasNoErrors();
    expect($grn->fresh()->warehouse_override_reason)->toBe('Dititipkan');
});

it('halaman edit GRN memuat data item di atribut x-data tanpa memutus atribut (bug @json di dalam kutip ganda)', function () {
    $ctx = stkContext();
    $target = stkWarehouse($ctx, 'Gudang Tujuan');
    $po = grnPo($ctx, $target);
    $this->actingAs($ctx['user'])->post(route('gudang.penerimaan.store'), grnPayload($po, $target->id));
    $grn = PurchaseReceipt::latest('id')->firstOrFail();

    $html = $this->get(route('gudang.penerimaan.edit', $grn))->assertOk()->getContent();

    expect($html)->not->toContain('items: [{"')            // JSON mentah dengan " akan memutus x-data="..."
        ->and($html)->toContain('JSON.parse')
        ->and($html)->toContain('name="warehouse_override_reason"');
});

it('gudang tujuan PO yang sudah nonaktif tetap muncul di pilihan form GRN', function () {
    $ctx = stkContext();
    $target = stkWarehouse($ctx, 'Gudang Lama');
    $target->update(['status' => 0]);
    $po = grnPo($ctx, $target);

    $this->actingAs($ctx['user'])->get(route('gudang.penerimaan.create', ['po_id' => $po->id]))
        ->assertOk()->assertSee('Gudang Lama')->assertSee('nonaktif');
});

it('"Pindah ke Gudang Tujuan PO": tombol muncul setelah GRN dikonfirmasi dan form Pindah Stok terisi dari GRN', function () {
    $ctx = stkContext();
    $target = stkWarehouse($ctx, 'Gudang Tujuan');
    $other = stkWarehouse($ctx, 'Gudang Titipan');
    $po = grnPo($ctx, $target);

    $this->actingAs($ctx['user'])->post(route('gudang.penerimaan.store'), grnPayload($po, $other->id, ['warehouse_override_reason' => 'Vendor salah kirim']))
        ->assertSessionHasNoErrors();
    $grn = PurchaseReceipt::latest('id')->firstOrFail();
    $transferUrl = route('gudang.transfer.create', ['from_purchase_receipt' => $grn->id]);

    // Tanpa hak Pindah Stok: tidak ada tombol sama sekali.
    $this->get(route('gudang.penerimaan.show', $grn))->assertOk()->assertDontSee('Pindah ke Gudang Tujuan PO');
    \Spatie\Permission\Models\Permission::findOrCreate('create stock transfer', 'web');
    $ctx['user']->givePermissionTo('create stock transfer');
    $ctx['user']->refresh();

    // Draf: stok belum masuk, tombol belum aktif dan prefill diabaikan.
    $this->get(route('gudang.penerimaan.show', $grn))->assertOk()
        ->assertSee('tersedia setelah penerimaan dikonfirmasi')->assertDontSee($transferUrl, false);
    $this->get($transferUrl)->assertOk()->assertDontSee('Pindah ke Gudang Tujuan PO.');

    $grn->update(['status' => 'completed']);

    $this->get(route('gudang.penerimaan.show', $grn))->assertOk()->assertSee($transferUrl, false);

    $html = $this->get($transferUrl)->assertOk()->assertSee('Pindah ke Gudang Tujuan PO.')->assertSee($grn->receipt_number)->getContent();
    expect($html)->toContain('value="'.$other->id.'"')                 // gudang asal = gudang terima
        ->and($html)->toContain('value="'.$target->id.'"')            // gudang tujuan = tujuan PO
        ->and($html)->toContain((string) \Illuminate\Support\Js::from([
            ['product_id' => $ctx['product']->id, 'quantity' => 10.0, 'from_rak_id' => null, 'to_rak_id' => null],
        ]));
});

it('"Pindah ke Gudang Tujuan PO": GRN yang tidak menyimpang tidak mengisi form', function () {
    $ctx = stkContext();
    $target = stkWarehouse($ctx, 'Gudang Tujuan');
    $po = grnPo($ctx, $target);
    $this->actingAs($ctx['user'])->post(route('gudang.penerimaan.store'), grnPayload($po, $target->id))->assertSessionHasNoErrors();
    $grn = PurchaseReceipt::latest('id')->firstOrFail();
    $grn->update(['status' => 'completed']);
    \Spatie\Permission\Models\Permission::findOrCreate('create stock transfer', 'web');
    $ctx['user']->givePermissionTo('create stock transfer');

    $this->get(route('gudang.penerimaan.show', $grn))->assertOk()->assertDontSee('Pindah ke Gudang Tujuan PO');
    $this->get(route('gudang.transfer.create', ['from_purchase_receipt' => $grn->id]))->assertOk()
        ->assertDontSee('Pindah ke Gudang Tujuan PO.');
});
