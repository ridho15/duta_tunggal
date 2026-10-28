<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;

echo "=== MENYIAPKAN FIXTURES UAT MODUL 06 (PO & APPROVAL BERTINGKAT) ===\n";

// 1. Update user manage_type agar dapat mengakses semua cabang
$usersToUpdate = [
    'purchasing@example.com',
    'purchasing_manager@example.com',
    'owner@example.com',
    'inventory_manager@example.com',
];

foreach ($usersToUpdate as $email) {
    User::where('email', $email)->update([
        'manage_type' => ['all'],
        'cabang_id' => 1,
    ]);
}

$purchasingUser = User::where('email', 'purchasing@example.com')->firstOrFail();
$purchasingManagerUser = User::where('email', 'purchasing_manager@example.com')->firstOrFail();
$ownerUser = User::where('email', 'owner@example.com')->firstOrFail();
$inventoryManagerUser = User::where('email', 'inventory_manager@example.com')->firstOrFail();

$currency = Currency::where('code', 'IDR')->first() ?? Currency::first();
$cabang = Cabang::find(1) ?? Cabang::first();
$warehouse = Warehouse::where('cabang_id', $cabang->id)->first() ?? Warehouse::first();

// 2. Verifikasi / Buat Supplier SUPP-TEST-UAT-001
$supplier = Supplier::updateOrCreate(
    ['code' => 'SUPP-TEST-UAT-001'],
    [
        'perusahaan' => 'PT Supplier Logam Sejahtera',
        'kontak_person' => 'Budi Santoso',
        'phone' => '08123456789',
        'email' => 'supplier.logam@example.com',
        'address' => 'Kawasan Industri Pulogadung No. 12, Jakarta Timur',
        'cabang_id' => $cabang->id,
        'tempo_hutang' => 30,
    ]
);

// 3. Verifikasi / Buat Produk PROD-TEST-A
$product = Product::updateOrCreate(
    ['sku' => 'PROD-TEST-A'],
    [
        'name' => 'Pipa Seamless 5/8 Inch',
        'cost_price' => 5667.00,
        'cabang_id' => $cabang->id,
        'tipe_pajak' => 'Eksklusif',
        'pajak' => 0,
    ]
);

// Kaitkan supplier ke produk jika belum
if (!$product->suppliers()->where('suppliers.id', $supplier->id)->exists()) {
    $product->suppliers()->attach($supplier->id, ['supplier_price' => 5667.00]);
}

echo "Supplier: {$supplier->perusahaan} (Code: {$supplier->code})\n";
echo "Produk: {$product->name} (SKU: {$product->sku}, Cost: Rp " . number_format($product->cost_price, 0, ',', '.') . ")\n";

// 4. Bersihkan data test lama modul 06
PurchaseOrder::whereIn('po_number', [
    'PO-TEST-UAT-01',
    'PO-TEST-UAT-25JT',
    'PO-TEST-UAT-5JT-SELF',
])->each(function ($po) {
    $po->purchaseOrderItem()->forceDelete();
    $po->purchaseOrderCurrency()->delete();
    $po->forceDelete();
});

// Bersihkan OR lama
OrderRequest::where('request_number', 'OR-TEST-UAT-01')->each(function ($or) {
    $or->orderRequestItem()->forceDelete();
    $or->forceDelete();
});

// 5. Fixture UAT-PO-01: Order Request (OR) Approved siap diterbitkan PO
$or = OrderRequest::create([
    'request_number' => 'OR-TEST-UAT-01',
    'request_date' => Carbon::now()->format('Y-m-d'),
    'required_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
    'purpose' => 'Kebutuhan stok produksi proyek UAT Modul 06',
    'status' => 'approved',
    'created_by' => $purchasingUser->id,
    'cabang_id' => $cabang->id,
    'currency_id' => $currency->id,
    'note' => 'Order Request UAT-PO-01 telah disetujui Inventory Manager',
]);

$orItem = OrderRequestItem::create([
    'order_request_id' => $or->id,
    'supplier_id' => $supplier->id,
    'cabang_id' => $cabang->id,
    'product_id' => $product->id,
    'quantity' => 100,
    'fulfilled_quantity' => 0,
    'status' => 'approved',
    'approved_by' => $inventoryManagerUser->id,
    'approved_at' => Carbon::now(),
    'unit_price' => 5667.00,
    'unit_price_idr' => 5667.00,
    'original_price' => 5667.00,
    'original_price_idr' => 5667.00,
    'discount' => 0,
    'tax' => 0,
    'tipe_pajak' => 'eklusif',
    'subtotal' => 566700.00,
    'currency_id' => $currency->id,
    'note' => '100 pcs untuk stok UAT',
]);

echo "Fixture UAT-PO-01 Siap: Order Request {$or->request_number} (ID: {$or->id}, Status: {$or->status})\n";

// 6. Fixture UAT-PO-02 & UAT-PO-03: PO Rp 25.000.000 (status request_approve, dibuat oleh Purchasing Staff)
$po25 = PurchaseOrder::create([
    'po_number' => 'PO-TEST-UAT-25JT',
    'supplier_id' => $supplier->id,
    'cabang_id' => $cabang->id,
    'warehouse_id' => $warehouse->id,
    'order_date' => Carbon::now()->format('Y-m-d'),
    'expected_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
    'status' => 'request_approve',
    'total_amount' => 25000000.00,
    'created_by' => $purchasingUser->id,
    'top_type' => 'credit',
    'tempo_hutang' => 30,
    'note' => 'Pengadaan skala besar Rp 25.000.000 untuk pengujian tier limit approval',
]);

PurchaseOrderItem::create([
    'purchase_order_id' => $po25->id,
    'product_id' => $product->id,
    'quantity' => 5000,
    'unit_price' => 5000.00,
    'original_unit_price' => 5000.00,
    'discount' => 0,
    'tax' => 0,
    'tipe_pajak' => 'eklusif',
    'currency_id' => $currency->id,
]);

PurchaseOrderCurrency::create([
    'purchase_order_id' => $po25->id,
    'currency_id' => $currency->id,
    'nominal' => 1.0,
]);

echo "Fixture UAT-PO-02 & 03 Siap: PO {$po25->po_number} (ID: {$po25->id}, Nilai: Rp 25.000.000, Status: {$po25->status})\n";

// 7. Fixture UAT-PO-04: PO Rp 5.000.000 (status request_approve, dibuat oleh Purchasing Manager sendiri)
$po5Self = PurchaseOrder::create([
    'po_number' => 'PO-TEST-UAT-5JT-SELF',
    'supplier_id' => $supplier->id,
    'cabang_id' => $cabang->id,
    'warehouse_id' => $warehouse->id,
    'order_date' => Carbon::now()->format('Y-m-d'),
    'expected_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
    'status' => 'request_approve',
    'total_amount' => 5000000.00,
    'created_by' => $purchasingManagerUser->id, // Dibuat oleh Purchasing Manager sendiri!
    'top_type' => 'credit',
    'tempo_hutang' => 14,
    'note' => 'PO buatan Purchasing Manager untuk uji Segregation of Duties (Anti-Self-Approval)',
]);

PurchaseOrderItem::create([
    'purchase_order_id' => $po5Self->id,
    'product_id' => $product->id,
    'quantity' => 1000,
    'unit_price' => 5000.00,
    'original_unit_price' => 5000.00,
    'discount' => 0,
    'tax' => 0,
    'tipe_pajak' => 'eklusif',
    'currency_id' => $currency->id,
]);

PurchaseOrderCurrency::create([
    'purchase_order_id' => $po5Self->id,
    'currency_id' => $currency->id,
    'nominal' => 1.0,
]);

echo "Fixture UAT-PO-04 Siap: PO {$po5Self->po_number} (ID: {$po5Self->id}, Nilai: Rp 5.000.000, Dibuat oleh: {$purchasingManagerUser->email}, Status: {$po5Self->status})\n";

echo "=== FIXTURES MODUL 06 SELESAI DISIAPKAN DENGAN SUKSES! ===\n";
