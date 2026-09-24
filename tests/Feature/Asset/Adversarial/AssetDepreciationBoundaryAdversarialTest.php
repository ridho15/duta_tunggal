<?php

use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Services\AssetDepreciationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = astContext();
    $this->service = app(AssetDepreciationService::class);
});

test('AST-DEP-01: Menolak penyusutan pada aset yang tidak aktif (inactive atau disposed)', function () {
    $inactiveAsset = astCreate($this->ctx, [
        'status' => 'inactive',
    ]);

    expect(fn () => $this->service->generateMonthlyDepreciation($inactiveAsset, now()))
        ->toThrow(\Exception::class, 'Aset tidak aktif');

    $disposedAsset = astCreate($this->ctx, [
        'status' => 'disposed',
    ]);

    expect(fn () => $this->service->generateMonthlyDepreciation($disposedAsset, now()))
        ->toThrow(\Exception::class, 'Aset tidak aktif');
});

test('AST-DEP-02: Tanggal penyusutan sebelum tanggal pemakaian aset ditolak keras', function () {
    // Aset mulai dipakai tanggal 1 Juli 2026
    $asset = astCreate($this->ctx, [
        'usage_date' => Carbon::parse('2026-07-01'),
        'purchase_date' => Carbon::parse('2026-06-15'),
    ]);

    // Percobaan menyusutkan untuk bulan Mei 2026 (sebelum tanggal pakai)
    $priorDate = Carbon::parse('2026-05-31');

    expect(fn () => $this->service->generateMonthlyDepreciation($asset, $priorDate))
        ->toThrow(\Exception::class, 'Tanggal penyusutan tidak boleh sebelum tanggal pakai aset');

    expect(AssetDepreciation::where('asset_id', $asset->id)->count())->toBe(0);
});

test('AST-DEP-03: Menolak duplikasi penyusutan pada periode bulan dan tahun yang sama', function () {
    $asset = astCreate($this->ctx, [
        'usage_date' => Carbon::parse('2026-01-01'),
        'purchase_date' => Carbon::parse('2026-01-01'),
    ]);

    $date1 = Carbon::parse('2026-01-15');
    $date2 = Carbon::parse('2026-01-28');

    // Eksekusi pertama sukses
    $dep1 = $this->service->generateMonthlyDepreciation($asset, $date1);
    expect($dep1)->not->toBeNull();
    expect($dep1->status)->toBe('recorded');

    // Eksekusi kedua pada bulan yang sama harus ditolak tegas
    expect(fn () => $this->service->generateMonthlyDepreciation($asset, $date2))
        ->toThrow(\Exception::class, 'Penyusutan untuk periode ini sudah ada');

    expect(AssetDepreciation::where('asset_id', $asset->id)->count())->toBe(1);
});

test('AST-DEP-04: Underflow Guard: Nilai buku tidak boleh disusutkan di bawah nilai residu', function () {
    // Biaya 10 Juta, Residu 9 Juta, Nilai yang dapat disusutkan = 1 Juta (Umur 1 tahun = 12 bulan)
    // Penyusutan bulanan = 1.000.000 / 12 = 83.333,33
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 10000000.0,
        'salvage_value' => 9000000.0,
        'useful_life_years' => 1,
        'usage_date' => Carbon::parse('2025-01-01'),
    ]);

    // Simulasi akumulasi penyusutan yang sudah mendekati limit (misal sudah 950.000 disusutkan)
    AssetDepreciation::create([
        'asset_id' => $asset->id,
        'depreciation_date' => Carbon::parse('2025-11-30'),
        'period_month' => 11,
        'period_year' => 2025,
        'amount' => 950000.0,
        'accumulated_total' => 950000.0,
        'book_value' => 9050000.0,
        'status' => 'recorded',
    ]);

    $asset->accumulated_depreciation = 950000.0;
    $asset->book_value = 9050000.0;
    $asset->save();

    // Sisa plafon hanya 50.000, sedangkan monthly_depreciation adalah 83.333,33
    // Eksekusi penyusutan berikutnya akan menekan newBookValue menjadi 8.966.666,67 (< salvage_value 9.000.000)
    expect(fn () => $this->service->generateMonthlyDepreciation($asset, Carbon::parse('2025-12-31')))
        ->toThrow(\Exception::class, 'Aset sudah disusutkan penuh');
});
