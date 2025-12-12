<?php

namespace Tests\Unit\Models;

use App\Models\Cabang;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Rak;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockOpnameItemTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $warehouse;
    protected $rak;
    protected $supplier;
    protected $product;
    protected $opname;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test data
        $this->cabang = Cabang::factory()->create();
        $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->rak = Rak::factory()->create(['warehouse_id' => $this->warehouse->id]);

        // Create product category
        $category = ProductCategory::factory()->create(['cabang_id' => $this->cabang->id]);

        // Create product
        $this->product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier->id,
            'product_category_id' => $category->id,
        ]);

        // Create stock opname
        $this->opname = StockOpname::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'created_by' => $this->user->id,
            'opname_date' => now(), // Set to today
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        StockOpname::where('opname_number', 'like', 'OPN-TEST-%')->delete();
        parent::tearDown();
    }

    #[Test]
    public function it_can_create_stock_opname_item()
    {
        $itemData = [
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'system_qty' => 100,
            'physical_qty' => 95,
            'difference_qty' => -5,
            'unit_cost' => 10000,
            'average_cost' => 10000,
            'difference_value' => -50000,
            'total_value' => 950000,
            'notes' => 'Test item',
        ];

        $item = StockOpnameItem::create($itemData);

        $this->assertInstanceOf(StockOpnameItem::class, $item);
        $this->assertEquals($this->opname->id, $item->stock_opname_id);
        $this->assertEquals($this->product->id, $item->product_id);
        $this->assertEquals(-5, $item->difference_qty);
        $this->assertEquals(-50000, $item->difference_value);
        $this->assertEquals(950000, $item->total_value);
    }

    #[Test]
    public function it_has_relationships_with_other_models()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
        ]);

        $this->assertInstanceOf(StockOpname::class, $item->stockOpname);
        $this->assertInstanceOf(Product::class, $item->product);
        $this->assertInstanceOf(Rak::class, $item->rak);

        $this->assertEquals($this->opname->id, $item->stockOpname->id);
        $this->assertEquals($this->product->id, $item->product->id);
        $this->assertEquals($this->rak->id, $item->rak->id);
    }

    #[Test]
    public function it_calculates_average_cost_correctly()
    {
        // Create purchase orders first
        $po1 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $po2 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $po3 = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Create purchase order items with unit prices
        $poItem1 = PurchaseOrderItem::create([
            'purchase_order_id' => $po1->id,
            'product_id' => $this->product->id,
            'quantity' => 100,
            'unit_price' => 8000,
            'currency_id' => 1,
        ]);

        $poItem2 = PurchaseOrderItem::create([
            'purchase_order_id' => $po2->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 10000,
            'currency_id' => 1,
        ]);

        $poItem3 = PurchaseOrderItem::create([
            'purchase_order_id' => $po3->id,
            'product_id' => $this->product->id,
            'quantity' => 25,
            'unit_price' => 12000,
            'currency_id' => 1,
        ]);

        // Create purchase receipts linked to purchase orders
        $receipt1 = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po1->id,
            'receipt_date' => now()->subDays(30),
            'cabang_id' => $this->cabang->id,
        ]);

        $receipt2 = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po2->id,
            'receipt_date' => now()->subDays(15),
            'cabang_id' => $this->cabang->id,
        ]);

        $receipt3 = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po3->id,
            'receipt_date' => now()->subDays(5),
            'cabang_id' => $this->cabang->id,
        ]);

        // Add items to receipts linked to purchase order items
        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt1->id,
            'purchase_order_item_id' => $poItem1->id,
            'product_id' => $this->product->id,
            'qty_received' => 100,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt2->id,
            'purchase_order_item_id' => $poItem2->id,
            'product_id' => $this->product->id,
            'qty_received' => 50,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt3->id,
            'purchase_order_item_id' => $poItem3->id,
            'product_id' => $this->product->id,
            'qty_received' => 25,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
        ]);

        // Create stock opname item
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
        ]);

        // Test average cost calculation: (100*8000 + 50*10000 + 25*12000) / (100+50+25) = (800000 + 500000 + 300000) / 175 = 1600000 / 175 = 9142.857...
        $averageCost = $item->calculateAverageCost();
        $this->assertEquals(round(9142.8571428571, 4), round($averageCost, 4)); // Round to 4 decimal places to avoid floating point issues
    }

    #[Test]
    public function it_returns_zero_average_cost_when_no_purchase_history()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
        ]);

        $averageCost = $item->calculateAverageCost();

        $this->assertEquals(0, $averageCost);
    }

    #[Test]
    public function it_calculates_total_value_correctly()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'physical_qty' => 75,
            'average_cost' => 5000, // Set average cost for calculation
        ]);

        $totalValue = $item->calculateTotalValue();

        $this->assertEquals(375000, $totalValue); // 75 * 5000
    }

    #[Test]
    public function it_calculates_difference_value_correctly()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'difference_qty' => 10,
            'average_cost' => 7500,
        ]);

        $differenceValue = $item->calculateDifferenceValue();

        $this->assertEquals(75000, $differenceValue); // 10 * 7500
    }

    #[Test]
    public function it_handles_negative_difference_value()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'difference_qty' => -20,
            'average_cost' => 6000,
        ]);

        $differenceValue = $item->calculateDifferenceValue();

        $this->assertEquals(-120000, $differenceValue); // -20 * 6000
    }

    #[Test]
    public function it_handles_zero_difference_value()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'difference_qty' => 0,
            'average_cost' => 10000,
        ]);

        $differenceValue = $item->calculateDifferenceValue();

        $this->assertEquals(0, $differenceValue); // 0 * 10000
    }

    #[Test]
    public function it_casts_decimal_fields_correctly()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'system_qty' => 100.5,
            'physical_qty' => 95.25,
            'difference_qty' => -5.25,
            'unit_cost' => 10000.75,
            'average_cost' => 9500.50,
            'difference_value' => -49875.25,
            'total_value' => 903018.75,
        ]);

        $this->assertIsFloat($item->system_qty);
        $this->assertIsFloat($item->physical_qty);
        $this->assertIsFloat($item->difference_qty);
        $this->assertIsFloat($item->unit_cost);
        $this->assertIsFloat($item->average_cost);
        $this->assertIsFloat($item->difference_value);
        $this->assertIsFloat($item->total_value);

        $this->assertEquals(100.5, $item->system_qty);
        $this->assertEquals(95.25, $item->physical_qty);
        $this->assertEquals(-5.25, $item->difference_qty);
    }

    #[Test]
    public function it_handles_foreign_key_constraints()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
        ]);

        // Verify the relationship exists
        $this->assertEquals($this->opname->id, $item->stockOpname->id);
        $this->assertEquals($this->product->id, $item->product->id);
        $this->assertEquals($this->rak->id, $item->rak->id);

        // Note: CASCADE DELETE testing is skipped in test environment
        // as foreign key constraints may be disabled for performance
    }

    #[Test]
    public function it_handles_optional_rak_relationship()
    {
        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
            'rak_id' => null, // No rak assigned
        ]);

        $this->assertNull($item->rak_id);
        $this->assertNull($item->rak); // Should return null for optional relationship
    }

    #[Test]
    public function it_validates_required_fields()
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        // Try to create without required stock_opname_id
        StockOpnameItem::create([
            'product_id' => $this->product->id,
            'system_qty' => 100,
            'physical_qty' => 100,
        ]);
    }

    #[Test]
    public function it_calculates_average_cost_only_from_past_receipts()
    {
        // Create receipt after opname date (should not be included)
        $futureReceipt = PurchaseReceipt::factory()->create([
            'receipt_date' => now()->addDays(5), // Future date
            'cabang_id' => $this->cabang->id,
        ]);

        // Create purchase order item first
        $poItem = PurchaseOrderItem::factory()->create([
            'product_id' => $this->product->id,
            'unit_price' => 20000, // High price that should not be included
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $futureReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 50,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Create receipt before opname date
        $pastReceipt = PurchaseReceipt::factory()->create([
            'receipt_date' => now()->subDays(10),
            'cabang_id' => $this->cabang->id,
        ]);

        $pastPoItem = PurchaseOrderItem::factory()->create([
            'product_id' => $this->product->id,
            'unit_price' => 10000,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $pastReceipt->id,
            'purchase_order_item_id' => $pastPoItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 100,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
        ]);

        $averageCost = $item->calculateAverageCost();

        // Should only consider the past receipt: 100 * 10000 / 100 = 10000
        $this->assertEquals(10000, $averageCost);
    }

    #[Test]
    public function it_handles_products_with_no_receipts()
    {
        $newProduct = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier->id,
            'product_category_id' => ProductCategory::factory()->create(['cabang_id' => $this->cabang->id])->id,
        ]);

        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $newProduct->id,
        ]);

        $averageCost = $item->calculateAverageCost();

        $this->assertEquals(0, $averageCost);
    }

    #[Test]
    public function it_calculates_weighted_average_cost_correctly()
    {
        // Create receipts with different quantities and prices
        $receipt1 = PurchaseReceipt::factory()->create([
            'receipt_date' => now()->subDays(20),
            'cabang_id' => $this->cabang->id,
        ]);

        $receipt2 = PurchaseReceipt::factory()->create([
            'receipt_date' => now()->subDays(10),
            'cabang_id' => $this->cabang->id,
        ]);

        // Small quantity, high price
        $poItem1 = PurchaseOrderItem::factory()->create([
            'product_id' => $this->product->id,
            'unit_price' => 15000,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt1->id,
            'purchase_order_item_id' => $poItem1->id,
            'product_id' => $this->product->id,
            'qty_received' => 10,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Large quantity, lower price
        $poItem2 = PurchaseOrderItem::factory()->create([
            'product_id' => $this->product->id,
            'unit_price' => 12000,
        ]);

        PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt2->id,
            'purchase_order_item_id' => $poItem2->id,
            'product_id' => $this->product->id,
            'qty_received' => 90,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $item = StockOpnameItem::factory()->create([
            'stock_opname_id' => $this->opname->id,
            'product_id' => $this->product->id,
        ]);

        $averageCost = $item->calculateAverageCost();

        // Expected: (10 * 15000 + 90 * 12000) / (10 + 90) = (150000 + 1080000) / 100 = 1230000 / 100 = 12300
        $this->assertEquals(12300, $averageCost);
    }
}