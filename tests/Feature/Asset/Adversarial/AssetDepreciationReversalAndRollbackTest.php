<?php

use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\JournalEntry;
use App\Services\AssetDepreciationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = astContext();
    $this->service = app(AssetDepreciationService::class);
});

test('AST-REV-01: Pembatalan penyusutan (reversal) menghapus jurnal dan memulihkan nilai akumulasi aset', function () {
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 120000000.0,
        'salvage_value' => 12000000.0,
        'useful_life_years' => 5,
        'usage_date' => Carbon::parse('2025-01-01'),
        'purchase_date' => Carbon::parse('2025-01-01'),
    ]);

    // Eksekusi penyusutan bulan Januari 2025
    $dep = $this->service->generateMonthlyDepreciation($asset, Carbon::parse('2025-01-31'));

    expect($dep->status)->toBe('recorded');
    expect((float)$asset->fresh()->accumulated_depreciation)->toBe(1800000.0);
    expect((float)$asset->fresh()->book_value)->toBe(118200000.0);

    // Pastikan 2 jurnal terbit (Dr Beban Penyusutan, Cr Akumulasi)
    expect(JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $dep->id)->count())->toBe(2);

    // Lakukan pembalikan penyusutan (reversal)
    $result = $this->service->reverseDepreciation($dep);
    expect($result)->toBeTrue();

    // 1. Status penyusutan berubah menjadi reversed
    expect($dep->fresh()->status)->toBe('reversed');

    // 2. Jurnal terkait dibersihkan dari buku besar
    expect(JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $dep->id)->count())->toBe(0);

    // 3. Akumulasi penyusutan aset pulih kembali ke 0
    $asset->refresh();
    expect((float)$asset->accumulated_depreciation)->toBe(0.0);
    expect((float)$asset->book_value)->toBe(120000000.0);
});

test('AST-REV-02: Idempotensi reversal dan pengecualian entri reversed dari total akumulasi aset', function () {
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 60000000.0,
        'salvage_value' => 6000000.0,
        'useful_life_years' => 5, // 900.000 / bulan
        'usage_date' => Carbon::parse('2025-01-01'),
    ]);

    // Susutkan bulan Januari dan Februari
    $depJan = $this->service->generateMonthlyDepreciation($asset, Carbon::parse('2025-01-31'));
    $depFeb = $this->service->generateMonthlyDepreciation($asset, Carbon::parse('2025-02-28'));

    expect((float)$asset->fresh()->accumulated_depreciation)->toBe(1800000.0);

    // Batalkan hanya penyusutan Februari
    $this->service->reverseDepreciation($depFeb);

    $asset->refresh();
    // Akumulasi penyusutan hanya menghitung entri yang aktif (Januari = 900.000)
    expect((float)$asset->accumulated_depreciation)->toBe(900000.0);
    expect((float)$asset->book_value)->toBe(59100000.0);

    // Jurnal Januari tetap aman
    expect(JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depJan->id)->count())->toBe(2);

    // Jurnal Februari terhapus
    expect(JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depFeb->id)->count())->toBe(0);
});

test('AST-REV-03: Soft-delete aset otomatis menghapus seluruh jurnal perolehan via observer cascading', function () {
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 45000000.0,
    ]);

    $journalQuery = JournalEntry::where('source_type', Asset::class)
        ->where('source_id', $asset->id);

    // Pastikan jurnal perolehan ada
    expect($journalQuery->count())->toBeGreaterThan(0);

    // Hapus aset (soft-delete)
    $asset->delete();

    // Observer AssetObserver::deleting() harus membersihkan jurnal perolehan aset
    expect($journalQuery->count())->toBe(0);
});
