<?php

namespace Database\Seeders;

use App\Models\DeliveryOrder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DeliveryOrder::factory()->count(20)->create();
    }
}
