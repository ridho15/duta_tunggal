<?php

use App\Models\InventoryStock;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
    $this->ctx = mfgContext();
});

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

it('QC-MFG-01: kuantitas rejected pada QC Manufaktur tidak menambah stok fisik produk jadi di gudang', function () {
    [$bom] = mfgBom($this->ctx);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 10);
    $mo = mfgOrder($this->ctx, $plan, ['status' => 'in_progress']);
    $production = mfgProduction($this->ctx, $mo, qtyProduced: 10);

    $qcService = app(QualityControlService::class);
    $qc = $qcService->createQCFromProduction($production);

    $fgStockBefore = InventoryStock::where('product_id', $this->ctx['finishedGood']->id)
        ->where('warehouse_id', $this->ctx['warehouse']->id)
        ->value('qty_available') ?? 0;

    // Selesaikan QC dengan hasil: 0 passed, 10 rejected (semua cacat / scrap)
    $qcService->completeQualityControl($qc, [
        'passed_quantity' => 0,
        'rejected_quantity' => 10,
        'reason_reject' => 'Keretakan fitting dan cacat dimensi toleransi',
        'warehouse_id' => $this->ctx['warehouse']->id,
    ]);

    $fgStockAfter = InventoryStock::where('product_id', $this->ctx['finishedGood']->id)
        ->where('warehouse_id', $this->ctx['warehouse']->id)
        ->value('qty_available') ?? 0;

    // Stok fisik produk jadi tidak boleh bertambah sama sekali
    expect((float) $fgStockAfter)->toEqual((float) $fgStockBefore,
        'Produk yang rejected pada QC tidak boleh menambah stok fisik barang jadi di gudang.'
    );

    // Pastikan tidak ada StockMovement manufacture_in untuk barang reject
    $movementIn = StockMovement::where('from_model_type', QualityControl::class)
        ->where('from_model_id', $qc->id)
        ->where('type', 'manufacture_in')
        ->first();

    expect($movementIn)->toBeNull();
});

it('QC-MFG-02: sistem menolak keras kuantitas pemeriksaan QC negatif atau melebihi kuantitas produksi', function () {
    [$bom] = mfgBom($this->ctx);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 10);
    $mo = mfgOrder($this->ctx, $plan);
    $production = mfgProduction($this->ctx, $mo, qtyProduced: 10);

    $qcService = app(QualityControlService::class);
    $qc = $qcService->createQCFromProduction($production);

    // Kasus A: Kuantitas negatif
    expect(fn () => $qcService->completeQualityControl($qc, [
        'passed_quantity' => -1,
        'rejected_quantity' => 0,
    ]))->toThrow(Exception::class, 'tidak boleh bernilai negatif');

    // Kasus B: Total passed + rejected (8 + 5 = 13) melebihi kuantitas produksi (10)
    expect(fn () => $qcService->completeQualityControl($qc, [
        'passed_quantity' => 8,
        'rejected_quantity' => 5,
    ]))->toThrow(Exception::class, 'tidak boleh melebihi quantity produksi');
});

it('QC-MFG-03: produk yang lolos QC otomatis menambah stok fisik produk jadi via manufacture_in', function () {
    [$bom] = mfgBom($this->ctx, qty: 1, rawPerUnit: 2, labor: 5000, overhead: 3000);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 5);
    $mo = mfgOrder($this->ctx, $plan, ['status' => 'in_progress']);

    // Pastikan ada material issue completed untuk mendanai biaya WIP/BDP
    [$issue] = mfgMaterialIssue($this->ctx, $plan, rawQty: 10);
    mfgConfirmMaterialIssue($issue);
    $issue->update([
        'status' => 'completed',
        'approved_by' => $this->ctx['user']->id,
        'approved_at' => now(),
    ]);


    $production = mfgProduction($this->ctx, $mo, qtyProduced: 5);

    $qcService = app(QualityControlService::class);
    $qc = $qcService->createQCFromProduction($production);

    // Selesaikan QC: 5 lolos
    $qcService->completeQualityControl($qc, [
        'passed_quantity' => 5,
        'rejected_quantity' => 0,
        'warehouse_id' => $this->ctx['warehouse']->id,
    ]);

    // Verifikasi mutasi stok bertipe manufacture_in terbentuk
    $movement = StockMovement::where('from_model_type', QualityControl::class)
        ->where('from_model_id', $qc->id)
        ->where('type', 'manufacture_in')
        ->first();

    expect($movement)->not->toBeNull('StockMovement tipe manufacture_in harus terbentuk saat QC lolos.');
    expect((float) $movement->quantity)->toEqual(5.0);
    expect($movement->product_id)->toEqual($this->ctx['finishedGood']->id);
});
