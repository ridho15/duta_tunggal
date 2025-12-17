<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductStandardCost;
use App\Models\ProductionCostEntry;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AdvancedHppSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create standard costs for products
        $products = Product::where('is_raw_material', false)->take(5)->get();

        foreach ($products as $product) {
            ProductStandardCost::create([
                'product_id' => $product->id,
                'standard_material_cost' => rand(50000, 200000), // 50k - 200k
                'standard_labor_cost' => rand(25000, 75000), // 25k - 75k
                'standard_overhead_cost' => rand(15000, 45000), // 15k - 45k
                'total_standard_cost' => 0, // Will be calculated
                'effective_date' => now()->subDays(rand(1, 30)),
            ]);
        }

        // Calculate total standard cost
        ProductStandardCost::all()->each(function ($standardCost) {
            $total = $standardCost->standard_material_cost +
                    $standardCost->standard_labor_cost +
                    $standardCost->standard_overhead_cost;

            $standardCost->update(['total_standard_cost' => $total]);
        });

        // Create production cost entries for variance analysis
        $standardCosts = ProductStandardCost::with('product')->get();

        foreach ($standardCosts as $standardCost) {
            // Create production entry with some variance
            $quantity = rand(50, 200);
            $varianceFactor = rand(90, 110) / 100; // 90% - 110% of standard

            ProductionCostEntry::create([
                'product_id' => $standardCost->product_id,
                'quantity_produced' => $quantity,
                'actual_material_cost' => $standardCost->standard_material_cost * $quantity * $varianceFactor,
                'actual_labor_cost' => $standardCost->standard_labor_cost * $quantity * $varianceFactor,
                'actual_overhead_cost' => $standardCost->standard_overhead_cost * $quantity * $varianceFactor,
                'total_actual_cost' => 0, // Will be calculated
                'production_date' => now()->subDays(rand(1, 30)),
            ]);
        }

        // Calculate total actual cost
        ProductionCostEntry::all()->each(function ($entry) {
            $total = $entry->actual_material_cost +
                    $entry->actual_labor_cost +
                    $entry->actual_overhead_cost;

            $entry->update(['total_actual_cost' => $total]);
        });

        // Calculate variances
        ProductionCostEntry::all()->each(function ($entry) {
            $entry->calculateVariances();
        });

        $this->command->info('Advanced HPP data seeded successfully!');
        $this->command->info('Created ' . ProductStandardCost::count() . ' standard costs');
        $this->command->info('Created ' . ProductionCostEntry::count() . ' production cost entries');
        $this->command->info('Created ' . \App\Models\CostVariance::count() . ' cost variances');
    }
}
