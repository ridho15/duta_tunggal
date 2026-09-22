<?php

namespace Database\Seeders;

use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderRequestItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $orderRequests = OrderRequest::query()->latest('id')->get();

        if ($orderRequests->isEmpty()) {
            $orderRequests = OrderRequest::factory()->count(20)->create();
        }

        $orderRequests->each(function (OrderRequest $orderRequest) {
            $count = random_int(2, 5);
            for ($i = 0; $i < $count; $i++) {
                $product = Product::query()->inRandomOrder()->first();

                $supplierId = $product
                    ? $product->suppliers()->inRandomOrder()->value('suppliers.id') ?? $product->supplier_id
                    : Supplier::query()->inRandomOrder()->value('id');

                $currencyId = $orderRequest->currency_id
                    ?? Currency::where('code', 'IDR')->value('id')
                    ?? Currency::query()->inRandomOrder()->value('id');

                OrderRequestItem::factory()->create([
                    'order_request_id' => $orderRequest->id,
                    'product_id' => $product?->id,
                    'supplier_id' => $supplierId,
                    'cabang_id' => $product?->cabang_id,
                    'currency_id' => $currencyId,
                ]);
            }
        });
    }
}
