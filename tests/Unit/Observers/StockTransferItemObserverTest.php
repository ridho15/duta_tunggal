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
    public function it_does_not_move_stock_when_items_of_an_approved_transfer_are_created_changed_or_deleted(): void
    {
        // Alur 4 tahap (24 Nov 2026): stok mutasi hanya bergerak lewat Kirim (gudang asal) & Terima (gudang tujuan),
        // tidak lagi lewat observer item saat transfer berstatus Approved.
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
        $item->update(['quantity' => 15]);
        $item->delete();

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEquals(100.0, (float) InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->fromWarehouse->id)->where('rak_id', $this->fromRak->id)->value('qty_available'));
    }
}
