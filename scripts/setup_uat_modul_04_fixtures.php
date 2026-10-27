<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\Driver;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

echo "=== MENYIAPKAN FIXTURES UAT MODUL 04 (SURAT JALAN KOLEKTIF & AUTO-RECEIVED) ===\n";

$cabang = Cabang::find(27);
$whUtama = Warehouse::find(38);
$whKenari = Warehouse::find(39);
$customer = Customer::where('code', 'CUST-TEST-UAT-001')->first();
$prodA = Product::where('sku', 'PROD-TEST-A')->first();
$adminPengiriman = User::where('email', 'admin_pengiriman@example.com')->first();

// Pastikan driver & kendaraan tersedia
$driver = Driver::firstOrCreate(
    ['name' => 'Budi Santoso'],
    ['phone' => '081234567890', 'is_active' => true]
);
$vehicle = Vehicle::firstOrCreate(
    ['plate' => 'B 9876 DTA'],
    ['type' => 'Engkel Box', 'is_active' => true]
);

// Pastikan stok PROD-TEST-A cukup di Gudang Utama & Gudang Kenari
InventoryStock::updateOrCreate(
    ['product_id' => $prodA->id, 'warehouse_id' => $whUtama->id],
    ['qty_available' => 100, 'qty_reserved' => 0]
);
InventoryStock::updateOrCreate(
    ['product_id' => $prodA->id, 'warehouse_id' => $whKenari->id],
    ['qty_available' => 50, 'qty_reserved' => 0]
);

// -------------------------------------------------------------
// FIXTURE 1: Multi-DO dalam 1 SO untuk UAT-SJ-01, 04, 05, 06
// -------------------------------------------------------------
DB::transaction(function () use ($cabang, $whUtama, $whKenari, $customer, $prodA, $adminPengiriman) {
    // Bersihkan Surat Jalan sebelumnya jika ada
    $oldSJs = SuratJalan::whereHas('deliveryOrder', function ($q) {
        $q->whereIn('do_number', ['DO-TEST-SJ-01A', 'DO-TEST-SJ-01B']);
    })->get();
    foreach ($oldSJs as $sj) {
        $sj->deliveryOrder()->detach();
        $sj->forceDelete();
    }

    DeliveryOrder::whereIn('do_number', ['DO-TEST-SJ-01A', 'DO-TEST-SJ-01B'])->forceDelete();
    SaleOrder::where('so_number', 'SO-TEST-SJ-01')->forceDelete();

    $so = SaleOrder::create([
        'so_number' => 'SO-TEST-SJ-01',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'status' => 'approved',
        'order_date' => now(),
        'delivery_date' => now()->addDays(2),
        'created_by' => $adminPengiriman->id,
        'total_amount' => 15 * 7500,
        'shipping_cost' => 0,
        'shipped_to' => 'Jl. Pengujian UAT No. 40, Jakarta Pusat',
    ]);

    $soItemA = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $prodA->id,
        'quantity' => 15,
        'unit_price' => 7500,
        'subtotal' => 15 * 7500,
    ]);

    // DO 1: Dari Gudang Utama (10 pcs)
    $do1 = DeliveryOrder::create([
        'do_number' => 'DO-TEST-SJ-01A',
        'cabang_id' => $cabang->id,
        'warehouse_id' => $whUtama->id,
        'delivery_date' => now(),
        'status' => 'approved',
        'created_by' => $adminPengiriman->id,
    ]);
    $do1->salesOrders()->attach($so->id);

    $doItem1 = DeliveryOrderItem::create([
        'delivery_order_id' => $do1->id,
        'sale_order_item_id' => $soItemA->id,
        'product_id' => $prodA->id,
        'quantity' => 10,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItem1->id,
        'warehouse_id' => $whUtama->id,
        'quantity' => 10,
    ]);

    // DO 2: Dari Gudang Kenari (5 pcs)
    $do2 = DeliveryOrder::create([
        'do_number' => 'DO-TEST-SJ-01B',
        'cabang_id' => $cabang->id,
        'warehouse_id' => $whKenari->id,
        'delivery_date' => now(),
        'status' => 'approved',
        'created_by' => $adminPengiriman->id,
    ]);
    $do2->salesOrders()->attach($so->id);

    $doItem2 = DeliveryOrderItem::create([
        'delivery_order_id' => $do2->id,
        'sale_order_item_id' => $soItemA->id,
        'product_id' => $prodA->id,
        'quantity' => 5,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItem2->id,
        'warehouse_id' => $whKenari->id,
        'quantity' => 5,
    ]);

    echo "Fixture 1 dibuat: SO-TEST-SJ-01 dengan DO-TEST-SJ-01A (ID: {$do1->id}, Gudang Utama 10 pcs) & DO-TEST-SJ-01B (ID: {$do2->id}, Gudang Kenari 5 pcs).\n";
});

// -------------------------------------------------------------
// FIXTURE 2: Dua SO Berbeda untuk UAT-SJ-02 (Blokir Cross-SO)
// -------------------------------------------------------------
DB::transaction(function () use ($cabang, $whUtama, $customer, $prodA, $adminPengiriman) {
    DeliveryOrder::whereIn('do_number', ['DO-TEST-SJ-02A', 'DO-TEST-SJ-02B'])->forceDelete();
    SaleOrder::whereIn('so_number', ['SO-TEST-SJ-02A', 'SO-TEST-SJ-02B'])->forceDelete();

    // SO A
    $soA = SaleOrder::create([
        'so_number' => 'SO-TEST-SJ-02A',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'status' => 'approved',
        'order_date' => now(),
        'delivery_date' => now()->addDays(2),
        'created_by' => $adminPengiriman->id,
        'total_amount' => 5 * 7500,
        'shipping_cost' => 0,
        'shipped_to' => 'Jl. Cross SO No. 1, Jakarta',
    ]);
    $soItemA = SaleOrderItem::create([
        'sale_order_id' => $soA->id,
        'product_id' => $prodA->id,
        'quantity' => 5,
        'unit_price' => 7500,
        'subtotal' => 37500,
    ]);
    $doA = DeliveryOrder::create([
        'do_number' => 'DO-TEST-SJ-02A',
        'cabang_id' => $cabang->id,
        'warehouse_id' => $whUtama->id,
        'delivery_date' => now(),
        'status' => 'approved',
        'created_by' => $adminPengiriman->id,
    ]);
    $doA->salesOrders()->attach($soA->id);
    $doItemA = DeliveryOrderItem::create([
        'delivery_order_id' => $doA->id,
        'sale_order_item_id' => $soItemA->id,
        'product_id' => $prodA->id,
        'quantity' => 5,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItemA->id,
        'warehouse_id' => $whUtama->id,
        'quantity' => 5,
    ]);

    // SO B
    $soB = SaleOrder::create([
        'so_number' => 'SO-TEST-SJ-02B',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'status' => 'approved',
        'order_date' => now(),
        'delivery_date' => now()->addDays(2),
        'created_by' => $adminPengiriman->id,
        'total_amount' => 5 * 7500,
        'shipping_cost' => 0,
        'shipped_to' => 'Jl. Cross SO No. 2, Jakarta',
    ]);
    $soItemB = SaleOrderItem::create([
        'sale_order_id' => $soB->id,
        'product_id' => $prodA->id,
        'quantity' => 5,
        'unit_price' => 7500,
        'subtotal' => 37500,
    ]);
    $doB = DeliveryOrder::create([
        'do_number' => 'DO-TEST-SJ-02B',
        'cabang_id' => $cabang->id,
        'warehouse_id' => $whUtama->id,
        'delivery_date' => now(),
        'status' => 'approved',
        'created_by' => $adminPengiriman->id,
    ]);
    $doB->salesOrders()->attach($soB->id);
    $doItemB = DeliveryOrderItem::create([
        'delivery_order_id' => $doB->id,
        'sale_order_item_id' => $soItemB->id,
        'product_id' => $prodA->id,
        'quantity' => 5,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItemB->id,
        'warehouse_id' => $whUtama->id,
        'quantity' => 5,
    ]);

    echo "Fixture 2 dibuat: SO-TEST-SJ-02A (DO ID: {$doA->id}) & SO-TEST-SJ-02B (DO ID: {$doB->id}).\n";
});

echo "Semua fixtures UAT Modul 04 berhasil disiapkan!\n";
