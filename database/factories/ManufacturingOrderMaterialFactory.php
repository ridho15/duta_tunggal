<?php

namespace Database\Factories;

use App\Models\ManufacturingOrder;
use App\Models\ManufacturingOrderMaterial;
use App\Models\Product;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class ManufacturingOrderMaterialFactory extends Factory
{
    protected $model = ManufacturingOrderMaterial::class;

    public function definition(): array
    {
        return [
            'manufacturing_order_id' => ManufacturingOrder::factory(),
            'material_id' => Product::factory()->state(['is_raw_material' => true]),
            'qty_required' => $this->faker->numberBetween(1, 50),
            'qty_used' => 0,
            'warehouse_id' => Warehouse::factory(),
            'uom_id' => UnitOfMeasure::factory(),
            'rak_id' => Rak::factory(),
        ];
    }

    public function used(): static
    {
        return $this->state(function (array $attributes) {
            $qtyRequired = $attributes['qty_required'] ?? $this->faker->numberBetween(1, 50);
            return [
                'qty_required' => $qtyRequired,
                'qty_used' => $this->faker->numberBetween(0, $qtyRequired),
            ];
        });
    }
}