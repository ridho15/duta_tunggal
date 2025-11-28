<?php

use App\Models\Asset;
use App\Models\AssetDepreciation;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AssetDepreciationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(AssetDepreciationService::class);
    
    $this->assetCoa = ChartOfAccount::factory()->create([
        'code' => '1210.01',
        'name' => 'Aset Test',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $this->accDepCoa = ChartOfAccount::factory()->create([
        'code' => '1220.01',
        'name' => 'Akumulasi Penyusutan Test',
        'type' => 'Asset',
        'is_active' => true,
    ]);

    $this->expenseCoa = ChartOfAccount::factory()->create([
        'code' => '6311',
        'name' => 'Beban Penyusutan Test',
        'type' => 'Expense',
        'is_active' => true,
    ]);
});

test('generateMonthlyDepreciation creates correct depreciation record', function () {
    $asset = Asset::factory()->create([
        'name' => 'Test Asset',
        'purchase_date' => now()->subMonths(6),
        'usage_date' => now()->subMonths(6),
        'purchase_cost' => 60000000,
        'salvage_value' => 6000000,
        'useful_life_years' => 6,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    $date = now()->startOfMonth();
    
    DB::beginTransaction();
    $depreciation = $this->service->generateMonthlyDepreciation($asset, $date);
    DB::commit();

    expect($depreciation)
        ->toBeInstanceOf(AssetDepreciation::class)
        ->asset_id->toBe($asset->id)
        ->amount->toEqual(750000.0) // (60jt - 6jt) / 6 / 12
        ->status->toBe('recorded');
    
    expect(\Carbon\Carbon::parse($depreciation->depreciation_date)->format('Y-m-d'))
        ->toBe($date->format('Y-m-d'));
});

test('generateMonthlyDepreciation creates balanced journal entries', function () {
    $asset = Asset::factory()->create([
        'purchase_cost' => 24000000,
        'salvage_value' => 2400000,
        'useful_life_years' => 4,
        'usage_date' => now()->subYear(),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    DB::beginTransaction();
    $depreciation = $this->service->generateMonthlyDepreciation($asset, now());
    DB::commit();

    $journals = JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depreciation->id)
        ->get();

    expect($journals)->toHaveCount(2);

    $totalDebit = $journals->sum('debit');
    $totalCredit = $journals->sum('credit');

    expect($totalDebit)->toEqual($totalCredit);
    expect($totalDebit)->toEqual(450000.0); // (24jt - 2.4jt) / 4 / 12
});

test('generateMonthlyDepreciation uses correct COAs', function () {
    $asset = Asset::factory()->create([
        'purchase_cost' => 30000000,
        'salvage_value' => 3000000,
        'useful_life_years' => 5,
        'usage_date' => now()->subMonths(6),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    DB::beginTransaction();
    $depreciation = $this->service->generateMonthlyDepreciation($asset, now());
    DB::commit();

    $journals = JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depreciation->id)
        ->get();

    // Debit: Beban Penyusutan
    $debitEntry = $journals->where('debit', '>', 0)->first();
    expect($debitEntry->coa_id)->toBe($this->expenseCoa->id);

    // Credit: Akumulasi Penyusutan
    $creditEntry = $journals->where('credit', '>', 0)->first();
    expect($creditEntry->coa_id)->toBe($this->accDepCoa->id);
});

test('generateMonthlyDepreciation updates asset accumulated depreciation', function () {
    $asset = Asset::factory()->create([
        'purchase_cost' => 18000000,
        'salvage_value' => 1800000,
        'useful_life_years' => 3,
        'usage_date' => now()->subMonths(3),
        'accumulated_depreciation' => 0,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    $initialBookValue = $asset->book_value;
    $monthlyDep = $asset->monthly_depreciation;

    DB::beginTransaction();
    $this->service->generateMonthlyDepreciation($asset, now());
    DB::commit();

    $asset->refresh();

    expect($asset->accumulated_depreciation)->toEqual($monthlyDep);
    expect($asset->book_value)->toEqual($initialBookValue - $monthlyDep);
});

test('generateMonthlyDepreciation handles transaction rollback on error', function () {
    $asset = Asset::factory()->create([
        'purchase_cost' => 10000000,
        'salvage_value' => 1000000,
        'useful_life_years' => 3,
        'usage_date' => now()->subMonths(6),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    $date = now();
    
    // First depreciation
    $this->service->generateMonthlyDepreciation($asset, $date);

    $depCountBefore = AssetDepreciation::count();
    $journalCountBefore = JournalEntry::count();

    // Try to create duplicate (should fail and rollback)
    try {
        $this->service->generateMonthlyDepreciation($asset, $date);
    } catch (\Exception $e) {
        // Expected exception
    }

    // Verify no new records created
    expect(AssetDepreciation::count())->toBe($depCountBefore);
    expect(JournalEntry::count())->toBe($journalCountBefore);
});

test('generateAllMonthlyDepreciation processes only active assets', function () {
    // Active asset
    Asset::factory()->create([
        'name' => 'Active Asset',
        'usage_date' => now()->subYear(),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    // Inactive asset
    Asset::factory()->create([
        'name' => 'Inactive Asset',
        'usage_date' => now()->subYear(),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'disposed',
    ]);

    $results = $this->service->generateAllMonthlyDepreciation(now());

    expect($results['success'])->toBe(1);
    expect(AssetDepreciation::count())->toBe(1);
});

test('generateAllMonthlyDepreciation skips assets not yet in use', function () {
    // Asset already in use
    Asset::factory()->create([
        'usage_date' => now()->subMonths(6),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    // Asset to be used in future
    Asset::factory()->create([
        'usage_date' => now()->addMonths(2),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    $results = $this->service->generateAllMonthlyDepreciation(now());

    expect($results['success'])->toBe(1);
});

test('generateAllMonthlyDepreciation reports errors correctly', function () {
    // Valid asset
    Asset::factory()->create([
        'usage_date' => now()->subYear(),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    // Invalid asset (future usage date - will be skipped, not failed)
    Asset::factory()->create([
        'name' => 'Future Asset',
        'usage_date' => now()->addYear(),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    $results = $this->service->generateAllMonthlyDepreciation(now());

    expect($results)
        ->success->toBe(1)
        ->failed->toBe(0); // Future assets are skipped, not failed
});

test('reverseDepreciation changes status to reversed', function () {
    $asset = Asset::factory()->create([
        'usage_date' => now()->subMonths(6),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    $depreciation = $this->service->generateMonthlyDepreciation($asset, now());

    expect($depreciation->status)->toBe('recorded');

    DB::beginTransaction();
    $this->service->reverseDepreciation($depreciation);
    DB::commit();

    expect($depreciation->fresh()->status)->toBe('reversed');
});

test('reverseDepreciation deletes related journal entries', function () {
    $asset = Asset::factory()->create([
        'usage_date' => now()->subMonths(6),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    $depreciation = $this->service->generateMonthlyDepreciation($asset, now());

    $journalCountBefore = JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depreciation->id)
        ->count();

    expect($journalCountBefore)->toBe(2);

    DB::beginTransaction();
    $this->service->reverseDepreciation($depreciation);
    DB::commit();

    $journalCountAfter = JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depreciation->id)
        ->count();

    expect($journalCountAfter)->toBe(0);
});

test('reverseDepreciation updates asset accumulated depreciation', function () {
    $usageDate = Carbon::parse('2024-01-01');
    $asset = Asset::factory()->create([
        'usage_date' => $usageDate,
        'accumulated_depreciation' => 0,
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    // Generate 2 depreciations (different months to avoid duplicate, both after usage date)
    $month1 = Carbon::parse('2024-02-01');
    $month2 = Carbon::parse('2024-03-01');
    
    $dep1 = $this->service->generateMonthlyDepreciation($asset, $month1);
    $dep2 = $this->service->generateMonthlyDepreciation($asset, $month2);

    $asset->refresh();
    $accumulatedBefore = $asset->accumulated_depreciation;
    $expected = $dep1->amount;

    // Reverse the second one
    DB::beginTransaction();
    $this->service->reverseDepreciation($dep2);
    DB::commit();

    $asset->refresh();

    expect($asset->accumulated_depreciation)
        ->toBeLessThan($accumulatedBefore)
        ->toEqual($expected);
});

test('journal entries have correct journal_type', function () {
    $asset = Asset::factory()->create([
        'usage_date' => now()->subMonths(6),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    $depreciation = $this->service->generateMonthlyDepreciation($asset, now());

    $journals = JournalEntry::where('source_type', AssetDepreciation::class)
        ->where('source_id', $depreciation->id)
        ->get();

    $journals->each(function ($journal) {
        expect($journal->journal_type)->toBe('depreciation');
    });
});

test('depreciation links to journal entry', function () {
    $asset = Asset::factory()->create([
        'usage_date' => now()->subMonths(6),
        'asset_coa_id' => $this->assetCoa->id,
        'accumulated_depreciation_coa_id' => $this->accDepCoa->id,
        'depreciation_expense_coa_id' => $this->expenseCoa->id,
        'status' => 'active',
    ]);

    $depreciation = $this->service->generateMonthlyDepreciation($asset, now());

    expect($depreciation->journal_entry_id)->not->toBeNull();
    expect($depreciation->journalEntry)->toBeInstanceOf(JournalEntry::class);
    expect($depreciation->journalEntry->coa_id)->toBe($this->expenseCoa->id);
});
