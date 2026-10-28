<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderCurrency;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

echo "=== MENYIAPKAN FIXTURES UAT MODUL 07 (TERIMA BARANG, QC & RETUR OTOMATIS) ===\n";

$cabang = Cabang::find(27) ?? Cabang::first();
$warehouse = Warehouse::where('cabang_id', $cabang->id)->first() ?? Warehouse::first();
$currency = Currency::where('code', 'IDR')->first() ?? Currency::first();

// 1. Verifikasi / Update User Checker
$checker = User::where('email', 'checker@example.com')->first();
if (!$checker) {
    $checker = User::create([
        'name' => 'Checker Gudang UAT',
        'email' => 'checker@example.com',
        'password' => Hash::make('password'),
        'cabang_id' => $cabang->id,
        'manage_type' => ['all'],
    ]);
    $checker->assignRole('Checker');
} else {
    $checker->update([
        'password' => Hash::make('password'),
        'cabang_id' => $cabang->id,
        'manage_type' => ['all'],
    ]);
}

$purchasingUser = User::where('email', 'purchasing@example.com')->first() ?? $checker;

// 2. Verifikasi Supplier & Produk
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

$product = Product::updateOrCreate(
    ['sku' => 'PROD-TEST-A'],
    [
        'name' => 'Pipa Seamless 5/8 Inch',
        'cost_price' => 5667.00,
        'cabang_id' => $cabang->id,
        'tipe_pajak' => 'Eksklusif',
        'pajak' => 11,
    ]
);

if (!$product->suppliers()->where('suppliers.id', $supplier->id)->exists()) {
    $product->suppliers()->attach($supplier->id, ['supplier_price' => 5667.00]);
}

echo "Cabang ID: {$cabang->id} ({$cabang->name})\n";
echo "Gudang ID: {$warehouse->id} ({$warehouse->name})\n";
echo "Checker: {$checker->name} ({$checker->email})\n";
echo "Supplier: {$supplier->perusahaan} ({$supplier->code})\n";
echo "Produk: {$product->name} (SKU: {$product->sku}, Cost: Rp " . number_format($product->cost_price, 0, ',', '.') . ")\n";

// 3. Bersihkan transaksi lama modul 07
$oldPos = PurchaseOrder::whereIn('po_number', [
    'PO-TEST-QC-50PCS',
    'PO-TEST-QC-OVERFLOW',
])->get();

foreach ($oldPos as $oldPo) {
    foreach ($oldPo->purchaseReceipt as $rec) {
        PurchaseReturn::where('purchase_receipt_id', $rec->id)->each(function ($pr) {
            $pr->purchaseReturnItem()->forceDelete();
            $pr->forceDelete();
        });
        QualityControl::where('from_model_type', PurchaseReceipt::class)->where('from_model_id', $rec->id)->delete();
        StockMovement::where('meta->purchase_receipt_id', $rec->id)->delete();
        $rec->purchaseReceiptItem()->forceDelete();
        $rec->purchaseReceiptBiaya()->delete();
        $rec->update(['status' => 'draft']);
        PurchaseReceipt::withoutEvents(function () use ($rec) {
            $rec->forceDelete();
        });
    }
    $oldPo->purchaseOrderItem()->forceDelete();
    $oldPo->purchaseOrderCurrency()->delete();
    $oldPo->forceDelete();
}

// 4. Fixture UAT-QC-01 & UAT-QC-02: PO 50 pcs siap diterima di gudang
$po50 = PurchaseOrder::create([
    'po_number' => 'PO-TEST-QC-50PCS',
    'supplier_id' => $supplier->id,
    'cabang_id' => $cabang->id,
    'warehouse_id' => $warehouse->id,
    'order_date' => Carbon::now()->format('Y-m-d'),
    'expected_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
    'status' => 'approved',
    'total_amount' => 50 * 5667.00 * 1.11,
    'created_by' => $purchasingUser->id,
    'approved_by' => 15,
    'date_approved' => Carbon::now(),
    'top_type' => 'credit',
    'tempo_hutang' => 30,
    'note' => 'PO 50 pcs untuk pengujian UAT Modul 07 Terima Barang & QC Reject',
]);

$poItem50 = PurchaseOrderItem::create([
    'purchase_order_id' => $po50->id,
    'product_id' => $product->id,
    'quantity' => 50,
    'unit_price' => 5667.00,
    'original_unit_price' => 5667.00,
    'discount' => 0,
    'tax' => 11,
    'tipe_pajak' => 'eklusif',
    'currency_id' => $currency->id,
]);

PurchaseOrderCurrency::create([
    'purchase_order_id' => $po50->id,
    'currency_id' => $currency->id,
    'nominal' => 1.0,
]);

echo "Fixture PO 50 Pcs Siap: {$po50->po_number} (ID: {$po50->id}, Status: {$po50->status})\n";

// 5. Fixture UAT-QC-03: PO 20 pcs untuk uji overflow validation
$po20 = PurchaseOrder::create([
    'po_number' => 'PO-TEST-QC-OVERFLOW',
    'supplier_id' => $supplier->id,
    'cabang_id' => $cabang->id,
    'warehouse_id' => $warehouse->id,
    'order_date' => Carbon::now()->format('Y-m-d'),
    'expected_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
    'status' => 'approved',
    'total_amount' => 20 * 5667.00 * 1.11,
    'created_by' => $purchasingUser->id,
    'approved_by' => 15,
    'date_approved' => Carbon::now(),
    'top_type' => 'credit',
    'tempo_hutang' => 30,
    'note' => 'PO 20 pcs untuk uji validasi Passed + Rejected > Received (UAT-QC-03)',
]);

$poItem20 = PurchaseOrderItem::create([
    'purchase_order_id' => $po20->id,
    'product_id' => $product->id,
    'quantity' => 20,
    'unit_price' => 5667.00,
    'original_unit_price' => 5667.00,
    'discount' => 0,
    'tax' => 11,
    'tipe_pajak' => 'eklusif',
    'currency_id' => $currency->id,
]);

PurchaseOrderCurrency::create([
    'purchase_order_id' => $po20->id,
    'currency_id' => $currency->id,
    'nominal' => 1.0,
]);

echo "Fixture PO 20 Pcs Overflow Siap: {$po20->po_number} (ID: {$po20->id}, Status: {$po20->status})\n";

// Catat saldo stok awal produk di gudang
$initialStock = InventoryStock::where('product_id', $product->id)
    ->where('warehouse_id', $warehouse->id)
    ->value('qty_available') ?? 0;
echo "Stok awal {$product->sku} di {$warehouse->name}: {$initialStock} pcs\n";

echo "=== FIXTURES MODUL 07 SELESAI DISIAPKAN DENGAN SUKSES! ===\n";
