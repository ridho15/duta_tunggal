<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Cabang;
use App\Models\User;

echo "=== MEMPERSIAPKAN DATA FIXTURE UAT MODUL 02 ===" . PHP_EOL;

$cabang = Cabang::find(27) ?? Cabang::first();
$customer = Customer::where('code', 'CUST-TEST-UAT-001')->first();
$productA = Product::where('sku', 'PROD-TEST-A')->first();
$sales = User::where('email', 'sales@example.com')->first();
$kasir = User::where('email', 'kasir@example.com')->first();

if (!$customer || !$productA) {
    echo "ERROR: Data customer atau produk UAT belum siap! Jalankan setup_uat_fixtures.php dulu." . PHP_EOL;
    exit(1);
}

// 1. SO Draft: SO-TEST-DRAFT-001
$soDraft = SaleOrder::updateOrCreate(
    ['so_number' => 'SO-TEST-DRAFT-001'],
    [
        'order_date' => now()->toDateString(),
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'created_by' => $sales->id,
        'status' => 'draft',
        'total_amount' => 75000.00,
        'shipping_cost' => 0.00,
        'notes' => 'SO Draft Pengujian UAT-DO-01',
    ]
);
SaleOrderItem::updateOrCreate(
    ['sale_order_id' => $soDraft->id, 'product_id' => $productA->id],
    [
        'quantity' => 10,
        'delivered_quantity' => 0,
        'unit_price' => 7500.00,
        'discount' => 0.00,
    ]
);
echo "[1] SO Draft SO-TEST-DRAFT-001 siap (Status: {$soDraft->status})" . PHP_EOL;

// 2. SO Approved Multi Warehouse: SO-TEST-MULTI-WH
$soMulti = SaleOrder::updateOrCreate(
    ['so_number' => 'SO-TEST-MULTI-WH'],
    [
        'order_date' => now()->toDateString(),
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'created_by' => $sales->id,
        'status' => 'approved',
        'approve_by' => $kasir->id,
        'approve_at' => now(),
        'cashier_approved_by' => $kasir->id,
        'cashier_approved_at' => now(),
        'total_amount' => 112500.00,
        'shipping_cost' => 30000.00,
        'notes' => 'SO Multi-Gudang UAT-DO-02/05 (Ongkir Rp 30.000)',
    ]
);
SaleOrderItem::updateOrCreate(
    ['sale_order_id' => $soMulti->id, 'product_id' => $productA->id],
    [
        'quantity' => 15,
        'delivered_quantity' => 0,
        'unit_price' => 7500.00,
        'discount' => 0.00,
    ]
);
echo "[2] SO Approved SO-TEST-MULTI-WH siap (Qty: 15, Ongkir: Rp 30.000)" . PHP_EOL;

// 3. SO Approved Partial: SO-TEST-PARTIAL
$soPartial = SaleOrder::updateOrCreate(
    ['so_number' => 'SO-TEST-PARTIAL'],
    [
        'order_date' => now()->toDateString(),
        'customer_id' => $customer->id,
        'cabang_id' => $cabang->id,
        'created_by' => $sales->id,
        'status' => 'approved',
        'approve_by' => $kasir->id,
        'approve_at' => now(),
        'cashier_approved_by' => $kasir->id,
        'cashier_approved_at' => now(),
        'total_amount' => 75000.00,
        'shipping_cost' => 0.00,
        'notes' => 'SO Partial Pengujian UAT-DO-04',
    ]
);
SaleOrderItem::updateOrCreate(
    ['sale_order_id' => $soPartial->id, 'product_id' => $productA->id],
    [
        'quantity' => 10,
        'delivered_quantity' => 0,
        'unit_price' => 7500.00,
        'discount' => 0.00,
    ]
);
echo "[3] SO Approved SO-TEST-PARTIAL siap (Qty: 10)" . PHP_EOL;

echo "=== FIXTURES SO MODUL 02 SELESAI DISIAPKAN ===" . PHP_EOL;
