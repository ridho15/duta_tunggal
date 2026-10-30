<?php

use App\Models\Cabang;
use App\Models\JournalEntry;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockAdjustmentService;
use App\Services\StockAvailability;
use App\Services\StockOpnameService;
use App\Services\StockTransferService;
use Illuminate\Support\Facades\Auth;

test('INV-RC-01: rekonsiliasi tiga arah stok fisik, pergerakan stock movement, dan mutasi transfer/adjustment', function () {
    $ctx = opnContext();
    $adjService = app(StockAdjustmentService::class);
    $trfService = app(StockTransferService::class);

    // Saldo stok awal pada Rak A Gudang 1 = 50 unit
    $initialStock = 50.0;
    $stockRow = opnSetStock($ctx['product'], $ctx['warehouse'], $ctx['rakSource'], $initialStock);

    // Transaksi 1: Adjustment In +20 unit
    $adj = adjCreate($ctx, ['adjustment_type' => 'increase']);
    adjItem($adj, $ctx, [
        'current_qty' => $initialStock,
        'adjusted_qty' => $initialStock + 20.0,
        'difference_qty' => 20.0,
    ]);
    $adjService->approveStockAdjustment($adj, $ctx['user']->id);

    // Transaksi 2: Stock Transfer 15 unit dari Gudang 1 (Rak A) ke Gudang 2 (Rak B)
    $trf = stfCreate($ctx);
    stfItem($trf, $ctx, ['quantity' => 15.0]);
    $trfService->requestTransfer($trf);
    $trfService->approveStockTransfer($trf);

    // Hitung seluruh mutasi StockMovement untuk Gudang 1
    $movementsGudang1 = StockMovement::where('warehouse_id', $ctx['warehouse']->id)
        ->where('product_id', $ctx['product']->id)
        ->get();

    $inflowGudang1 = (float) $movementsGudang1->whereIn('type', ['adjustment_in', 'transfer_in'])->sum('quantity');
    $outflowGudang1 = (float) $movementsGudang1->whereIn('type', ['adjustment_out', 'transfer_out'])->sum('quantity');

    expect($inflowGudang1)->toBe(20.0);
    expect($outflowGudang1)->toBe(15.0);

    // Rekonsiliasi Matematis: Net Movement = +5 unit
    $netMovement = $inflowGudang1 - $outflowGudang1;
    $expectedFinalStock = $initialStock + $netMovement;
    expect($expectedFinalStock)->toBe(55.0);

    // Hitung mutasi untuk Gudang 2
    $movementsGudang2 = StockMovement::where('warehouse_id', $ctx['warehouseTarget']->id)
        ->where('product_id', $ctx['product']->id)
        ->get();

    $inflowGudang2 = (float) $movementsGudang2->where('type', 'transfer_in')->sum('quantity');
    expect($inflowGudang2)->toBe(15.0);
});

test('INV-RC-02: integritas buku besar stock opname selalu memiliki total debit sama dengan total kredit', function () {
    $ctx = opnContext();
    $opnService = app(StockOpnameService::class);

    // Skenario opname multi-item dengan selisih surplus dan defisit bersamaan
    $opname = opnCreate($ctx, ['status' => 'completed']);
    opnItem($opname, $ctx, [
        'system_qty' => 20.0,
        'physical_qty' => 25.0,
        'unit_cost' => 50000.0, // Surplus +5 * 50,000 = +250,000
    ]);
    opnItem($opname, $ctx, [
        'system_qty' => 15.0,
        'physical_qty' => 10.0,
        'unit_cost' => 30000.0, // Defisit -5 * 30,000 = -150,000
    ]);

    // Total net selisih = +100,000
    $opnService->approveStockOpname($opname, $ctx['user']->id);

    $journals = JournalEntry::where('source_type', StockOpname::class)
        ->where('source_id', $opname->id)
        ->get();

    expect($journals)->toHaveCount(2);

    $totalDebit = (float) $journals->sum('debit');
    $totalCredit = (float) $journals->sum('credit');

    expect($totalDebit)->toBe(100000.0);
    expect($totalCredit)->toBe(100000.0);
    expect($totalDebit - $totalCredit)->toBe(0.0);
});

test('INV-RC-03: isolasi cabang mencegah akses gudang milik cabang lain saat lihat_stok_cabang_lain non-aktif', function () {
    $ctx = opnContext();
    $cabangA = $ctx['cabang'];
    $cabangA->update(['lihat_stok_cabang_lain' => false]);

    $cabangB = Cabang::factory()->create([
        'kode' => 'BR-B-' . strtoupper(substr(uniqid(), -4)),
        'nama' => 'Cabang Regional Timur',
        'status' => 1,
        'lihat_stok_cabang_lain' => false,
    ]);

    $gudangB = Warehouse::factory()->create([
        'cabang_id' => $cabangB->id,
        'kode' => 'WH-B-' . strtoupper(substr(uniqid(), -4)),
        'name' => 'Gudang Cabang B',
        'status' => 1,
    ]);

    $availability = app(StockAvailability::class);

    // Pengguna cabang A tidak boleh melihat gudang B
    $accessibleA = $availability->accessibleWarehouseIds($cabangA->id);
    expect($accessibleA)->toContain($ctx['warehouse']->id);
    expect($accessibleA)->not->toContain($gudangB->id);

    // Login sebagai user cabang B yang hanya manage 'own'
    $userB = User::factory()->create([
        'cabang_id' => $cabangB->id,
        'manage_type' => 'own',
    ]);
    Auth::login($userB);

    // CabangScope pada Warehouse menyaring gudang cabang A dari pandangan User B
    $visibleWarehouses = Warehouse::all();
    expect($visibleWarehouses->pluck('id'))->toContain($gudangB->id);
    expect($visibleWarehouses->pluck('id'))->not->toContain($ctx['warehouse']->id);
});
