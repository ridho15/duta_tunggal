<?php

namespace Database\Seeders;

use App\Models\DeliveryOrder;
use App\Models\ProductCategory;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call([
            CurrencySeeder::class,
            UnitOfMeasureSeeder::class,
            ProductCategorySeeder::class,
            CustomerSeeder::class,
            SupplierSeeder::class,
            DriverSeeder::class,
            VehicleSeeder::class,
            ProductSeeder::class,
            WarehouseSeeder::class,
            RakSeeder::class,
            DeliveryOrderSeeder::class,
        ]);
    }
}
