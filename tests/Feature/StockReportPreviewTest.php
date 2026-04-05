<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StockReportPreviewTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_uses_stock_movements_for_preview_rows_and_outbound_totals(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view any inventory stock');
        $this->actingAs($user);

        $cabang = Cabang::factory()->create();
        $supplier = Supplier::factory()->create();
        $category = ProductCategory::factory()->create();
        $uom = UnitOfMeasure::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
        $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id, 'name' => 'Rak Preview']);
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
            'cost_price' => 1000,
            'sku' => 'SKU-PREVIEW-001',
            'name' => 'Produk Preview Ledger',
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'purchase_in',
            'quantity' => 10,
            'value' => 10000,
            'date' => now()->subDay(),
            'from_model_type' => 'App\\Models\\PurchaseReceipt',
            'from_model_id' => 1,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'sales',
            'quantity' => 4,
            'value' => 4000,
            'date' => now(),
            'from_model_type' => 'App\\Models\\DeliveryOrder',
            'from_model_id' => 1,
        ]);

        $response = $this->get(route('reports.stock-report.preview', [
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
            'product_ids' => [$product->id],
            'warehouse_ids' => [$warehouse->id],
        ]));

        $response->assertOk();
        $response->assertSee('Produk Preview Ledger');
        $response->assertViewHas('totals', function (array $totals) {
            return (float) $totals['items'] === 1.0
                && (float) $totals['total_in'] === 10.0
                && (float) $totals['total_out'] === 4.0
                && (float) $totals['qty_on_hand'] === 6.0;
        });
        $response->assertViewHas('rows', function ($rows) use ($warehouse, $rak) {
            if ($rows->count() !== 1) {
                return false;
            }

            $row = $rows->first();

            return $row['warehouse_name'] === $warehouse->name
                && $row['rak_name'] === $rak->name
                && (float) $row['total_in'] === 10.0
                && (float) $row['total_out'] === 4.0
                && (float) $row['qty_on_hand'] === 6.0
                && (float) $row['opening_qty'] === 0.0;
        });
    }

    #[Test]
    public function it_prefers_inventory_snapshot_for_qty_on_hand_and_computes_status_and_value(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view any inventory stock');
        $this->actingAs($user);

        $cabang = Cabang::factory()->create();
        $supplier = Supplier::factory()->create();
        $category = ProductCategory::factory()->create();
        $uom = UnitOfMeasure::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);
        $rak = Rak::factory()->create(['warehouse_id' => $warehouse->id, 'name' => 'Rak Snapshot']);
        $product = Product::factory()->create([
            'supplier_id' => $supplier->id,
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
            'cost_price' => 2500,
            'sku' => 'SKU-PREVIEW-002',
            'name' => 'Produk Preview Snapshot',
        ]);

        InventoryStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'qty_available' => 8,
            'qty_reserved' => 3,
            'qty_min' => 6,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'purchase_in',
            'quantity' => 12,
            'value' => 30000,
            'date' => now()->subDays(3),
            'from_model_type' => 'App\\Models\\PurchaseReceipt',
            'from_model_id' => 2,
        ]);

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'sales',
            'quantity' => 4,
            'value' => 10000,
            'date' => now()->subDay(),
            'from_model_type' => 'App\\Models\\DeliveryOrder',
            'from_model_id' => 2,
        ]);

        $response = $this->get(route('reports.stock-report.preview', [
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->toDateString(),
            'product_ids' => [$product->id],
            'warehouse_ids' => [$warehouse->id],
        ]));

        $response->assertOk();
        $response->assertViewHas('totals', function (array $totals) {
            return (float) $totals['items'] === 1.0
                && (float) $totals['qty_on_hand'] === 13.0
                && (float) $totals['qty_available'] === 16.0
                && (float) $totals['qty_reserved'] === 3.0
                && (float) $totals['total_value'] === 32500.0;
        });
        $response->assertViewHas('rows', function ($rows) use ($warehouse, $rak) {
            if ($rows->count() !== 1) {
                return false;
            }

            $row = $rows->first();

            return $row['warehouse_name'] === $warehouse->name
                && $row['rak_name'] === $rak->name
                && (float) $row['opening_qty'] === 5.0
                && (float) $row['qty_available'] === 16.0
                && (float) $row['qty_reserved'] === 3.0
                && (float) $row['qty_on_hand'] === 13.0
                && (float) $row['cost_price'] === 2500.0
                && (float) $row['total_value'] === 32500.0
                && $row['status'] === 'Normal';
        });
        $response->assertSee('Produk Preview Snapshot');
        $response->assertSee('32.500');
    }
}
