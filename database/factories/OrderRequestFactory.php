<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OrderRequest>
 */
class OrderRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_number' => $this->faker->unique()->word(),
            'warehouse_id' => Warehouse::inRandomOrder()->first()->id,
            'supplier_id' => Supplier::inRandomOrder()->first()->id,
            'cabang_id' => function () {
                return Cabang::inRandomOrder()->first()?->id ?? Cabang::factory()->create()->id;
            },
            'request_date' => $this->faker->date(),
            'note' => $this->faker->sentence(),
            'created_by' => User::inRandomOrder()->first()->id,
        ];
    }
}
