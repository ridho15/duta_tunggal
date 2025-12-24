<?php

namespace Database\Factories;

use App\Models\Cabang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => 'CAT-' . $this->faker->unique()->numerify('###'),
            'name' => 'Kategori ' . $this->faker->word,
            'kenaikan_harga' => $this->faker->randomFloat(2, 0, 20), // 0% - 20%
        ];
    }
}
