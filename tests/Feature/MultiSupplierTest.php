<?php

namespace Tests\Feature;

use App\Helpers\MoneyHelper;
use App\Models\User;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function product_can_have_multiple_suppliers()
    {
        $product = Product::factory()->forCabang($this->cabang)->create([
            'supplier_id' => $this->supplier1->id,
        ]);

        // Attach additional suppliers
        $product->suppliers()->attach($this->supplier2->id, [
            'supplier_price' => 15000,
        ]);

        $product->load('suppliers');

        $this->assertCount(1, $product->suppliers);
        $this->assertEquals($this->supplier2->id, $product->suppliers->first()->id);
        $this->assertEquals(15000, $product->suppliers->first()->pivot->supplier_price);

        echo "✓ Test passed: Product can have multiple suppliers\n";
    }

    #[Test]
    public function supplier_can_have_multiple_products()
    {
        $product1 = Product::factory()->forCabang($this->cabang)->create();
        $product2 = Product::factory()->forCabang($this->cabang)->create();

        $this->supplier1->productSuppliers()->attach($product1->id, [
            'supplier_price' => 10000,
        ]);

        $this->supplier1->productSuppliers()->attach($product2->id, [
            'supplier_price' => 20000,
        ]);

        $this->supplier1->load('productSuppliers');

        $this->assertCount(2, $this->supplier1->productSuppliers);

        echo "✓ Test passed: Supplier can have multiple products\n";
    }

    #[Test]
    public function product_supplier_pivot_stores_additional_data()
    {
        $product = Product::factory()->forCabang($this->cabang)->create();

        $product->suppliers()->attach($this->supplier1->id, [
            'supplier_price' => 12500,
        ]);

        $pivot = $product->suppliers()->first()->pivot;

        $this->assertEquals(12500, $pivot->supplier_price);

        echo "✓ Test passed: Product-Supplier pivot stores supplier price\n";
    }

    #[Test]
    public function product_supplier_price_keeps_two_decimal_money_format()
    {
        $product = Product::factory()->forCabang($this->cabang)->create();

        $product->suppliers()->attach($this->supplier1->id, [
            'supplier_price' => MoneyHelper::safeParse('92.550,52'),
        ]);

        $pivot = $product->suppliers()->first()->pivot;

        $this->assertSame(92550.52, (float) $pivot->supplier_price);
        $this->assertSame('Rp 92.550,52', MoneyHelper::rupiah($pivot->supplier_price));
    }
}
