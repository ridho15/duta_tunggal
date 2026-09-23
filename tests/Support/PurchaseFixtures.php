<?php

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;

/**
 * Fixture context untuk pengujian modul pengadaan / pembelian (Tahap 3).
 */
function purContext(array $overrides = []): array
{
    $cabang = Cabang::factory()->create([
        'kode' => 'PUR-' . strtoupper(substr(uniqid(), -5)),
        'nama' => 'Cabang Pengadaan',
        'status' => 1,
        'lihat_stok_cabang_lain' => false,
    ]);

    $user = User::factory()->create([
        'cabang_id' => $cabang->id,
        'manage_type' => 'all',
    ]);
    Auth::login($user);

    $idr = Currency::firstOrCreate(['code' => 'IDR'], [
        'name' => 'Rupiah',
        'symbol' => 'Rp',
        'to_rupiah' => 1,
    ]);

    $warehouse = Warehouse::factory()->create([
        'cabang_id' => $cabang->id,
        'kode' => 'G-PUR-' . strtoupper(substr(uniqid(), -5)),
        'name' => 'Gudang Pengadaan',
        'status' => 1,
    ]);

    $supplier = Supplier::factory()->create([
        'cabang_id' => $cabang->id,
        'code' => 'SUP-' . strtoupper(substr(uniqid(), -5)),
        'perusahaan' => 'PT Mitra Supplier Logam',
        'tempo_hutang' => 30,
    ]);

    $coa = fn (string $code, string $name, string $type) => ChartOfAccount::firstOrCreate(
        ['code' => $code],
        ['name' => $name, 'type' => $type, 'is_active' => true, 'opening_balance' => 0]
    );

    $inventory = $coa('1140.10', 'Persediaan Barang Dagangan', 'Asset');
    $unbilled = $coa('2100.10', 'Pembelian Belum Tertagih', 'Liability');
    $ap = $coa('2110', 'Hutang Dagang', 'Liability');
    $vatIn = $coa('1170.06', 'PPN Masukan', 'Asset');
    $pph22 = $coa('1170.02', 'PPh 22', 'Asset');
    $bank = $coa('1112.01', 'Kas dan Bank', 'Asset');
    $purReturn = $coa('5120.10', 'Retur Pembelian', 'Expense');
    $cogs = $coa('5100.10', 'HPP', 'Expense');

    $uom = UnitOfMeasure::factory()->create(['name' => 'Unit', 'abbreviation' => 'unt']);

    $product = Product::factory()->create([
        'sku' => 'PUR-MAT-' . strtoupper(substr(uniqid(), -5)),
        'name' => 'Bahan Baku Pengadaan',
        'uom_id' => $uom->id,
        'cost_price' => 50000,
        'sell_price' => 80000,
        'inventory_coa_id' => $inventory->id,
        'unbilled_purchase_coa_id' => $unbilled->id,
        'purchase_return_coa_id' => $purReturn->id,
        'cogs_coa_id' => $cogs->id,
    ]);

    return array_merge(compact(
        'cabang', 'user', 'idr', 'warehouse', 'supplier', 'uom', 'product',
        'inventory', 'unbilled', 'ap', 'vatIn', 'pph22', 'bank', 'purReturn', 'cogs'
    ), $overrides);
}

/**
 * Buat PurchaseOrder dengan 1 baris item.
 */
function purOrder(array $ctx, float $qty = 10, float $unitPrice = 50000, array $attributes = []): array
{
    $totalAmount = round($qty * $unitPrice, 2);

    $po = PurchaseOrder::create(array_merge([
        'po_number' => 'PO-TEST-' . strtoupper(substr(uniqid(), -6)),
        'supplier_id' => $ctx['supplier']->id,
        'cabang_id' => $ctx['cabang']->id,
        'warehouse_id' => $ctx['warehouse']->id,
        'order_date' => now()->toDateString(),
        'expected_date' => now()->addDays(7)->toDateString(),
        'status' => 'approved',
        'total_amount' => $totalAmount,
        'top_type' => 'Tempo',
        'tempo_hutang' => 30,
        'created_by' => $ctx['user']->id,
    ], $attributes));

    $poItem = PurchaseOrderItem::create([
        'purchase_order_id' => $po->id,
        'product_id' => $ctx['product']->id,
        'quantity' => $qty,
        'unit_price' => $unitPrice,
        'currency_id' => $ctx['idr']->id,
        'discount' => 0,
        'tax' => 0,
        'tipe_pajak' => 'Non Pajak',
    ]);

    return [$po->fresh(), $poItem->fresh()];
}

/**
 * Buat PurchaseReceipt (GRN) dengan 1 baris item untuk PO yang ditentukan.
 */
function purReceipt(array $ctx, PurchaseOrder $po, float $qtyReceived = 10, array $attributes = []): array
{
    $poItem = $po->purchaseOrderItem()->first();

    $receipt = PurchaseReceipt::create(array_merge([
        'receipt_number' => 'GRN-TEST-' . strtoupper(substr(uniqid(), -6)),
        'purchase_order_id' => $po->id,
        'receipt_date' => now(),
        'received_by' => $ctx['user']->id,
        'status' => 'completed',
        'cabang_id' => $ctx['cabang']->id,
        'currency_id' => $ctx['idr']->id,
    ], $attributes));

    $receiptItem = PurchaseReceiptItem::create([
        'purchase_receipt_id' => $receipt->id,
        'purchase_order_item_id' => $poItem?->id,
        'product_id' => $poItem?->product_id ?? $ctx['product']->id,
        'qty_received' => $qtyReceived,
        'qty_accepted' => $qtyReceived,
        'qty_rejected' => 0,
        'warehouse_id' => $ctx['warehouse']->id,
        'status' => 'completed',
    ]);

    return [$receipt->fresh(), $receiptItem->fresh()];
}
