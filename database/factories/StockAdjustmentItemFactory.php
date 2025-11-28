<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Rak;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockAdjustmentItem>
 */
class StockAdjustmentItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StockAdjustmentItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currentQty = $this->faker->randomFloat(2, 0, 1000);
        $adjustedQty = $this->faker->randomFloat(2, 0, 1000);
        $differenceQty = $adjustedQty - $currentQty;
        $unitCost = $this->faker->randomFloat(2, 1000, 100000);

        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'product_id' => Product::factory(),
            'rak_id' => Rak::factory(),
            'current_qty' => $currentQty,
            'adjusted_qty' => $adjustedQty,
            'difference_qty' => $differenceQty,
            'unit_cost' => $unitCost,
            'difference_value' => $differenceQty * $unitCost,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that this is an increase adjustment item.
     */
    public function increase(): static
    {
        return $this->state(function (array $attributes) {
            $currentQty = $this->faker->randomFloat(2, 0, 500);
            $adjustedQty = $currentQty + $this->faker->randomFloat(2, 1, 500);
            $differenceQty = $adjustedQty - $currentQty;
            $unitCost = $this->faker->randomFloat(2, 1000, 100000);

            return [
                'current_qty' => $currentQty,
                'adjusted_qty' => $adjustedQty,
                'difference_qty' => $differenceQty,
                'difference_value' => $differenceQty * $unitCost,
            ];
        });
    }

    /**
     * Indicate that this is a decrease adjustment item.
     */
    public function decrease(): static
    {
        return $this->state(function (array $attributes) {
            $currentQty = $this->faker->randomFloat(2, 100, 1000);
            $adjustedQty = $currentQty - $this->faker->randomFloat(2, 1, $currentQty);
            $differenceQty = $adjustedQty - $currentQty;
            $unitCost = $this->faker->randomFloat(2, 1000, 100000);

            return [
                'current_qty' => $currentQty,
                'adjusted_qty' => $adjustedQty,
                'difference_qty' => $differenceQty,
                'difference_value' => $differenceQty * $unitCost,
            ];
        });
    }
}