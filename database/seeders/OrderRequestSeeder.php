<?php

namespace Database\Seeders;

use App\Models\OrderRequest;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        OrderRequest::factory()->count(20)->create();
    }
}
