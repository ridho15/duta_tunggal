<?php

namespace Database\Seeders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\SaleOrder;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

class StockMovementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Map movement types to source model classes
        $sourceMap = [
            'purchase_in' => [PurchaseOrder::class, 'po_number'],
            'purchase_receipt_in' => [PurchaseReceipt::class, 'receipt_number'],
            'sales' => [SaleOrder::class, 'so_number'],
            'adjustment_in' => [StockAdjustment::class, 'adjustment_number'],
            'adjustment_out' => [StockAdjustment::class, 'adjustment_number'],
        ];

        $created = 0;
        $attempts = 0;
        $maxAttempts = 150;

        while ($created < 50 && $attempts < $maxAttempts) {
            $attempts++;

            try {
                $product = \App\Models\Product::inRandomOrder()->first();
                $warehouse = \App\Models\Warehouse::inRandomOrder()->first();

                if (!$product || !$warehouse) {
                    continue;
                }

                $type = collect([
                    'purchase_in',
                    'sales',
                    'transfer_in',
                    'transfer_out',
                    'manufacture_in',
                    'manufacture_out',
                    'adjustment_in',
                    'adjustment_out',
                ])->random();

                $value = fake()->randomFloat(2, 100, 1_000);

                $fromModelType = null;
                $fromModelId = null;
                $referenceId = fake()->word();

                // Attempt to link to a real source record
                if (isset($sourceMap[$type])) {
                    [$modelClass, $numberField] = $sourceMap[$type];
                    $sourceRecord = $modelClass::inRandomOrder()->first();
                    if ($sourceRecord) {
                        $fromModelType = $modelClass;
                        $fromModelId = $sourceRecord->id;
                        $referenceId = $sourceRecord->{$numberField} ?? $referenceId;
                    }
                }

                StockMovement::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                    'quantity' => fake()->numberBetween(10, 100),
                    'value' => $value,
                    'type' => $type,
                    'reference_id' => $referenceId,
                    'date' => fake()->dateTimeBetween('-1 year', 'now'),
                    'from_model_type' => $fromModelType,
                    'from_model_id' => $fromModelId,
                    'meta' => ['faker' => true],
                ]);

                $created++;
            } catch (\Exception $e) {
                continue;
            }
        }

        $this->command->info("Created {$created} stock movement records after {$attempts} attempts.");
    }
}

