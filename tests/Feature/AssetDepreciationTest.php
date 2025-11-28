<?php

use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AssetDepreciationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create COA for assets
    $this->assetCoa = ChartOfAccount::factory()->create([
        'code' => '1210.01',
        'name' => 'Kendaraan',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $this->accumulatedDepreciationCoa = ChartOfAccount::factory()->create([
        'code' => '1220.01',
        'name' => 'Akumulasi Penyusutan Kendaraan',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $this->depreciationExpenseCoa = ChartOfAccount::factory()->create([
        'code' => '6311',
        'name' => 'Beban Penyusutan Kendaraan',
        'type' => 'Expense',
        'is_active' => true,
    ]);

    $this->service = app(AssetDepreciationService::class);
});

test('asset automatically calculates depreciation on creation', function () {
    $asset = Asset::create([
        'name' => 'Mobil Operasional',
        'purchase_date' => now()->subMonths(6),
        'usage_date' => now()->subMonths(6),
        'purchase_cost' => 100000000, // 100 juta
        'salvage_value' => 10000000, // 10 juta
        'useful_life_years' => 5,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        'status' => 'active',
    ]);

    // Verify auto-calculation
    expect($asset->fresh())
        ->annual_depreciation->toEqual(18000000.0) // (100jt - 10jt) / 5 tahun
        ->monthly_depreciation->toEqual(1500000.0) // 18jt / 12 bulan
        ->book_value->toEqual(100000000.0); // Initial book value
});

test('asset recalculates depreciation when purchase cost changes', function () {
    $asset = Asset::create([
        'name' => 'Komputer',
        'purchase_date' => now(),
        'usage_date' => now(),
        'purchase_cost' => 10000000,
        'salvage_value' => 1000000,
        'useful_life_years' => 3,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        'status' => 'active',
    ]);

    $originalMonthly = $asset->monthly_depreciation;

    // Update purchase cost
    $asset->update(['purchase_cost' => 15000000]);

    expect($asset->fresh()->monthly_depreciation)
        ->not->toEqual($originalMonthly)
        ->toEqual(388888.89); // (15jt - 1jt) / 3 / 12
});

test('generate monthly depreciation creates journal entries', function () {
    $asset = Asset::create([
        'name' => 'Mesin Produksi',
        'purchase_date' => now()->subYear(),
        'usage_date' => now()->subYear(),
        'purchase_cost' => 50000000,
        'salvage_value' => 5000000,
        'useful_life_years' => 5,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        'status' => 'active',
    ]);

    $depreciationDate = now()->startOfMonth();
    
    $depreciation = $this->service->generateMonthlyDepreciation($asset, $depreciationDate);

    // Verify depreciation created
    expect($depreciation)
        ->asset_id->toBe($asset->id)
        ->amount->toEqual(750000.0) // (50jt - 5jt) / 5 / 12
        ->status->toBe('recorded')
        ->period_month->toBe($depreciationDate->month)
        ->period_year->toBe($depreciationDate->year);

    // Verify journal entries created
    $journals = JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depreciation->id)
        ->get();

    expect($journals)->toHaveCount(2);

    // Verify debit entry (Beban Penyusutan)
    $debitEntry = $journals->where('debit', '>', 0)->first();
    expect($debitEntry)
        ->coa_id->toBe($this->depreciationExpenseCoa->id)
        ->debit->toEqual(750000.0)
        ->credit->toEqual(0.0)
        ->journal_type->toBe('depreciation');

    // Verify credit entry (Akumulasi Penyusutan)
    $creditEntry = $journals->where('credit', '>', 0)->first();
    expect($creditEntry)
        ->coa_id->toBe($this->accumulatedDepreciationCoa->id)
        ->credit->toEqual(750000.0)
        ->debit->toEqual(0.0)
        ->journal_type->toBe('depreciation');

    // Verify double entry balance
    expect($journals->sum('debit'))->toEqual($journals->sum('credit'));
});

test('cannot create duplicate depreciation for same period', function () {
    $asset = Asset::create([
        'name' => 'Furniture',
        'purchase_date' => now()->subMonths(3),
        'usage_date' => now()->subMonths(3),
        'purchase_cost' => 20000000,
        'salvage_value' => 2000000,
        'useful_life_years' => 4,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        'status' => 'active',
    ]);

    $date = now()->startOfMonth();
    
    // First depreciation should succeed
    $this->service->generateMonthlyDepreciation($asset, $date);

    // Second attempt should fail
    expect(fn() => $this->service->generateMonthlyDepreciation($asset, $date))
        ->toThrow(Exception::class, 'Penyusutan untuk periode ini sudah ada');
});

test('depreciation updates asset accumulated depreciation and book value', function () {
    $asset = Asset::create([
        'name' => 'Peralatan Kantor',
        'purchase_date' => now()->subMonths(6),
        'usage_date' => now()->subMonths(6),
        'purchase_cost' => 30000000,
        'salvage_value' => 3000000,
        'useful_life_years' => 5,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        'accumulated_depreciation' => 0,
        'status' => 'active',
    ]);

    $monthlyDepreciation = 450000; // (30jt - 3jt) / 5 / 12

    // Generate 3 months of depreciation
    for ($i = 0; $i < 3; $i++) {
        $date = now()->subMonths(2 - $i)->startOfMonth();
        $this->service->generateMonthlyDepreciation($asset, $date);
    }

    $asset->refresh();

    expect($asset)
        ->accumulated_depreciation->toEqual(1350000.0) // 450k * 3
        ->book_value->toEqual(28650000.0); // 30jt - 1.35jt
});

test('cannot depreciate inactive asset', function () {
    $asset = Asset::create([
        'name' => 'Asset Inactive',
        'purchase_date' => now(),
        'usage_date' => now(),
        'purchase_cost' => 10000000,
        'salvage_value' => 1000000,
        'useful_life_years' => 3,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        'status' => 'disposed',
    ]);

    expect(fn() => $this->service->generateMonthlyDepreciation($asset, now()))
        ->toThrow(Exception::class, 'Aset tidak aktif');
});

test('cannot depreciate before usage date', function () {
    $usageDate = now()->addMonths(2);
    
    $asset = Asset::create([
        'name' => 'Future Asset',
        'purchase_date' => now(),
        'usage_date' => $usageDate,
        'purchase_cost' => 10000000,
        'salvage_value' => 1000000,
        'useful_life_years' => 3,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        'status' => 'active',
    ]);

    expect(fn() => $this->service->generateMonthlyDepreciation($asset, now()))
        ->toThrow(Exception::class, 'Tanggal penyusutan tidak boleh sebelum tanggal pakai aset');
});

test('generate all monthly depreciation processes multiple assets', function () {
    // Create 3 active assets
    for ($i = 1; $i <= 3; $i++) {
        Asset::create([
            'name' => "Asset $i",
            'purchase_date' => now()->subYear(),
            'usage_date' => now()->subYear(),
            'purchase_cost' => 10000000 * $i,
            'salvage_value' => 1000000 * $i,
            'useful_life_years' => 3,
            'asset_coa_id' => $this->assetCoa->id,
            'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
            'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
            'status' => 'active',
        ]);
    }

    $results = $this->service->generateAllMonthlyDepreciation(now());

    expect($results)
        ->toHaveKey('success')
        ->toHaveKey('failed')
        ->toHaveKey('errors');

    expect($results['success'])->toBe(3);
    expect($results['failed'])->toBe(0);
});

test('reverse depreciation deletes journal entries and updates asset', function () {
    $asset = Asset::create([
        'name' => 'Test Asset',
        'purchase_date' => Carbon::parse('2024-01-01'),
        'usage_date' => Carbon::parse('2024-01-01'),
        'purchase_cost' => 20000000,
        'salvage_value' => 2000000,
        'useful_life_years' => 5,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        'status' => 'active',
    ]);

    // Generate depreciation (after usage date)
    $depDate = Carbon::parse('2024-02-01');
    $depreciation = $this->service->generateMonthlyDepreciation($asset, $depDate);
    
    $journalCountBefore = JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depreciation->id)
        ->count();

    expect($journalCountBefore)->toBe(2);
    expect($asset->fresh()->accumulated_depreciation)->toBeGreaterThan(0);

    // Reverse depreciation
    $this->service->reverseDepreciation($depreciation);

    // Verify status changed
    expect($depreciation->fresh()->status)->toBe('reversed');

    // Verify journal entries deleted
    $journalCountAfter = JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depreciation->id)
        ->count();

    expect($journalCountAfter)->toBe(0);

    // Verify asset updated - accumulated depreciation recalculated from remaining entries
    expect($asset->fresh()->accumulated_depreciation)->toEqual(0.0);
});

test('asset status changes to fully depreciated when book value reaches salvage value', function () {
    $usageDate = Carbon::parse('2024-01-01');
    
    $asset = Asset::create([
        'name' => 'Short Life Asset',
        'purchase_date' => $usageDate,
        'usage_date' => $usageDate,
        'purchase_cost' => 12000000,
        'salvage_value' => 0,
        'useful_life_years' => 1, // 1 year = 12 months
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        'status' => 'active',
    ]);

    // Monthly depreciation = 12jt / 1 / 12 = 1jt
    // Generate 12 months of depreciation
    for ($i = 0; $i < 12; $i++) {
        $date = Carbon::parse('2024-01-01')->addMonths($i)->startOfMonth();
        $this->service->generateMonthlyDepreciation($asset, $date);
    }

    $asset->refresh();

    expect($asset)
        ->accumulated_depreciation->toEqual(12000000.0)
        ->book_value->toEqual(0.0)
        ->status->toBe('fully_depreciated');
});

test('journal entries have correct reference and description', function () {
    $asset = Asset::create([
        'name' => 'Test Machine',
        'purchase_date' => now()->subMonths(3),
        'usage_date' => now()->subMonths(3),
        'purchase_cost' => 15000000,
        'salvage_value' => 1500000,
        'useful_life_years' => 5,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accumulatedDepreciationCoa->id,
        'depreciation_expense_coa_id' => $this->depreciationExpenseCoa->id,
        'status' => 'active',
    ]);

    $date = now()->startOfMonth();
    $depreciation = $this->service->generateMonthlyDepreciation($asset, $date);

    $journals = JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depreciation->id)
        ->get();

    $expectedReference = 'DEP-' . $date->format('Ym') . '-' . $asset->id;

    $journals->each(function ($journal) use ($expectedReference, $asset, $date) {
        expect($journal->reference)->toBe($expectedReference);
        expect($journal->description)->toContain('Penyusutan');
        expect($journal->description)->toContain($asset->name);
        expect(\Carbon\Carbon::parse($journal->date)->format('Y-m-d'))
            ->toBe($date->format('Y-m-d'));
    });
});
