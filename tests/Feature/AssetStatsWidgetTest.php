<?php

use App\Filament\Widgets\AssetStatsWidget;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetStatsWidgetTest extends TestCase
{
    use RefreshDatabase;

    private function makeCoa(string $type = 'Asset'): ChartOfAccount
    {
        return ChartOfAccount::factory()->create([
            'type' => $type,
            'is_active' => true,
        ]);
    }

    private function createAsset(array $overrides = []): Asset
    {
        $assetCoa = $this->makeCoa('Asset');
        $accumCoa = $this->makeCoa('Contra Asset');
        $expenseCoa = $this->makeCoa('Expense');

        return Asset::factory()->create(array_merge([
            'status'                           => 'active',
            'purchase_cost'                    => 10_000_000,
            'accumulated_depreciation'         => 0,
            'book_value'                       => 10_000_000,
            'asset_coa_id'                     => $assetCoa->id,
            'accumulated_depreciation_coa_id'  => $accumCoa->id,
            'depreciation_expense_coa_id'      => $expenseCoa->id,
        ], $overrides));
    }

    /** Helper: invoke protected getStats() via reflection */
    private function getStats(): array
    {
        $widget = new AssetStatsWidget();
        $method = new \ReflectionMethod(AssetStatsWidget::class, 'getStats');
        $method->setAccessible(true);
        return $method->invoke($widget);
    }

    /** @test */
    public function widget_returns_correct_total_asset_count(): void
    {
        $this->createAsset();
        $this->createAsset(['status' => 'active']);
        $this->createAsset(['status' => 'disposed']);

        $stats = $this->getStats();

        // Total count stat is the first one
        $this->assertEquals(3, $stats[0]->getValue());
    }

    /** @test */
    public function widget_counts_only_active_and_posted_assets(): void
    {
        $this->createAsset(['status' => 'active']);
        $this->createAsset(['status' => 'posted']);
        $this->createAsset(['status' => 'disposed']);
        $this->createAsset(['status' => 'fully_depreciated']);

        $stats = $this->getStats();

        // Active stat is the second one (active + posted)
        $this->assertEquals(2, $stats[1]->getValue());
    }

    /** @test */
    public function widget_counts_fully_depreciated_assets(): void
    {
        $this->createAsset(['status' => 'active']);
        $this->createAsset(['status' => 'fully_depreciated']);
        $this->createAsset(['status' => 'fully_depreciated']);

        $stats = $this->getStats();

        // Fully depreciated stat is the third one
        $this->assertEquals(2, $stats[2]->getValue());
    }

    /** @test */
    public function widget_total_value_stat_has_currency_icon(): void
    {
        $this->createAsset(['purchase_cost' => 5_000_000]);

        $stats = $this->getStats();

        // Total nilai aset is the 4th stat
        $this->assertStringContainsString('heroicon-m-currency-dollar', $stats[3]->getDescriptionIcon());
    }

    /** @test */
    public function widget_book_value_stat_has_calculator_icon(): void
    {
        $this->createAsset(['purchase_cost' => 10_000_000, 'book_value' => 8_000_000]);

        $stats = $this->getStats();

        // Total book value is the 5th stat
        $this->assertStringContainsString('heroicon-m-calculator', $stats[4]->getDescriptionIcon());
    }

    /** @test */
    public function widget_shows_zero_when_no_assets_exist(): void
    {
        $stats = $this->getStats();

        $this->assertEquals(0, $stats[0]->getValue()); // total
        $this->assertEquals(0, $stats[1]->getValue()); // active
        $this->assertEquals(0, $stats[2]->getValue()); // fully_depreciated
    }

    /** @test */
    public function widget_returns_five_stats(): void
    {
        $stats = $this->getStats();

        $this->assertCount(5, $stats);
    }
}
