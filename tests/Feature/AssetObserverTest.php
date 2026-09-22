<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_creation_uses_selected_depreciation_method(): void
    {
        $asset = Asset::factory()->create([
            'purchase_cost' => 1_200,
            'salvage_value' => 0,
            'useful_life_years' => 3,
            'depreciation_method' => 'sum_of_years_digits',
            'asset_coa_id' => ChartOfAccount::factory()->create(['type' => 'Asset'])->id,
            'accumulated_depreciation_coa_id' => ChartOfAccount::factory()->create(['type' => 'Asset'])->id,
            'depreciation_expense_coa_id' => ChartOfAccount::factory()->create(['type' => 'Expense'])->id,
        ]);

        $asset->refresh();

        $this->assertSame(600.0, (float) $asset->annual_depreciation);
        $this->assertSame(50.0, (float) $asset->monthly_depreciation);
    }

    public function test_asset_update_recalculates_using_current_depreciation_method(): void
    {
        $asset = Asset::factory()->create([
            'purchase_cost' => 1_000,
            'salvage_value' => 100,
            'useful_life_years' => 4,
            'depreciation_method' => 'straight_line',
            'asset_coa_id' => ChartOfAccount::factory()->create(['type' => 'Asset'])->id,
            'accumulated_depreciation_coa_id' => ChartOfAccount::factory()->create(['type' => 'Asset'])->id,
            'depreciation_expense_coa_id' => ChartOfAccount::factory()->create(['type' => 'Expense'])->id,
        ]);

        $asset->update([
            'depreciation_method' => 'declining_balance',
            'purchase_cost' => 1_200,
        ]);

        $asset->refresh();

        $this->assertSame(600.0, (float) $asset->annual_depreciation);
        $this->assertSame(50.0, (float) $asset->monthly_depreciation);
    }
}