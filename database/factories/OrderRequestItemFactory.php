<?php

namespace Database\Factories;

use App\Models\OrderRequest;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderRequestItem>
 */
class OrderRequestItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_request_id' => OrderRequest::inRandomOrder()->first()->id,
            'product_id' => Product::inRandomOrder()->first()->id,
            'quantity' => random_int(1, 20),
            'note' => $this->faker->sentence()
        ];
    }
}
