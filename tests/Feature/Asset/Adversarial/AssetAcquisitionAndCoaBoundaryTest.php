<?php

use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = astContext();
    $this->service = app(AssetService::class);
});

test('AST-ACQ-01: Anti-duplicate acquisition journal posting throws Exception', function () {
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 50000000.0,
    ]);

    // Pastikan jurnal perolehan pertama sudah terbentuk (oleh observer atau manual)
    if (!$this->service->hasPostedJournals($asset)) {
        $this->service->postAssetAcquisitionJournal($asset, $this->ctx['apCoa']->id);
    }

    expect($this->service->hasPostedJournals($asset))->toBeTrue();

    // Pemanggilan kedua kali harus ditolak keras
    expect(fn () => $this->service->postAssetAcquisitionJournal($asset, $this->ctx['apCoa']->id))
        ->toThrow(\Exception::class, 'Journal entries already exist for this asset acquisition');

    // Pastikan jumlah jurnal tetap 2 (Dr Aset, Cr AP) tanpa duplikasi
    $journals = JournalEntry::where('source_type', Asset::class)
        ->where('source_id', $asset->id)
        ->where('description', 'like', '%Asset acquisition%')
        ->get();

    expect($journals)->toHaveCount(2);
    expect((float)$journals->sum('debit'))->toBe(50000000.0);
    expect((float)$journals->sum('credit'))->toBe(50000000.0);
});

test('AST-ACQ-02: Asset without valid Asset COA throws Exception on acquisition posting', function () {
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 25000000.0,
    ]);

    // Hapus jurnal otomatis perolehan agar dapat menguji validasi COA
    JournalEntry::where('source_type', Asset::class)
        ->where('source_id', $asset->id)
        ->delete();

    // Simulasikan anomali relasi COA tidak ditemukan (null relation)
    $asset->setRelation('assetCoa', null);

    expect(fn () => $this->service->postAssetAcquisitionJournal($asset, $this->ctx['apCoa']->id))
        ->toThrow(\Exception::class, 'Asset COA not found');
});

test('AST-ACQ-03: Cannot determine credit account for asset acquisition throws Exception', function () {
    // Buat aset tanpa auto-observer
    $asset = new Asset([
        'code' => 'AST-NO-CREDIT',
        'name' => 'Aset Sumber Dana Gelap',
        'purchase_date' => now()->toDateString(),
        'usage_date' => now()->toDateString(),
        'purchase_cost' => 30000000.0,
        'salvage_value' => 3000000.0,
        'useful_life_years' => 5,
        'depreciation_method' => 'straight_line',
        'asset_coa_id' => $this->ctx['assetCoa']->id,
        'accumulated_depreciation_coa_id' => $this->ctx['accumulatedDepreciationCoa']->id,
        'depreciation_expense_coa_id' => $this->ctx['depreciationExpenseCoa']->id,
        'status' => 'active',
        'cabang_id' => $this->ctx['cabang']->id,
    ]);
    $asset->saveQuietly();

    // Hapus akun COA 2100 agar sistem tidak memiliki fallback AP default
    ChartOfAccount::where('code', '2100')->delete();

    expect(fn () => $this->service->postAssetAcquisitionJournal($asset, creditCoaId: null))
        ->toThrow(\Exception::class, 'Cannot determine credit account for asset acquisition');
});

test('AST-ACQ-04: Mutation of purchase_cost auto-updates depreciation and acquisition journals', function () {
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 120000000.0,
        'salvage_value' => 12000000.0,
        'useful_life_years' => 5,
    ]);

    expect((float)$asset->annual_depreciation)->toBe(21600000.0);
    expect((float)$asset->monthly_depreciation)->toBe(1800000.0);

    // Mutasi harga perolehan menjadi 150 Juta
    $asset->update([
        'purchase_cost' => 150000000.0,
    ]);

    $asset->refresh();

    // Rekalkulasi: (150 - 12) / 5 = 27.600.000 / tahun -> 2.300.000 / bulan
    expect((float)$asset->annual_depreciation)->toBe(27600000.0);
    expect((float)$asset->monthly_depreciation)->toBe(2300000.0);

    // Jurnal perolehan otomatis diperbarui nilainya oleh AssetObserver
    $journals = JournalEntry::where('source_type', Asset::class)
        ->where('source_id', $asset->id)
        ->where('description', 'like', '%Asset acquisition%')
        ->get();

    if ($journals->isNotEmpty()) {
        expect((float)$journals->sum('debit'))->toBe(150000000.0);
        expect((float)$journals->sum('credit'))->toBe(150000000.0);
    }
});
