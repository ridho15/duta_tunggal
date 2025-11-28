<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductionPlan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductionPlanFactory extends Factory
{
    protected $model = ProductionPlan::class;

    public function definition(): array
    {
        return [
            'plan_number' => 'PP-' . now()->format('Ymd') . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'name' => $this->faker->sentence(3),
            'source_type' => $this->faker->randomElement(['sale_order', 'manual']),
            'product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(10, 1000),
            'uom_id' => UnitOfMeasure::factory(),
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'status' => $this->faker->randomElement(['draft', 'scheduled', 'in_progress', 'completed', 'cancelled']),
            'notes' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}