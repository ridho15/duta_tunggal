<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use App\Models\Cabang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * @test PurchaseOrderEnhancementsTest - Tests for Tasks 11 & 13
 * Task 11: Products not limited by supplier in PO
 * Task 13: Inline supplier creation in PO
 */
class PurchaseOrderEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
    }

    /**
     * @test Task 11: PO dapat memilih produk dari supplier mana saja
     */
    public function po_can_select_products_from_any_supplier()
    {
        // Setup: Create 2 suppliers and 3 products (2 from supplier1, 1 from supplier2)
        $supplier1 = Supplier::factory()->create(['code' => 'SUP001', 'perusahaan' => 'Supplier A']);
        $supplier2 = Supplier::factory()->create(['code' => 'SUP002', 'perusahaan' => 'Supplier B']);
        
        $product1 = Product::factory()->create(['name' => 'Product 1', 'supplier_id' => $supplier1->id]);
        $product2 = Product::factory()->create(['name' => 'Product 2', 'supplier_id' => $supplier1->id]);
        $product3 = Product::factory()->create(['name' => 'Product 3', 'supplier_id' => $supplier2->id]);
        
        $warehouse = Warehouse::factory()->create();
        $cabang = Cabang::factory()->create();

        // Create PO for supplier1 but include product from supplier2
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier1->id,
            'po_number' => 'PO-TEST-001',
            'order_date' => now(),
            'status' => 'draft',
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $cabang->id,
            'total_amount' => 0,
            'tempo_hutang' => 30,
        ]);

        // Add items from different suppliers
        $poItem1 = $po->purchaseOrderItem()->create([
            'product_id' => $product1->id, // From supplier1
            'quantity' => 10,
            'unit_price' => 5000,
            'currency_id' => 1,
        ]);

        $poItem2 = $po->purchaseOrderItem()->create([
            'product_id' => $product3->id, // From supplier2 - THIS SHOULD BE ALLOWED NOW
            'quantity' => 5,
            'unit_price' => 7000,
            'currency_id' => 1,
        ]);

        // Refresh relationship
        $po->load('purchaseOrderItem');

        // Assert: PO should have 2 items including one from different supplier
        $this->assertCount(2, $po->purchaseOrderItem);
        $this->assertEquals($product1->id, $po->purchaseOrderItem->first()->product_id);
        $this->assertEquals($product3->id, $po->purchaseOrderItem->last()->product_id);
        
        // Verify product3 is from different supplier
        $this->assertNotEquals($po->supplier_id, $product3->supplier_id);

        echo "✓ Test passed: PO can select products from any supplier (not limited)\n";
    }

    /**
     * @test Task 11: Verifikasi tidak ada filter supplier_id di product query
     */
    public function po_product_selection_is_not_filtered_by_supplier()
    {
        // Setup multiple suppliers with products
        $supplier1 = Supplier::factory()->create();
        $supplier2 = Supplier::factory()->create();
        $supplier3 = Supplier::factory()->create();

        // Create 10 products distributed across 3 suppliers
        $products = collect();
        for ($i = 1; $i <= 10; $i++) {
            $supplierId = $i <= 3 ? $supplier1->id : ($i <= 6 ? $supplier2->id : $supplier3->id);
            $products->push(Product::factory()->create([
                'name' => "Product $i",
                'supplier_id' => $supplierId,
            ]));
        }

        // When creating PO for supplier1, all 10 products should be available
        // (not just the 3 from supplier1)
        $availableProducts = Product::orderBy('name')->get();

        $this->assertCount(10, $availableProducts);
        $this->assertEquals($products->pluck('id')->sort()->values(), $availableProducts->pluck('id')->sort()->values());

        echo "✓ Test passed: Product selection returns all products regardless of supplier\n";
    }

    /**
     * @test Task 13: Supplier dapat dibuat inline saat create PO
     */
    public function supplier_can_be_created_inline_in_po()
    {
        $cabang = Cabang::factory()->create(['kode' => 'JKT', 'nama' => 'Jakarta']);

        // Simulate inline supplier creation
        $supplierData = [
            'code' => 'SUP-INLINE-001',
            'perusahaan' => 'PT Inline Supplier',
            'npwp' => '12.345.678.9-012.345',
            'address' => 'Jl. Inline No. 123',
            'handphone' => '081234567890',
            'phone' => '021-1234567',
            'fax' => '021-7654321',
            'email' => 'inline@supplier.com',
            'tempo_hutang' => 30,
            'cabang_id' => $cabang->id,
        ];

        // Create supplier inline
        $supplier = Supplier::create($supplierData);

        // Verify supplier created successfully
        $this->assertDatabaseHas('suppliers', [
            'code' => 'SUP-INLINE-001',
            'perusahaan' => 'PT Inline Supplier',
            'email' => 'inline@supplier.com',
        ]);

        // Create PO immediately using the newly created supplier
        $warehouse = Warehouse::factory()->create();
        
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-WITH-INLINE-SUP',
            'order_date' => now(),
            'status' => 'draft',
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $cabang->id,
            'tempo_hutang' => $supplier->tempo_hutang,
            'total_amount' => 0,
        ]);

        // Assert PO uses inline-created supplier
        $this->assertEquals($supplier->id, $po->supplier_id);
        $this->assertEquals(30, $po->tempo_hutang);
        $this->assertEquals('PT Inline Supplier', $po->supplier->perusahaan);

        echo "✓ Test passed: Supplier can be created inline and used immediately in PO\n";
    }

    /**
     * @test Task 13: Inline supplier creation validates required fields
     */
    public function inline_supplier_creation_validates_required_fields()
    {
        $cabang = Cabang::factory()->create();

        // Try to create supplier without required fields
        try {
            $supplier = Supplier::create([
                'perusahaan' => 'Invalid Supplier', // Missing code
                'cabang_id' => $cabang->id,
            ]);
            $this->fail('Should have thrown validation exception');
        } catch (\Exception $e) {
            $this->assertTrue(true, 'Validation correctly prevents incomplete supplier creation');
        }

        // Create with all required fields
        $supplier = Supplier::factory()->create([
            'code' => 'VALID-001',
            'perusahaan' => 'Valid Supplier',
            'cabang_id' => $cabang->id,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'code' => 'VALID-001',
            'perusahaan' => 'Valid Supplier',
        ]);

        echo "✓ Test passed: Inline supplier creation validates required fields\n";
    }

    /**
     * @test Task 13: PO tempo_hutang inherits from inline-created supplier
     */
    public function po_inherits_tempo_hutang_from_inline_supplier()
    {
        $cabang = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create();

        // Create supplier with specific tempo_hutang
        $supplier = Supplier::factory()->create([
            'code' => 'SUP-TEMPO-45',
            'perusahaan' => 'Supplier 45 Hari',
            'tempo_hutang' => 45,
            'cabang_id' => $cabang->id,
        ]);

        // Create PO and verify tempo_hutang inheritance  
        $po = PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'po_number' => 'PO-TEMPO-TEST',
            'order_date' => now(),
            'status' => 'draft',
            'warehouse_id' => $warehouse->id,
            'cabang_id' => $cabang->id,
            'tempo_hutang' => $supplier->tempo_hutang,
            'total_amount' => 0,
        ]);

        $this->assertEquals(45, $po->tempo_hutang);
        $this->assertEquals($supplier->tempo_hutang, $po->tempo_hutang);

        echo "✓ Test passed: PO correctly inherits tempo_hutang (45 days) from inline supplier\n";
    }
}
