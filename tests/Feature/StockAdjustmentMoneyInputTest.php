<?php

namespace Tests\Feature;

use App\Filament\Resources\StockAdjustmentResource\Pages\CreateStockAdjustment;
use App\Filament\Resources\StockAdjustmentResource\Pages\EditStockAdjustment;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Field uang pada form Penyesuaian Stok (Harga Satuan dan Nilai Selisih) harus memakai input uang yang sama
 * dengan halaman lain (indonesianMoney): tampil "10.000,00", dihitung live dari nilai bermask, dan tersimpan sebagai angka.
 */
class StockAdjustmentMoneyInputTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;
    private Rak $rak;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        // Resource ini tidak memakai policy/izin khusus, jadi cukup user yang login (akses semua cabang).
        $this->actingAs(User::factory()->create(['manage_type' => 'all']));

        UnitOfMeasure::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['status' => 1]);
        $this->rak = Rak::factory()->create(['warehouse_id' => $this->warehouse->id]);
        $this->product = Product::factory()->create(['cost_price' => 10000]);
    }

    private function itemKey($page): string
    {
        return (string) array_key_first($page->get('data.items'));
    }

    private function fillItem($page, string $key, float $adjustedQty): void
    {
        $page->set('data.warehouse_id', $this->warehouse->id)
            ->set("data.items.{$key}.product_id", $this->product->id)
            ->set("data.items.{$key}.rak_id", $this->rak->id)
            ->set("data.items.{$key}.adjusted_qty", $adjustedQty);
    }

    public function test_unit_cost_and_difference_value_follow_the_indonesian_money_format_live(): void
    {
        $page = Livewire::test(CreateStockAdjustment::class);
        $key = $this->itemKey($page);

        $this->fillItem($page, $key, 10);

        // Memilih produk mengisi Harga Satuan dari harga beli dalam format uang, dan Nilai Selisih ikut dihitung.
        $this->assertSame('10.000,00', $page->get("data.items.{$key}.unit_cost"));
        $this->assertSame('100.000,00', $page->get("data.items.{$key}.difference_value"));

        // Mengetik harga bermask dihitung dengan benar (bukan (float) "12.500,50" = 12,5).
        $page->set("data.items.{$key}.unit_cost", '12.500,50');
        $this->assertSame('125.005,00', $page->get("data.items.{$key}.difference_value"));
    }

    public function test_masked_values_are_saved_as_plain_numbers(): void
    {
        $page = Livewire::test(CreateStockAdjustment::class);
        $key = $this->itemKey($page);

        $this->fillItem($page, $key, 10);
        $page->set("data.items.{$key}.unit_cost", '12.500,50')
            ->set('data.adjustment_type', 'increase')
            ->set('data.reason', 'Saldo awal uji')
            ->call('create')
            ->assertHasNoFormErrors();

        $item = StockAdjustmentItem::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(12500.50, (float) $item->unit_cost, 0.001);
        $this->assertEqualsWithDelta(125005.00, (float) $item->difference_value, 0.001);
    }

    public function test_decrease_gives_a_negative_difference_value_that_is_saved_correctly(): void
    {
        InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => $this->rak->id,
            'qty_available' => 20,
            'qty_reserved' => 0,
        ]);

        $page = Livewire::test(CreateStockAdjustment::class);
        $key = $this->itemKey($page);

        $this->fillItem($page, $key, 5); // 20 -> 5 = selisih -15
        $page->set("data.items.{$key}.unit_cost", '12.500,00');

        $this->assertSame('-187.500,00', $page->get("data.items.{$key}.difference_value"));

        $page->set('data.adjustment_type', 'decrease')
            ->set('data.reason', 'Barang rusak uji')
            ->call('create')
            ->assertHasNoFormErrors();

        $item = StockAdjustmentItem::query()->latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(-187500.00, (float) $item->difference_value, 0.001);
    }

    public function test_edit_page_shows_saved_money_in_the_same_format(): void
    {
        $adjustment = StockAdjustment::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'current_qty' => 0,
            'adjusted_qty' => 10,
            'difference_qty' => 10,
            'unit_cost' => 12500.5,
            'difference_value' => 125005,
        ]);

        $page = Livewire::test(EditStockAdjustment::class, ['record' => $adjustment->getRouteKey()]);
        $key = $this->itemKey($page);

        $this->assertSame('12.500,50', $page->get("data.items.{$key}.unit_cost"));
        $this->assertSame('125.005,00', $page->get("data.items.{$key}.difference_value"));
    }
}
