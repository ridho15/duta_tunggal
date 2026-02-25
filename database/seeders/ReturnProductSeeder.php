<?php

namespace Database\Seeders;

use App\Models\ReturnProduct;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReturnProductSeeder extends Seeder
{
    public function run(): void
    {
        ReturnProduct::factory()->count(20)->create();
    }
}
