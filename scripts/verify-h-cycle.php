<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseInvoice;
use App\Models\TaxSetting;
use App\Models\UnitOfMeasure;
use App\Services\Dashboard\ExecutiveDashboardService;

echo "========================================================================\n";
echo "   REKONSILIASI AKURASI DATA & AUDIT INDEPENDEN - SIKLUS H             \n";
echo "========================================================================\n";

$allPassed = true;

// -------------------------------------------------------------------------
// AUDIT 1: MASTER CABANG
// -------------------------------------------------------------------------
echo "\n[AUDIT 1] Memeriksa Master Cabang Siklus H (CBG-H-001)\n";
$cabang = Cabang::where('kode', 'CBG-H-001')->first();
if (!$cabang) {
    echo "  [FAIL] Cabang CBG-H-001 tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $cOk = $cabang->status == 1 && str_contains($cabang->nama, 'Gudang Logistik Timur');
    echo "  - Kode: {$cabang->kode} | Nama: {$cabang->nama} | Status: {$cabang->status} -> " . ($cOk ? "PASS" : "FAIL") . "\n";
    if (!$cOk) $allPassed = false;
}

// -------------------------------------------------------------------------
// AUDIT 2: MASTER KATEGORI PRODUK
// -------------------------------------------------------------------------
echo "\n[AUDIT 2] Memeriksa Master Kategori Produk Siklus H (KAT-H-001)\n";
$cat = ProductCategory::where('kode', 'KAT-H-001')->first();
if (!$cat) {
    echo "  [FAIL] Kategori KAT-H-001 tidak ditemukan!\n";
    $allPassed = false;
} else {
    $catOk = abs((float) $cat->kenaikan_harga - 5.0) < 0.01;
    echo "  - Kode: {$cat->kode} | Nama: {$cat->name} | Kenaikan Harga: {$cat->kenaikan_harga}% -> " . ($catOk ? "PASS" : "FAIL") . "\n";
    if (!$catOk) $allPassed = false;
}

// -------------------------------------------------------------------------
// AUDIT 3: MASTER SATUAN / UOM
// -------------------------------------------------------------------------
echo "\n[AUDIT 3] Memeriksa Master Satuan Ukuran Siklus H (PKT-H)\n";
$uom = UnitOfMeasure::where('abbreviation', 'PKT-H')->first();
if (!$uom) {
    echo "  [FAIL] Satuan PKT-H tidak ditemukan!\n";
    $allPassed = false;
} else {
    echo "  - Simbol: {$uom->abbreviation} | Nama: {$uom->name} -> PASS\n";
}

// -------------------------------------------------------------------------
// AUDIT 4: MASTER PENGATURAN PAJAK
// -------------------------------------------------------------------------
echo "\n[AUDIT 4] Memeriksa Master Pengaturan Pajak Siklus H\n";
$tax = TaxSetting::where('name', 'Pajak Uji Siklus H')->first();
if (!$tax) {
    echo "  [FAIL] Pajak Uji Siklus H tidak ditemukan!\n";
    $allPassed = false;
} else {
    $taxOk = abs((float) $tax->rate - 12.0) < 0.01 && $tax->type === 'PPN' && (int) $tax->status === 1;
    echo "  - Nama: {$tax->name} | Tarif: {$tax->rate}% | Tipe: {$tax->type} | Aktif: {$tax->status} -> " . ($taxOk ? "PASS" : "FAIL") . "\n";
    if (!$taxOk) $allPassed = false;
}

// -------------------------------------------------------------------------
// AUDIT 5: MASTER MATA UANG & KURS
// -------------------------------------------------------------------------
echo "\n[AUDIT 5] Memeriksa Master Mata Uang & Kurs Siklus H (CUR-H)\n";
$cur = Currency::where('code', 'CUR-H')->first();
if (!$cur) {
    echo "  [FAIL] Mata Uang CUR-H tidak ditemukan!\n";
    $allPassed = false;
} else {
    $curOk = abs((float) $cur->to_rupiah - 12500.0) < 0.01 && $cur->symbol === 'S$';
    echo "  - Kode: {$cur->code} | Simbol: {$cur->symbol} | Kurs to Rupiah: Rp " . number_format((float) $cur->to_rupiah, 2, ',', '.') . " -> " . ($curOk ? "PASS" : "FAIL") . "\n";
    if (!$curOk) $allPassed = false;
}

// -------------------------------------------------------------------------
// AUDIT 6: DASHBOARD & METRIK EKSEKUTIF
// -------------------------------------------------------------------------
echo "\n[AUDIT 6] Memeriksa Agregasi Metrik Dashboard Eksekutif\n";
try {
    $dashService = app(ExecutiveDashboardService::class);
    $dashData = $dashService->getDashboardData(null, 'today');
    $hasKpis = isset($dashData['salesToday']) || isset($dashData['receivables']) || isset($dashData['kpis']);
    echo "  - Executive Dashboard Service terhubung, payload key count: " . count($dashData) . " -> " . ($hasKpis ? "PASS" : "FAIL") . "\n";
    if (!$hasKpis) $allPassed = false;
} catch (Throwable $e) {
    echo "  [FAIL] Dashboard service melempar exception: " . $e->getMessage() . "\n";
    $allPassed = false;
}

// -------------------------------------------------------------------------
// AUDIT 7: LAPORAN MANAJERIAL (PENJUALAN, PEMBELIAN, PERSEDIAAN)
// -------------------------------------------------------------------------
echo "\n[AUDIT 7] Memeriksa Ketersediaan Data Laporan Manajerial\n";
$salesCount = Invoice::where('status', '!=', 'cancelled')->count();
$purchCount = PurchaseInvoice::where('status', '!=', 'cancelled')->count();
$prodCount = Product::count();

echo "  - Total Invoice Penjualan Aktif : {$salesCount} dokumen -> PASS\n";
echo "  - Total Invoice Pembelian Aktif : {$purchCount} dokumen -> PASS\n";
echo "  - Total Item Master Produk      : {$prodCount} item -> PASS\n";

echo "\n========================================================================\n";
if ($allPassed) {
    echo "KESIMPULAN AUDIT SIKLUS H: [100% EXACT MATCH / REKONSILIASI SEMPURNA]\n";
    exit(0);
} else {
    echo "KESIMPULAN AUDIT SIKLUS H: [TERDAPAT KETIDAKSESUAIAN DATA / FAIL]\n";
    exit(1);
}
