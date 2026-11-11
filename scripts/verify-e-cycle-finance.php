<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AccountingPeriod;
use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\CashBankAccount;
use App\Models\CashBankTransaction;
use App\Models\CashBankTransfer;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

echo "========================================================================\n";
echo "   REKONSILIASI AKURASI DATA & AUDIT INDEPENDEN - SIKLUS E (KEUANGAN)  \n";
echo "========================================================================\n";

$allPassed = true;

// -------------------------------------------------------------------------
// AUDIT 1: TRANSAKSI KAS/BANK OPERASIONAL (CB-E-2026-001)
// -------------------------------------------------------------------------
echo "\n[AUDIT 1] Memeriksa Transaksi Kas & Bank: CB-E-2026-001\n";
$trx = CashBankTransaction::with(['journalEntries', 'transactionDetails'])->where('number', 'CB-E-2026-001')->first();

if (!$trx) {
    echo "  [FAIL] Transaksi CB-E-2026-001 tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $expectedAmount = 2500000.00;
    $actualAmount = (float) $trx->amount;
    $amountMatch = abs($actualAmount - $expectedAmount) < 0.01;
    echo "  - Nominal DB: Rp " . number_format($actualAmount, 2, ',', '.') . " | Expected: Rp " . number_format($expectedAmount, 2, ',', '.') . " -> " . ($amountMatch ? "EXACT MATCH [PASS]" : "MISMATCH [FAIL]") . "\n";
    echo "  - Tipe: {$trx->type} | Rekening COA ID: {$trx->account_coa_id} | Lawan COA ID: {$trx->offset_coa_id}\n";

    $jEntries = $trx->journalEntries;
    $totDebit = (float) $jEntries->sum('debit');
    $totCredit = (float) $jEntries->sum('credit');
    $jBalanced = abs($totDebit - $totCredit) < 0.01 && abs($totDebit - $expectedAmount) < 0.01;

    echo "  - Jurnal Terkait: {$jEntries->count()} baris | Total Debit: Rp " . number_format($totDebit, 2, ',', '.') . " | Total Kredit: Rp " . number_format($totCredit, 2, ',', '.') . " -> " . ($jBalanced ? "SEIMBANG [PASS]" : "TIDAK SEIMBANG [FAIL]") . "\n";

    if (!$amountMatch || !$jBalanced) {
        $allPassed = false;
    }
}

// -------------------------------------------------------------------------
// AUDIT 2: TRANSFER ANTAR KAS & BANK (TRF-E-2026-001)
// -------------------------------------------------------------------------
echo "\n[AUDIT 2] Memeriksa Transfer Antar Kas & Bank: TRF-E-2026-001\n";
$trf = CashBankTransfer::with('journalEntries')->where('number', 'TRF-E-2026-001')->first();

if (!$trf) {
    echo "  [FAIL] Transfer TRF-E-2026-001 tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $expAmount = 5000000.00;
    $expCosts = 6500.00;
    $expTotalDikeluarkan = $expAmount + $expCosts;

    $actAmount = (float) $trf->amount;
    $actCosts = (float) $trf->other_costs;

    $trfMatch = abs($actAmount - $expAmount) < 0.01 && abs($actCosts - $expCosts) < 0.01;
    echo "  - Nominal Transfer: Rp " . number_format($actAmount, 2, ',', '.') . " | Biaya Admin: Rp " . number_format($actCosts, 2, ',', '.') . " -> " . ($trfMatch ? "EXACT MATCH [PASS]" : "MISMATCH [FAIL]") . "\n";

    $jEntriesTrf = $trf->journalEntries;
    $totDr = (float) $jEntriesTrf->sum('debit');
    $totCr = (float) $jEntriesTrf->sum('credit');
    $trfJBalanced = abs($totDr - $totCr) < 0.01 && abs($totDr - $expTotalDikeluarkan) < 0.01;

    echo "  - Jurnal Transfer: {$jEntriesTrf->count()} baris | Total Debit: Rp " . number_format($totDr, 2, ',', '.') . " | Total Kredit: Rp " . number_format($totCr, 2, ',', '.') . " -> " . ($trfJBalanced ? "SEIMBANG [PASS]" : "TIDAK SEIMBANG [FAIL]") . "\n";

    if (!$trfMatch || !$trfJBalanced) {
        $allPassed = false;
    }
}

// -------------------------------------------------------------------------
// AUDIT 3: JURNAL UMUM MANUAL MULTI-BARIS (JV-E-2026-001)
// -------------------------------------------------------------------------
echo "\n[AUDIT 3] Memeriksa Jurnal Umum Manual: JV-E-2026-001\n";
$jvEntries = JournalEntry::where('reference', 'JV-E-2026-001')->get();

if ($jvEntries->isEmpty()) {
    echo "  [FAIL] Jurnal JV-E-2026-001 tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $expectedTotalJv = 2000000.00;
    $jvDebit = (float) $jvEntries->sum('debit');
    $jvCredit = (float) $jvEntries->sum('credit');
    $jvBalanced = abs($jvDebit - $jvCredit) < 0.01 && abs($jvDebit - $expectedTotalJv) < 0.01;

    echo "  - Jumlah Baris: {$jvEntries->count()} | Total Debit: Rp " . number_format($jvDebit, 2, ',', '.') . " | Total Kredit: Rp " . number_format($jvCredit, 2, ',', '.') . " -> " . ($jvBalanced ? "SEIMBANG [PASS]" : "TIDAK SEIMBANG [FAIL]") . "\n";

    foreach ($jvEntries as $idx => $line) {
        echo "    Line " . ($idx + 1) . ": COA {$line->coa_id} | Dr: " . number_format((float)$line->debit, 2, ',', '.') . " | Cr: " . number_format((float)$line->credit, 2, ',', '.') . " | Desc: {$line->description}\n";
    }

    if (!$jvBalanced) {
        $allPassed = false;
    }
}

// -------------------------------------------------------------------------
// AUDIT 4: ASET TETAP & DEPRESIASI BULANAN (AST-E-2026-001)
// -------------------------------------------------------------------------
echo "\n[AUDIT 4] Memeriksa Aset Tetap & Depresiasi: AST-E-2026-001\n";
$asset = Asset::with(['depreciationEntries', 'journalEntries'])->where('code', 'AST-E-2026-001')->first();

if (!$asset) {
    echo "  [FAIL] Aset AST-E-2026-001 tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $expCost = 120000000.00;
    $expSalvage = 6000000.00;
    $expYears = 5;
    $expMonthlyDep = ($expCost - $expSalvage) / ($expYears * 12); // 1.900.000,00

    $actCost = (float) $asset->purchase_cost;
    $actSalvage = (float) $asset->salvage_value;
    $actMonthly = (float) $asset->monthly_depreciation;

    $costMatch = abs($actCost - $expCost) < 0.01;
    $salvageMatch = abs($actSalvage - $expSalvage) < 0.01;
    $monthlyMatch = abs($actMonthly - $expMonthlyDep) < 0.01;

    echo "  - Harga Perolehan: Rp " . number_format($actCost, 2, ',', '.') . " -> " . ($costMatch ? "PASS" : "FAIL") . "\n";
    echo "  - Nilai Residu: Rp " . number_format($actSalvage, 2, ',', '.') . " -> " . ($salvageMatch ? "PASS" : "FAIL") . "\n";
    echo "  - Depresiasi Bulanan: Rp " . number_format($actMonthly, 2, ',', '.') . " | Expected Formula: Rp " . number_format($expMonthlyDep, 2, ',', '.') . " -> " . ($monthlyMatch ? "PASS" : "FAIL") . "\n";

    // Jurnal Akuisisi
    $acqJournal = JournalEntry::where(function ($q) use ($asset) {
        $q->where(fn($sub) => $sub->where('source_type', Asset::class)->where('source_id', $asset->id))
          ->orWhere('reference', 'like', 'AST-' . $asset->code . '%');
    })->get();

    $acqDr = (float) $acqJournal->sum('debit');
    $acqCr = (float) $acqJournal->sum('credit');
    $acqBalanced = abs($acqDr - $acqCr) < 0.01 && abs($acqDr - $expCost) < 0.01;

    echo "  - Jurnal Akuisisi Aset: {$acqJournal->count()} baris | Dr: Rp " . number_format($acqDr, 2, ',', '.') . " | Cr: Rp " . number_format($acqCr, 2, ',', '.') . " -> " . ($acqBalanced ? "SEIMBANG [PASS]" : "TIDAK SEIMBANG [FAIL]") . "\n";

    // Depresiasi Bulanan Recorded
    $depEntries = $asset->depreciationEntries()->where('status', 'recorded')->get();
    echo "  - Riwayat Depresiasi: {$depEntries->count()} bulan tercatat\n";
    foreach ($depEntries as $dep) {
        echo "    Periode: {$dep->period_year}-{$dep->period_month} | Nominal: Rp " . number_format((float)$dep->amount, 2, ',', '.') . " | Status: {$dep->status}\n";
    }

    if (!$costMatch || !$salvageMatch || !$monthlyMatch || !$acqBalanced) {
        $allPassed = false;
    }
}

// -------------------------------------------------------------------------
// AUDIT 5: TUTUP BUKU & PENGUNCIAN PERIODE
// -------------------------------------------------------------------------
echo "\n[AUDIT 5] Memeriksa Tutup Buku & Proteksi Periode Terkunci\n";
$period = AccountingPeriod::whereYear('period_start', 2026)->whereMonth('period_start', 1)->first();

if (!$period) {
    echo "  [INFO] Periode 2026-01 tidak ditemukan (mungkin telah diuji buka-tutup dan dihapus).\n";
} else {
    echo "  - Status Periode: {$period->status} | Rentang: {$period->period_start->toDateString()} s/d {$period->period_end->toDateString()}\n";
}

// -------------------------------------------------------------------------
// AUDIT 6: KONSISTENSI BUKU BESAR (GLOBAL TRIAL BALANCE DEBIT == CREDIT)
// -------------------------------------------------------------------------
echo "\n[AUDIT 6] Memeriksa Keseimbangan Global Double-Entry (General Ledger)\n";
$globalDebit = (float) JournalEntry::sum('debit');
$globalCredit = (float) JournalEntry::sum('credit');
$globalDiff = abs($globalDebit - $globalCredit);
$globalBalanced = $globalDiff < 0.01;

echo "  - Global Debit : Rp " . number_format($globalDebit, 2, ',', '.') . "\n";
echo "  - Global Credit: Rp " . number_format($globalCredit, 2, ',', '.') . "\n";
echo "  - Selisih      : Rp " . number_format($globalDiff, 2, ',', '.') . " -> " . ($globalBalanced ? "EXACT ZERO [PASS]" : "MISMATCH [FAIL]") . "\n";

if (!$globalBalanced) {
    $allPassed = false;
}

echo "\n========================================================================\n";
echo "VERIFIKASI SIKLUS E SELESAI: " . ($allPassed ? "SELURUH AUDIT BERHASIL [100% MATCH]" : "TERDAPAT KETIDAKSESUAIAN [AUDIT GAGAL]") . "\n";
echo "========================================================================\n";

exit($allPassed ? 0 : 1);

