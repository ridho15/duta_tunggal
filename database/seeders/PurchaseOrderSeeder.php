<?php

namespace Database\Seeders;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PurchaseOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PurchaseOrder::factory()
            ->count(10)
            ->create()
            ->each(function ($po) {
                PurchaseOrderItem::factory()
                    ->count(rand(2, 5))
                    ->create([
                        'purchase_order_id' => $po->id
                    ]);
            });
    }
}
