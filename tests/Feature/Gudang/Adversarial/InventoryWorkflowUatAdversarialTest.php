<?php

namespace Tests\Feature\Gudang\Adversarial;

use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use App\Services\StockAvailability;
use App\Services\StockOpnameService;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

require_once __DIR__ . '/../../../Support/StockOpnameFixtures.php';

test('ADV-INV-01: Boundary Underflow & Saldo Guard pada Penyesuaian Stok (Stock Adjustment)', function () {
    $ctx = opnContext();
    $adjService = app(StockAdjustmentService::class);

    // Saldo fisik awal pada Rak Sumber = 10 unit
    opnSetStock($ctx['product'], $ctx['warehouse'], $ctx['rakSource'], 10.0);

    // Kasus 1: Mencoba adjustment decrease sebanyak 25 unit (stok saat ini hanya 10)
    $adjDecrease = adjCreate($ctx, ['adjustment_type' => 'decrease']);
    adjItem($adjDecrease, $ctx, [
        'current_qty' => 10.0,
        'adjusted_qty' => 0.0,
        'difference_qty' => -25.0, // Melebihi stok yang ada
    ]);

    // Sistem wajib menolak dan melempar ValidationException (Underflow Guard)
    expect(fn () => $adjService->approveStockAdjustment($adjDecrease, $ctx['user']->id))
        ->toThrow(ValidationException::class);

    // Saldo fisik tetap utuh 10.0 (tidak berubah)
    $stockAfter = InventoryStock::withoutGlobalScopes()
        ->where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->where('rak_id', $ctx['rakSource']->id)
        ->first();
    expect((float) $stockAfter->qty_available)->toBe(10.0);

    // Kasus 2: Menolak persetujuan jika status adjustment bukan draft (Double Approval Prevention)
    $adjValid = adjCreate($ctx, ['adjustment_type' => 'increase', 'status' => 'draft']);
    adjItem($adjValid, $ctx, [
        'current_qty' => 10.0,
        'adjusted_qty' => 15.0,
        'difference_qty' => 5.0,
    ]);
    $approvedAdj = $adjService->approveStockAdjustment($adjValid, $ctx['user']->id);
    expect($approvedAdj->status)->toBe('approved');

    expect(fn () => $adjService->approveStockAdjustment($approvedAdj, $ctx['user']->id))
        ->toThrow(ValidationException::class);
});

test('ADV-INV-02: State Guard & Mutasi Antar-Gudang (Stock Transfer) Lintas Gudang / Rak', function () {
    $ctx = opnContext();
    $trfService = app(StockTransferService::class);

    // Saldo fisik awal: 20 unit di Rak Sumber
    opnSetStock($ctx['product'], $ctx['warehouse'], $ctx['rakSource'], 20.0);

    // Kasus 1: Transfer dari Gudang A Rak A ke Gudang A Rak A (Sama persis) wajib ditolak
    $trfSameLoc = stfCreate($ctx, [
        'from_warehouse_id' => $ctx['warehouse']->id,
        'to_warehouse_id' => $ctx['warehouse']->id,
    ]);
    stfItem($trfSameLoc, $ctx, [
        'from_warehouse_id' => $ctx['warehouse']->id,
        'to_warehouse_id' => $ctx['warehouse']->id,
        'from_rak_id' => $ctx['rakSource']->id,
        'to_rak_id' => $ctx['rakSource']->id,
    ]);

    expect(fn () => $trfService->requestTransfer($trfSameLoc))
        ->toThrow(ValidationException::class);

    // Kasus 2: Transfer yang meminta kuantitas melebihi stok yang tersedia (minta 50 unit, stok hanya 20)
    $trfExcess = stfCreate($ctx);
    stfItem($trfExcess, $ctx, ['quantity' => 50.0]);
    $trfService->requestTransfer($trfExcess);

    expect(fn () => $trfService->approveStockTransfer($trfExcess))
        ->toThrow(ValidationException::class);

    // Kasus 3: Menolak persetujuan transfer yang belum dalam status Request (misal langsung approve dari Draft)
    $trfDraft = stfCreate($ctx);
    stfItem($trfDraft, $ctx, ['quantity' => 5.0]);

    expect(fn () => $trfService->approveStockTransfer($trfDraft))
        ->toThrow(ValidationException::class);
});

test('ADV-INV-03: Stock Opname Selisih Fisik & Zero Balance Journal Drift', function () {
    $ctx = opnContext();
    $opnService = app(StockOpnameService::class);

    // Siapkan opname dengan selisih surplus dan defisit
    $opname = opnCreate($ctx, ['status' => 'completed']);
    opnItem($opname, $ctx, [
        'system_qty' => 50.0,
        'physical_qty' => 60.0, // Surplus +10 * 50,000 = +500,000
        'unit_cost' => 50000.0,
    ]);

    $approved = $opnService->approveStockOpname($opname, $ctx['user']->id);
    expect($approved->status)->toBe('approved');

    // Verifikasi jurnal akuntansi yang terbit
    $journals = JournalEntry::where('source_type', StockOpname::class)
        ->where('source_id', $opname->id)
        ->get();

    expect($journals)->toHaveCount(2);

    $totalDebit = (float) $journals->sum('debit');
    $totalCredit = (float) $journals->sum('credit');

    expect($totalDebit)->toBe(500000.0);
    expect($totalCredit)->toBe(500000.0);
    expect($totalDebit - $totalCredit)->toBe(0.0); // Zero balance drift

    // Verifikasi StockMovement penyesuaian hasil opname terbit
    $movement = StockMovement::where('from_model_type', StockOpname::class)
        ->where('from_model_id', $opname->id)
        ->first();

    expect($movement)->not->toBeNull();
    expect($movement->type)->toBe('adjustment_in');
    expect((float) $movement->quantity)->toBe(10.0);
});

test('ADV-INV-04: Isolasi Multi-Cabang & CabangScope pada Pergudangan', function () {
    $ctx = opnContext();
    $cabangUtama = $ctx['cabang'];
    $cabangUtama->update(['lihat_stok_cabang_lain' => false]);

    // Cabang kompetitor / cabang lain yang terisolasi
    $cabangLain = Cabang::factory()->create([
        'kode' => 'BR-ISOL-' . strtoupper(substr(uniqid(), -4)),
        'nama' => 'Cabang Gudang Bali',
        'status' => 1,
        'lihat_stok_cabang_lain' => false,
    ]);

    $gudangLain = Warehouse::factory()->create([
        'cabang_id' => $cabangLain->id,
        'kode' => 'WH-BL-' . strtoupper(substr(uniqid(), -4)),
        'name' => 'Gudang Logistik Bali',
        'status' => 1,
    ]);

    $availability = app(StockAvailability::class);

    // Pengguna Cabang Utama tidak boleh memiliki akses ke gudang Cabang Lain
    $accessibleGudang = $availability->accessibleWarehouseIds($cabangUtama->id);
    expect($accessibleGudang)->toContain($ctx['warehouse']->id);
    expect($accessibleGudang)->not->toContain($gudangLain->id);

    // Pengguna Cabang Lain hanya melihat gudangnya sendiri
    $userLain = User::factory()->create([
        'cabang_id' => $cabangLain->id,
        'manage_type' => 'own',
    ]);
    Auth::login($userLain);

    $visibleWarehouses = Warehouse::all();
    expect($visibleWarehouses->pluck('id'))->toContain($gudangLain->id);
    expect($visibleWarehouses->pluck('id'))->not->toContain($ctx['warehouse']->id);
    expect($visibleWarehouses->pluck('id'))->not->toContain($ctx['warehouseTarget']->id);
});

test('UAT-INV-01: End-to-End Siklus Lengkap Persediaan Gudang (Stok Awal -> Transfer Gudang -> Penyesuaian -> Opname Fisik -> 4-Layer Verification)', function () {
    $ctx = opnContext();
    $trfService = app(StockTransferService::class);
    $adjService = app(StockAdjustmentService::class);
    $opnService = app(StockOpnameService::class);

    // --- TAHAP 1: Pembentukan Saldo Awal Gudang ---
    // Rak A Gudang 1 diisi 100 unit
    $initialQty = 100.0;
    opnSetStock($ctx['product'], $ctx['warehouse'], $ctx['rakSource'], $initialQty);

    $stockSource = InventoryStock::withoutGlobalScopes()
        ->where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouse']->id)
        ->where('rak_id', $ctx['rakSource']->id)
        ->first();
    expect((float) $stockSource->qty_available)->toBe(100.0);

    // --- TAHAP 2: Transfer 30 unit dari Gudang 1 ke Gudang 2 (Transit) ---
    $transfer = stfCreate($ctx);
    stfItem($transfer, $ctx, ['quantity' => 30.0]);
    $trfService->requestTransfer($transfer);
    $approvedTransfer = $trfService->approveStockTransfer($transfer);
    expect($approvedTransfer->status)->toBe('Approved');

    // Verifikasi Mutasi StockMovement Transfer
    $trfMovements = StockMovement::where('from_model_type', StockTransfer::class)
        ->where('from_model_id', $transfer->id)
        ->get();
    expect($trfMovements)->toHaveCount(2);
    expect($trfMovements->where('type', 'transfer_out')->first()->quantity)->toBe(30.0);
    expect($trfMovements->where('type', 'transfer_in')->first()->quantity)->toBe(30.0);

    // Saldo Gudang 1 berkurang menjadi 70 unit
    $stockSource->refresh();
    expect((float) $stockSource->qty_available)->toBe(70.0);

    // Saldo Gudang 2 bertambah 30 unit
    $stockTarget = InventoryStock::withoutGlobalScopes()
        ->where('product_id', $ctx['product']->id)
        ->where('warehouse_id', $ctx['warehouseTarget']->id)
        ->where('rak_id', $ctx['rakTarget']->id)
        ->first();
    expect((float) $stockTarget->qty_available)->toBe(30.0);

    // --- TAHAP 3: Penyesuaian Stok (Stock Adjustment - Penambahan 10 unit di Gudang 1) ---
    $adj = adjCreate($ctx, ['adjustment_type' => 'increase']);
    adjItem($adj, $ctx, [
        'current_qty' => 70.0,
        'adjusted_qty' => 80.0,
        'difference_qty' => 10.0,
    ]);
    $approvedAdj = $adjService->approveStockAdjustment($adj, $ctx['user']->id);
    expect($approvedAdj->status)->toBe('approved');

    // Verifikasi Jurnal Adjustment: Debit Persediaan, Credit Selisih Persediaan (Zero Drift)
    $adjJournals = JournalEntry::where('source_type', StockAdjustment::class)
        ->where('source_id', $adj->id)
        ->get();
    expect($adjJournals)->toHaveCount(2);
    expect((float) $adjJournals->sum('debit'))->toBe((float) $adjJournals->sum('credit'));
    expect((float) $adjJournals->sum('debit'))->toBe(500000.0); // 10 * 50,000

    // Saldo Gudang 1 kini menjadi 80 unit
    $stockSource->refresh();
    expect((float) $stockSource->qty_available)->toBe(80.0);

    // --- TAHAP 4: Stock Opname Fisik Akhir Periode di Gudang 1 ---
    // Hasil hitung fisik menemukan 78 unit (defisit/shrinkage 2 unit = Rp 100.000)
    $opname = opnCreate($ctx, ['status' => 'completed']);
    opnItem($opname, $ctx, [
        'system_qty' => 80.0,
        'physical_qty' => 78.0,
        'unit_cost' => 50000.0,
    ]);
    $approvedOpname = $opnService->approveStockOpname($opname, $ctx['user']->id);
    expect($approvedOpname->status)->toBe('approved');

    // Verifikasi Jurnal Stock Opname: Debit Beban Selisih, Credit Persediaan
    $opnJournals = JournalEntry::where('source_type', StockOpname::class)
        ->where('source_id', $opname->id)
        ->get();
    expect($opnJournals)->toHaveCount(2);
    expect((float) $opnJournals->sum('debit'))->toBe((float) $opnJournals->sum('credit'));
    expect((float) $opnJournals->sum('debit'))->toBe(100000.0); // 2 * 50,000

    // --- 4-LAYER INTEGRITY VERIFICATION ---
    // Lapis 1: Status Dokumen Transaksi Sah
    expect($approvedTransfer->status)->toBe('Approved');
    expect($approvedAdj->status)->toBe('approved');
    expect($approvedOpname->status)->toBe('approved');

    // Lapis 2: Saldo Fisik Persediaan Akhir
    $stockSource->refresh();
    // 100 (awal) - 30 (transfer) + 10 (adjustment) - 2 (opname shrinkage) = 78 unit
    expect((float) $stockSource->qty_available)->toBe(78.0);
    expect((float) $stockTarget->qty_available)->toBe(30.0);

    // Lapis 3: Kartu Pergerakan Stok (StockMovement) Terkonsolidasi
    $allMovementsGudang1 = StockMovement::where('warehouse_id', $ctx['warehouse']->id)
        ->where('product_id', $ctx['product']->id)
        ->get();
    $inflow = (float) $allMovementsGudang1->whereIn('type', ['adjustment_in', 'transfer_in'])->sum('quantity');
    $outflow = (float) $allMovementsGudang1->whereIn('type', ['adjustment_out', 'transfer_out'])->sum('quantity');

    expect($inflow)->toBe(10.0); // +10 dari adjustment
    expect($outflow)->toBe(32.0); // 30 transfer_out + 2 opname adjustment_out
    // Net pergerakan tercatat = -22 unit. (100 - 22 = 78 unit fisik akhir)
    expect($initialQty + ($inflow - $outflow))->toBe(78.0);

    // Lapis 4: Keseimbangan Finansial Jurnal Seluruh Transaksi Gudang
    $allJournals = JournalEntry::whereIn('source_type', [StockAdjustment::class, StockOpname::class])
        ->whereIn('source_id', [$adj->id, $opname->id])
        ->get();
    $totalAllDebit = (float) $allJournals->sum('debit');
    $totalAllCredit = (float) $allJournals->sum('credit');
    expect($totalAllDebit)->toBe($totalAllCredit);
    expect($totalAllDebit - $totalAllCredit)->toBe(0.0);
});
