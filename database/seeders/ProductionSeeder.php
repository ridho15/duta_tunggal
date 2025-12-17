<?php

namespace Database\Seeders;

use App\Models\ManufacturingOrder;
use App\Models\Production;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->createProductions();
        });
    }

    private function createProductions(): void
    {
        $this->command->info('Creating production records...');

        // Get manufacturing orders that have material issues completed
        $manufacturingOrders = ManufacturingOrder::with('productionPlan.product')
            ->where('status', 'in_progress')
            ->get();

        if ($manufacturingOrders->isEmpty()) {
            $this->command->warn('No manufacturing orders in progress found. Please run ManufacturingOrderSeeder first.');
            return;
        }

        $productionCounter = 1;

        foreach ($manufacturingOrders as $mo) {
            // Create production record for completed manufacturing orders
        $this->createProductionForOrder($mo, $productionCounter);
        $productionCounter++;
        }

        $this->command->info('Production records created successfully!');
    }

    private function createProductionForOrder($mo, &$productionCounter): void
    {
        // Simulate production completion
        $productionData = [
            'production_number' => 'PR-' . str_pad($productionCounter, 4, '0', STR_PAD_LEFT),
            'manufacturing_order_id' => $mo->id,
            'quantity_produced' => $mo->productionPlan->quantity, // Use quantity from production plan
            'production_date' => Carbon::now(),
            'status' => 'finished', // Mark as finished for demo data
        ];

        Production::create($productionData);

        // Update manufacturing order status to completed
        $mo->update(['status' => 'completed']);

        $this->command->info("Created production: {$productionData['production_number']} for MO: {$mo->mo_number}");
    }
}