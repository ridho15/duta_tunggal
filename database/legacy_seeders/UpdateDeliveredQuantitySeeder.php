<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UpdateDeliveredQuantitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SaleOrderItem::with('deliveryOrderItems.deliveryOrder')->chunk(100, function ($saleOrderItems) {
            foreach ($saleOrderItems as $saleOrderItem) {
                // Hitung total delivered quantity dari semua delivery orders yang sudah sent/completed
                $totalDelivered = $saleOrderItem->deliveryOrderItems()
                    ->whereHas('deliveryOrder', function ($query) {
                        $query->whereIn('status', ['sent', 'received', 'completed']);
                    })
                    ->sum('quantity');

                // Update delivered_quantity
                $saleOrderItem->update([
                    'delivered_quantity' => $totalDelivered
                ]);

                $this->command->info("Updated SaleOrderItem ID {$saleOrderItem->id}: delivered_quantity = {$totalDelivered}");
            }
        });

        $this->command->info('Delivered quantity update completed for all sale order items.');
    }
}
