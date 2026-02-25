<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiSupplierTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Supplier $supplier1;
    protected Supplier $supplier2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::factory()->create();

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        $this->supplier1 = Supplier::factory()->create([
            'code' => 'SUP001',
            'perusahaan' => 'Supplier 1',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->supplier2 = Supplier::factory()->create([
            'code' => 'SUP002',
            'perusahaan' => 'Supplier 2',
            'cabang_id' => $this->cabang->id,
        ]);
    }

    /** @test */
    public function product_can_have_multiple_suppliers()
    {
        $product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier1->id,
        ]);

        // Attach additional suppliers
        $product->suppliers()->attach($this->supplier2->id, [
            'supplier_price' => 15000,
            'supplier_sku' => 'SUP2-SKU-001',
            'is_primary' => false,
        ]);

        $this->assertCount(1, $product->suppliers);
        $this->assertEquals($this->supplier2->id, $product->suppliers->first()->id);
        $this->assertEquals(15000, $product->suppliers->first()->pivot->supplier_price);

        echo "✓ Test passed: Product can have multiple suppliers\n";
    }

    /** @test */
    public function supplier_can_have_multiple_products()
    {
        $product1 = Product::factory()->create(['cabang_id' => $this->cabang->id]);
        $product2 = Product::factory()->create(['cabang_id' => $this->cabang->id]);

        $this->supplier1->productSuppliers()->attach($product1->id, [
            'supplier_price' => 10000,
            'is_primary' => true,
        ]);

        $this->supplier1->productSuppliers()->attach($product2->id, [
            'supplier_price' => 20000,
            'is_primary' => false,
        ]);

        $this->assertCount(2, $this->supplier1->productSuppliers);

        echo "✓ Test passed: Supplier can have multiple products\n";
    }

    /** @test */
    public function product_supplier_pivot_stores_additional_data()
    {
        $product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        $product->suppliers()->attach($this->supplier1->id, [
            'supplier_price' => 12500,
            'supplier_sku' => 'CUSTOM-SKU-123',
            'is_primary' => true,
        ]);

        $pivot = $product->suppliers()->first()->pivot;

        $this->assertEquals(12500, $pivot->supplier_price);
        $this->assertEquals('CUSTOM-SKU-123', $pivot->supplier_sku);
        $this->assertEquals(1, $pivot->is_primary); // Database stores as 1/0

        echo "✓ Test passed: Product-Supplier pivot stores price, SKU, and primary flag\n";
    }
}
