<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\InventoryStock;
use App\Models\ChartOfAccount;
use Spatie\Permission\Models\Role;

echo "=== MEMPERSIAPKAN DATA FIXTURE UAT RESMI ===" . PHP_EOL;

// 1. Cabang
$cabang = Cabang::find(1) ?? Cabang::first();
if (!$cabang) {
    $cabang = Cabang::create([
        'nama' => 'Cabang Utama Jakarta',
        'kode' => 'CAB-JKT',
        'alamat' => 'Jl. Duta Tunggal No. 1, Jakarta Pusat',
        'telepon' => '021-5551234',
        'is_pusat' => true,
    ]);
}
echo "[1] Cabang: {$cabang->nama} (id: {$cabang->id})" . PHP_EOL;

// 2. Akun Penguji admin_pengiriman@example.com
$adminPengiriman = User::where('email', 'admin_pengiriman@example.com')->first();
if (!$adminPengiriman) {
    $adminPengiriman = User::create([
        'name' => 'Admin Pengiriman',
        'first_name' => 'Admin',
        'last_name' => 'Pengiriman',
        'username' => 'admin_pengiriman',
        'email' => 'admin_pengiriman@example.com',
        'password' => bcrypt('password'),
        'cabang_id' => $cabang->id,
        'manage_type' => 'cabang',
        'status' => 1,
    ]);
}
$roleAdminInv = Role::where('name', 'Admin Inventory')->first();
if ($roleAdminInv && !$adminPengiriman->hasRole('Admin Inventory')) {
    $adminPengiriman->assignRole('Admin Inventory');
}
echo "[2] User Penguji: admin_pengiriman@example.com siap (id: {$adminPengiriman->id})" . PHP_EOL;

// 3. Customer Uji
$c1 = Customer::updateOrCreate(
    ['code' => 'CUST-TEST-UAT-001'],
    [
        'name' => 'PT UAT Pelanggan Kredit',
        'perusahaan' => 'PT UAT Pelanggan Kredit',
        'tipe' => 'PKP',
        'tipe_pembayaran' => 'Kredit',
        'tempo_kredit' => 30,
        'kredit_limit' => 50000000.00, // Rp 50.000.000
        'phone' => '081234567891',
        'telephone' => '021-5551111',
        'fax' => '-',
        'nik_npwp' => '01.234.567.8-001.000',
        'email' => 'cust.uat.kredit@example.com',
        'address' => 'Kawasan Industri Pulogadung Blok A1, Jakarta Timur',
        'cabang_id' => $cabang->id,
    ]
);

$c2 = Customer::updateOrCreate(
    ['code' => 'CUST-TEST-UAT-002'],
    [
        'name' => 'Toko UAT Mitra COD',
        'perusahaan' => 'Toko UAT Mitra COD',
        'tipe' => 'PKP',
        'tipe_pembayaran' => 'COD (Bayar Lunas)',
        'tempo_kredit' => 0,
        'kredit_limit' => 0,
        'phone' => '081234567892',
        'telephone' => '021-5552222',
        'fax' => '-',
        'nik_npwp' => '01.234.567.8-002.000',
        'email' => 'cust.uat.cod@example.com',
        'address' => 'Jl. Kenari No. 45, Jakarta Pusat',
        'cabang_id' => $cabang->id,
    ]
);

$c3 = Customer::updateOrCreate(
    ['code' => 'CUST-TEST-UAT-003'],
    [
        'name' => 'UD UAT Pembeli Tunai',
        'perusahaan' => 'UD UAT Pembeli Tunai',
        'tipe' => 'PKP',
        'tipe_pembayaran' => 'Bebas',
        'tempo_kredit' => 0,
        'kredit_limit' => 0,
        'phone' => '081234567893',
        'telephone' => '021-5553333',
        'fax' => '-',
        'nik_npwp' => '01.234.567.8-003.000',
        'email' => 'cust.uat.tunai@example.com',
        'address' => 'Pasar Glodok Lantai 2 Blok B No. 12',
        'cabang_id' => $cabang->id,
    ]
);
echo "[3] Customer Uji: {$c1->code}, {$c2->code}, {$c3->code} siap" . PHP_EOL;

// 4. Supplier Uji
$s1 = Supplier::updateOrCreate(
    ['code' => 'SUPP-TEST-UAT-001'],
    [
        'perusahaan' => 'PT Supplier Logam Sejahtera',
        'kontak_person' => 'Bpk. Hendra Gunawan',
        'phone' => '021-7788990',
        'handphone' => '081234567894',
        'fax' => '-',
        'npwp' => '02.345.678.9-001.000',
        'email' => 'sales@logamsejahtera.test',
        'address' => 'Jl. Industri Baja No. 88, Cikarang',
        'nama_bank' => 'BCA',
        'nomor_rekening' => '1234567890',
        'nama_rekening' => 'PT Supplier Logam Sejahtera',
        'cabang_id' => $cabang->id,
    ]
);

$s2 = Supplier::updateOrCreate(
    ['code' => 'SUPP-TEST-UAT-002'],
    [
        'perusahaan' => 'CV Baja Utama Distribusi',
        'kontak_person' => 'Ibu Ratna Sari',
        'phone' => '021-3344556',
        'handphone' => '081234567895',
        'fax' => '-',
        'npwp' => '02.345.678.9-002.000',
        'email' => 'info@bajautama.test',
        'address' => 'Jl. Raya Pelabuhan No. 10, Tanjung Priok',
        'nama_bank' => 'Mandiri',
        'nomor_rekening' => '9876543210',
        'nama_rekening' => 'CV Baja Utama Distribusi',
        'cabang_id' => $cabang->id,
    ]
);
echo "[4] Supplier Uji: {$s1->code}, {$s2->code} siap" . PHP_EOL;

// 5. Gudang Uji
$whUtama = Warehouse::firstOrCreate(
    ['name' => 'Gudang Utama'],
    [
        'kode' => 'GDG-UTM',
        'location' => 'Lantai 1 Kantor Pusat',
        'cabang_id' => $cabang->id,
        'is_quarantine' => false,
    ]
);

$whKenari = Warehouse::firstOrCreate(
    ['name' => 'Gudang Kenari'],
    [
        'kode' => 'GDG-KNR',
        'location' => 'Jl. Kenari No. 12',
        'cabang_id' => $cabang->id,
        'is_quarantine' => false,
    ]
);

$whKarantina = Warehouse::firstOrCreate(
    ['name' => 'Gudang Karantina'],
    [
        'kode' => 'GDG-QRN',
        'location' => 'Zona Karantina QC Belakang',
        'cabang_id' => $cabang->id,
        'is_quarantine' => true,
    ]
);
echo "[5] Gudang Uji: {$whUtama->name}, {$whKenari->name}, {$whKarantina->name} siap" . PHP_EOL;

// 6. UOM & Category
$uom = UnitOfMeasure::where('abbreviation', 'pcs')->first() ?? UnitOfMeasure::first();
$category = ProductCategory::first() ?? ProductCategory::create(['name' => 'Pipa & Fitting', 'cabang_id' => $cabang->id]);

// Referensi CoA default
$firstCoa = ChartOfAccount::first();
$coaId = $firstCoa ? $firstCoa->id : 1;

// 7. Produk Uji
$p1 = Product::updateOrCreate(
    ['sku' => 'PROD-TEST-A'],
    [
        'name' => 'Pipa Seamless 5/8 Inch',
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'kode_merk' => 'SP-001',
        'cabang_id' => $cabang->id,
        'cost_price' => 5667.00,
        'harga_batas' => 5667.00,
        'sell_price' => 7500.00,
        'is_active' => true,
        'supplier_id' => $s1->id,
        'inventory_coa_id' => $coaId,
        'sales_coa_id' => $coaId,
        'cogs_coa_id' => $coaId,
    ]
);

$p2 = Product::updateOrCreate(
    ['sku' => 'PROD-TEST-B'],
    [
        'name' => 'Pipa Galvanis 1/2 Inch',
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'kode_merk' => 'PG-002',
        'cabang_id' => $cabang->id,
        'cost_price' => 3600.00,
        'harga_batas' => 3600.00,
        'sell_price' => 5000.00,
        'is_active' => true,
        'supplier_id' => $s1->id,
        'inventory_coa_id' => $coaId,
        'sales_coa_id' => $coaId,
        'cogs_coa_id' => $coaId,
    ]
);

$p3 = Product::updateOrCreate(
    ['sku' => 'PROD-TEST-C'],
    [
        'name' => 'Fitting Elbow 3/4 Inch',
        'product_category_id' => $category->id,
        'uom_id' => $uom->id,
        'kode_merk' => 'FE-003',
        'cabang_id' => $cabang->id,
        'cost_price' => 10000.00,
        'harga_batas' => 10000.00,
        'sell_price' => 15000.00,
        'is_active' => true,
        'supplier_id' => $s2->id,
        'inventory_coa_id' => $coaId,
        'sales_coa_id' => $coaId,
        'cogs_coa_id' => $coaId,
    ]
);
echo "[6] Produk Uji: {$p1->sku}, {$p2->sku}, {$p3->sku} siap" . PHP_EOL;

// 8. Saldo Stok Fisik Uji
InventoryStock::updateOrCreate(
    ['product_id' => $p1->id, 'warehouse_id' => $whUtama->id],
    ['qty_available' => 100, 'qty_reserved' => 0]
);
InventoryStock::updateOrCreate(
    ['product_id' => $p1->id, 'warehouse_id' => $whKenari->id],
    ['qty_available' => 8, 'qty_reserved' => 0]
);

InventoryStock::updateOrCreate(
    ['product_id' => $p2->id, 'warehouse_id' => $whUtama->id],
    ['qty_available' => 50, 'qty_reserved' => 0]
);

InventoryStock::updateOrCreate(
    ['product_id' => $p3->id, 'warehouse_id' => $whUtama->id],
    ['qty_available' => 2, 'qty_reserved' => 0] // Stok Kritis
);
echo "[7] Stok Fisik Tersedia di Gudang berhasil diselaraskan." . PHP_EOL;

echo "=== SELURUH DATA FIXTURE UAT RESMI SUDAH AKTIF ===" . PHP_EOL;
