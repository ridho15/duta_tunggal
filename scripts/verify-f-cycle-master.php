<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Product;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\ChartOfAccount;
use App\Models\Rak;
use App\Models\Vehicle;
use App\Models\Driver;
use App\Models\User;
use App\Models\ApprovalRule;
use Spatie\Permission\Models\Role;

echo "\n========================================================================\n";
echo "   REKONSILIASI AKURASI DATA & AUDIT INDEPENDEN - SIKLUS F (MASTER & RBAC)  \n";
echo "========================================================================\n\n";

$allPassed = true;

// -------------------------------------------------------------
// [AUDIT 1] Master Produk: PRD-F-2026-001
// -------------------------------------------------------------
echo "[AUDIT 1] Memeriksa Master Produk: PRD-F-2026-001\n";
$product = Product::where('sku', 'PRD-F-2026-001')->first();
if (!$product) {
    echo "  [FAIL] Produk PRD-F-2026-001 tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $expectedCost = 25000.00;
    $expectedSell = 35000.00;
    $expectedBottom = 30000.00;

    $costMatch = abs((float)$product->cost_price - $expectedCost) < 0.01;
    $sellMatch = abs((float)$product->sell_price - $expectedSell) < 0.01;
    $bottomMatch = abs((float)$product->harga_batas - $expectedBottom) < 0.01;

    echo "  - SKU: {$product->sku} | Nama: {$product->name}\n";
    echo "  - Harga Beli (Cost): Rp " . number_format($product->cost_price, 2, ',', '.') . " | Expected: Rp 25.000,00 -> " . ($costMatch ? "PASS" : "FAIL") . "\n";
    echo "  - Harga Jual (Sell): Rp " . number_format($product->sell_price, 2, ',', '.') . " | Expected: Rp 35.000,00 -> " . ($sellMatch ? "PASS" : "FAIL") . "\n";
    echo "  - Batas Bawah Harga: Rp " . number_format($product->harga_batas, 2, ',', '.') . " | Expected: Rp 30.000,00 -> " . ($bottomMatch ? "PASS" : "FAIL") . "\n";
    echo "  - Status Aktif: " . ($product->is_active ? "Aktif (true)" : "Nonaktif (false)") . "\n";

    if (!$costMatch || !$sellMatch || !$bottomMatch) $allPassed = false;
}

// -------------------------------------------------------------
// [AUDIT 2] Master Customer: CUST-F-2026-001
// -------------------------------------------------------------
echo "\n[AUDIT 2] Memeriksa Master Customer: CUST-F-2026-001\n";
$customer = Customer::where('code', 'CUST-F-2026-001')->first();
if (!$customer) {
    echo "  [FAIL] Customer CUST-F-2026-001 tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $expectedLimit = 75000000.00;
    $limitMatch = abs((float)$customer->kredit_limit - $expectedLimit) < 0.01;
    $tempoMatch = (int)$customer->tempo_kredit === 30;

    echo "  - Kode: {$customer->code} | Perusahaan: {$customer->perusahaan} | PIC: {$customer->name}\n";
    echo "  - NIK/NPWP: {$customer->nik_npwp} | Tipe: {$customer->tipe}\n";
    echo "  - Plafon Kredit Limit: Rp " . number_format($customer->kredit_limit, 2, ',', '.') . " | Expected: Rp 75.000.000,00 -> " . ($limitMatch ? "EXACT MATCH [PASS]" : "FAIL") . "\n";
    echo "  - Tempo Kredit: {$customer->tempo_kredit} Hari | Expected: 30 Hari -> " . ($tempoMatch ? "PASS" : "FAIL") . "\n";

    if (!$limitMatch || !$tempoMatch) $allPassed = false;
}

// -------------------------------------------------------------
// [AUDIT 3] Master Supplier: SUP-F-2026-001
// -------------------------------------------------------------
echo "\n[AUDIT 3] Memeriksa Master Supplier: SUP-F-2026-001\n";
$supplier = Supplier::where('code', 'SUP-F-2026-001')->first();
if (!$supplier) {
    echo "  [FAIL] Supplier SUP-F-2026-001 tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $tempoHutangMatch = (int)$supplier->tempo_hutang === 14;
    echo "  - Kode: {$supplier->code} | Perusahaan: {$supplier->perusahaan}\n";
    echo "  - Telepon: {$supplier->phone} | NPWP: {$supplier->npwp}\n";
    echo "  - Tempo Hutang: {$supplier->tempo_hutang} Hari | Expected: 14 Hari -> " . ($tempoHutangMatch ? "PASS" : "FAIL") . "\n";

    if (!$tempoHutangMatch) $allPassed = false;
}

// -------------------------------------------------------------
// [AUDIT 4] Master Bagan Akun (COA): 6313
// -------------------------------------------------------------
echo "\n[AUDIT 4] Memeriksa Master Bagan Akun: 6313\n";
$coa = ChartOfAccount::where('code', '6313')->first();
if (!$coa) {
    echo "  [FAIL] Akun COA 6313 tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $typeMatch = $coa->type === 'Expense';
    echo "  - Kode Akun: {$coa->code} | Nama: {$coa->name}\n";
    echo "  - Tipe Akun: {$coa->type} | Expected: Expense -> " . ($typeMatch ? "PASS" : "FAIL") . "\n";

    if (!$typeMatch) $allPassed = false;
}

// -------------------------------------------------------------
// [AUDIT 5] Master Gudang & Rak: RAK-F-001 di Gudang Utama (ID 1)
// -------------------------------------------------------------
echo "\n[AUDIT 5] Memeriksa Master Rak: RAK-F-001 di Gudang Utama\n";
$rak = Rak::where('code', 'RAK-F-001')->first();
if (!$rak) {
    echo "  [FAIL] Rak RAK-F-001 tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $whMatch = (int)$rak->warehouse_id === 1;
    echo "  - Kode Rak: {$rak->code} | Nama: {$rak->name}\n";
    echo "  - ID Gudang: {$rak->warehouse_id} | Expected: 1 (Gudang Utama) -> " . ($whMatch ? "PASS" : "FAIL") . "\n";

    if (!$whMatch) $allPassed = false;
}

// -------------------------------------------------------------
// [AUDIT 6] Master Armada: Kendaraan B 9901 DT & Driver Pak Joko Santoso
// -------------------------------------------------------------
echo "\n[AUDIT 6] Memeriksa Master Armada Logistik\n";
$veh = Vehicle::where('plate', 'B 9901 DT')->first();
$dri = Driver::where('name', 'Pak Joko Santoso')->first();

if (!$veh) {
    echo "  [FAIL] Kendaraan B 9901 DT tidak ditemukan!\n";
    $allPassed = false;
} else {
    echo "  - Kendaraan Plat: {$veh->plate} | Tipe: {$veh->type} | Kapasitas: {$veh->capacity} -> PASS\n";
}

if (!$dri) {
    echo "  [FAIL] Driver Pak Joko Santoso tidak ditemukan!\n";
    $allPassed = false;
} else {
    echo "  - Driver: {$dri->name} | Phone: {$dri->phone} | SIM/Lisensi: {$dri->license} -> PASS\n";
}

// -------------------------------------------------------------
// [AUDIT 7] Pengaturan Pengguna & RBAC: staf.gudang.f@dutanggal.test
// -------------------------------------------------------------
echo "\n[AUDIT 7] Memeriksa Akun Pengguna & Otorisasi Peran\n";
$user = User::where('email', 'staf.gudang.f@dutanggal.test')->first();
if (!$user) {
    echo "  [FAIL] Pengguna staf.gudang.f@dutanggal.test tidak ditemukan!\n";
    $allPassed = false;
} else {
    $hasRole = $user->hasRole('Warehouse Staff');
    $statusMatch = $user->status !== null;
    echo "  - Nama: {$user->name} | Username: {$user->username} | Email: {$user->email}\n";
    echo "  - Role Spatie: " . implode(', ', $user->getRoleNames()->toArray()) . " | Expected: Warehouse Staff -> " . ($hasRole ? "PASS" : "FAIL") . "\n";
    echo "  - Posisi: {$user->posisi} | Status: " . ($user->status ? "Aktif" : "Nonaktif") . "\n";

    if (!$hasRole) $allPassed = false;
}

// -------------------------------------------------------------
// [AUDIT 8] Aturan Persetujuan (Approval Rules)
// -------------------------------------------------------------
echo "\n[AUDIT 8] Memeriksa Aturan Persetujuan (Approval Rule)\n";
$rule = ApprovalRule::where('label', 'like', '%di atas Rp 20.000.000%')->first();
if (!$rule) {
    echo "  [FAIL] Aturan persetujuan di atas 20jt tidak ditemukan!\n";
    $allPassed = false;
} else {
    $expectedAbove = 20000000.00;
    $aboveMatch = abs((float)$rule->above_amount - $expectedAbove) < 0.01;
    $docMatch = $rule->document_type === 'quotation';
    echo "  - Label: {$rule->label} | Dokumen: {$rule->document_type}\n";
    echo "  - Threshold Batas Bawah: Rp " . number_format($rule->above_amount, 2, ',', '.') . " | Expected: Rp 20.000.000,00 -> " . ($aboveMatch ? "PASS" : "FAIL") . "\n";
    echo "  - Peran Penyetuju: " . json_encode($rule->roles) . " | Penyetuju: {$rule->approver_label}\n";

    if (!$aboveMatch || !$docMatch) $allPassed = false;
}

// -------------------------------------------------------------
// [AUDIT 9] RBAC Security Boundary Check
// -------------------------------------------------------------
echo "\n[AUDIT 9] Memeriksa Pembatasan Hak Akses Keamanan (RBAC Boundary)\n";
$whRole = Role::where('name', 'Warehouse Staff')->first();
if ($whRole) {
    $hasJournalPerm = $whRole->hasPermissionTo('view journal entry') || $whRole->hasPermissionTo('create journal entry');
    echo "  - Role Warehouse Staff memiliki izin Jurnal Keuangan? " . ($hasJournalPerm ? "YA (BERBAHAYA!)" : "TIDAK (AMAN [PASS])") . "\n";
    if ($hasJournalPerm) $allPassed = false;
}

echo "\n========================================================================\n";
if ($allPassed) {
    echo "VERIFIKASI SIKLUS F SELESAI: SELURUH AUDIT BERHASIL [100% MATCH]\n";
} else {
    echo "VERIFIKASI SIKLUS F GAGAL: DITEMUKAN KETIDAKSESUAIAN NILAI/DATA\n";
}
echo "========================================================================\n\n";

exit($allPassed ? 0 : 1);

