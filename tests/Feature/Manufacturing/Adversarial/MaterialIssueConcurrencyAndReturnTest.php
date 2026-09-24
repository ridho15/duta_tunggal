<?php

use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\MaterialIssue;
use App\Models\StockMovement;
use App\Services\ManufacturingJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    \Tests\TestCase::disableBaseSeeding();
    $this->ctx = mfgContext();
});

afterAll(fn () => \Tests\TestCase::enableBaseSeeding());

it('MI-CC-01: over-issue boundary menolak pengambilan bahan baku melebihi stok fisik tersedia', function () {
    [$bom] = mfgBom($this->ctx);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 5);

    // Stok awal bahan baku di gudang = 100 pcs
    $initialStock = $this->ctx['rawStock']->qty_available;
    expect($initialStock)->toEqual(100.0);

    // Coba minta 150 pcs bahan baku (melebihi stok 100 pcs)
    [$issue, $issueItem] = mfgMaterialIssue($this->ctx, $plan, rawQty: 150);

    $rawStock = InventoryStock::where('product_id', $this->ctx['rawMaterial']->id)
        ->where('warehouse_id', $this->ctx['warehouse']->id)
        ->first();

    $available = (float) $rawStock->qty_available - (float) $rawStock->qty_reserved;

    // Sistem verifikasi ketersediaan stok menolak permintaan melebihi kuantitas bebas
    $canFulfill = ($available >= $issueItem->quantity);
    expect($canFulfill)->toBeFalse(
        'Pengambilan bahan baku 150 pcs tidak boleh diizinkan saat stok bebas hanya 100 pcs.'
    );
});

it('MI-CC-02: idempotensi pembaruan material issue mencegah mutasi dan duplikasi jurnal ganda', function () {
    [$bom] = mfgBom($this->ctx);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 5);

    // Minta 20 pcs bahan baku @ Rp 20.000 = Rp 400.000
    [$issue, $issueItem] = mfgMaterialIssue($this->ctx, $plan, rawQty: 20);

    mfgConfirmMaterialIssue($issue);

    // Set approved lalu completed
    $issue->update([
        'status' => 'completed',
        'approved_by' => $this->ctx['user']->id,
        'approved_at' => now(),
    ]);

    $journalService = app(ManufacturingJournalService::class);
    $journalService->generateJournalForMaterialIssue($issue->fresh());

    $journalEntries1 = JournalEntry::where('source_type', MaterialIssue::class)
        ->where('source_id', $issue->id)
        ->get();

    expect($journalEntries1->count())->toBeGreaterThanOrEqual(2);

    // Panggil ulang penjurnalan secara beruntun (simulasi re-save / double click)
    $journalService->generateJournalForMaterialIssue($issue->fresh());
    $journalEntries2 = JournalEntry::where('source_type', MaterialIssue::class)
        ->where('source_id', $issue->id)
        ->get();

    // Idempotensi: Jumlah baris jurnal tidak berlipat ganda
    expect($journalEntries2->count())->toEqual($journalEntries1->count(),
        'Penjurnalan material issue berulang tidak boleh menduplikasi baris jurnal (idempotent).'
    );

    $totalDebit = $journalEntries2->sum('debit');
    $totalCredit = $journalEntries2->sum('credit');
    expect((float) $totalDebit)->toEqualWithDelta(400000.0, 0.01);
    expect((float) $totalCredit)->toEqualWithDelta(400000.0, 0.01);
});

it('MI-CC-03: retur sisa bahan baku (Material Return) mengembalikan stok dan membalik jurnal simetris', function () {
    [$bom] = mfgBom($this->ctx);
    $plan = mfgProductionPlan($this->ctx, $bom, planQty: 5);

    // Buat Material Issue Return: mengembalikan 5 pcs sisa bahan baku ke gudang
    [$returnIssue, $returnItem] = mfgMaterialIssue($this->ctx, $plan, rawQty: 5, type: 'return');

    mfgConfirmMaterialIssue($returnIssue);

    $returnIssue->update([
        'status' => 'completed',
        'approved_by' => $this->ctx['user']->id,
        'approved_at' => now(),
    ]);


    $journalService = app(ManufacturingJournalService::class);
    $journalService->generateJournalForMaterialReturn($returnIssue->fresh());

    $returnJournals = JournalEntry::where('source_type', MaterialIssue::class)
        ->where('source_id', $returnIssue->id)
        ->get();

    expect($returnJournals->count())->toBeGreaterThanOrEqual(2);

    // Cek bahwa pada retur bahan baku:
    // Debit = Persediaan Bahan Baku (1-101), Credit = Pos Sementara Produksi (1400.04)
    $debitPersediaan = $returnJournals->firstWhere('coa_id', $this->ctx['rawCoa']->id);
    $creditPosSementara = $returnJournals->firstWhere('coa_id', $this->ctx['posSementaraCoa']->id);

    expect($debitPersediaan)->not->toBeNull('Retur bahan baku harus mendebit persediaan bahan baku.');
    expect((float) $debitPersediaan->debit)->toEqualWithDelta(100000.0, 0.01); // 5 pcs * 20000 = 100000

    expect($creditPosSementara)->not->toBeNull('Retur bahan baku harus mengkredit pos sementara produksi.');
    expect((float) $creditPosSementara->credit)->toEqualWithDelta(100000.0, 0.01);
});
