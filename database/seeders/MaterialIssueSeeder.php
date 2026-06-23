<?php

namespace Database\Seeders;

use App\Models\ProductionPlan;
use App\Models\ManufacturingOrder;
use App\Models\MaterialIssue;
use App\Models\MaterialIssueItem;
use App\Services\ManufacturingService;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MaterialIssueSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->createMaterialIssues();
        });
    }

    private function createMaterialIssues(): void
    {
        $this->command->info('Creating material issues...');

        // Get existing manufacturing orders with their production plans
        $manufacturingOrders = ManufacturingOrder::with([
            'productionPlan.billOfMaterial.items.product.inventoryStock',
            'productionPlan.warehouse'
        ])->get();

        $user = User::first();
        $warehouse = Warehouse::first();

        if ($manufacturingOrders->isEmpty()) {
            $this->command->warn('No manufacturing orders found. Please run ManufacturingOrderSeeder first.');
            return;
        }

        $issueCounter = 1;

        foreach ($manufacturingOrders as $mo) {
            if ($mo->status === 'in_progress') {
                $this->createMaterialIssueForOrder($mo, $issueCounter, $user, $warehouse);
                $issueCounter++;
            }
        }

        $this->command->info('Material issues created successfully!');
    }

    private function createMaterialIssueForOrder($mo, &$issueCounter, $user, $warehouse): void
    {
        $plan = $mo->productionPlan;

        if (!$plan || !$plan->billOfMaterial || $plan->billOfMaterial->items->isEmpty()) {
            $this->command->warn("No BOM items found for MO: {$mo->mo_number}");
            return;
        }

        // Create material issue
        $materialIssue = MaterialIssue::create([
            'issue_number' => 'MI-' . str_pad($issueCounter, 4, '0', STR_PAD_LEFT),
            'production_plan_id' => $plan->id,
            'manufacturing_order_id' => $mo->id,
            'warehouse_id' => $plan->warehouse_id ?? $warehouse->id,
            'issue_date' => Carbon::now(),
            'type' => 'issue',
            'status' => 'draft',
            'notes' => "Material issue for {$mo->mo_number}",
            'created_by' => $user->id,
        ]);

        $totalCost = 0;

        // Create material issue items
        foreach ($plan->billOfMaterial->items as $bomItem) {
            $requiredQuantity = $bomItem->quantity * $plan->quantity;

            // Get cost from inventory stock or use default
            $unitCost = $bomItem->product->inventoryStock->first()?->average_cost ?? 100000;
            $itemTotalCost = $requiredQuantity * $unitCost;

            MaterialIssueItem::create([
                'material_issue_id' => $materialIssue->id,
                'product_id' => $bomItem->product_id,
                'uom_id' => $bomItem->product->uom_id ?? 1,
                'warehouse_id' => $plan->warehouse_id ?? $warehouse->id,
                'quantity' => $requiredQuantity,
                'cost_per_unit' => $unitCost,
                'total_cost' => $itemTotalCost,
                'notes' => "Required for {$mo->mo_number}",
            ]);

            $totalCost += $itemTotalCost;
        }

        // Update total cost
        $materialIssue->update(['total_cost' => $totalCost]);

        app(ManufacturingService::class)->createWarehouseConfirmationForMaterialIssue($materialIssue, [
            'status' => 'request',
            'confirmed_by' => null,
            'confirmed_at' => null,
        ]);

        $warehouseConfirmations = $materialIssue->warehouseConfirmations()
            ->with('warehouseConfirmationItems')
            ->get();

        $warehouseConfirmations->each(function ($warehouseConfirmation) use ($user) {
            $warehouseConfirmation->warehouseConfirmationItems->each(function ($warehouseConfirmationItem) use ($user) {
                $warehouseConfirmationItem->update([
                    'status' => 'confirmed',
                    'confirmed_qty' => $warehouseConfirmationItem->requested_qty,
                ]);
            });

            $warehouseConfirmation->forceFill([
                'confirmed_by' => $user->id,
                'confirmed_at' => Carbon::now(),
            ])->save();
        });

        $materialIssue = $materialIssue->fresh();

        $materialIssue->update([
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
        ]);

        $materialIssue->update([
            'status' => 'completed',
            'approved_by' => $user->id,
            'approved_at' => Carbon::now(),
        ]);

        $this->command->info("Created material issue: {$materialIssue->issue_number} for MO: {$mo->mo_number}");
    }
}