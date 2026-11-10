<?php

/**
 * Keputusan Ko Handy 10 Nov 2026 (butir 8): Laporan Stok DAN Buku Besar menilai persediaan pada HPP master produk, bukan harga PO.
 * Persediaan didebit pada HPP; akun barang diterima belum ditagih tetap pada harga PO (dibersihkan faktur pembelian pada harga PO);
 * selisih harga PO − HPP dibukukan ke Selisih Pembelian (5160).
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
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReceiptService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Terima 10 pcs pada harga PO $poPrice untuk produk ber-HPP $hpp; kembalikan [item, coa persediaan, coa belum ditagih, coa selisih]. */
function hppReceive(float $hpp, float $poPrice, bool $withVarianceCoa = true): array
{
    $cabang = Cabang::factory()->create();
    $user = User::factory()->create(['cabang_id' => $cabang->id, 'manage_type' => 'all']);
    $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
    $currency = Currency::factory()->create(['code' => 'IDR', 'to_rupiah' => 1]);

    $inventory = ChartOfAccount::firstOrCreate(['code' => '1140.01'], ['name' => 'Persediaan Barang Dagang', 'type' => 'Asset', 'is_active' => true]);
    $unbilled = ChartOfAccount::firstOrCreate(['code' => '2100.10'], ['name' => 'Hutang Pembelian Belum Ditagih', 'type' => 'Liability', 'is_active' => true]);
    $variance = $withVarianceCoa
        ? ChartOfAccount::firstOrCreate(['code' => '5160'], ['name' => 'Selisih Pembelian', 'type' => 'Expense', 'is_active' => true])
        : null;

    $product = Product::factory()->create([
        'cabang_id' => $cabang->id, 'cost_price' => $hpp,
        'inventory_coa_id' => $inventory->id, 'unbilled_purchase_coa_id' => $unbilled->id,
    ]);
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => Supplier::factory()->create()->id, 'warehouse_id' => $warehouse->id,
        'status' => 'approved', 'created_by' => $user->id, 'cabang_id' => $cabang->id,
    ]);
    $poItem = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id, 'product_id' => $product->id, 'quantity' => 10, 'unit_price' => $poPrice, 'currency_id' => $currency->id,
    ]);
    $receipt = PurchaseReceipt::factory()->create([
        'purchase_order_id' => $po->id, 'receipt_number' => 'RN-HPP-'.uniqid(), 'receipt_date' => now(),
        'received_by' => $user->id, 'currency_id' => $currency->id, 'status' => 'completed', 'cabang_id' => $cabang->id,
    ]);
    $item = PurchaseReceiptItem::factory()->create([
        'purchase_receipt_id' => $receipt->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $product->id,
        'warehouse_id' => $warehouse->id, 'rak_id' => null, 'qty_received' => 10, 'qty_accepted' => 10, 'qty_rejected' => 0, 'status' => 'completed',
    ]);

    expect(app(PurchaseReceiptService::class)->postItemInventoryAfterQC($item)['status'])->toBe('posted');

    return [$item, $inventory, $unbilled, $variance];
}

function hppLines(PurchaseReceiptItem $item)
{
    return JournalEntry::where('source_type', PurchaseReceiptItem::class)->where('source_id', $item->id)->where('journal_type', 'inventory')->get();
}

it('harga PO di atas HPP: persediaan didebit HPP, selisih didebit ke Selisih Pembelian, belum ditagih dikredit harga PO', function () {
    [$item, $inventory, $unbilled, $variance] = hppReceive(hpp: 8000, poPrice: 10000);
    $lines = hppLines($item);

    expect((float) $lines->where('coa_id', $inventory->id)->sum('debit'))->toBe(80000.0)
        ->and((float) $lines->where('coa_id', $variance->id)->sum('debit'))->toBe(20000.0)
        ->and((float) $lines->where('coa_id', $unbilled->id)->sum('credit'))->toBe(100000.0)
        ->and((float) $lines->sum('debit'))->toBe((float) $lines->sum('credit'));

    // Nilai gerakan stok = HPP, sama dengan Laporan Stok (qty × cost_price)
    $movement = StockMovement::where('type', 'purchase_in')->where('product_id', $item->product_id)->first();
    expect((float) $movement->value)->toBe(80000.0);
});

it('harga PO di bawah HPP: selisih dikredit ke Selisih Pembelian', function () {
    [$item, $inventory, $unbilled, $variance] = hppReceive(hpp: 8000, poPrice: 7000);
    $lines = hppLines($item);

    expect((float) $lines->where('coa_id', $inventory->id)->sum('debit'))->toBe(80000.0)
        ->and((float) $lines->where('coa_id', $variance->id)->sum('credit'))->toBe(10000.0)
        ->and((float) $lines->where('coa_id', $unbilled->id)->sum('credit'))->toBe(70000.0)
        ->and((float) $lines->sum('debit'))->toBe((float) $lines->sum('credit'));
});

it('harga PO sama dengan HPP: tidak ada baris selisih', function () {
    [$item, $inventory, $unbilled, $variance] = hppReceive(hpp: 9000, poPrice: 9000);
    $lines = hppLines($item);

    expect($lines)->toHaveCount(2)
        ->and((float) $lines->where('coa_id', $inventory->id)->sum('debit'))->toBe(90000.0)
        ->and($lines->where('coa_id', $variance->id))->toHaveCount(0);
});

it('produk tanpa HPP (cost_price 0): persediaan memakai harga PO agar tidak bernilai nol', function () {
    [$item, $inventory, $unbilled] = hppReceive(hpp: 0, poPrice: 12000);
    $lines = hppLines($item);

    expect($lines)->toHaveCount(2)
        ->and((float) $lines->where('coa_id', $inventory->id)->sum('debit'))->toBe(120000.0)
        ->and((float) $lines->where('coa_id', $unbilled->id)->sum('credit'))->toBe(120000.0);
});
