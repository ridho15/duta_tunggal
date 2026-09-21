<?php

namespace Tests\Feature;

use App\Filament\Resources\StockAdjustmentResource\Pages\EditStockAdjustment;
use App\Filament\Resources\StockAdjustmentResource\RelationManagers\StockAdjustmentItemsRelationManager;
use App\Filament\Resources\StockOpnameResource\Pages\EditStockOpname;
use App\Filament\Resources\StockOpnameResource\RelationManagers\StockOpnameItemsRelationManager;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Relation manager item Stock Opname dan Penyesuaian Stok: field uang otomatis (Harga Satuan, Average Cost,
 * Nilai Selisih, Total Nilai) tampil dalam format uang yang sama dengan input lain dan dihitung ulang
 * setiap qty atau harga berubah, bukan hanya saat Harga Satuan diedit.
 */
class StockItemRelationManagersMoneyTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['manage_type' => 'all']));

        UnitOfMeasure::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['status' => 1]);
        $this->product = Product::factory()->create(['cost_price' => 10000]);
    }

    private function state($component, string $field): mixed
    {
        return $component->get("mountedTableActionsData.0.{$field}");
    }

    public function test_opname_values_are_formatted_and_recalculated_on_every_change(): void
    {
        $opname = StockOpname::factory()->create(['warehouse_id' => $this->warehouse->id, 'status' => 'draft']);

        $page = Livewire::test(StockOpnameItemsRelationManager::class, ['ownerRecord' => $opname, 'pageClass' => EditStockOpname::class])
            ->mountTableAction('create')
            ->set('mountedTableActionsData.0.product_id', $this->product->id);

        // Tanpa riwayat pembelian/stok, semua nilai otomatis berformat uang (bukan angka mentah).
        $this->assertSame('0,00', $this->state($page, 'unit_cost'));
        $this->assertSame('0,00', $this->state($page, 'average_cost'));
        $this->assertSame('0,00', $this->state($page, 'difference_value'));
        $this->assertSame('0,00', $this->state($page, 'total_value'));

        $page->set('mountedTableActionsData.0.unit_cost', '12.500,00')
            ->set('mountedTableActionsData.0.physical_qty', 15);

        $this->assertSame('187.500,00', $this->state($page, 'difference_value'));
        $this->assertSame('187.500,00', $this->state($page, 'total_value'));

        // Mengubah qty fisik saja (tanpa menyentuh harga) menghitung ulang nilainya.
        $page->set('mountedTableActionsData.0.physical_qty', 20);
        $this->assertSame('250.000,00', $this->state($page, 'difference_value'));
        $this->assertSame('250.000,00', $this->state($page, 'total_value'));
    }

    public function test_opname_saves_formatted_values_as_plain_numbers(): void
    {
        $opname = StockOpname::factory()->create(['warehouse_id' => $this->warehouse->id, 'status' => 'draft']);

        Livewire::test(StockOpnameItemsRelationManager::class, ['ownerRecord' => $opname, 'pageClass' => EditStockOpname::class])
            ->mountTableAction('create')
            ->set('mountedTableActionsData.0.product_id', $this->product->id)
            ->set('mountedTableActionsData.0.unit_cost', '12.500,50')
            ->set('mountedTableActionsData.0.physical_qty', 10)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $item = StockOpnameItem::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(12500.50, (float) $item->unit_cost, 0.001);
        $this->assertEqualsWithDelta(125005.00, (float) $item->difference_value, 0.001);
        $this->assertEqualsWithDelta(125005.00, (float) $item->total_value, 0.001);
    }

    public function test_adjustment_values_are_formatted_and_recalculated_on_every_change(): void
    {
        $adjustment = StockAdjustment::factory()->create(['warehouse_id' => $this->warehouse->id, 'status' => 'draft']);

        $page = Livewire::test(StockAdjustmentItemsRelationManager::class, ['ownerRecord' => $adjustment, 'pageClass' => EditStockAdjustment::class])
            ->mountTableAction('create')
            ->set('mountedTableActionsData.0.product_id', $this->product->id);

        // Harga otomatis dari harga beli produk, berformat uang.
        $this->assertSame('10.000,00', $this->state($page, 'unit_cost'));

        $page->set('mountedTableActionsData.0.current_qty', 20)
            ->set('mountedTableActionsData.0.adjusted_qty', 15);
        $this->assertSame('-50.000,00', $this->state($page, 'difference_value'));

        $page->set('mountedTableActionsData.0.unit_cost', '12.500,00');
        $this->assertSame('-62.500,00', $this->state($page, 'difference_value'));

        // Mengubah qty setelah adjustment menghitung ulang nilainya tanpa menyentuh harga.
        $page->set('mountedTableActionsData.0.adjusted_qty', 25);
        $this->assertSame('62.500,00', $this->state($page, 'difference_value'));
    }

    public function test_adjustment_saves_formatted_values_as_plain_numbers(): void
    {
        $adjustment = StockAdjustment::factory()->create(['warehouse_id' => $this->warehouse->id, 'status' => 'draft']);

        Livewire::test(StockAdjustmentItemsRelationManager::class, ['ownerRecord' => $adjustment, 'pageClass' => EditStockAdjustment::class])
            ->mountTableAction('create')
            ->set('mountedTableActionsData.0.product_id', $this->product->id)
            ->set('mountedTableActionsData.0.current_qty', 20)
            ->set('mountedTableActionsData.0.adjusted_qty', 15)
            ->set('mountedTableActionsData.0.unit_cost', '12.500,00')
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $item = StockAdjustmentItem::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(12500.00, (float) $item->unit_cost, 0.001);
        $this->assertEqualsWithDelta(-62500.00, (float) $item->difference_value, 0.001);
    }
}
