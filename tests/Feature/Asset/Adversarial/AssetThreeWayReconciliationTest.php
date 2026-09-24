<?php

use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\AssetDisposal;
use App\Models\Cabang;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AssetDepreciationService;
use App\Services\AssetDisposalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ctx = astContext();
    $this->depreciationService = app(AssetDepreciationService::class);
    $this->disposalService = app(AssetDisposalService::class);
});

test('AST-RC-01: Rekonsiliasi tiga arah Nilai Buku (Model vs Riwayat Penyusutan vs Buku Besar Akumulasi)', function () {
    $cost = 120000000.0;
    $salvage = 12000000.0;
    $monthlyDep = 1800000.0; // (120jt - 12jt) / 5 / 12

    $asset = astCreate($this->ctx, [
        'purchase_cost' => $cost,
        'salvage_value' => $salvage,
        'useful_life_years' => 5,
        'usage_date' => Carbon::parse('2025-01-01'),
        'purchase_date' => Carbon::parse('2025-01-01'),
    ]);

    // Siklus penyusutan 3 bulan berturut-turut
    $this->depreciationService->generateMonthlyDepreciation($asset, Carbon::parse('2025-01-31'));
    $this->depreciationService->generateMonthlyDepreciation($asset, Carbon::parse('2025-02-28'));
    $this->depreciationService->generateMonthlyDepreciation($asset, Carbon::parse('2025-03-31'));

    $asset->refresh();

    $expectedTotalDep = $monthlyDep * 3; // 5.400.000
    $expectedBookValue = $cost - $expectedTotalDep; // 114.600.000

    // 1. Verifikasi Nilai pada Model Asset
    expect((float)$asset->accumulated_depreciation)->toBe($expectedTotalDep);
    expect((float)$asset->book_value)->toBe($expectedBookValue);

    // 2. Verifikasi Riwayat Entri Penyusutan (AssetDepreciation)
    $entriesTotal = (float)$asset->depreciationEntries()->where('status', 'recorded')->sum('amount');
    expect($entriesTotal)->toBe($expectedTotalDep);

    // 3. Verifikasi Saldo Buku Besar Akumulasi Penyusutan (Chart of Account)
    $glAccumulatedCredit = (float)JournalEntry::where('coa_id', $this->ctx['accumulatedDepreciationCoa']->id)
        ->sum('credit');
    expect($glAccumulatedCredit)->toBe($expectedTotalDep);

    // Invarian Tiga Arah Terpenuhi Presisi
    expect($entriesTotal)->toBe((float)$asset->accumulated_depreciation);
    expect($glAccumulatedCredit)->toBe((float)$asset->accumulated_depreciation);
});

test('AST-RC-02: Keseimbangan Buku Besar pada seluruh siklus hidup aset (Akuisisi, Penyusutan, Pelepasan)', function () {
    // 1. Akuisisi Aset senilai 100 Juta
    $asset = astCreate($this->ctx, [
        'purchase_cost' => 100000000.0,
        'salvage_value' => 10000000.0,
        'useful_life_years' => 5, // 1.500.000 / bulan
        'usage_date' => Carbon::parse('2025-01-01'),
    ]);

    // 2. Penyusutan 2 bulan (2 x 1.500.000 = 3.000.000)
    $this->depreciationService->generateMonthlyDepreciation($asset, Carbon::parse('2025-01-31'));
    $this->depreciationService->generateMonthlyDepreciation($asset, Carbon::parse('2025-02-28'));

    // 3. Pelepasan Aset (Dijual seharga 85 Juta)
    // Nilai buku saat dilepas: 100jt - 3jt = 97 Juta
    // Hasil: Rugi pelepasan = 85jt - 97jt = -12 Juta
    $disposal = $this->disposalService->createDisposal($asset, [
        'disposal_date' => Carbon::parse('2025-03-15')->toDateString(),
        'disposal_type' => 'sale',
        'sale_price' => 85000000.0,
        'notes' => 'Pelepasan aset kendaraan operasional',
    ]);

    expect($disposal->status)->toBe('completed');

    // 4. Audit Seluruh Jurnal Buku Besar Terkait Aset Ini
    $allJournals = JournalEntry::where(function ($query) use ($asset, $disposal) {
        $query->where(fn ($q) => $q->where('source_type', Asset::class)->where('source_id', $asset->id))
            ->orWhere(fn ($q) => $q->where('source_type', AssetDepreciation::class)->whereIn('source_id', $asset->depreciationEntries->pluck('id')))
            ->orWhere(fn ($q) => $q->where('source_type', AssetDisposal::class)->where('source_id', $disposal->id));
    })->get();

    $totalDebit = (float)$allJournals->sum('debit');
    $totalCredit = (float)$allJournals->sum('credit');

    expect($totalDebit)->toBeGreaterThan(0);
    expect($totalDebit)->toBe($totalCredit);
    expect($totalDebit - $totalCredit)->toBe(0.0);
});

test('AST-RC-03: Isolasi Multi-Cabang: Penegakan CabangScope membatasi akses aset antar-cabang', function () {
    $cabangB = Cabang::factory()->create([
        'kode' => 'AST-BR-B-' . strtoupper(substr(uniqid(), -4)),
        'nama' => 'Cabang Aset Wilayah B',
        'status' => 1,
    ]);

    $userA = User::factory()->create([
        'cabang_id' => $this->ctx['cabang']->id,
        'manage_type' => 'cabang', // Non-all
    ]);

    $userB = User::factory()->create([
        'cabang_id' => $cabangB->id,
        'manage_type' => 'cabang', // Non-all
    ]);

    // Aset Cabang A
    $assetA = astCreate($this->ctx, [
        'name' => 'Truk Operasional Cabang A',
    ]);

    // Aset Cabang B
    $assetB = astCreate($this->ctx, [
        'name' => 'Truk Operasional Cabang B',
        'cabang_id' => $cabangB->id,
    ]);

    // Login sebagai User B -> Hanya boleh melihat Aset B
    Auth::login($userB);
    $assetsViewedByB = Asset::all();
    expect($assetsViewedByB->pluck('id')->all())->toContain($assetB->id);
    expect($assetsViewedByB->pluck('id')->all())->not->toContain($assetA->id);

    // Login sebagai User A -> Hanya boleh melihat Aset A
    Auth::login($userA);
    $assetsViewedByA = Asset::all();
    expect($assetsViewedByA->pluck('id')->all())->toContain($assetA->id);
    expect($assetsViewedByA->pluck('id')->all())->not->toContain($assetB->id);
});
