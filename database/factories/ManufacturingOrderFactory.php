<?php

namespace Database\Factories;

use App\Models\Cabang;
use App\Models\ManufacturingOrder;
use App\Models\Product;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class ManufacturingOrderFactory extends Factory
{
    protected $model = ManufacturingOrder::class;

    public function definition(): array
    {
        return [
            'mo_number' => 'MO-' . now()->format('Ymd') . '-' . str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'product_id' => Product::factory(),
            'quantity' => $this->faker->numberBetween(1, 100),
            'status' => $this->faker->randomElement(['draft', 'in_progress', 'completed']),
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'uom_id' => UnitOfMeasure::factory(),
            'warehouse_id' => Warehouse::factory(),
            'rak_id' => Rak::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
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