<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AccountReceivable;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetTransfer;
use App\Models\ChartOfAccount;
use App\Models\CreditNote;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItemCheck;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\OrderRequest;

echo "========================================================================\n";
echo "   REKONSILIASI AKURASI DATA & AUDIT INDEPENDEN - SIKLUS G             \n";
echo "========================================================================\n";

$allPassed = true;

// -------------------------------------------------------------------------
// AUDIT 1: HULU PENGADAAN - PERMINTAAN PEMBELIAN (OR)
// -------------------------------------------------------------------------
echo "\n[AUDIT 1] Memeriksa Permintaan Pembelian (OR) Siklus G\n";
$or = OrderRequest::with('orderRequestItem')
    ->where('purpose', 'Pengadaan Monitor LED Cadangan Siklus G')
    ->latest('id')
    ->first();
if (!$or) {
    echo "  [FAIL] OrderRequest Siklus G tidak ditemukan di database!\n";
    $allPassed = false;
} else {
    $statusOk = $or->status === 'approved';
    $itemOk = $or->orderRequestItem->count() >= 1 && (float) $or->orderRequestItem->first()->quantity >= 5;
    echo "  - Nomor: {$or->request_number} | Status DB: {$or->status} | Item count: {$or->orderRequestItem->count()} | Total Qty: " . $or->orderRequestItem->sum('quantity') . " -> " . ($statusOk && $itemOk ? "PASS" : "FAIL") . "\n";
    if (!$statusOk || !$itemOk) $allPassed = false;
}

// -------------------------------------------------------------------------
// AUDIT 2: CHECKER KIRIMAN GUDANG & JADWAL PENGIRIMAN ARMADA
// -------------------------------------------------------------------------
echo "\n[AUDIT 2] Memeriksa Logistik Lanjutan (Kiriman Checker & Jadwal Armada): DO-G-2026-001\n";
$do = DeliveryOrder::with(['deliveryOrderItem', 'deliverySchedules'])->where('do_number', 'DO-G-2026-001')->first();
if (!$do) {
    echo "  [FAIL] DO-G-2026-001 tidak ditemukan!\n";
    $allPassed = false;
} else {
    $doItemIds = $do->deliveryOrderItem->pluck('id')->all();
    $checksCount = DeliveryOrderItemCheck::whereIn('delivery_order_item_id', $doItemIds)->count();
    $schedule = $do->deliverySchedules->first();

    $doCompleted = $do->status === 'completed';
    $checkedOk = $checksCount === count($doItemIds) && $checksCount > 0;
    $scheduleOk = $schedule && $schedule->status === 'delivered' && (int) $schedule->driver_id === 40 && (int) $schedule->vehicle_id === 40;

    echo "  - DO Status: {$do->status} (" . ($doCompleted ? "PASS" : "FAIL") . ") | Item Checks: {$checksCount}/" . count($doItemIds) . " (" . ($checkedOk ? "PASS" : "FAIL") . ")\n";
    echo "  - Schedule: " . ($schedule ? "{$schedule->schedule_number} [{$schedule->status}] Driver={$schedule->driver_id} Vehicle={$schedule->vehicle_id}" : "NONE") . " -> " . ($scheduleOk ? "PASS" : "FAIL") . "\n";

    if (!$doCompleted || !$checkedOk || !$scheduleOk) $allPassed = false;
}

// -------------------------------------------------------------------------
// AUDIT 3: NOTA KREDIT PENJUALAN & JURNAL BALIK OTOMATIS (INV-G-2026-001)
// -------------------------------------------------------------------------
echo "\n[AUDIT 3] Memeriksa Nota Kredit & Saldo Piutang pada INV-G-2026-001\n";
$inv = Invoice::where('invoice_number', 'INV-G-2026-001')->first();
$cn = $inv ? CreditNote::with('items')->where('invoice_id', $inv->id)->latest('id')->first() : null;
$ar = $inv ? AccountReceivable::where('invoice_id', $inv->id)->first() : null;

if (!$inv || !$cn || !$ar) {
    echo "  [FAIL] Invoice / CreditNote / AR Siklus G tidak lengkap!\n";
    $allPassed = false;
} else {
    $expSub = 2000000.00;
    $expTax = 220000.00;
    $expTotal = 2220000.00;
    $expRemainingAr = 8880000.00; // 11.100.000 - 2.220.000

    $cnMathOk = $cn->status === 'issued'
        && abs((float) $cn->subtotal - $expSub) < 0.01
        && abs((float) $cn->tax_amount - $expTax) < 0.01
        && abs((float) $cn->total - $expTotal) < 0.01
        && abs((float) $cn->applied_to_ar - $expTotal) < 0.01;

    $arMathOk = abs((float) $ar->remaining - $expRemainingAr) < 0.01;

    $cnJournals = JournalEntry::where('source_type', CreditNote::class)->where('source_id', $cn->id)->get();
    $cnDr = (float) $cnJournals->sum('debit');
    $cnCr = (float) $cnJournals->sum('credit');
    $cnJournalOk = abs($cnDr - $cnCr) < 0.01 && abs($cnDr - $expTotal) < 0.01 && $cnJournals->count() >= 3;

    echo "  - CN Number: {$cn->credit_note_number} [{$cn->status}] | Sub: Rp " . number_format((float) $cn->subtotal, 2, ',', '.') . " | PPN: Rp " . number_format((float) $cn->tax_amount, 2, ',', '.') . " | Total: Rp " . number_format((float) $cn->total, 2, ',', '.') . " -> " . ($cnMathOk ? "EXACT MATCH [PASS]" : "FAIL") . "\n";
    echo "  - Piutang (AR) Remaining: Rp " . number_format((float) $ar->remaining, 2, ',', '.') . " (Expected: Rp 8.880.000,00) -> " . ($arMathOk ? "EXACT MATCH [PASS]" : "FAIL") . "\n";
    echo "  - Jurnal Balik CN ({$cnJournals->count()} baris): Dr Rp " . number_format($cnDr, 2, ',', '.') . " == Cr Rp " . number_format($cnCr, 2, ',', '.') . " -> " . ($cnJournalOk ? "SEIMBANG [PASS]" : "FAIL") . "\n";

    if (!$cnMathOk || !$arMathOk || !$cnJournalOk) $allPassed = false;
}

// -------------------------------------------------------------------------
// AUDIT 4: MUTASI ASET ANTAR CABANG (AST-G-2026-001)
// -------------------------------------------------------------------------
echo "\n[AUDIT 4] Memeriksa Mutasi Aset Antar Cabang: AST-G-2026-001\n";
$assetTrf = Asset::where('code', 'AST-G-2026-001')->first();
$transfer = $assetTrf ? AssetTransfer::where('asset_id', $assetTrf->id)->latest('id')->first() : null;
if (!$assetTrf || !$transfer) {
    echo "  [FAIL] AST-G-2026-001 atau AssetTransfer tidak ditemukan!\n";
    $allPassed = false;
} else {
    $trfOk = $transfer->status === 'completed'
        && (int) $transfer->from_cabang_id === 1
        && (int) $transfer->to_cabang_id === 2
        && (int) $assetTrf->cabang_id === 2;
    echo "  - Transfer #{$transfer->id} Status: {$transfer->status} | From Cabang {$transfer->from_cabang_id} -> To {$transfer->to_cabang_id} | Current Asset Cabang: {$assetTrf->cabang_id} -> " . ($trfOk ? "PASS" : "FAIL") . "\n";
    if (!$trfOk) $allPassed = false;
}

// -------------------------------------------------------------------------
// AUDIT 5: PELEPASAN / DISPOSAL ASET TETAP (AST-G-2026-002)
// -------------------------------------------------------------------------
echo "\n[AUDIT 5] Memeriksa Pelepasan / Disposal Aset: AST-G-2026-002\n";
$assetDisp = Asset::where('code', 'AST-G-2026-002')->first();
$disposal = $assetDisp ? AssetDisposal::where('asset_id', $assetDisp->id)->latest('id')->first() : null;
if (!$assetDisp || !$disposal) {
    echo "  [FAIL] AST-G-2026-002 atau AssetDisposal tidak ditemukan!\n";
    $allPassed = false;
} else {
    $expGain = 5000000.00;
    $dispStatusOk = $assetDisp->status === 'disposed' && $disposal->status === 'completed' && $disposal->disposal_type === 'sale';
    $dispMathOk = abs((float) $disposal->sale_price - 45000000.00) < 0.01
        && abs((float) $disposal->book_value_at_disposal - 40000000.00) < 0.01
        && abs((float) $disposal->gain_loss_amount - $expGain) < 0.01;

    $dispJournals = JournalEntry::where('source_type', AssetDisposal::class)->where('source_id', $disposal->id)->get();
    $dispDr = (float) $dispJournals->sum('debit');
    $dispCr = (float) $dispJournals->sum('credit');
    $dispJournalOk = abs($dispDr - $dispCr) < 0.01 && abs($dispDr - 55000000.00) < 0.01 && $dispJournals->count() === 4;

    echo "  - Asset Status: {$assetDisp->status} | Disposal Type: {$disposal->disposal_type} | Gain/Loss: Rp " . number_format((float) $disposal->gain_loss_amount, 2, ',', '.') . " -> " . ($dispStatusOk && $dispMathOk ? "EXACT MATCH [PASS]" : "FAIL") . "\n";
    echo "  - Jurnal Disposal ({$dispJournals->count()} baris): Dr Rp " . number_format($dispDr, 2, ',', '.') . " == Cr Rp " . number_format($dispCr, 2, ',', '.') . " -> " . ($dispJournalOk ? "SEIMBANG [PASS]" : "FAIL") . "\n";

    if (!$dispStatusOk || !$dispMathOk || !$dispJournalOk) $allPassed = false;
}

echo "\n========================================================================\n";
if ($allPassed) {
    echo "KESIMPULAN AUDIT SIKLUS G: [100% EXACT MATCH / REKONSILIASI SEMPURNA]\n";
    exit(0);
} else {
    echo "KESIMPULAN AUDIT SIKLUS G: [TERDAPAT KETIDAKSESUAIAN DATA / FAIL]\n";
    exit(1);
}

