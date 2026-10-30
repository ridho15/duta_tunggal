<?php

namespace Database\Seeders;

use App\Models\DeliverySalesOrder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliverySalesOrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DeliverySalesOrder::factory()->count(100)->create();
    }
}
