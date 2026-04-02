<?php

namespace Tests\Unit\Observers;

use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockTransferItemObserverTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected Warehouse $fromWarehouse;
    protected Warehouse $toWarehouse;
    protected Rak $fromRak;
    protected Rak $fromRakTwo;
    protected Rak $toRak;
    protected Rak $toRakTwo;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::factory()->create();
        $this->fromWarehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id, 'name' => 'From Warehouse']);
        $this->toWarehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id, 'name' => 'To Warehouse']);

        $this->fromRak = Rak::factory()->create(['warehouse_id' => $this->fromWarehouse->id, 'name' => 'From Rack A1']);
        $this->fromRakTwo = Rak::factory()->create(['warehouse_id' => $this->fromWarehouse->id, 'name' => 'From Rack A2']);
        $this->toRak = Rak::factory()->create(['warehouse_id' => $this->toWarehouse->id, 'name' => 'To Rack B1']);
        $this->toRakTwo = Rak::factory()->create(['warehouse_id' => $this->toWarehouse->id, 'name' => 'To Rack B2']);

        $category = ProductCategory::factory()->create();
        $this->product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'product_category_id' => $category->id,
        ]);
    }

    #[Test]
    public function it_does_not_create_movements_for_draft_transfer_items(): void
    {
        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->fromWarehouse->id,
            'rak_id' => $this->fromRak->id,
            'qty_available' => 100,
        ]);

        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Draft',
        ]);

        StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEquals(100.0, InventoryStock::query()->value('qty_available'));
    }

    #[Test]
    public function it_creates_and_applies_movements_for_approved_transfer_items(): void
    {
        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->fromWarehouse->id,
            'rak_id' => $this->fromRak->id,
            'qty_available' => 100,
        ]);

        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Approved',
        ]);

        $item = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        $this->assertDatabaseCount('stock_movements', 2);
        $this->assertNotNull($item->stockMovement()->where('type', 'transfer_out')->first());
        $this->assertNotNull($item->stockMovement()->where('type', 'transfer_in')->first());

        $sourceStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->where('rak_id', $this->fromRak->id)
            ->first();

        $destinationStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->toWarehouse->id)
            ->where('rak_id', $this->toRak->id)
            ->first();

        $this->assertEquals(90.0, $sourceStock->qty_available);
        $this->assertEquals(10.0, $destinationStock->qty_available);
    }

    #[Test]
    public function it_syncs_existing_movements_when_an_approved_item_quantity_changes(): void
    {
        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->fromWarehouse->id,
            'rak_id' => $this->fromRak->id,
            'qty_available' => 100,
        ]);

        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Approved',
        ]);

        $item = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        $item->update(['quantity' => 20]);

        $this->assertEquals(2, StockMovement::count());
        $this->assertEquals(20.0, $item->stockMovement()->where('type', 'transfer_out')->value('quantity'));
        $this->assertEquals(20.0, $item->stockMovement()->where('type', 'transfer_in')->value('quantity'));

        $sourceStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)
            ->where('rak_id', $this->fromRak->id)
            ->first();

        $destinationStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->toWarehouse->id)
            ->where('rak_id', $this->toRak->id)
            ->first();

        $this->assertEquals(80.0, $sourceStock->qty_available);
        $this->assertEquals(20.0, $destinationStock->qty_available);
    }

    #[Test]
    public function it_reverses_movements_when_an_approved_item_is_deleted(): void
    {
        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->fromWarehouse->id,
            'rak_id' => $this->fromRak->id,
            'qty_available' => 100,
        ]);

        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Approved',
        ]);

        $item = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        $item->delete();

        $this->assertEquals(2, StockMovement::onlyTrashed()->count());
        $this->assertEquals(100.0, InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->fromWarehouse->id)->where('rak_id', $this->fromRak->id)->value('qty_available'));
        $this->assertEquals(0.0, InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->toWarehouse->id)->where('rak_id', $this->toRak->id)->value('qty_available'));
    }

    #[Test]
    public function it_only_deletes_movements_for_the_deleted_item_when_same_product_exists_multiple_times(): void
    {
        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->fromWarehouse->id,
            'rak_id' => $this->fromRak->id,
            'qty_available' => 50,
        ]);

        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->fromWarehouse->id,
            'rak_id' => $this->fromRakTwo->id,
            'qty_available' => 40,
        ]);

        $transfer = StockTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'status' => 'Approved',
        ]);

        $firstItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRak->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRak->id,
        ]);

        $secondItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'from_rak_id' => $this->fromRakTwo->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'to_rak_id' => $this->toRakTwo->id,
        ]);

        $firstItem->delete();

        $this->assertCount(2, $secondItem->stockMovement()->get());
        $this->assertEquals(2, StockMovement::query()->count());
        $this->assertEquals(2, StockMovement::onlyTrashed()->count());
        $this->assertEquals(50.0, InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->fromWarehouse->id)->where('rak_id', $this->fromRak->id)->value('qty_available'));
        $this->assertEquals(35.0, InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->fromWarehouse->id)->where('rak_id', $this->fromRakTwo->id)->value('qty_available'));
    }
}
