<?php

/**
 * Isu 9 — Penomoran dokumen: Purchase Receipt tidak lagi memiliki DUA prefix untuk dokumen yang sama (dulu 'RN-' pada
 * jalur manual vs 'GRN-' pada jalur QC auto-receipt); dan referensi jurnal memakai nomor dokumen ASLI, bukan string
 * sintetis ("PR-{id}", atau "PR-" + nota_retur yang membuat referensi ganda "PR-NR-...").
 */

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('zeroOutTemporaryProcurementPositions: referensi jurnal = nomor GRN- asli, bukan sintetis "PR-{id}"', function () {
    $cabang = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $supplier = Supplier::factory()->create();
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    $currency = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);
    $product = Product::factory()->create(['cabang_id' => $cabang->id]);

    $inventoryCoa = ChartOfAccount::firstOrCreate(['code' => '1140.01'], ['name' => 'Persediaan Bahan Baku', 'type' => 'Asset', 'is_active' => true]);
    $temporaryProcurementCoa = ChartOfAccount::firstOrCreate(['code' => '1400.01'], ['name' => 'Pos Sementara Produksi', 'type' => 'Asset', 'is_active' => true]);
    $unbilledPurchaseCoa = ChartOfAccount::firstOrCreate(['code' => '2100.10'], ['name' => 'Hutang Pembelian Belum Ditagih', 'type' => 'Liability', 'is_active' => true]);
    $product->update(['inventory_coa_id' => $inventoryCoa->id, 'temporary_procurement_coa_id' => $temporaryProcurementCoa->id, 'unbilled_purchase_coa_id' => $unbilledPurchaseCoa->id]);

    $purchaseOrder = PurchaseOrder::factory()->create(['supplier_id' => $supplier->id, 'status' => 'approved', 'created_by' => $user->id]);
    $poItem = PurchaseOrderItem::factory()->create(['purchase_order_id' => $purchaseOrder->id, 'product_id' => $product->id, 'quantity' => 5, 'unit_price' => 10000]);

    $service = app(PurchaseReceiptService::class);
    $receipt = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $purchaseOrder->id, 'receipt_number' => $service->generateReceiptNumber(), 'receipt_date' => now(),
        'received_by' => $user->id, 'currency_id' => $currency->id, 'status' => 'completed', 'cabang_id' => $cabang->id,
    ]);
    $receiptItem = PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $receipt->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'qty_received' => 5, 'qty_accepted' => 5, 'qty_rejected' => 0, 'status' => 'completed',
    ]);

    expect($receipt->receipt_number)->toStartWith('GRN-');

    $service->createTemporaryProcurementEntriesForReceiptItem($receiptItem);
    $service->postItemInventoryAfterQC($receiptItem);
    $result = $service->zeroOutTemporaryProcurementPositions($receipt);
    expect($result['status'])->toBe('posted');

    $entries = JournalEntry::where('source_type', PurchaseReceipt::class)->where('source_id', $receipt->id)->get();
    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('reference')->unique()->all())->toBe([$receipt->receipt_number])
        ->and($entries->pluck('reference')->first())->not->toStartWith('PR-');
});

it('jurnal retur pembelian: referensi = nota_retur asli (NR-…), bukan "PR-" + nota_retur (dulu jadi "PR-NR-…")', function () {
    $cabang = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    $product = Product::factory()->create();

    foreach ([['1140.01', 'Persediaan Bahan Baku', 'Asset'], ['2110', 'Hutang Dagang', 'Liability']] as [$code, $name, $type]) {
        ChartOfAccount::firstOrCreate(['code' => $code], ['name' => $name, 'type' => $type, 'is_active' => true]);
    }

    $currency = Currency::firstOrCreate(['code' => 'IDR'], ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]);
    $purchaseOrder = PurchaseOrder::factory()->create(['created_by' => $user->id, 'currency_id' => $currency->id]);
    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $purchaseOrder->id, 'product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50000,
        'discount' => 0, 'tax' => 0, 'tipe_pajak' => 'Non Pajak', 'currency_id' => $currency->id,
    ]);
    $purchaseReceipt = PurchaseReceipt::create([
        'purchase_order_id' => $purchaseOrder->id, 'receipt_number' => app(PurchaseReceiptService::class)->generateReceiptNumber(),
        'receipt_date' => now()->toDateString(), 'status' => 'completed', 'received_by' => $user->id, 'total_received' => 50000,
        'cabang_id' => $cabang->id, 'created_by' => $user->id, 'currency_id' => $currency->id,
    ]);
    $receiptItem = PurchaseReceiptItem::create([
        'purchase_receipt_id' => $purchaseReceipt->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $product->id,
        'qty_received' => 1, 'qty_accepted' => 1, 'qty_rejected' => 0, 'status' => 'completed', 'warehouse_id' => $warehouse->id,
    ]);
    $purchaseReturn = \App\Models\PurchaseReturn::create([
        'purchase_receipt_id' => $purchaseReceipt->id, 'return_date' => now()->toDateString(),
        'nota_retur' => app(\App\Services\PurchaseReturnService::class)->generateNotaRetur(), 'created_by' => $user->id,
        'status' => 'approved', 'cabang_id' => $cabang->id,
    ]);
    \App\Models\PurchaseReturnItem::create([
        'purchase_return_id' => $purchaseReturn->id, 'purchase_receipt_item_id' => $receiptItem->id, 'product_id' => $product->id,
        'qty_returned' => 1, 'unit_price' => 50000, 'reason' => 'Barang cacat produksi',
    ]);

    expect($purchaseReturn->nota_retur)->toStartWith('NR-');
    expect(app(\App\Services\PurchaseReturnService::class)->createJournalEntry($purchaseReturn))->toBeTrue();

    $entries = JournalEntry::where('source_type', \App\Models\PurchaseReturn::class)->where('source_id', $purchaseReturn->id)->get();
    expect($entries)->not->toBeEmpty()
        ->and($entries->pluck('reference')->unique()->all())->toBe([$purchaseReturn->nota_retur])
        ->and($entries->pluck('reference')->first())->not->toStartWith('PR-');
});
