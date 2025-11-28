<?php

namespace Tests\Feature;

use App\Models\BillOfMaterial;
use App\Models\BillOfMaterialItem;
use App\Models\Cabang;
use App\Models\Product;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\BillOfMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillOfMaterialTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Models\User */
    protected $user;
    /** @var \App\Models\Cabang */
    protected $cabang;
    /** @var \App\Models\Product */
    protected $finishedProduct;
    /** @var \App\Models\Product */
    protected $componentProduct1;
    /** @var \App\Models\Product */
    protected $componentProduct2;
    /** @var \App\Models\UnitOfMeasure */
    protected $uom;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user
        $this->user = User::factory()->create();

        // Create test cabang
        $this->cabang = Cabang::factory()->create();

        // Create test UOM
        $this->uom = UnitOfMeasure::factory()->create([
            'name' => 'Pieces',
            'abbreviation' => 'PCS'
        ]);

        // Create finished product
        $this->finishedProduct = Product::factory()->create([
            'name' => 'Finished Product A',
            'cost_price' => 150.00,
            'uom_id' => $this->uom->id
        ]);

        // Create component products
        $this->componentProduct1 = Product::factory()->create([
            'name' => 'Component Part 1',
            'cost_price' => 25.00,
            'uom_id' => $this->uom->id
        ]);

        $this->componentProduct2 = Product::factory()->create([
            'name' => 'Component Part 2',
            'cost_price' => 35.00,
            'uom_id' => $this->uom->id
        ]);
    }

    /** @test */
    public function bom_creation_with_components()
    {
        // Arrange
        $bomData = [
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 10.00,
            'code' => 'BOM-TEST-001',
            'nama_bom' => 'BOM for Product A',
            'note' => 'Test BOM creation',
            'is_active' => true,
            'uom_id' => $this->uom->id,
            'labor_cost' => 5000.00,
            'overhead_cost' => 2000.00,
        ];

        $bomItems = [
            [
                'product_id' => $this->componentProduct1->id,
                'quantity' => 2.00,
                'uom_id' => $this->uom->id,
                'unit_price' => 25.00,
                'subtotal' => 50.00,
                'note' => 'Component 1 for assembly'
            ],
            [
                'product_id' => $this->componentProduct2->id,
                'quantity' => 1.00,
                'uom_id' => $this->uom->id,
                'unit_price' => 35.00,
                'subtotal' => 35.00,
                'note' => 'Component 2 for assembly'
            ]
        ];

        // Act
        $bom = BillOfMaterial::create($bomData);

        foreach ($bomItems as $itemData) {
            $bom->items()->create($itemData);
        }

        // Assert
        $this->assertDatabaseHas('bill_of_materials', [
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'nama_bom' => 'BOM for Product A',
            'labor_cost' => 5000.00,
            'overhead_cost' => 2000.00,
        ]);

        $this->assertEquals(2, $bom->items()->count());

        // Check items
        $this->assertDatabaseHas('bill_of_material_items', [
            'bill_of_material_id' => $bom->id,
            'product_id' => $this->componentProduct1->id,
            'quantity' => 2.00,
            'unit_price' => 25.00,
            'subtotal' => 50.00,
        ]);

        $this->assertDatabaseHas('bill_of_material_items', [
            'bill_of_material_id' => $bom->id,
            'product_id' => $this->componentProduct2->id,
            'quantity' => 1.00,
            'unit_price' => 35.00,
            'subtotal' => 35.00,
        ]);
    }

    /** @test */
    public function multi_level_bom_support()
    {
        // Arrange - Create a sub-assembly BOM first
        $subAssemblyBom = BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->componentProduct1->id, // This becomes a sub-assembly
            'quantity' => 5.00,
            'nama_bom' => 'Sub-assembly BOM',
            'uom_id' => $this->uom->id,
            'labor_cost' => 1000.00,
            'overhead_cost' => 500.00,
        ]);

        // Add components to sub-assembly
        $subAssemblyBom->items()->create([
            'product_id' => Product::factory()->create(['cost_price' => 10.00])->id,
            'quantity' => 3.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 10.00,
            'subtotal' => 30.00,
        ]);

        // Create main BOM that uses the sub-assembly
        $mainBom = BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 2.00,
            'nama_bom' => 'Main Product BOM',
            'uom_id' => $this->uom->id,
            'labor_cost' => 3000.00,
            'overhead_cost' => 1500.00,
        ]);

        // Add sub-assembly to main BOM
        $mainBom->items()->create([
            'product_id' => $this->componentProduct1->id, // Using the sub-assembly as component
            'quantity' => 4.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 40.00, // Cost of sub-assembly
            'subtotal' => 160.00,
        ]);

        // Act & Assert
        $this->assertEquals(1, $mainBom->items()->count());
        $this->assertEquals(1, $subAssemblyBom->items()->count());

        // Verify relationships
        $mainBomItem = $mainBom->items()->first();
        $this->assertEquals($this->componentProduct1->id, $mainBomItem->product_id);
        $this->assertEquals(4.00, $mainBomItem->quantity);
    }

    /** @test */
    public function bom_cost_calculation()
    {
        // Arrange
        $bom = BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'quantity' => 1.00,
            'nama_bom' => 'Cost Calculation Test BOM',
            'uom_id' => $this->uom->id,
            'labor_cost' => 1000.00,
            'overhead_cost' => 500.00,
        ]);

        // Add components with known costs
        $bom->items()->create([
            'product_id' => $this->componentProduct1->id, // cost_price = 25.00
            'quantity' => 2.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 25.00,
            'subtotal' => 50.00,
        ]);

        $bom->items()->create([
            'product_id' => $this->componentProduct2->id, // cost_price = 35.00
            'quantity' => 1.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 35.00,
            'subtotal' => 35.00,
        ]);

        // Act
        $calculatedCost = $bom->calculateTotalCost();

        // Assert
        // Material cost: (2 * 25) + (1 * 35) = 50 + 35 = 85
        // Labor cost: 1000
        // Overhead cost: 500
        // Total: 85 + 1000 + 500 = 1585
        $this->assertEquals(1585.00, $calculatedCost);

        // Test updateTotalCost method
        $bom->updateTotalCost();
        $this->assertEquals(1585.00, $bom->fresh()->total_cost);
    }

    /** @test */
    public function bom_version_update()
    {
        // Arrange
        $bom = BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'nama_bom' => 'Version 1 BOM',
            'uom_id' => $this->uom->id,
        ]);

        $bom->items()->create([
            'product_id' => $this->componentProduct1->id,
            'quantity' => 1.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 25.00,
            'subtotal' => 25.00,
        ]);

        // Act - Update BOM (simulate version change)
        $bom->update([
            'nama_bom' => 'Version 2 BOM',
            'labor_cost' => 2000.00,
        ]);

        // Add new component
        $bom->items()->create([
            'product_id' => $this->componentProduct2->id,
            'quantity' => 2.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 35.00,
            'subtotal' => 70.00,
        ]);

        // Assert
        $bom->refresh();
        $this->assertEquals('Version 2 BOM', $bom->nama_bom);
        $this->assertEquals(2000.00, $bom->labor_cost);
        $this->assertEquals(2, $bom->items()->count());
    }

    /** @test */
    public function bom_code_generation()
    {
        // Arrange
        $service = new BillOfMaterialService();

        // Act
        $code1 = $service->generateCode();

        // Create a BOM to increment the counter
        BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'uom_id' => $this->uom->id,
            'code' => $code1,
        ]);

        $code2 = $service->generateCode();

        // Assert
        $this->assertStringStartsWith('BOM-', $code1);
        $this->assertStringStartsWith('BOM-', $code2);
        $this->assertNotEquals($code1, $code2);

        // Verify date format
        $expectedDate = now()->format('Ymd');
        $this->assertTrue(str_contains($code1, $expectedDate));
        $this->assertTrue(str_contains($code2, $expectedDate));
    }

    /** @test */
    public function bom_relationships()
    {
        // Arrange
        $bom = BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'uom_id' => $this->uom->id,
        ]);

        $bom->items()->create([
            'product_id' => $this->componentProduct1->id,
            'quantity' => 1.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 25.00,
            'subtotal' => 25.00,
        ]);

        // Act & Assert
        $this->assertInstanceOf(Cabang::class, $bom->cabang);
        $this->assertInstanceOf(Product::class, $bom->product);
        $this->assertInstanceOf(UnitOfMeasure::class, $bom->uom);
        $this->assertEquals($this->cabang->id, $bom->cabang->id);
        $this->assertEquals($this->finishedProduct->id, $bom->product->id);

        // Check items relationship
        $item = $bom->items()->first();
        $this->assertInstanceOf(BillOfMaterialItem::class, $item);
        $this->assertInstanceOf(Product::class, $item->product);
        $this->assertInstanceOf(UnitOfMeasure::class, $item->uom);
        $this->assertEquals($this->componentProduct1->id, $item->product->id);
    }

    /** @test */
    public function bom_soft_delete_cascade()
    {
        // Arrange
        $bom = BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'uom_id' => $this->uom->id,
        ]);

        $bom->items()->create([
            'product_id' => $this->componentProduct1->id,
            'quantity' => 1.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 25.00,
            'subtotal' => 25.00,
        ]);

        $bom->items()->create([
            'product_id' => $this->componentProduct2->id,
            'quantity' => 2.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 35.00,
            'subtotal' => 70.00,
        ]);

        $bomId = $bom->id;
        $itemIds = $bom->items()->pluck('id')->toArray();

        // Act
        $bom->delete();

        // Assert
        $this->assertSoftDeleted('bill_of_materials', ['id' => $bomId]);

        // Items should also be soft deleted (if cascade is set up)
        foreach ($itemIds as $itemId) {
            $this->assertDatabaseHas('bill_of_material_items', [
                'id' => $itemId,
                'deleted_at' => now(), // Should have deletion timestamp
            ]);
        }
    }

    /** @test */
    public function bom_active_status_filtering()
    {
        // Arrange
        BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'is_active' => true,
            'uom_id' => $this->uom->id,
        ]);

        BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => Product::factory()->create()->id,
            'is_active' => false,
            'uom_id' => $this->uom->id,
        ]);

        // Act
        $activeBoms = BillOfMaterial::where('is_active', true)->count();
        $inactiveBoms = BillOfMaterial::where('is_active', false)->count();

        // Assert
        $this->assertEquals(1, $activeBoms);
        $this->assertEquals(1, $inactiveBoms);
    }

    /** @test */
    public function bom_item_subtotal_calculation()
    {
        // Arrange
        $bom = BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'uom_id' => $this->uom->id,
        ]);

        // Act
        $item = $bom->items()->create([
            'product_id' => $this->componentProduct1->id,
            'quantity' => 3.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 25.00,
            'subtotal' => 75.00, // 3 * 25
        ]);

        // Assert
        $this->assertEquals(3.00, $item->quantity);
        $this->assertEquals(25.00, $item->unit_price);
        $this->assertEquals(75.00, $item->subtotal);
        $this->assertEquals(75.00, $item->quantity * $item->unit_price);
    }

    /** @test */
    public function bom_with_zero_costs()
    {
        // Arrange
        $bom = BillOfMaterial::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_id' => $this->finishedProduct->id,
            'uom_id' => $this->uom->id,
            'labor_cost' => 0.00,
            'overhead_cost' => 0.00,
        ]);

        $bom->items()->create([
            'product_id' => $this->componentProduct1->id,
            'quantity' => 1.00,
            'uom_id' => $this->uom->id,
            'unit_price' => 25.00,
            'subtotal' => 25.00,
        ]);

        // Act
        $totalCost = $bom->calculateTotalCost();

        // Assert
        $this->assertEquals(25.00, $totalCost); // Only material cost
    }
}