<?php

namespace Database\Seeders;

use App\Models\BillOfMaterial;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\ProductionPlan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProductionPlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->createProductionPlans();
        });
    }

    private function createProductionPlans(): void
    {
        $this->command->info('Creating production plans...');

        // Get existing data
        $boms = BillOfMaterial::with(['product', 'items.product'])->get();
        $warehouses = Warehouse::all();
        $user = User::first();

        if ($boms->isEmpty()) {
            $this->command->warn('No Bill of Materials found. Please run BillOfMaterialSeeder first.');
            return;
        }

        if ($warehouses->isEmpty()) {
            $this->command->warn('No warehouses found. Please run WarehouseSeeder first.');
            return;
        }

        // Get some sale orders for reference
        $saleOrders = SaleOrder::with('items.product')->take(3)->get();

        $plans = [
            [
                'plan_number' => 'PP-' . str_pad(1, 4, '0', STR_PAD_LEFT),
                'name' => 'Production Plan - Meja Kantor Premium',
                'source_type' => 'sale_order',
                'sale_order_id' => $saleOrders->first()?->id,
                'quantity' => 10,
                'start_date' => Carbon::now()->addDays(1),
                'end_date' => Carbon::now()->addDays(7),
                'status' => 'scheduled',
                'notes' => 'Rencana produksi meja kantor untuk pesanan pelanggan'
            ],
            [
                'plan_number' => 'PP-' . str_pad(2, 4, '0', STR_PAD_LEFT),
                'name' => 'Production Plan - Kursi Kantor Executive',
                'source_type' => 'sale_order',
                'sale_order_id' => $saleOrders->skip(1)->first()?->id,
                'quantity' => 5,
                'start_date' => Carbon::now()->addDays(2),
                'end_date' => Carbon::now()->addDays(10),
                'status' => 'scheduled',
                'notes' => 'Rencana produksi kursi executive untuk kantor'
            ],
            [
                'plan_number' => 'PP-' . str_pad(3, 4, '0', STR_PAD_LEFT),
                'name' => 'Production Plan - Lemari Arsip',
                'source_type' => 'manual',
                'quantity' => 8,
                'start_date' => Carbon::now()->addDays(3),
                'end_date' => Carbon::now()->addDays(12),
                'status' => 'scheduled',
                'notes' => 'Rencana produksi lemari arsip untuk stok gudang'
            ],
            [
                'plan_number' => 'PP-' . str_pad(4, 4, '0', STR_PAD_LEFT),
                'name' => 'Production Plan - Meja Meeting',
                'source_type' => 'sale_order',
                'sale_order_id' => $saleOrders->skip(2)->first()?->id,
                'quantity' => 3,
                'start_date' => Carbon::now()->addDays(4),
                'end_date' => Carbon::now()->addDays(8),
                'status' => 'in_progress',
                'notes' => 'Rencana produksi meja meeting untuk ruang rapat'
            ]
        ];

        foreach ($plans as $planData) {
            // Find appropriate BOM for this plan
            $bom = $this->findAppropriateBom($boms, $planData['name']);

            if (!$bom) {
                $this->command->warn("No appropriate BOM found for plan: {$planData['name']}");
                continue;
            }

            $planData['bill_of_material_id'] = $bom->id;
            $planData['product_id'] = $bom->product_id;
            $planData['uom_id'] = $bom->product->uom_id ?? 1;
            $planData['warehouse_id'] = $warehouses->first()->id;
            $planData['created_by'] = $user->id;

            ProductionPlan::create($planData);

            $this->command->info("Created production plan: {$planData['name']}");
        }

        $this->command->info('Production plans created successfully!');
    }

    private function findAppropriateBom($boms, $planName)
    {
        // Simple matching based on keywords in plan name
        $keywords = [
            'meja' => ['meja', 'table'],
            'kursi' => ['kursi', 'chair'],
            'lemari' => ['lemari', 'cabinet'],
        ];

        foreach ($keywords as $keyword => $aliases) {
            if (stripos($planName, $keyword) !== false) {
                foreach ($boms as $bom) {
                    $productName = strtolower($bom->product->name ?? '');
                    foreach ($aliases as $alias) {
                        if (stripos($productName, $alias) !== false) {
                            return $bom;
                        }
                    }
                }
            }
        }

        // Return first BOM if no match found
        return $boms->first();
    }
}