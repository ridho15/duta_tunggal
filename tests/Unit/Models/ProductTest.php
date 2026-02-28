<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use App\Models\Supplier;
use Tests\TestCase;

class ProductTest extends TestCase
{
    public function test_product_can_update_harga()
    {
        \DB::beginTransaction();

        try {
            // Create product without factory to avoid dependencies
            $product = Product::create([
                'sku' => 'TEST-' . time() . '-001',
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

            \DB::rollBack();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public function test_product_bulk_update_harga()
    {
        \DB::beginTransaction();

        try {
            // Create products
            $product1 = Product::create([
                'sku' => 'TEST-' . time() . '-002',
                'name' => 'Test Product 2',
                'product_category_id' => 1,
                'uom_id' => 1,
                'kode_merk' => 'TEST',
                'cost_price' => 10000,
            ]);

            $product2 = Product::create([
                'sku' => 'TEST-' . time() . '-003',
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

            \DB::rollBack();
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public function test_product_supplier_relationship()
    {
        // Use database transaction for cleanup
        \DB::beginTransaction();

        try {
            // Create test supplier
            $supplier = Supplier::create([
                'code' => 'TEST-SUP-001',
                'perusahaan' => 'Test Supplier',
                'address' => 'Test Address',
                'phone' => '021-1234567',
                'handphone' => '08123456789',
                'fax' => '021-1234567',
                'email' => 'test@example.com',
                'npwp' => '12.345.678.9-001.000',
                'tempo_hutang' => 30,
                'kontak_person' => 'Test Person',
                'keterangan' => 'Test supplier for unit test'
            ]);

            // Create product with unique SKU
            $product = Product::create([
                'sku' => 'TEST-SUP-' . time() . '-001',
                'name' => 'Test Product with Suppliers',
                'product_category_id' => 1,
                'uom_id' => 1,
                'kode_merk' => 'TEST',
                'cost_price' => 10000,
                'sell_price' => 15000,
                'biaya' => 2000,
            ]);

            // Attach supplier with price
            $product->suppliers()->attach($supplier->id, [
                'supplier_price' => 12000
            ]);

            $product->refresh();

            // Test relationship
            $this->assertCount(1, $product->suppliers);
            $this->assertEquals($supplier->id, $product->suppliers->first()->id);
            $this->assertEquals(12000, $product->suppliers->first()->pivot->supplier_price);

            // Test that supplier_sku is not in pivot (should not exist)
            $this->assertFalse(isset($product->suppliers->first()->pivot->supplier_sku));

            \DB::rollBack(); // Rollback all changes
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }

    public function test_product_multiple_suppliers_with_different_prices()
    {
        // Use database transaction for cleanup
        \DB::beginTransaction();

        try {
            // Create test suppliers
            $supplier1 = Supplier::create([
                'code' => 'TEST-SUP-002',
                'perusahaan' => 'Test Supplier 1',
                'address' => 'Test Address 1',
                'phone' => '021-1234567',
                'handphone' => '08123456789',
                'fax' => '021-1234567',
                'email' => 'test1@example.com',
                'npwp' => '12.345.678.9-002.000',
                'tempo_hutang' => 30,
                'kontak_person' => 'Test Person 1',
                'keterangan' => 'Test supplier 1 for unit test'
            ]);

            $supplier2 = Supplier::create([
                'code' => 'TEST-SUP-003',
                'perusahaan' => 'Test Supplier 2',
                'address' => 'Test Address 2',
                'phone' => '021-9876543',
                'handphone' => '08198765432',
                'fax' => '021-9876543',
                'email' => 'test2@example.com',
                'npwp' => '12.345.678.9-003.000',
                'tempo_hutang' => 45,
                'kontak_person' => 'Test Person 2',
                'keterangan' => 'Test supplier 2 for unit test'
            ]);

            // Create product with unique SKU
            $product = Product::create([
                'sku' => 'TEST-MULTI-SUP-' . time() . '-001',
                'name' => 'Test Product Multiple Suppliers',
                'product_category_id' => 1,
                'uom_id' => 1,
                'kode_merk' => 'TEST',
                'cost_price' => 10000,
                'sell_price' => 15000,
                'biaya' => 2000,
            ]);

            // Attach suppliers with different prices
            $product->suppliers()->attach($supplier1->id, [
                'supplier_price' => 12000
            ]);

            $product->suppliers()->attach($supplier2->id, [
                'supplier_price' => 11000
            ]);

            $product->refresh();

            // Test relationships
            $this->assertCount(2, $product->suppliers);

            // Find suppliers by price
            $supplier1FromProduct = $product->suppliers->where('id', $supplier1->id)->first();
            $this->assertNotNull($supplier1FromProduct);
            $this->assertEquals(12000, $supplier1FromProduct->pivot->supplier_price);

            $supplier2FromProduct = $product->suppliers->where('id', $supplier2->id)->first();
            $this->assertNotNull($supplier2FromProduct);
            $this->assertEquals(11000, $supplier2FromProduct->pivot->supplier_price);

            // Test that supplier_sku is not in any pivot
            foreach ($product->suppliers as $supplier) {
                $this->assertFalse(isset($supplier->pivot->supplier_sku));
            }

            \DB::rollBack(); // Rollback all changes
        } catch (\Exception $e) {
            \DB::rollBack();
            throw $e;
        }
    }
}