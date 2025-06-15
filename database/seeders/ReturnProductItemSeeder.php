<?php

namespace Database\Seeders;

use App\Models\ReturnProductItem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReturnProductItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ReturnProductItem::factory()->count(100)->create();
    }
}
