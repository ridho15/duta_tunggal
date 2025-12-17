<?php

namespace Database\Seeders;

use App\Models\ProductionPlan;
use App\Models\ManufacturingOrder;
use App\Models\Cabang;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ManufacturingOrderSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->createManufacturingOrders();
        });
    }

    private function createManufacturingOrders(): void
    {
        $this->command->info('Creating manufacturing orders...');

        // Get existing production plans
        $productionPlans = ProductionPlan::with(['billOfMaterial.items.product'])->get();
        $cabang = Cabang::first();

        if ($productionPlans->isEmpty()) {
            $this->command->warn('No production plans found. Please run ProductionPlanSeeder first.');
            return;
        }

        $orderCounter = 1;

        foreach ($productionPlans as $plan) {
            // Create manufacturing orders for each production plan
            $this->createManufacturingOrderForPlan($plan, $orderCounter, $cabang);
            $orderCounter++;
        }

        $this->command->info('Manufacturing orders created successfully!');
    }

    private function createManufacturingOrderForPlan($plan, &$orderCounter, $cabang): void
    {
        // Calculate how many manufacturing orders to create based on plan quantity
        $maxOrderQuantity = 5; // Maximum quantity per manufacturing order
        $remainingQuantity = $plan->quantity;
        $orderNumber = 1;

        while ($remainingQuantity > 0) {
            $orderQuantity = min($remainingQuantity, $maxOrderQuantity);

            // Prepare items array from BOM
            $items = [];
            if ($plan->billOfMaterial && $plan->billOfMaterial->items) {
                foreach ($plan->billOfMaterial->items as $bomItem) {
                    $items[] = [
                        'product_id' => $bomItem->product_id,
                        'product_name' => $bomItem->product->name ?? 'Unknown Product',
                        'required_quantity' => $bomItem->quantity * $plan->quantity, // Use plan quantity instead of order quantity
                        'uom' => $bomItem->product->uom->name ?? 'Unit',
                        'issued_quantity' => 0,
                        'remaining_quantity' => $bomItem->quantity * $plan->quantity,
                    ];
                }
            }

            $moData = [
                'mo_number' => 'MO-' . str_pad($orderCounter, 4, '0', STR_PAD_LEFT) . '-' . $orderNumber,
                'production_plan_id' => $plan->id,
                'status' => $this->getOrderStatus($plan->status, $orderNumber),
                'start_date' => $plan->start_date,
                'end_date' => $plan->end_date,
                'items' => $items,
                'cabang_id' => $cabang->id ?? null,
            ];

            ManufacturingOrder::create($moData);

            $this->command->info("Created MO: {$moData['mo_number']} for plan: {$plan->name}");

            $remainingQuantity -= $orderQuantity;
            $orderNumber++;
            $orderCounter++;
        }
    }

    private function getOrderStatus($planStatus, $orderNumber): string
    {
        // Logic to determine MO status based on plan status and order number
        if ($planStatus === 'scheduled') {
            return $orderNumber === 1 ? 'in_progress' : 'draft';
        } elseif ($planStatus === 'in_progress') {
            return $orderNumber === 1 ? 'in_progress' : 'draft';
        } else {
            return 'draft';
        }
    }
}