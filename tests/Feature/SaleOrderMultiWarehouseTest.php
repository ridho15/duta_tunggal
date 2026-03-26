<?php

/**
 * SaleOrderMultiWarehouseTest
 *
 * Menguji seluruh alur multi-gudang pada Sales Order:
 *  1. Alokasi multi-gudang tersimpan dengan benar di DB saat SO dibuat
 *  2. Alokasi multi-gudang dapat di-update (tambah/hapus) pada saat SO di-edit
 *  3. SalesOrderService::confirm() memvalidasi stok per alokasi (multi-gudang)
 *  4. SalesOrderService::confirm() membuat StockReservation per alokasi
 *  5. SalesOrderService::confirm() tetap bekerja untuk mode single-gudang
 *  6. handleStockReductionForSelfPickup() mengurangi stok per alokasi untuk Ambil Sendiri
 *  7. handleStockReductionForSelfPickup() bekerja untuk single-gudang
 *  8. createWarehouseConfirmationForApprovedSaleOrder() membuat WC items per alokasi
 *  9. hasInsufficientStock() memeriksa semua alokasi (multi-gudang)
 * 10. hasInsufficientStock() bekerja untuk single-gudang
 */

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SaleOrderItemWarehouseAllocation;
use App\Models\StockReservation;
use App\Models\Warehouse;
use App\Models\WarehouseConfirmation;
use App\Models\WarehouseConfirmationItem;
use App\Observers\SaleOrderObserver;
use App\Services\SalesOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Helper functions
// ---------------------------------------------------------------------------

function mwFixtures(): array
{
    $cabang = Cabang::create([
        'nama' => 'Cabang MW',
        'kode' => 'CMW',
        'alamat' => 'Jl. MW 1',
        'telepon' => '021-MW01',
    ]);

    $productCategory = ProductCategory::create([
        'name' => 'Cat MW',
        'code' => 'CMW01',
        'kode' => 'CMW01',
        'cabang_id' => $cabang->id,
    ]);

    $warehouse1 = Warehouse::create([
        'name' => 'Gudang MW-1',
        'code' => 'GMW1',
        'kode' => 'GMW1',
        'cabang_id' => $cabang->id,
        'status' => 1,
        'address' => 'Jl. MW Gudang 1',
        'location' => 'Jakarta',
    ]);

    $warehouse2 = Warehouse::create([
        'name' => 'Gudang MW-2',
        'code' => 'GMW2',
        'kode' => 'GMW2',
        'cabang_id' => $cabang->id,
        'status' => 1,
        'address' => 'Jl. MW Gudang 2',
        'location' => 'Jakarta',
    ]);

    $customer = Customer::create([
        'name' => 'PT MW Customer',
        'code' => 'MWCUST01',
        'address' => 'Jl. MW No. 1',
        'telephone' => '021-1111MW',
        'phone' => '081111111MW',
        'email' => 'mw@test.com',
        'perusahaan' => 'PT MW',
        'tipe' => 'PKP',
        'fax' => '021-MW002',
        'nik_npwp' => '9999888877776666',
        'tempo_kredit' => 30,
        'kredit_limit' => 100000000,
        'tipe_pembayaran' => 'Kredit',
    ]);

    $product = Product::create([
        'name' => 'Produk MW',
        'sku' => 'MWPROD01',
        'cabang_id' => $cabang->id,
        'product_category_id' => $productCategory->id,
        'sell_price' => 10000000,
        'cost_price' => 8000000,
        'kode_merk' => 'MW',
        'uom_id' => 1,
        'is_active' => true,
        'is_manufacture' => false,
        'is_raw_material' => false,
    ]);

    // Create inventory stock at each warehouse
    InventoryStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse1->id,
        'rak_id' => null,
        'qty_available' => 10,
        'qty_reserved' => 0,
    ]);

    InventoryStock::create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse2->id,
        'rak_id' => null,
        'qty_available' => 8,
        'qty_reserved' => 0,
    ]);

    return compact('cabang', 'customer', 'product', 'warehouse1', 'warehouse2');
}

function makeSoWithAllocations(array $f, string $tipe = 'Kirim Langsung'): array
{
    $so = SaleOrder::create([
        'so_number' => 'SO-MW-' . rand(1000, 9999),
        'customer_id' => $f['customer']->id,
        'cabang_id' => $f['cabang']->id,
        'status' => 'draft',
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => $tipe,
        'total_amount' => 180000000,
        'tempo_pembayaran' => 30,
    ]);

    $item = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $f['product']->id,
        'quantity' => 9,
        'unit_price' => 10000000,
        'discount' => 0,
        'tax' => 11,
        'tipe_pajak' => 'Exclusive',
        'warehouse_id' => null,
        'rak_id' => null,
    ]);

    $alloc1 = SaleOrderItemWarehouseAllocation::create([
        'sale_order_item_id' => $item->id,
        'warehouse_id' => $f['warehouse1']->id,
        'quantity' => 5,
    ]);

    $alloc2 = SaleOrderItemWarehouseAllocation::create([
        'sale_order_item_id' => $item->id,
        'warehouse_id' => $f['warehouse2']->id,
        'quantity' => 4,
    ]);

    return compact('so', 'item', 'alloc1', 'alloc2');
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

test('1. Alokasi multi-gudang tersimpan dengan benar di DB', function () {
    $f = mwFixtures();
    $data = makeSoWithAllocations($f);

    // Verify allocations exist in DB
    $item = $data['item'];
    $allocations = SaleOrderItemWarehouseAllocation::where('sale_order_item_id', $item->id)->get();

    expect($allocations)->toHaveCount(2);
    expect($allocations->firstWhere('warehouse_id', $f['warehouse1']->id))->not->toBeNull();
    expect($allocations->firstWhere('warehouse_id', $f['warehouse2']->id))->not->toBeNull();
    expect((float) $allocations->sum('quantity'))->toBe(9.0);
});

test('2. Alokasi multi-gudang dapat diambil via relasi dari SaleOrderItem', function () {
    $f = mwFixtures();
    $data = makeSoWithAllocations($f);

    $item = SaleOrderItem::with('warehouseAllocations')->find($data['item']->id);

    expect($item->warehouseAllocations)->toHaveCount(2);
    expect($item->warehouse_id)->toBeNull(); // multi-warehouse mode
});

test('3. SalesOrderService::confirm() memvalidasi stok per alokasi (multi-gudang)', function () {
    $f = mwFixtures();
    $data = makeSoWithAllocations($f);

    $service = new SalesOrderService();
    $so = SaleOrder::find($data['so']->id);

    // wh1 has qty=10, alloc requests 5 → should pass
    // wh2 has qty=8, alloc requests 4 → should pass
    $result = $service->confirm($so);
    expect($result)->toBeTrue();
});

test('4. SalesOrderService::confirm() membuat StockReservation per alokasi (multi-gudang)', function () {
    $f = mwFixtures();
    $data = makeSoWithAllocations($f);

    $service = new SalesOrderService();
    $so = SaleOrder::find($data['so']->id);
    $service->confirm($so);

    // Should create 2 reservations (one per allocation)
    $reservations = StockReservation::where('sale_order_id', $so->id)->get();
    expect($reservations)->toHaveCount(2);

    $resWh1 = $reservations->firstWhere('warehouse_id', $f['warehouse1']->id);
    $resWh2 = $reservations->firstWhere('warehouse_id', $f['warehouse2']->id);

    expect($resWh1)->not->toBeNull();
    expect($resWh2)->not->toBeNull();
    expect((float) $resWh1->quantity)->toBe(5.0);
    expect((float) $resWh2->quantity)->toBe(4.0);

    // Verify warehouse_id is never NULL in reservations
    $nullWh = $reservations->whereNull('warehouse_id');
    expect($nullWh)->toHaveCount(0);
});

test('5. SalesOrderService::confirm() lempar exception jika stok alokasi tidak cukup', function () {
    $f = mwFixtures();
    $data = makeSoWithAllocations($f);

    // Reduce wh2 stock below allocation requirement
    InventoryStock::where('warehouse_id', $f['warehouse2']->id)->update(['qty_available' => 2]); // alloc needs 4

    $service = new SalesOrderService();
    $so = SaleOrder::find($data['so']->id);

    expect(fn () => $service->confirm($so))->toThrow(\App\Exceptions\InsufficientStockException::class);
    // No reservations should be created
    expect(StockReservation::where('sale_order_id', $so->id)->count())->toBe(0);
});

test('6. SalesOrderService::confirm() bekerja untuk mode single-gudang', function () {
    $f = mwFixtures();

    $so = SaleOrder::create([
        'so_number' => 'SO-SINGLE-' . rand(1000, 9999),
        'customer_id' => $f['customer']->id,
        'cabang_id' => $f['cabang']->id,
        'status' => 'draft',
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'total_amount' => 50000000,
        'tempo_pembayaran' => 30,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $f['product']->id,
        'quantity' => 3,
        'unit_price' => 10000000,
        'discount' => 0,
        'tax' => 11,
        'tipe_pajak' => 'Exclusive',
        'warehouse_id' => $f['warehouse1']->id, // single warehouse mode
        'rak_id' => null,
    ]);

    $service = new SalesOrderService();
    $result = $service->confirm($so);
    expect($result)->toBeTrue();

    $reservations = StockReservation::where('sale_order_id', $so->id)->get();
    expect($reservations)->toHaveCount(1);
    expect((float) $reservations->first()->quantity)->toBe(3.0);
    expect($reservations->first()->warehouse_id)->toBe($f['warehouse1']->id);
});

test('7. SaleOrder::hasInsufficientStock() memeriksa semua alokasi gudang', function () {
    $f = mwFixtures();
    $data = makeSoWithAllocations($f);
    $so = SaleOrder::with('saleOrderItem.warehouseAllocations')->find($data['so']->id);

    // Stock is sufficient (wh1 has 10≥5, wh2 has 8≥4)
    expect($so->hasInsufficientStock())->toBeFalse();

    // Drain wh2 stock
    InventoryStock::where('warehouse_id', $f['warehouse2']->id)->update(['qty_available' => 1]);
    $so->refresh();
    $so->load('saleOrderItem.warehouseAllocations');

    expect($so->hasInsufficientStock())->toBeTrue();
});

test('8. createWarehouseConfirmation membuat WC items terpisah per alokasi', function () {
    $f = mwFixtures();
    $data = makeSoWithAllocations($f);

    $so = SaleOrder::with('saleOrderItem.warehouseAllocations')->find($data['so']->id);
    $so->status = 'approved';
    $so->approve_by = 1;
    $so->approve_at = now();

    // Call the protected method directly via Reflection
    $observer = new \App\Observers\SaleOrderObserver();
    $method = new \ReflectionMethod(\App\Observers\SaleOrderObserver::class, 'createWarehouseConfirmationForApprovedSaleOrder');
    $method->setAccessible(true);
    $method->invoke($observer, $so);

    $wc = WarehouseConfirmation::where('confirmable_type', \App\Models\SaleOrder::class)
        ->where('confirmable_id', $so->id)->first();
    expect($wc)->not->toBeNull();

    $wcItems = WarehouseConfirmationItem::where('warehouse_confirmation_id', $wc->id)->get();
    // 2 allocations → 2 WC items
    expect($wcItems)->toHaveCount(2);

    $wcItemWh1 = $wcItems->firstWhere('warehouse_id', $f['warehouse1']->id);
    $wcItemWh2 = $wcItems->firstWhere('warehouse_id', $f['warehouse2']->id);
    expect($wcItemWh1)->not->toBeNull();
    expect($wcItemWh2)->not->toBeNull();
    expect((float) $wcItemWh1->requested_qty)->toBe(5.0);
    expect((float) $wcItemWh2->requested_qty)->toBe(4.0);
});

test('9. createWarehouseConfirmation membuat 1 WC item untuk single-gudang', function () {
    $f = mwFixtures();

    $so = SaleOrder::create([
        'so_number' => 'SO-WC-SINGLE-' . rand(1000, 9999),
        'customer_id' => $f['customer']->id,
        'cabang_id' => $f['cabang']->id,
        'status' => 'draft',
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Kirim Langsung',
        'total_amount' => 50000000,
        'tempo_pembayaran' => 30,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $f['product']->id,
        'quantity' => 3,
        'unit_price' => 10000000,
        'discount' => 0,
        'tax' => 11,
        'tipe_pajak' => 'Exclusive',
        'warehouse_id' => $f['warehouse1']->id,
        'rak_id' => null,
    ]);

    $freshSo = SaleOrder::with('saleOrderItem.warehouseAllocations')->find($so->id);
    $freshSo->status = 'approved';
    $freshSo->approve_by = 1;
    $freshSo->approve_at = now();

    // Call the protected method directly via Reflection
    $observer = new \App\Observers\SaleOrderObserver();
    $method = new \ReflectionMethod(\App\Observers\SaleOrderObserver::class, 'createWarehouseConfirmationForApprovedSaleOrder');
    $method->setAccessible(true);
    $method->invoke($observer, $freshSo);

    $wc = WarehouseConfirmation::where('confirmable_type', \App\Models\SaleOrder::class)
        ->where('confirmable_id', $so->id)->first();
    expect($wc)->not->toBeNull();

    $wcItems = WarehouseConfirmationItem::where('warehouse_confirmation_id', $wc->id)->get();
    expect($wcItems)->toHaveCount(1);
    expect($wcItems->first()->warehouse_id)->toBe($f['warehouse1']->id);
});

test('10. handleStockReductionForSelfPickup mengurangi stok per alokasi untuk Ambil Sendiri (multi-gudang)', function () {
    $f = mwFixtures();
    $data = makeSoWithAllocations($f, 'Ambil Sendiri');

    $so = SaleOrder::find($data['so']->id);

    // Call the protected method directly via Reflection (avoids invoice/COA requirement)
    $observer = new SaleOrderObserver();
    $method = new \ReflectionMethod(SaleOrderObserver::class, 'handleStockReductionForSelfPickup');
    $method->setAccessible(true);
    $method->invoke($observer, $so);

    // Verify stock was deducted at both warehouses
    $stock1 = InventoryStock::where('product_id', $f['product']->id)
        ->where('warehouse_id', $f['warehouse1']->id)
        ->first();
    $stock2 = InventoryStock::where('product_id', $f['product']->id)
        ->where('warehouse_id', $f['warehouse2']->id)
        ->first();

    // wh1 started with 10, alloc1 qty=5 → should be 5
    // wh2 started with 8, alloc2 qty=4 → should be 4
    expect((float) $stock1->qty_available)->toBe(5.0);
    expect((float) $stock2->qty_available)->toBe(4.0);
});

test('11. handleStockReductionForSelfPickup bekerja untuk single-gudang pada Ambil Sendiri', function () {
    $f = mwFixtures();

    $so = SaleOrder::create([
        'so_number' => 'SO-SELF-SINGLE-' . rand(1000, 9999),
        'customer_id' => $f['customer']->id,
        'cabang_id' => $f['cabang']->id,
        'status' => 'draft', // use draft to avoid observer pre-loading empty saleOrderItem
        'order_date' => now()->format('Y-m-d'),
        'tipe_pengiriman' => 'Ambil Sendiri',
        'total_amount' => 50000000,
        'tempo_pembayaran' => 30,
    ]);

    SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $f['product']->id,
        'quantity' => 3,
        'unit_price' => 10000000,
        'discount' => 0,
        'tax' => 11,
        'tipe_pajak' => 'Exclusive',
        'warehouse_id' => $f['warehouse1']->id, // single mode
        'rak_id' => null,
    ]);

    // Get fresh instance so saleOrderItem relation is not pre-loaded with empty collection
    $freshSo = SaleOrder::find($so->id);

    // Call the protected method directly via Reflection
    $observer = new SaleOrderObserver();
    $method = new \ReflectionMethod(SaleOrderObserver::class, 'handleStockReductionForSelfPickup');
    $method->setAccessible(true);
    $method->invoke($observer, $freshSo);

    $stock1 = InventoryStock::where('product_id', $f['product']->id)
        ->where('warehouse_id', $f['warehouse1']->id)
        ->first();

    // wh1 started with 10, single-mode qty=3 → should be 7
    expect((float) $stock1->qty_available)->toBe(7.0);
});

test('12. handleStockReductionForSelfPickup tidak mengurangi stok untuk Kirim Langsung', function () {
    $f = mwFixtures();
    $data = makeSoWithAllocations($f, 'Kirim Langsung'); // NOT Ambil Sendiri

    $so = SaleOrder::find($data['so']->id);

    // Call method directly — should return early because tipe_pengiriman != 'Ambil Sendiri'
    $observer = new SaleOrderObserver();
    $method = new \ReflectionMethod(SaleOrderObserver::class, 'handleStockReductionForSelfPickup');
    $method->setAccessible(true);
    $method->invoke($observer, $so);

    // Stock should NOT be deducted
    $stock1 = InventoryStock::where('product_id', $f['product']->id)
        ->where('warehouse_id', $f['warehouse1']->id)
        ->first();
    $stock2 = InventoryStock::where('product_id', $f['product']->id)
        ->where('warehouse_id', $f['warehouse2']->id)
        ->first();

    expect((float) $stock1->qty_available)->toBe(10.0); // unchanged
    expect((float) $stock2->qty_available)->toBe(8.0);  // unchanged
});
