<?php

use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\MaterialIssue;
use App\Models\QualityControl;
use App\Models\StockMovement;
use App\Services\ManufacturingJournalService;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
    $this->ctx = mfgContext();
});

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

it('MFG-RC-01: invarian jurnal pengeluaran bahan baku menjamin keseimbangan debit dan kredit mutlak', function () {
    [$bom] = mfgBom($this->ctx);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 5);

    // Minta 20 pcs bahan baku @ Rp 20.000 = Rp 400.000
    [$issue, $issueItem] = mfgMaterialIssue($this->ctx, $plan, rawQty: 20);

    mfgConfirmMaterialIssue($issue);

    $issue->update([
        'status' => 'completed',
        'approved_by' => $this->ctx['user']->id,
        'approved_at' => now(),
    ]);

    $journalService = app(ManufacturingJournalService::class);
    $journalService->generateJournalForMaterialIssue($issue->fresh());

    $journals = JournalEntry::where('source_type', MaterialIssue::class)
        ->where('source_id', $issue->id)
        ->get();

    $totalDebit = (float) $journals->sum('debit');
    $totalCredit = (float) $journals->sum('credit');

    expect($totalDebit)->toBeGreaterThan(0);
    expect($totalCredit)->toBeGreaterThan(0);
    expect(abs($totalDebit - $totalCredit))->toBeLessThan(0.01,
        "Debit ({$totalDebit}) dan Credit ({$totalCredit}) jurnal material issue harus seimbang mutlak."
    );
});

it('MFG-RC-02: invarian jurnal penyelesaian produksi (FG) menjamin keseimbangan debit dan kredit mutlak', function () {
    [$bom] = mfgBom($this->ctx, qty: 1, rawPerUnit: 2, labor: 5000, overhead: 3000);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 5);
    $mo = mfgOrder($this->ctx, $plan, ['status' => 'in_progress']);

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

    $qcService->completeQualityControl($qc, [
        'passed_quantity' => 5,
        'rejected_quantity' => 0,
        'warehouse_id' => $this->ctx['warehouse']->id,
    ]);

    $fgJournals = JournalEntry::where('source_type', QualityControl::class)
        ->where('source_id', $qc->id)
        ->get();

    $totalDebit = (float) $fgJournals->sum('debit');
    $totalCredit = (float) $fgJournals->sum('credit');

    expect($totalDebit)->toBeGreaterThan(0);
    expect($totalCredit)->toBeGreaterThan(0);
    expect(abs($totalDebit - $totalCredit))->toBeLessThan(0.01,
        "Debit ({$totalDebit}) dan Credit ({$totalCredit}) jurnal penyelesaian produksi harus seimbang mutlak."
    );
});

it('MFG-RC-03: rekonsiliasi kartu persediaan menjamin kesinambungan kuantitas bahan keluar dan produk masuk', function () {
    [$bom] = mfgBom($this->ctx, qty: 1, rawPerUnit: 2);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 5);
    $mo = mfgOrder($this->ctx, $plan, ['status' => 'in_progress']);

    // 10 pcs bahan baku dikeluarkan (MaterialIssueObserver otomatis mencatat StockMovement manufacture_out)
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

    $qcService->completeQualityControl($qc, [
        'passed_quantity' => 5,
        'rejected_quantity' => 0,
        'warehouse_id' => $this->ctx['warehouse']->id,
    ]);

    // Verifikasi catatan mutasi kartu persediaan
    $rawOutMovement = StockMovement::where('product_id', $this->ctx['rawMaterial']->id)
        ->where('type', 'manufacture_out')
        ->sum('quantity');

    $fgInMovement = StockMovement::where('product_id', $this->ctx['finishedGood']->id)
        ->where('type', 'manufacture_in')
        ->sum('quantity');

    expect((float) $rawOutMovement)->toEqual(10.0, 'Kuantitas bahan baku keluar harus tepat 10 pcs.');
    expect((float) $fgInMovement)->toEqual(5.0, 'Kuantitas barang jadi masuk harus tepat 5 pcs.');
});
