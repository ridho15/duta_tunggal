<?php

namespace Database\Factories;

use App\Models\BillOfMaterial;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillOfMaterialFactory extends Factory
{
    protected $model = BillOfMaterial::class;

    public function definition(): array
    {
        return [
            'cabang_id' => Cabang::factory(),
            'product_id' => Product::factory(),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'code' => 'BOM-' . $this->faker->unique()->numberBetween(1000, 9999),
            'nama_bom' => $this->faker->words(3, true),
            'note' => $this->faker->optional()->sentence(),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'uom_id' => UnitOfMeasure::factory(),
            'labor_cost' => $this->faker->randomFloat(2, 1000, 50000),
            'overhead_cost' => $this->faker->randomFloat(2, 500, 10000),
            'total_cost' => 0, // Will be calculated
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withCost(float $laborCost = null, float $overheadCost = null): static
    {
        return $this->state(fn (array $attributes) => [
            'labor_cost' => $laborCost ?? $this->faker->randomFloat(2, 1000, 50000),
            'overhead_cost' => $overheadCost ?? $this->faker->randomFloat(2, 500, 10000),
        ]);
    }
}