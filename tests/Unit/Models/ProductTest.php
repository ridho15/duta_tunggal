<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use Tests\TestCase;

class ProductTest extends TestCase
{
    public function test_product_can_update_harga()
    {
        // Create product without factory to avoid dependencies
        $product = Product::create([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'product_category_id' => 1, // Assume exists
            'uom_id' => 1, // Assume exists
            'kode_merk' => 'TEST',
            'cost_price' => 10000,
            'sell_price' => 15000,
            'biaya' => 2000,
        ]);

        $product->update([
            'cost_price' => 12000,
            'sell_price' => 18000,
            'biaya' => 2500,
        ]);

        $product->refresh();

        $this->assertEquals(12000, $product->cost_price);
        $this->assertEquals(18000, $product->sell_price);
        $this->assertEquals(2500, $product->biaya);

        // Cleanup
        $product->delete();
    }

    public function test_product_bulk_update_harga()
    {
        // Create products
        $product1 = Product::create([
            'sku' => 'TEST-002',
            'name' => 'Test Product 2',
            'product_category_id' => 1,
            'uom_id' => 1,
            'kode_merk' => 'TEST',
            'cost_price' => 10000,
        ]);

        $product2 = Product::create([
            'sku' => 'TEST-003',
            'name' => 'Test Product 3',
            'product_category_id' => 1,
            'uom_id' => 1,
            'kode_merk' => 'TEST',
            'cost_price' => 10000,
        ]);

        Product::whereIn('id', [$product1->id, $product2->id])->update([
            'cost_price' => 15000,
        ]);

        $product1->refresh();
        $product2->refresh();

        $this->assertEquals(15000, $product1->cost_price);
        $this->assertEquals(15000, $product2->cost_price);

        // Cleanup
        $product1->delete();
        $product2->delete();
    }
}