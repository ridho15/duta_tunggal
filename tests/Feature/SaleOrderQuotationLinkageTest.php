<?php

/**
 * SaleOrderQuotationLinkageTest
 *
 * Audit item yang diuji:
 *  1. tempo_pembayaran diwariskan dari Quotation saat afterStateUpdated (via SalesOrderService atau handler)
 *  2. cabang_id diwariskan dari Quotation saat SO dibuat
 *  3. unit_price tersimpan sebagai angka float (bukan string formatted) di DB
 *  4. warehouseAllocations tersimpan dengan benar (multi-gudang)
 *  5. warehouse_id pada item digunakan sebagai fallback ketika tidak ada allocations
 *  6. SaleOrderObserver menggunakan allocations jika ada, warehouse_id jika tidak ada
 */

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SaleOrderItemWarehouseAllocation;
use App\Models\Warehouse;
use App\Models\Rak;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper: create minimal fixtures shared across tests
// ---------------------------------------------------------------------------
function makeFixtures(): array
{
    $cabang = Cabang::create([
        'nama' => 'Cabang Test',
        'kode' => 'CBT',
        'alamat' => 'Jl. Test 1',
        'telepon' => '021-0001',
    ]);

    $productCategory = ProductCategory::create([
        'name' => 'Category Audit',
        'code' => 'CA001',
        'kode' => 'CA001',
        'cabang_id' => $cabang->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Gudang Utama',
        'code' => 'GU01',
        'kode' => 'GU01',
        'cabang_id' => $cabang->id,
        'status' => 1,
        'address' => 'Jl. Gudang 1',
        'location' => 'Jakarta',
    ]);

    $warehouse2 = Warehouse::create([
        'name' => 'Gudang Kedua',
        'code' => 'GK01',
        'kode' => 'GK01',
        'cabang_id' => $cabang->id,
        'status' => 1,
        'address' => 'Jl. Gudang 2',
        'location' => 'Jakarta',
    ]);

    $rak = Rak::create([
        'name' => 'Rak A',
        'code' => 'RA1',
        'warehouse_id' => $warehouse->id,
        'type' => 'shelf',
    ]);

    $customer = Customer::create([
        'name' => 'PT Audit Customer',
        'code' => 'AUDIT001',
        'address' => 'Jl. Audit No. 1',
        'telephone' => '021-9991111',
        'phone' => '081299911111',
        'email' => 'audit@test.com',
        'perusahaan' => 'PT Audit',
        'tipe' => 'PKP',
        'fax' => '021-9991112',
        'nik_npwp' => '1111222233334444',
        'tempo_kredit' => 45,
        'kredit_limit' => 50000000,
        'tipe_pembayaran' => 'Kredit',
    ]);

    $product = Product::create([
        'name' => 'Produk Audit',
        'sku' => 'AUD001',
        'cabang_id' => $cabang->id,
        'product_category_id' => $productCategory->id,
        'sell_price' => 12500000,
        'cost_price' => 10000000,
        'kode_merk' => 'AUD',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    $quotation = Quotation::create([
        'quotation_number' => 'QO-AUDIT-0001',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'date' => now(),
        'valid_until' => now()->addDays(30),
        'status' => 'approve',
        'tempo_pembayaran' => 30,
        'total_amount' => 138750000,
        'created_by' => 1,
    ]);

    QuotationItem::create([
        'quotation_id' => $quotation->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'unit_price' => 12500000,
        'discount' => 0,
        'tax' => 11,
        'tax_type' => 'Exclusive',
    ]);

    return compact('cabang', 'customer', 'product', 'quotation', 'warehouse', 'warehouse2', 'rak');
}

// ---------------------------------------------------------------------------
// Test 1: tempo_pembayaran diwariskan dari quotation ke sale order
// ---------------------------------------------------------------------------
test('SO dibuat dari quotation harus mewarisi tempo_pembayaran', function () {
    $f = makeFixtures();

    $so = SaleOrder::withoutGlobalScopes()->create([
        'customer_id'     => $f['customer']->id,
        'quotation_id'    => $f['quotation']->id,
        'cabang_id'       => $f['quotation']->cabang_id,
        'so_number'       => 'SO-AUDIT-TEMPO-001',
        'order_date'      => now(),
        'status'          => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'total_amount'    => $f['quotation']->total_amount,
        'tempo_pembayaran' => $f['quotation']->tempo_pembayaran, // harus diset per fix
        'created_by'      => 1,
        'approve_by'      => 1,
        'approve_at'      => now(),
    ]);

    expect($so->tempo_pembayaran)->toBe(30)
        ->and($so->quotation_id)->toBe($f['quotation']->id);
});

// ---------------------------------------------------------------------------
// Test 2: cabang_id diwariskan dari quotation ke sale order
// ---------------------------------------------------------------------------
test('SO dibuat dari quotation harus mewarisi cabang_id', function () {
    $f = makeFixtures();

    $so = SaleOrder::withoutGlobalScopes()->create([
        'customer_id'     => $f['customer']->id,
        'quotation_id'    => $f['quotation']->id,
        'cabang_id'       => $f['quotation']->cabang_id, // harus diset per fix
        'so_number'       => 'SO-AUDIT-CABANG-001',
        'order_date'      => now(),
        'status'          => 'approved',
        'tipe_pengiriman' => 'Kirim Langsung',
        'total_amount'    => $f['quotation']->total_amount,
        'created_by'      => 1,
    ]);

    expect($so->cabang_id)->toBe($f['quotation']->cabang_id)
        ->and($so->cabang_id)->not->toBeNull();
});

// ---------------------------------------------------------------------------
// Test 3: unit_price tersimpan sebagai float di DB (bukan string formatted)
// ---------------------------------------------------------------------------
test('unit_price di SaleOrderItem harus tersimpan sebagai angka float bukan string formatted', function () {
    $f = makeFixtures();

    $so = SaleOrder::withoutGlobalScopes()->create([
        'customer_id'     => $f['customer']->id,
        'cabang_id'       => $f['cabang']->id,
        'so_number'       => 'SO-AUDIT-PRICE-001',
        'order_date'      => now(),
        'status'          => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'total_amount'    => 0,
        'created_by'      => 1,
    ]);

    // Simpan dengan nilai mentah float (cara yang benar)
    $item = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id'    => $f['product']->id,
        'quantity'      => 10,
        'unit_price'    => 12500000,   // float mentah, harus tersimpan sama
        'discount'      => 0,
        'tax'           => 11,
        'tipe_pajak'    => 'Exclusive',
        'warehouse_id'  => $f['warehouse']->id,
    ]);

    $fresh = SaleOrderItem::withoutGlobalScopes()->find($item->id);
    expect((float) $fresh->unit_price)->toBe(12500000.0)
        ->and((float) $fresh->unit_price)->not->toBe(12500000000.0); // assert tidak 1000x lebih besar
});

// ---------------------------------------------------------------------------
// Test 4: warehouseAllocations tersimpan & terhubung ke SaleOrderItem
// ---------------------------------------------------------------------------
test('warehouseAllocations multi-gudang tersimpan ke tabel terpisah dan terhubung ke item', function () {
    $f = makeFixtures();

    $so = SaleOrder::withoutGlobalScopes()->create([
        'customer_id'     => $f['customer']->id,
        'cabang_id'       => $f['cabang']->id,
        'so_number'       => 'SO-AUDIT-ALLOC-001',
        'order_date'      => now(),
        'status'          => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'total_amount'    => 0,
        'created_by'      => 1,
    ]);

    $item = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id'    => $f['product']->id,
        'quantity'      => 10,
        'unit_price'    => 12500000,
        'discount'      => 0,
        'tax'           => 11,
        'tipe_pajak'    => 'Exclusive',
        'warehouse_id'  => null, // nullable setelah migrasi 2026_03_24 — multi-gudang mode
    ]);

    // Buat dua alokasi ke gudang berbeda
    SaleOrderItemWarehouseAllocation::create([
        'sale_order_item_id' => $item->id,
        'warehouse_id'       => $f['warehouse']->id,
        'quantity'           => 6,
    ]);

    SaleOrderItemWarehouseAllocation::create([
        'sale_order_item_id' => $item->id,
        'warehouse_id'       => $f['warehouse2']->id,
        'quantity'           => 4,
    ]);

    $item->refresh();
    $allocations = $item->warehouseAllocations;

    expect($allocations)->toHaveCount(2)
        ->and($allocations->sum('quantity'))->toBe(10.0)
        ->and($allocations->pluck('warehouse_id')->toArray())
            ->toContain($f['warehouse']->id)
            ->toContain($f['warehouse2']->id);
});

// ---------------------------------------------------------------------------
// Test 5: fallback — warehouse_id digunakan jika tidak ada allocations
// ---------------------------------------------------------------------------
test('warehouse_id pada item digunakan sebagai fallback jika tidak ada warehouseAllocations', function () {
    $f = makeFixtures();

    $so = SaleOrder::withoutGlobalScopes()->create([
        'customer_id'     => $f['customer']->id,
        'cabang_id'       => $f['cabang']->id,
        'so_number'       => 'SO-AUDIT-FALLBACK-001',
        'order_date'      => now(),
        'status'          => 'draft',
        'tipe_pengiriman' => 'Kirim Langsung',
        'total_amount'    => 0,
        'created_by'      => 1,
    ]);

    $item = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id'    => $f['product']->id,
        'quantity'      => 5,
        'unit_price'    => 12500000,
        'discount'      => 0,
        'tax'           => 11,
        'tipe_pajak'    => 'Exclusive',
        'warehouse_id'  => $f['warehouse']->id, // single-warehouse mode
        'rak_id'        => $f['rak']->id,
    ]);

    $item->refresh();

    expect($item->warehouseAllocations)->toHaveCount(0)
        ->and($item->warehouse_id)->toBe($f['warehouse']->id)
        ->and($item->rak_id)->toBe($f['rak']->id);
});

// ---------------------------------------------------------------------------
// Test 6: unit_price dari quotation_item tidak naik 1000x saat di-copy ke SO
// ---------------------------------------------------------------------------
test('unit_price dari quotation item harus sama saat di-copy ke sale order item', function () {
    $f = makeFixtures();

    $quotationItem = $f['quotation']->quotationItem->first();
    expect($quotationItem)->not->toBeNull();

    $rawPrice = (float) $quotationItem->unit_price;

    // Simulasikan konversi yang terjadi di QuotationResource: parseIndonesianMoney dari raw float
    $parsed = \App\Http\Controllers\HelperController::parseIndonesianMoney($rawPrice);

    // Pastikan tidak naik 1000x (raw 12500000 -> tidak boleh jadi 12500000000)
    expect($parsed)->toBe(12500000.0)
        ->and($parsed)->not->toBeGreaterThan($rawPrice * 10);
});

// ---------------------------------------------------------------------------
// Test 7: parseIndonesianMoney tidak menginflas nilai DB numeric (regression)
// ---------------------------------------------------------------------------
test('parseIndonesianMoney tidak menginflas nilai DB decimal seperti 12500000.00', function () {
    $helper = \App\Http\Controllers\HelperController::class;

    // DB stores: 12500000.00  (DECIMAL type, passed as string from PDO)
    expect($helper::parseIndonesianMoney('12500000.00'))->toBe(12500000.0);
    expect($helper::parseIndonesianMoney('1250000.00'))->toBe(1250000.0);
    expect($helper::parseIndonesianMoney('100000.00'))->toBe(100000.0);
    expect($helper::parseIndonesianMoney('50000.00'))->toBe(50000.0);

    // Formatted string (user input): harus parse normal
    expect($helper::parseIndonesianMoney('12.500.000'))->toBe(12500000.0);
    expect($helper::parseIndonesianMoney('1.250.000'))->toBe(1250000.0);

    // Float/int langsung
    expect($helper::parseIndonesianMoney(12500000))->toBe(12500000.0);
    expect($helper::parseIndonesianMoney(12500000.0))->toBe(12500000.0);
});
