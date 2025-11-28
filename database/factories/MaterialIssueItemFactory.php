<?php

namespace Database\Factories;

use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Models\Product;
use App\Models\Rak;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaterialIssueItemFactory extends Factory
{
    protected $model = MaterialIssueItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(1, 100);
        $costPerUnit = $this->faker->numberBetween(10000, 50000);

        return [
            'material_issue_id' => MaterialIssue::factory(),
            'product_id' => Product::factory()->state(['is_raw_material' => true]),
            'uom_id' => UnitOfMeasure::factory(),
            'warehouse_id' => Warehouse::factory(),
            'rak_id' => Rak::factory(),
            'quantity' => $quantity,
            'cost_per_unit' => $costPerUnit,
            'total_cost' => $quantity * $costPerUnit,
            'notes' => $this->faker->sentence(),
        ];
    }
}