<?php

use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\AssetDisposalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetDisposalTest extends TestCase
{
    use RefreshDatabase;

    protected $disposalService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->disposalService = new AssetDisposalService();
    }

    /** @test */
    public function it_can_create_asset_disposal_with_sale()
    {
        // Create test data
        $cabang = Cabang::factory()->create();
        $user = User::factory()->create();

        $assetCoa = ChartOfAccount::factory()->create(['type' => 'Asset']);
        $depreciationCoa = ChartOfAccount::factory()->create(['type' => 'Asset']);
        $expenseCoa = ChartOfAccount::factory()->create(['type' => 'Expense']);
        $cashCoa = ChartOfAccount::factory()->create(['code' => '1101', 'type' => 'Asset', 'name' => 'Kas']);
        $gainLossCoa = ChartOfAccount::factory()->create(['code' => '4101', 'type' => 'Revenue', 'name' => 'Gain on Asset Disposal']);

        // Ensure the COAs match service lookup criteria
        $actualCashCoa = ChartOfAccount::where('code', '1101')->first() ?? $cashCoa;
        $actualGainLossCoa = ChartOfAccount::where('type', 'Revenue')->where('code', 'like', '41%')->first() ?? $gainLossCoa;

        $asset = Asset::factory()->create([
            'name' => 'Test Asset',
            'purchase_cost' => 1000000,
            'accumulated_depreciation' => 200000,
            'cabang_id' => $cabang->id,
            'asset_coa_id' => $assetCoa->id,
            'accumulated_depreciation_coa_id' => $depreciationCoa->id,
            'depreciation_expense_coa_id' => $expenseCoa->id,
            'status' => 'active',
        ]);

        // Act as user
        $this->actingAs($user);

        // Create disposal
        $disposalData = [
            'disposal_date' => '2025-12-05',
            'disposal_type' => 'sale',
            'sale_price' => 900000,
            'notes' => 'Asset sold to third party',
        ];

        $disposal = $this->disposalService->createDisposal($asset, $disposalData);

        // Assert disposal record
        $this->assertInstanceOf(AssetDisposal::class, $disposal);
        $this->assertEquals($asset->id, $disposal->asset_id);
        $this->assertEquals('sale', $disposal->disposal_type);
        $this->assertEquals(900000, $disposal->sale_price);
        $this->assertEquals(800000, $disposal->book_value_at_disposal); // 1000000 - 200000
        $this->assertEquals(100000, $disposal->gain_loss_amount); // 900000 - 800000
        $this->assertEquals('completed', $disposal->status);

        // Assert asset status updated
        $asset->refresh();
        $this->assertEquals('disposed', $asset->status);

        // Assert journal entries created
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\AssetDisposal',
            'source_id' => $disposal->id,
            'coa_id' => $actualCashCoa->id,
            'debit' => 900000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\AssetDisposal',
            'source_id' => $disposal->id,
            'coa_id' => $assetCoa->id,
            'debit' => 0,
            'credit' => 1000000,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\AssetDisposal',
            'source_id' => $disposal->id,
            'coa_id' => $depreciationCoa->id,
            'debit' => 200000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\AssetDisposal',
            'source_id' => $disposal->id,
            'coa_id' => $actualGainLossCoa->id,
            'debit' => 0,
            'credit' => 100000,
        ]);
    }

    /** @test */
    public function it_can_create_asset_disposal_with_loss()
    {
        // Create test data
        $cabang = Cabang::factory()->create();
        $user = User::factory()->create();

        $assetCoa = ChartOfAccount::factory()->create(['type' => 'Asset']);
        $depreciationCoa = ChartOfAccount::factory()->create(['type' => 'Asset']);
        $expenseCoa = ChartOfAccount::factory()->create(['type' => 'Expense']);
        $cashCoa = ChartOfAccount::factory()->create(['code' => '1101', 'type' => 'Asset', 'name' => 'Kas']);
        $lossCoa = ChartOfAccount::factory()->create(['code' => '5201', 'type' => 'Expense', 'name' => 'Loss on Asset Disposal']);

        $asset = Asset::factory()->create([
            'name' => 'Test Asset',
            'purchase_cost' => 1000000,
            'accumulated_depreciation' => 200000,
            'cabang_id' => $cabang->id,
            'asset_coa_id' => $assetCoa->id,
            'accumulated_depreciation_coa_id' => $depreciationCoa->id,
            'depreciation_expense_coa_id' => $expenseCoa->id,
            'status' => 'active',
        ]);

        // Act as user
        $this->actingAs($user);

        // Create disposal with loss
        $disposalData = [
            'disposal_date' => '2025-12-05',
            'disposal_type' => 'sale',
            'sale_price' => 700000,
            'notes' => 'Asset sold at loss',
        ];

        $disposal = $this->disposalService->createDisposal($asset, $disposalData);

        // Assert loss calculation
        $this->assertEquals(-100000, $disposal->gain_loss_amount); // 700000 - 800000
        $this->assertEquals('loss', $disposal->gain_loss_type);

        // Assert journal entries for loss
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\AssetDisposal',
            'source_id' => $disposal->id,
            'coa_id' => $lossCoa->id,
            'debit' => 100000,
            'credit' => 0,
        ]);
    }

    /** @test */
    public function it_can_create_asset_disposal_without_sale()
    {
        // Create test data
        $cabang = Cabang::factory()->create();
        $user = User::factory()->create();

        $assetCoa = ChartOfAccount::factory()->create(['type' => 'asset']);
        $depreciationCoa = ChartOfAccount::factory()->create(['type' => 'asset']);
        $expenseCoa = ChartOfAccount::factory()->create(['type' => 'expense']);
        $lossCoa = ChartOfAccount::factory()->create(['type' => 'Expense', 'code' => '5201', 'name' => 'Loss on Asset Disposal']);

        $asset = Asset::factory()->create([
            'name' => 'Test Asset',
            'purchase_cost' => 1000000,
            'accumulated_depreciation' => 200000,
            'cabang_id' => $cabang->id,
            'asset_coa_id' => $assetCoa->id,
            'accumulated_depreciation_coa_id' => $depreciationCoa->id,
            'depreciation_expense_coa_id' => $expenseCoa->id,
            'status' => 'active',
        ]);

        // Act as user
        $this->actingAs($user);

        // Create disposal without sale (scrapped)
        $disposalData = [
            'disposal_date' => '2025-12-05',
            'disposal_type' => 'scrap',
            'notes' => 'Asset scrapped due to damage',
        ];

        $disposal = $this->disposalService->createDisposal($asset, $disposalData);

        // Assert disposal record
        $this->assertEquals('scrap', $disposal->disposal_type);
        $this->assertNull($disposal->sale_price);
        $this->assertEquals(800000, $disposal->book_value_at_disposal);
        $this->assertEquals(-800000, $disposal->gain_loss_amount); // Loss of entire book value
        $this->assertEquals('loss', $disposal->gain_loss_type);

        // Assert journal entries for scrapped asset
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\AssetDisposal',
            'source_id' => $disposal->id,
            'coa_id' => $assetCoa->id,
            'debit' => 0,
            'credit' => 1000000,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\AssetDisposal',
            'source_id' => $disposal->id,
            'coa_id' => $depreciationCoa->id,
            'debit' => 200000,
            'credit' => 0,
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'App\Models\AssetDisposal',
            'source_id' => $disposal->id,
            'coa_id' => $lossCoa->id,
            'debit' => 800000,
            'credit' => 0,
        ]);
    }

    /** @test */
    public function it_handles_transaction_rollback_on_error()
    {
        // Create test data
        $cabang = Cabang::factory()->create();
        $user = User::factory()->create();

        $asset = Asset::factory()->create([
            'name' => 'Test Asset',
            'status' => 'active',
            'cabang_id' => $cabang->id,
            'asset_coa_id' => ChartOfAccount::factory()->create(['type' => 'asset'])->id,
            'accumulated_depreciation_coa_id' => ChartOfAccount::factory()->create(['type' => 'asset'])->id,
            'depreciation_expense_coa_id' => ChartOfAccount::factory()->create(['type' => 'expense'])->id,
        ]);

        // Act as user
        $this->actingAs($user);

        // Mock an error during disposal creation
        $this->expectException(\Exception::class);

        try {
            $disposalData = [
                'disposal_date' => 'invalid-date', // This should cause an error
                'disposal_type' => 'sale',
            ];

            $this->disposalService->createDisposal($asset, $disposalData);
        } catch (\Exception $e) {
            // Check that asset status was not changed
            $asset->refresh();
            $this->assertEquals('active', $asset->status);

            // Check that no disposal record was created
            $this->assertDatabaseMissing('asset_disposals', [
                'asset_id' => $asset->id,
            ]);

            throw $e;
        }
    }

    /** @test */
    public function it_calculates_gain_loss_correctly()
    {
        $cabang = Cabang::factory()->create();
        $user = User::factory()->create();

        $asset = Asset::factory()->create([
            'purchase_cost' => 1000000,
            'accumulated_depreciation' => 300000,
            'cabang_id' => $cabang->id,
            'status' => 'active',
            'asset_coa_id' => ChartOfAccount::factory()->create(['type' => 'asset'])->id,
            'accumulated_depreciation_coa_id' => ChartOfAccount::factory()->create(['type' => 'asset'])->id,
            'depreciation_expense_coa_id' => ChartOfAccount::factory()->create(['type' => 'expense'])->id,
        ]);

        $this->actingAs($user);

        // Test gain scenario
        $disposalData = [
            'disposal_date' => '2025-12-05',
            'disposal_type' => 'sale',
            'sale_price' => 800000,
        ];

        $disposal = $this->disposalService->createDisposal($asset, $disposalData);

        $this->assertEquals(700000, $disposal->book_value_at_disposal); // 1000000 - 300000
        $this->assertEquals(100000, $disposal->gain_loss_amount); // 800000 - 700000
        $this->assertEquals('gain', $disposal->gain_loss_type);
    }
}