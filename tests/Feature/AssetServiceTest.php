<?php

use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetServiceTest extends TestCase
{
    use RefreshDatabase;

    protected $assetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assetService = new AssetService();
    }

    /** @test */
    public function it_can_post_asset_acquisition_journal()
    {
        // Create test COAs
        $assetCoa = ChartOfAccount::factory()->create(['name' => 'Asset COA', 'type' => 'asset']);
        $supplierCoa = ChartOfAccount::factory()->create(['name' => 'Supplier COA', 'type' => 'liability']);

        // Create test asset
        $asset = Asset::factory()->create([
            'name' => 'Test Asset',
            'purchase_cost' => 1000000,
            'asset_coa_id' => $assetCoa->id,
            'accumulated_depreciation_coa_id' => ChartOfAccount::factory()->create(['type' => 'asset'])->id,
            'depreciation_expense_coa_id' => ChartOfAccount::factory()->create(['type' => 'expense'])->id,
        ]);

        // Post acquisition journal
        $this->assetService->postAssetAcquisitionJournal($asset, $supplierCoa->id);

        // Assert journal entries were created
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\Asset',
            'source_id' => $asset->id,
            'debit' => 1000000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\Asset',
            'source_id' => $asset->id,
            'debit' => 0,
            'credit' => 1000000,
        ]);

        // Assert asset status is posted
        $asset->refresh();
        $this->assertEquals('posted', $asset->status);
    }

    /** @test */
    public function it_can_post_asset_depreciation_journal()
    {
        // Create test COAs
        $depreciationExpenseCoa = ChartOfAccount::factory()->create(['name' => 'Depreciation Expense', 'type' => 'expense']);
        $accumulatedDepreciationCoa = ChartOfAccount::factory()->create(['name' => 'Accumulated Depreciation', 'type' => 'asset']);

        // Create test asset
        $asset = Asset::factory()->create([
            'name' => 'Test Asset',
            'purchase_cost' => 1000000,
            'monthly_depreciation' => 83333.33,
            'asset_coa_id' => ChartOfAccount::factory()->create(['type' => 'asset'])->id,
            'accumulated_depreciation_coa_id' => $accumulatedDepreciationCoa->id,
            'depreciation_expense_coa_id' => $depreciationExpenseCoa->id,
        ]);

        $depreciationAmount = 83333.33;
        $period = '2024-01';

        // Post depreciation journal
        $this->assetService->postAssetDepreciationJournal($asset, $depreciationAmount, $period);

        // Assert journal entries were created
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\Asset',
            'source_id' => $asset->id,
            'debit' => $depreciationAmount,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\Asset',
            'source_id' => $asset->id,
            'debit' => 0,
            'credit' => $depreciationAmount,
        ]);
    }

    /** @test */
    public function it_can_check_if_asset_has_posted_journals()
    {
        // Create test asset
        $asset = Asset::factory()->create([
            'asset_coa_id' => ChartOfAccount::factory()->create(['type' => 'asset'])->id,
            'accumulated_depreciation_coa_id' => ChartOfAccount::factory()->create(['type' => 'asset'])->id,
            'depreciation_expense_coa_id' => ChartOfAccount::factory()->create(['type' => 'expense'])->id,
        ]);

        // Initially should not have posted journals
        $this->assertFalse($this->assetService->hasPostedJournals($asset));

        // Create journal entries
        JournalEntry::factory()->create([
            'source_type' => 'App\Models\Asset',
            'source_id' => $asset->id,
        ]);

        // Now should have posted journals
        $this->assertTrue($this->assetService->hasPostedJournals($asset));
    }

    /** @test */
    public function it_can_get_asset_journals()
    {
        // Create test asset
        $asset = Asset::factory()->create([
            'asset_coa_id' => ChartOfAccount::factory()->create(['type' => 'asset'])->id,
            'accumulated_depreciation_coa_id' => ChartOfAccount::factory()->create(['type' => 'asset'])->id,
            'depreciation_expense_coa_id' => ChartOfAccount::factory()->create(['type' => 'expense'])->id,
        ]);

        // Create journal entries
        $acquisitionEntry = JournalEntry::factory()->create([
            'source_type' => 'App\Models\Asset',
            'source_id' => $asset->id,
            'date' => '2024-01-01',
        ]);

        $depreciationEntry = JournalEntry::factory()->create([
            'source_type' => 'App\Models\Asset',
            'source_id' => $asset->id,
            'date' => '2024-02-01',
        ]);

        $journals = $this->assetService->getAssetJournals($asset);

        $this->assertCount(2, $journals);
        $this->assertEquals($acquisitionEntry->id, $journals->first()->id);
        $this->assertEquals($depreciationEntry->id, $journals->last()->id);
    }
}