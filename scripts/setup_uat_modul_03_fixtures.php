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
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

echo "=== MENYIAPKAN FIXTURES UAT MODUL 03 (CHECKER & RETUR DO) ===\n";

$cabang = Cabang::find(27);
$whUtama = Warehouse::find(38);
$whKenari = Warehouse::find(39);
$whKarantina = Warehouse::find(40);
$customer = Customer::where('code', 'CUST-TEST-UAT-001')->first();
$prodA = Product::where('sku', 'PROD-TEST-A')->first();
$prodB = Product::where('sku', 'PROD-TEST-B')->first();
$adminPengiriman = User::where('email', 'admin_pengiriman@example.com')->first();
$checker = User::where('email', 'checker@example.com')->first();

// Pastikan stok PROD-TEST-A dan PROD-TEST-B di Gudang Utama cukup
InventoryStock::updateOrCreate(
    ['product_id' => $prodA->id, 'warehouse_id' => $whUtama->id],
    ['qty_available' => 100, 'qty_reserved' => 0]
);
InventoryStock::updateOrCreate(
    ['product_id' => $prodB->id, 'warehouse_id' => $whUtama->id],
    ['qty_available' => 50, 'qty_reserved' => 0]
);

// 1. Fixture DO-TEST-CHK-01 (Multi Item untuk test centang lengkap vs tidak lengkap)
DB::transaction(function () use ($cabang, $whUtama, $customer, $prodA, $prodB, $adminPengiriman) {
    DeliveryOrder::where('do_number', 'DO-TEST-CHK-01')->forceDelete();
    SaleOrder::where('so_number', 'SO-TEST-CHK-01')->forceDelete();

    $so = SaleOrder::create([
        'so_number' => 'SO-TEST-CHK-01',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'status' => 'approved',
        'order_date' => now(),
        'delivery_date' => now()->addDays(2),
        'created_by' => $adminPengiriman->id,
        'total_amount' => (10 * 7500) + (5 * 5000),
        'shipping_cost' => 0,
        'shipped_to' => $customer->address,
    ]);

    $soItemA = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $prodA->id,
        'quantity' => 10,
        'unit_price' => 7500,
        'subtotal' => 75000,
    ]);

    $soItemB = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $prodB->id,
        'quantity' => 5,
        'unit_price' => 5000,
        'subtotal' => 25000,
    ]);

    $do = DeliveryOrder::create([
        'do_number' => 'DO-TEST-CHK-01',
        'cabang_id' => $cabang->id,
        'warehouse_id' => $whUtama->id,
        'delivery_date' => now(),
        'status' => 'approved',
        'created_by' => $adminPengiriman->id,
    ]);
    $do->salesOrders()->attach($so->id);

    $doItemA = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'sale_order_item_id' => $soItemA->id,
        'product_id' => $prodA->id,
        'quantity' => 10,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItemA->id,
        'warehouse_id' => $whUtama->id,
        'quantity' => 10,
    ]);

    $doItemB = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'sale_order_item_id' => $soItemB->id,
        'product_id' => $prodB->id,
        'quantity' => 5,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItemB->id,
        'warehouse_id' => $whUtama->id,
        'quantity' => 5,
    ]);

    echo "Fixture 1 dibuat: DO-TEST-CHK-01 (ID: {$do->id}) dengan 2 item (10 pcs PROD-A + 5 pcs PROD-B).\n";
});

// 2. Fixture DO-TEST-CHK-RETUR (Untuk UAT-CHK-03, 04, 05: Catat Kurang & Rusak, Retur DO & DO Pengganti)
DB::transaction(function () use ($cabang, $whUtama, $customer, $prodA, $adminPengiriman) {
    DeliveryOrder::where('do_number', 'DO-TEST-CHK-RETUR')->forceDelete();
    SaleOrder::where('so_number', 'SO-TEST-CHK-RETUR')->forceDelete();

    $so = SaleOrder::create([
        'so_number' => 'SO-TEST-CHK-RETUR',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'status' => 'approved',
        'order_date' => now(),
        'delivery_date' => now()->addDays(2),
        'created_by' => $adminPengiriman->id,
        'total_amount' => 20 * 7500,
        'shipping_cost' => 0,
        'shipped_to' => $customer->address,
    ]);

    $soItem = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $prodA->id,
        'quantity' => 20,
        'unit_price' => 7500,
        'subtotal' => 150000,
    ]);

    $do = DeliveryOrder::create([
        'do_number' => 'DO-TEST-CHK-RETUR',
        'cabang_id' => $cabang->id,
        'warehouse_id' => $whUtama->id,
        'delivery_date' => now(),
        'status' => 'approved',
        'created_by' => $adminPengiriman->id,
    ]);
    $do->salesOrders()->attach($so->id);

    $doItem = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'sale_order_item_id' => $soItem->id,
        'product_id' => $prodA->id,
        'quantity' => 20,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItem->id,
        'warehouse_id' => $whUtama->id,
        'quantity' => 20,
    ]);

    echo "Fixture 2 dibuat: DO-TEST-CHK-RETUR (ID: {$do->id}) dengan 20 pcs PROD-A.\n";
});

// 3. Fixture DO-TEST-CHK-WITH-SJ (Untuk UAT-CHK-06: Blokir Retur jika SJ sudah terbit)
DB::transaction(function () use ($cabang, $whUtama, $customer, $prodA, $adminPengiriman) {
    DeliveryOrder::where('do_number', 'DO-TEST-CHK-WITH-SJ')->forceDelete();
    SaleOrder::where('so_number', 'SO-TEST-CHK-WITH-SJ')->forceDelete();

    $so = SaleOrder::create([
        'so_number' => 'SO-TEST-CHK-WITH-SJ',
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'status' => 'approved',
        'order_date' => now(),
        'delivery_date' => now()->addDays(2),
        'created_by' => $adminPengiriman->id,
        'total_amount' => 10 * 7500,
        'shipping_cost' => 0,
        'shipped_to' => $customer->address,
    ]);

    $soItem = SaleOrderItem::create([
        'sale_order_id' => $so->id,
        'product_id' => $prodA->id,
        'quantity' => 10,
        'unit_price' => 7500,
        'subtotal' => 75000,
    ]);

    $do = DeliveryOrder::create([
        'do_number' => 'DO-TEST-CHK-WITH-SJ',
        'cabang_id' => $cabang->id,
        'warehouse_id' => $whUtama->id,
        'delivery_date' => now(),
        'status' => 'confirmed',
        'created_by' => $adminPengiriman->id,
    ]);
    $do->salesOrders()->attach($so->id);

    $doItem = DeliveryOrderItem::create([
        'delivery_order_id' => $do->id,
        'sale_order_item_id' => $soItem->id,
        'product_id' => $prodA->id,
        'quantity' => 10,
    ]);
    DeliveryOrderItemWarehouseSource::create([
        'delivery_order_item_id' => $doItem->id,
        'warehouse_id' => $whUtama->id,
        'quantity' => 10,
    ]);

    // Terbitkan Surat Jalan
    $sj = SuratJalan::create([
        'sj_number' => 'SJ-TEST-CHK-001',
        'cabang_id' => $cabang->id,
        'status' => SuratJalan::STATUS_ISSUED,
        'issued_at' => now(),
        'created_by' => $adminPengiriman->id,
    ]);
    $do->suratJalan()->attach($sj->id);

    echo "Fixture 3 dibuat: DO-TEST-CHK-WITH-SJ (ID: {$do->id}) dengan Surat Jalan aktif {$sj->sj_number}.\n";
});

echo "Semua fixtures UAT Modul 03 berhasil disiapkan!\n";
