<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BillOfMaterial;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\Product;
use App\Models\Production;
use App\Models\QualityControl;
use Illuminate\Support\Facades\DB;

echo "====================================================================\n";
echo " SDLC PROMAX ADVERSARIAL QA AUDIT: SIKLUS C (MANUFAKTUR & PERAKITAN)\n";
echo "====================================================================\n\n";

$results = [
    'bom' => null,
    'spk' => null,
    'material_issue' => null,
    'production' => null,
    'qc' => null,
    'rm_stock' => null,
    'fg_stock' => null,
    'journal_balance' => true,
    'passed' => true,
    'discrepancies' => [],
];

// 1. Audit BOM
echo "[1/6] Mengaudit Bill of Materials (BOM-C-001)...\n";
$bom = BillOfMaterial::with(['items.product', 'product', 'uom'])
    ->where('code', 'BOM-C-001')
    ->first();

if (!$bom) {
    echo "  [FAIL] BOM-C-001 tidak ditemukan di database!\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "BOM-C-001 not found";
} else {
    $results['bom'] = $bom;
    echo "  - BOM ID: {$bom->id} | Code: {$bom->code} | Nama: {$bom->nama_bom}\n";
    echo "    Output: {$bom->product?->name} ({$bom->product?->sku}) | Qty: {$bom->quantity} {$bom->uom?->name}\n";
    echo "    Total Cost: Rp " . number_format((float)$bom->total_cost, 2, ',', '.') . "\n";
    echo "    Labor Cost: Rp " . number_format((float)$bom->labor_cost, 2, ',', '.') . " | Overhead: Rp " . number_format((float)$bom->overhead_cost, 2, ',', '.') . "\n";

    // Hitung manual biaya bahan baku
    $expectedMaterialCost = 0;
    foreach ($bom->items as $item) {
        $sub = (float)$item->quantity * (float)$item->unit_price;
        $expectedMaterialCost += $sub;
        echo "      * Komponen: {$item->product?->name} | Qty: {$item->quantity} | Harga: Rp " . number_format((float)$item->unit_price, 2, ',', '.') . " | Subtotal: Rp " . number_format((float)$item->subtotal, 2, ',', '.') . "\n";
    }
    $expectedBomTotal = $expectedMaterialCost + (float)$bom->labor_cost + (float)$bom->overhead_cost;
    if (abs((float)$bom->total_cost - $expectedBomTotal) > 0.01) {
        echo "  [FAIL] Selisih Biaya BOM! Expected: {$expectedBomTotal}, Actual: {$bom->total_cost}\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "BOM total cost mismatch";
    } else {
        echo "  [OK] Nilai Total Biaya BOM Presisi (100% Match): Rp " . number_format($expectedBomTotal, 2, ',', '.') . "\n";
    }
}

// 2. Audit SPK / MO
echo "\n[2/6] Mengaudit Surat Perintah Kerja (SPK / MO)...\n";
$spk = ManufacturingOrder::with(['productionPlan.product', 'productionPlan.warehouse'])
    ->where('mo_number', 'like', 'MO-C-%')
    ->orWhere('mo_number', 'like', 'MO-20261110-%')
    ->latest('id')
    ->first();

if (!$spk) {
    echo "  [FAIL] SPK Manufaktur tidak ditemukan di database!\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "SPK MO not found";
} else {
    $results['spk'] = $spk;
    echo "  - SPK ID: {$spk->id} | No: {$spk->mo_number} | Status: {$spk->status}\n";
    echo "    Target: {$spk->quantity} unit {$spk->productionPlan?->product?->name}\n";
    echo "    Gudang Sumber: {$spk->productionPlan?->warehouse?->name} (ID: {$spk->productionPlan?->warehouse_id})\n";
    if (!in_array($spk->status, ['in_progress', 'completed'])) {
        echo "  [FAIL] Status SPK tidak valid: {$spk->status}\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "SPK status invalid ({$spk->status})";
    } else {
        echo "  [OK] SPK berhasil dimulai (In Progress / Completed).\n";
    }
}

// 3. Audit Material Issue (Pengeluaran Bahan Baku)
echo "\n[3/6] Mengaudit Pengeluaran Bahan Baku (Material Issue)...\n";
$mi = MaterialIssue::with(['items.product', 'warehouse'])
    ->where('issue_number', 'like', 'MI-C-%')
    ->orWhere('issue_number', 'like', 'ISU-20261110-%')
    ->latest('id')
    ->first();

if (!$mi) {
    echo "  [FAIL] Dokumen Material Issue tidak ditemukan di database!\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "Material Issue not found";
} else {
    $results['material_issue'] = $mi;
    echo "  - Issue ID: {$mi->id} | No: {$mi->issue_number} | Type: {$mi->type} | Status: {$mi->status}\n";
    echo "    Gudang: {$mi->warehouse?->name} | Total Cost: Rp " . number_format((float)$mi->total_cost, 2, ',', '.') . "\n";
    foreach ($mi->items as $miItem) {
        echo "      * Bahan: {$miItem->product?->name} | Qty: {$miItem->quantity} | Cost/Unit: Rp " . number_format((float)$miItem->cost_per_unit, 2, ',', '.') . " | Total: Rp " . number_format((float)$miItem->total_cost, 2, ',', '.') . "\n";
    }

    if ($mi->status !== 'completed') {
        echo "  [FAIL] Status Material Issue belum completed ({$mi->status})\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "Material Issue not completed";
    } else {
        echo "  [OK] Material Issue Berstatus Completed.\n";
    }
}

// 4. Audit Hasil Produksi (Production Output)
echo "\n[4/6] Mengaudit Bukti Hasil Produksi (Production Output)...\n";
$prod = Production::with(['manufacturingOrder.productionPlan.product', 'qualityControl'])
    ->where('production_number', 'like', 'PROD-C-%')
    ->orWhere('production_number', 'like', 'PRO-C-%')
    ->orWhere('production_number', 'like', 'PRO-20261110-%')
    ->latest('id')
    ->first();

if (!$prod) {
    echo "  [FAIL] Dokumen Hasil Produksi tidak ditemukan di database!\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "Production output not found";
} else {
    $results['production'] = $prod;
    echo "  - Produksi ID: {$prod->id} | No: {$prod->production_number} | Status: {$prod->status}\n";
    echo "    Jumlah Output: {$prod->quantity_produced} unit\n";
    if ($prod->status !== 'finished') {
        echo "  [FAIL] Status Produksi belum finished ({$prod->status})\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "Production status not finished";
    } else {
        echo "  [OK] Dokumen Produksi Selesai (Finished).\n";
    }
}

// 5. Audit Quality Control Manufaktur & Mutasi Stok Fisik
echo "\n[5/6] Mengaudit Quality Control & Mutasi Stok Fisik Real-time...\n";
$qc = QualityControl::with(['product', 'warehouse', 'rak'])
    ->where('qc_number', 'like', 'QC-C-%')
    ->orWhere('qc_number', 'like', 'QC-M-%')
    ->orWhere('qc_number', 'like', 'QC-20261110-%')
    ->orWhere(function ($query) use ($prod) {
        if ($prod) {
            $query->where('from_model_type', Production::class)
                  ->where('from_model_id', $prod->id);
        }
    })
    ->latest('id')
    ->first();

if (!$qc) {
    echo "  [FAIL] Dokumen Quality Control Manufaktur tidak ditemukan di database!\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "Quality Control not found";
} else {
    $results['qc'] = $qc;
    echo "  - QC ID: {$qc->id} | No: {$qc->qc_number} | Status: {$qc->status} (0: Pending, 1: Processed)\n";
    echo "    Produk Diinspeksi: {$qc->product?->name}\n";
    echo "    Passed Qty: {$qc->passed_quantity} | Rejected Qty: {$qc->rejected_quantity}\n";
    echo "    Gudang Masuk: {$qc->warehouse?->name} | Rak: {$qc->rak?->name}\n";

    if ((int)$qc->status !== 1) {
        echo "  [FAIL] QC belum diproses (Status: {$qc->status})\n";
        $results['passed'] = false;
        $results['discrepancies'][] = "QC not processed";
    } else {
        echo "  [OK] Quality Control Selesai Diproses (Inspected & Approved).\n";
    }
}

// Audit Stok Fisik Real-time:
// Raw Material (Product 3): Starting 50 - Issued 10 = 40
$rmStock = InventoryStock::where('product_id', 3)->where('warehouse_id', 1)->first();
$results['rm_stock'] = $rmStock;
echo "\n  Audit Stok Fisik Bahan Baku (Product ID 3 - Plastik Granul):\n";
echo "    Stok Baseline: 50.00 unit\n";
echo "    Bahan Dikeluarkan (Material Issue): 10.00 unit\n";
echo "    Stok Aktual di Database: " . ($rmStock ? $rmStock->qty_available : 'NULL') . " unit\n";
if (!$rmStock || abs((float)$rmStock->qty_available - 40.0) > 0.001) {
    echo "    [FAIL] Stok Bahan Baku TIDAK SESUAI! Expected: 40.00, Actual: " . ($rmStock?->qty_available ?? 'NULL') . "\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "Raw Material stock mismatch";
} else {
    echo "    [OK] Stok Bahan Baku Berkurang Presisi: 40.00 unit (100% MATCH).\n";
}

// Finished Good (Product 1): Starting 10 + Passed 5 = 15
$fgStock = InventoryStock::where('product_id', 1)->where('warehouse_id', 1)->first();
$results['fg_stock'] = $fgStock;
echo "\n  Audit Stok Fisik Barang Jadi (Product ID 1 - Panel Kontrol Industri):\n";
echo "    Stok Baseline: 10.00 unit\n";
echo "    Hasil Lolos QC Masuk Gudang: 5.00 unit\n";
echo "    Stok Aktual di Database: " . ($fgStock ? $fgStock->qty_available : 'NULL') . " unit\n";
if (!$fgStock || abs((float)$fgStock->qty_available - 15.0) > 0.001) {
    echo "    [FAIL] Stok Barang Jadi TIDAK SESUAI! Expected: 15.00, Actual: " . ($fgStock?->qty_available ?? 'NULL') . "\n";
    $results['passed'] = false;
    $results['discrepancies'][] = "Finished Good stock mismatch";
} else {
    echo "    [OK] Stok Barang Jadi Bertambah Presisi: 15.00 unit (100% MATCH).\n";
}

// 6. Audit Keseimbangan Jurnal Akuntansi Manufaktur
echo "\n[6/6] Mengaudit Keseimbangan Jurnal Akuntansi Siklus C (Debit == Credit)...\n";
$manufakturSources = [
    ['type' => MaterialIssue::class, 'name' => 'Material Issue', 'id' => $mi?->id],
    ['type' => Production::class, 'name' => 'Production Output', 'id' => $prod?->id],
    ['type' => QualityControl::class, 'name' => 'Quality Control', 'id' => $qc?->id],
];

foreach ($manufakturSources as $source) {
    if (!$source['id']) continue;
    $journals = JournalEntry::with('chartOfAccount')
        ->where('source_type', $source['type'])
        ->where('source_id', $source['id'])
        ->get();

    echo "  - Jurnal Akuntansi {$source['name']} (ID: {$source['id']}):\n";
    if ($journals->isEmpty()) {
        echo "    [WARN] Tidak ada jurnal untuk {$source['name']} (Pastikan fitur pembukuan aktif).\n";
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
            $results['discrepancies'][] = "Journal {$source['name']} unbalanced";
        } else {
            echo "    [OK] Jurnal {$source['name']} SEIMBANG (Debit == Credit).\n";
        }
    }
}

echo "\n====================================================================\n";
if ($results['passed']) {
    echo " HASIL AUDIT DATA SIKLUS C: SEMUA DATA PRESISI & VALID (PASS)        \n";
} else {
    echo " HASIL AUDIT DATA SIKLUS C: DITEMUKAN DISKREPANSI / ERROR (FAIL)    \n";
    foreach ($results['discrepancies'] as $disc) {
        echo "   - {$disc}\n";
    }
}
echo "====================================================================\n";

exit($results['passed'] ? 0 : 1);

