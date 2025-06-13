<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cost_price = $this->faker->numberBetween(10000, 100000);
        return [
            'name' => $this->faker->word(),
            'sku' => $this->faker->unique()->word(),
            'product_category_id' => ProductCategory::inRandomOrder()->first()->id,
            'cost_price' => $cost_price,
            'sell_price' => $cost_price + ($cost_price * 10 / 100),
            'uom_id' => UnitOfMeasure::inRandomOrder()->first()->id,
        ];
    }
}
