<?php

namespace Database\Factories;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillOfMaterialItemFactory extends Factory
{
    protected $model = BillOfMaterialItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 0.1, 50);
        $unitPrice = $this->faker->randomFloat(2, 100, 10000);

        return [
            'bill_of_material_id' => BillOfMaterial::factory(),
            'product_id' => Product::factory(),
            'quantity' => $quantity,
            'uom_id' => UnitOfMeasure::factory(),
            'unit_price' => $unitPrice,
            'subtotal' => $quantity * $unitPrice,
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    public function withSpecificQuantity(float $quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $unitPrice = $attributes['unit_price'] ?? $this->faker->randomFloat(2, 100, 10000);
            return [
                'quantity' => $quantity,
                'subtotal' => $quantity * $unitPrice,
            ];
        });
    }

    public function withSpecificPrice(float $unitPrice): static
    {
        return $this->state(function (array $attributes) use ($unitPrice) {
            $quantity = $attributes['quantity'] ?? $this->faker->randomFloat(2, 0.1, 50);
            return [
                'unit_price' => $unitPrice,
                'subtotal' => $quantity * $unitPrice,
            ];
        });
    }
}