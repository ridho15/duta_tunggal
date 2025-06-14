<?php

namespace Database\Seeders;

use App\Models\DeliveryOrderItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryOrderItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DeliveryOrderItem::factory()->count(100)->create();
    }
}
