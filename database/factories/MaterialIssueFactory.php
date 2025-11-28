<?php

namespace Database\Factories;

use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialIssueFactory extends Factory
{
    protected $model = MaterialIssue::class;

    public function definition(): array
    {
        return [
            'issue_number' => 'MI-' . now()->format('Ymd') . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'manufacturing_order_id' => ManufacturingOrder::factory(),
            'warehouse_id' => Warehouse::factory(),
            'issue_date' => now(),
            'type' => 'issue',
            'status' => $this->faker->randomElement(['draft', 'completed']),
            'total_cost' => $this->faker->numberBetween(100000, 1000000),
            'notes' => $this->faker->sentence(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}