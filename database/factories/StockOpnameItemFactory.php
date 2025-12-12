<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Rak;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\StockOpnameItem>
 */
class StockOpnameItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StockOpnameItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stock_opname_id' => StockOpname::factory(),
            'product_id' => Product::factory(),
            'rak_id' => Rak::factory(),
            'system_qty' => $this->faker->numberBetween(10, 100),
            'physical_qty' => $this->faker->numberBetween(5, 95),
            'unit_cost' => $this->faker->numberBetween(1000, 100000),
        ];
    }

    /**
     * Indicate that the item has a positive difference (found more than system).
     */
    public function positiveDifference(): static
    {
        return $this->state(function (array $attributes) {
            $systemQty = $attributes['system_qty'] ?? $this->faker->numberBetween(10, 50);
            $physicalQty = $systemQty + $this->faker->numberBetween(1, 20);

            return [
                'system_qty' => $systemQty,
                'physical_qty' => $physicalQty,
            ];
        });
    }

    /**
     * Indicate that the item has a negative difference (found less than system).
     */
    public function negativeDifference(): static
    {
        return $this->state(function (array $attributes) {
            $systemQty = $attributes['system_qty'] ?? $this->faker->numberBetween(20, 100);
            $physicalQty = $systemQty - $this->faker->numberBetween(1, 15);

            return [
                'system_qty' => $systemQty,
                'physical_qty' => $physicalQty,
            ];
        });
    }

    /**
     * Indicate that the item has no difference.
     */
    public function noDifference(): static
    {
        return $this->state(function (array $attributes) {
            $qty = $this->faker->numberBetween(10, 100);

            return [
                'system_qty' => $qty,
                'physical_qty' => $qty,
            ];
        });
    }
}