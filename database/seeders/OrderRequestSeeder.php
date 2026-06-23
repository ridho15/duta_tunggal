<?php

namespace Database\Seeders;

use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orderRequests = OrderRequest::factory()->count(20)->create();

        $orderRequests->each(function (OrderRequest $orderRequest) {
            $itemCount = random_int(2, 4);

            for ($index = 0; $index < $itemCount; $index++) {
                $product = Product::query()->inRandomOrder()->first();
                $supplierId = $product
                    ? $product->suppliers()->inRandomOrder()->value('suppliers.id') ?? $product->supplier_id
                    : Supplier::query()->inRandomOrder()->value('id');

                OrderRequestItem::factory()->create([
                    'order_request_id' => $orderRequest->id,
                    'product_id' => $product?->id,
                    'supplier_id' => $supplierId,
                    'cabang_id' => $product?->cabang_id,
                    'currency_id' => $orderRequest->currency_id,
                ]);
            }
        });
    }
}
