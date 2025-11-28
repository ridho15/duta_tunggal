<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $purchaseCost = $this->faker->numberBetween(5000000, 100000000);
        $salvageValue = $purchaseCost * 0.1; // 10% of purchase cost
        $usefulLife = $this->faker->numberBetween(3, 10);
        
        // Calculate depreciation
        $depreciableAmount = $purchaseCost - $salvageValue;
        $annualDepreciation = $depreciableAmount / $usefulLife;
        $monthlyDepreciation = $annualDepreciation / 12;

        return [
            'name' => $this->faker->words(3, true),
            'purchase_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'usage_date' => $this->faker->dateTimeBetween('-2 years', 'now'),
            'purchase_cost' => $purchaseCost,
            'salvage_value' => $salvageValue,
            'useful_life_years' => $usefulLife,
            'annual_depreciation' => $annualDepreciation,
            'monthly_depreciation' => $monthlyDepreciation,
            'accumulated_depreciation' => 0,
            'book_value' => $purchaseCost,
            'status' => 'active',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the asset is disposed
     */
    public function disposed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'disposed',
        ]);
    }

    /**
     * Indicate that the asset is fully depreciated
     */
    public function fullyDepreciated(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'fully_depreciated',
                'accumulated_depreciation' => $attributes['purchase_cost'] - $attributes['salvage_value'],
                'book_value' => $attributes['salvage_value'],
            ];
        });
    }
}
