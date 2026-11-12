<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\ProductCategory;
use App\Models\TaxSetting;
use App\Models\UnitOfMeasure;

echo "=== [RESET DATA SIKLUS H: DASHBOARD, LAPORAN & MASTER PENDUKUNG] ===\n";

$delCabang = Cabang::withTrashed()->where('kode', 'like', 'CBG-H-%')->forceDelete();
echo "Dihapus: {$delCabang} Cabang (CBG-H-%)\n";

$delCat = ProductCategory::withTrashed()->where('kode', 'like', 'KAT-H-%')->orWhere('name', 'like', '%Siklus H%')->forceDelete();
echo "Dihapus: {$delCat} Kategori Produk (KAT-H-%)\n";

$delUom = UnitOfMeasure::withTrashed()->where('abbreviation', 'like', 'PKT-H%')->orWhere('name', 'like', '%Siklus H%')->forceDelete();
echo "Dihapus: {$delUom} Satuan (PKT-H%)\n";

$delTax = TaxSetting::withTrashed()->where('name', 'like', '%Siklus H%')->forceDelete();
echo "Dihapus: {$delTax} Pengaturan Pajak (%Siklus H%)\n";

$delCur = Currency::withTrashed()->where('code', 'like', 'CUR-H%')->orWhere('name', 'like', '%Siklus H%')->forceDelete();
echo "Dihapus: {$delCur} Mata Uang (CUR-H%)\n";

echo "=== [RESET DATA SIKLUS H SELESAI] ===\n";
