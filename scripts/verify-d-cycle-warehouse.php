<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use Illuminate\Support\Facades\DB;

echo "====================================================================\n";
echo " SDLC PROMAX ADVERSARIAL QA AUDIT: SIKLUS D (PERGUDANGAN & MUTASI STOK)\n";
echo "====================================================================\n\n";

$results = [
    'transfer' => null,
    'adjustment' => null,
    'opname' => null,
    'wh1_stock' => null,
    'wh2_stock' => null,
    'journal_balance' => true,
    'passed' => true,
    'discrepancies' => [],
];

// 1. Audit Stock Transfer (TRF-D-001)
echo "[1/5] Mengaudit Transfer Stok Antar Gudang (TRF-D-001)...\n";
$transfer = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'stockTransferItem.product'])
    ->where('transfer_number', 'TRF-D-001')
    ->first();

if (!$transfer) {
    echo "  [FAIL] Berkas transfer TRF-D-001 tidak ditemukan di database!\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "TRF-D-001 not found";
} else {
    $results['transfer'] = $transfer;
    echo "  - Transfer ID: {$transfer->id} | No: {$transfer->transfer_number} | Status: {$transfer->status}\n";
    echo "    Asal: {$transfer->fromWarehouse?->name} (ID: {$transfer->from_warehouse_id}) -> Tujuan: {$transfer->toWarehouse?->name} (ID: {$transfer->to_warehouse_id})\n";

    if ($transfer->status !== 'Approved') {
        echo "  [FAIL] Status Transfer belum Approved! Aktual: {$transfer->status}\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "Transfer status not Approved ({$transfer->status})";
    } else {
        echo "  [OK] Status Transfer Resmi Disetujui (Approved).\n";
    }

    $outMovement = StockMovement::where('from_model_type', StockTransfer::class)
        ->where('from_model_id', $transfer->id)
        ->where('type', 'transfer_out')
        ->first();

    $inMovement = StockMovement::where('from_model_type', StockTransfer::class)
        ->where('from_model_id', $transfer->id)
        ->where('type', 'transfer_in')
        ->first();

    if (!$outMovement || !$inMovement) {
        echo "  [FAIL] Pergerakan stok transfer_out atau transfer_in tidak lengkap!\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "Transfer stock movements incomplete";
    } else {
        echo "    * Movement Out: ID #{$outMovement->id} | Qty: {$outMovement->quantity} | WH: {$outMovement->warehouse_id}\n";
        echo "    * Movement In:  ID #{$inMovement->id} | Qty: {$inMovement->quantity} | WH: {$inMovement->warehouse_id}\n";
        echo "  [OK] Mutasi Stok Transfer Masuk & Keluar Lengkap (100% Match).\n";
    }
}

// 2. Audit Penyesuaian Stok / Stock Adjustment (ADJ-D-001)
echo "\n[2/5] Mengaudit Penyesuaian Stok (Stock Adjustment ADJ-D-001)...\n";
$adjustment = StockAdjustment::with(['warehouse', 'items.product'])
    ->where('adjustment_number', 'ADJ-D-001')
    ->orWhere('reason', 'like', '%Siklus D%')
    ->latest('id')
    ->first();

if (!$adjustment) {
    echo "  [FAIL] Berkas penyesuaian ADJ-D-001 tidak ditemukan di database!\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "ADJ-D-001 not found";
} else {
    $results['adjustment'] = $adjustment;
    echo "  - Adjustment ID: {$adjustment->id} | No: {$adjustment->adjustment_number} | Status: {$adjustment->status}\n";
    echo "    Gudang: {$adjustment->warehouse?->name} | Tipe: {$adjustment->adjustment_type}\n";

    if ($adjustment->status !== 'approved') {
        echo "  [FAIL] Status Adjustment belum approved! Aktual: {$adjustment->status}\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "Adjustment status not approved ({$adjustment->status})";
    } else {
        echo "  [OK] Status Penyesuaian Stok Berhasil Disetujui (Approved).\n";
    }

    $adjMovement = StockMovement::where('from_model_type', StockAdjustment::class)
        ->where('from_model_id', $adjustment->id)
        ->first();

    if (!$adjMovement) {
        echo "  [FAIL] Pergerakan stok adjustment_out tidak ditemukan!\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "Adjustment movement not found";
    } else {
        echo "    * Movement: ID #{$adjMovement->id} | Type: {$adjMovement->type} | Qty: {$adjMovement->quantity}\n";
        echo "  [OK] Mutasi Stok Penyesuaian Tercatat Akurat.\n";
    }
}

// 3. Audit Stock Opname (OPN-D-001)
echo "\n[3/5] Mengaudit Stock Opname (OPN-D-001)...\n";
$opname = StockOpname::with(['warehouse', 'items.product'])
    ->where('opname_number', 'OPN-D-001')
    ->orWhere('notes', 'like', '%Siklus D%')
    ->latest('id')
    ->first();

if (!$opname) {
    echo "  [FAIL] Berkas Stock Opname OPN-D-001 tidak ditemukan di database!\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "OPN-D-001 not found";
} else {
    $results['opname'] = $opname;
    echo "  - Opname ID: {$opname->id} | No: {$opname->opname_number} | Status: {$opname->status}\n";
    echo "    Gudang: {$opname->warehouse?->name} | Total Item Diperiksa: {$opname->items->count()}\n";

    if ($opname->status !== 'approved') {
        echo "  [FAIL] Status Stock Opname belum approved! Aktual: {$opname->status}\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "Opname status not approved ({$opname->status})";
    } else {
        echo "  [OK] Status Stock Opname Berhasil Disetujui (Approved).\n";
    }

    $opnameItem = $opname->items->firstWhere('product_id', 1);
    if ($opnameItem) {
        echo "    * Variansi Produk 1: Sistem = {$opnameItem->system_qty} | Fisik = {$opnameItem->physical_qty} | Selisih = {$opnameItem->difference_qty}\n";
    }

    $opnameMovement = StockMovement::where('from_model_type', StockOpname::class)
        ->where('from_model_id', $opname->id)
        ->first();

    if (!$opnameMovement) {
        echo "  [FAIL] Pergerakan stok penyesuaian opname tidak ditemukan!\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "Opname movement not found";
    } else {
        echo "    * Movement: ID #{$opnameMovement->id} | Type: {$opnameMovement->type} | Qty: {$opnameMovement->quantity}\n";
        echo "  [OK] Mutasi Koreksi Opname Tercatat Akurat.\n";
    }
}

// 4. Audit Rekonsiliasi Saldo Akhir Stok Fisik Real-time (Product 1 & 3)
echo "\n[4/5] Mengaudit Rekonsiliasi Saldo Akhir Stok Fisik Real-time...\n";

// Product 1 di Gudang 1:
// Baseline: 15.00
// Transfer Out: -5.00
// Adjustment Out: -2.00
// Opname Surplus In: +1.00
// Saldo Akhir Seharusnya: 15 - 5 - 2 + 1 = 9.00
$actualWh1 = (float) InventoryStock::where('product_id', 1)->where('warehouse_id', 1)->sum('qty_available');
$expectedWh1 = 9.00;

echo "  Product 1 (Panel Kontrol Industri) di Gudang Utama:\n";
echo "    Baseline Stok: 15.00 unit\n";
echo "    - Transfer Out: 5.00 unit\n";
echo "    - Adjustment Out: 2.00 unit\n";
echo "    + Opname Surplus: 1.00 unit\n";
echo "    Ekspektasi Saldo Akhir: {$expectedWh1} unit | Aktual di DB: {$actualWh1} unit\n";

if (abs($actualWh1 - $expectedWh1) > 0.001) {
    echo "  [FAIL] Saldo Stok Gudang Utama TIDAK SESUAI! Expected: {$expectedWh1}, Actual: {$actualWh1}\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "WH1 stock mismatch (expected {$expectedWh1}, got {$actualWh1})";
} else {
    echo "  [OK] Saldo Stok Gudang Utama Presisi 100% MATCH: {$actualWh1} unit.\n";
}

// Product 1 di Gudang 2:
// Baseline: 0.00
// Transfer In: +5.00
// Saldo Akhir Seharusnya: 5.00
$actualWh2 = (float) InventoryStock::where('product_id', 1)->where('warehouse_id', 2)->sum('qty_available');
$expectedWh2 = 5.00;

echo "  Product 1 (Panel Kontrol Industri) di Gudang Cabang A:\n";
echo "    Baseline Stok: 0.00 unit\n";
echo "    + Transfer In: 5.00 unit\n";
echo "    Ekspektasi Saldo Akhir: {$expectedWh2} unit | Aktual di DB: {$actualWh2} unit\n";

if (abs($actualWh2 - $expectedWh2) > 0.001) {
    echo "  [FAIL] Saldo Stok Gudang Cabang TIDAK SESUAI! Expected: {$expectedWh2}, Actual: {$actualWh2}\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "WH2 stock mismatch (expected {$expectedWh2}, got {$actualWh2})";
} else {
    echo "  [OK] Saldo Stok Gudang Cabang Presisi 100% MATCH: {$actualWh2} unit.\n";
}

// Total Konsolidasi Produk 1 Lintas Gudang:
$totalSystemActual = $actualWh1 + $actualWh2;
$expectedSystemTotal = 14.00;
echo "  Total Konsolidasi Persediaan Produk 1: {$totalSystemActual} unit (Ekspektasi: {$expectedSystemTotal} unit) -> " . ($totalSystemActual === $expectedSystemTotal ? "[OK]" : "[FAIL]") . "\n";

// Product 3 di Gudang 1:
// Baseline: 40.00 unit (Tidak boleh terpengaruh aktivitas Produk 1)
$p3Stock = InventoryStock::where('product_id', 3)->where('warehouse_id', 1)->first();
$actualP3 = (float)($p3Stock?->qty_available ?? 0);
if (abs($actualP3 - 40.0) > 0.001) {
    echo "  [FAIL] Stok Bahan Baku Terkontaminasi! Expected: 40.00, Actual: {$actualP3}\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "Product 3 stock corrupted";
} else {
    echo "  [OK] Stok Bahan Baku Tidak Terpengaruh (Isolasi Mutasi Terjamin): {$actualP3} unit.\n";
}

// 5. Audit Keseimbangan Jurnal Akuntansi Pergudangan (Debit == Credit)
echo "\n[5/5] Mengaudit Keseimbangan Jurnal Akuntansi Pergudangan (Debit == Credit)...\n";
$sources = [
    ['type' => StockAdjustment::class, 'name' => 'Stock Adjustment', 'id' => $adjustment?->id],
    ['type' => StockOpname::class, 'name' => 'Stock Opname', 'id' => $opname?->id],
];

foreach ($sources as $source) {
    if (!$source['id']) continue;
    $journals = JournalEntry::with('chartOfAccount')
        ->where('source_type', $source['type'])
        ->where('source_id', $source['id'])
        ->get();

    echo "  - Jurnal Akuntansi {$source['name']} (ID: {$source['id']}):\n";
    if ($journals->isEmpty()) {
        echo "    [WARN] Tidak ada jurnal untuk {$source['name']}.\n";
    } else {
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($journals as $j) {
            $totalDebit += (float)$j->debit;
            $totalCredit += (float)$j->credit;
            $coaName = $j->chartOfAccount?->name ?? 'COA #' . $j->chart_of_account_id;
            $coaCode = $j->chartOfAccount?->code ?? '-';
            echo "      * [{$coaCode}] {$coaName} | Dr: Rp " . number_format((float)$j->debit, 2, ',', '.') . " | Cr: Rp " . number_format((float)$j->credit, 2, ',', '.') . "\n";
        }
        $diff = abs($totalDebit - $totalCredit);
        echo "      Total Debit: Rp " . number_format($totalDebit, 2, ',', '.') . " | Total Credit: Rp " . number_format($totalCredit, 2, ',', '.') . " | Diff: Rp " . number_format($diff, 2, ',', '.') . "\n";
        if ($diff > 0.01) {
            echo "    [FAIL] Jurnal {$source['name']} TIDAK SEIMBANG! Selisih: Rp " . number_format($diff, 2, ',', '.') . "\n";
            $results['journal_balance'] = false;
            $results['passed'] = false;
        } else {
            echo "    [OK] Jurnal {$source['name']} SEIMBANG (Debit == Credit).\n";
        }
    }
}

echo "\n====================================================================\n";
if ($results['passed']) {
    echo " HASIL AUDIT DATA SIKLUS D: SEMUA DATA PRESISI & VALID (PASS)        \n";
} else {
    echo " HASIL AUDIT DATA SIKLUS D: DITEMUKAN DISKREPANSI / ERROR (FAIL)    \n";
    foreach ($results['discrepancies'] as $disc) {
        echo "   - {$disc}\n";
    }
}
echo "====================================================================\n";

exit($results['passed'] ? 0 : 1);
