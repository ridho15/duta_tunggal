<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InventoryCardPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_card_preview_uses_shared_dataset_for_preview_and_omits_opening_only_rows(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $user = User::factory()->create();
        $user->givePermissionTo('view any inventory stock');
        $this->actingAs($user);

        $fixture = $this->createFixture();

        $response = $this->get(route('inventory-card.print', [
            'start' => '2026-04-01',
            'end' => '2026-04-30',
            'warehouse_id' => $fixture['warehouse']->id,
        ]));

        $response->assertOk();
        $response->assertSee('KARTU PERSEDIAAN');
        $response->assertSee('Product With Movement');
        $response->assertSee('Warehouse Card');
        $response->assertDontSee('Product Opening Only');
        $response->assertSee('12,00');
    }

    private function createFixture(): array
    {
        $cabang = Cabang::factory()->create();
        $category = ProductCategory::factory()->create();
        $uom = UnitOfMeasure::factory()->create();

        $warehouse = Warehouse::create([
            'kode' => 'WH-CARD',
            'name' => 'Warehouse Card',
            'tipe' => 'Besar',
            'location' => 'Main Warehouse',
            'telepon' => '081100000001',
            'status' => 1,
            'warna_background' => '#ffffff',
            'cabang_id' => $cabang->id,
        ]);

        $rak = Rak::create([
            'code' => 'RAK-CARD',
            'name' => 'Rak Card',
            'warehouse_id' => $warehouse->id,
        ]);

        $productWithMovement = Product::create([
            'code' => 'PROD-CARD-1',
            'name' => 'Product With Movement',
            'sku' => 'SKU-CARD-1',
            'description' => 'Card Product One',
            'status' => 1,
            'cabang_id' => $cabang->id,
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
            'kode_merk' => 'MERK-CARD-1',
            'cost_price' => 1000,
            'sell_price' => 1500,
            'is_active' => 1,
        ]);

        $productOpeningOnly = Product::create([
            'code' => 'PROD-CARD-2',
            'name' => 'Product Opening Only',
            'sku' => 'SKU-CARD-2',
            'description' => 'Card Product Two',
            'status' => 1,
            'cabang_id' => $cabang->id,
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
            'kode_merk' => 'MERK-CARD-2',
            'cost_price' => 1000,
            'sell_price' => 1500,
            'is_active' => 1,
        ]);

        StockMovement::create([
            'product_id' => $productWithMovement->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'purchase_in',
            'quantity' => 10,
            'value' => 10000,
            'date' => '2026-03-25 08:00:00',
        ]);

        StockMovement::create([
            'product_id' => $productWithMovement->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'purchase_in',
            'quantity' => 5,
            'value' => 5000,
            'date' => '2026-04-02 08:00:00',
        ]);

        StockMovement::create([
            'product_id' => $productWithMovement->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'sales',
            'quantity' => 3,
            'value' => 3000,
            'date' => '2026-04-03 08:00:00',
        ]);

        StockMovement::create([
            'product_id' => $productOpeningOnly->id,
            'warehouse_id' => $warehouse->id,
            'rak_id' => $rak->id,
            'type' => 'purchase_in',
            'quantity' => 7,
            'value' => 7000,
            'date' => '2026-03-20 08:00:00',
        ]);

        return [
            'warehouse' => $warehouse,
        ];
    }
}