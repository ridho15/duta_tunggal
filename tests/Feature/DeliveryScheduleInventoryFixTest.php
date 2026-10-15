<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Driver;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\Rak;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DeliveryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test for bug fix: Inventory changes when DO status changes to 'sent'
 *
 * Bug Description:
 * Before fix: When starting delivery (status -> 'sent'), qty_reserved was decremented
 *             to 0 but qty_available remained the same, causing free_qty to increase incorrectly.
 *
 * After fix:
 * - StockMovement with type 'sales' is created when status becomes 'sent'
 * - This reduces qty_available (barang sudah keluar gudang)
 * - StockReservation is NOT deleted (qty_reserved remains for tracking)
 */
class DeliveryScheduleInventoryFixTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $cabang;
    protected $warehouse;
    protected $customer;
    protected $product;
    protected $rak;
    protected $driver;
    protected $vehicle;
    protected $deliveryOrderService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'password' => bcrypt('password'),
            'first_name' => 'Test',
            'kode_user' => 'TU001',
        ]);
        $this->cabang = \App\Models\Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->rak = Rak::factory()->create(['warehouse_id' => $this->warehouse->id]);
        $this->customer = Customer::factory()->create();
        $this->driver = Driver::factory()->create();
        $this->vehicle = Vehicle::factory()->create();

        // Create required COA
        $this->createRequiredCoas();

        $this->product = Product::factory()->create([
            'cost_price' => 50000,
            'sell_price' => 75000,
            'inventory_coa_id' => ChartOfAccount::where('code', '1140.10')->value('id'),
            'goods_delivery_coa_id' => ChartOfAccount::where('code', '1140.20')->value('id'),
            'cogs_coa_id' => ChartOfAccount::where('code', '5100.10')->value('id'),
        ]);

        $this->deliveryOrderService = new DeliveryOrderService();
        $this->actingAs($this->user);
    }

    protected function createRequiredCoas(): void
    {
        $coaData = [
            ['1120', 'PIUTANG DAGANG', 'Asset'],
            ['4000', 'PENJUALAN', 'Revenue'],
            ['2120.06', 'PPN KELUARAN', 'Liability'],
            ['4100.01', 'POTONGAN PENJUALAN', 'Revenue'],
            ['1140.10', 'PERSEDIAAN BARANG DAGANGAN - DEFAULT PRODUK', 'Asset'],
            ['1140.20', 'BARANG TERKIRIM', 'Asset'],
            ['1180.10', 'BARANG TERKIRIM - DEFAULT PRODUK', 'Asset'],
            ['5100.10', 'HPP PENJUALAN', 'Expense'],
        ];

        foreach ($coaData as [$code, $name, $type]) {
            ChartOfAccount::create([
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'is_active' => true,
            ]);
        }
    }

    /**
     * Kontrak Tahap 3: stok fisik dipotong saat item DO dibuat (satu mutasi 'sales'), bukan saat status 'sent'.
     * Persetujuan/pengiriman/penyelesaian tidak menambah mutasi maupun reservasi DO.
     */
    private function makeDeliveryOrderWithItem(string $suffix, float $stock, float $qty): array
    {
        // DO tanpa baris sumber memotong stok gudang tanpa rak (fallback gudang tunggal)
        $inventoryStock = stkSetStock($this->product, $this->warehouse, $stock);

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-' . now()->format('Ymd') . '-' . $suffix,
            'delivery_date' => now(),
            'warehouse_id' => $this->warehouse->id,
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $item = DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'warehouse_id' => $this->warehouse->id,
        ]);

        return [$deliveryOrder, $item, $inventoryStock];
    }

    private function salesMovements(DeliveryOrderItem $item)
    {
        return StockMovement::where('from_model_type', DeliveryOrderItem::class)
            ->where('from_model_id', $item->id)
            ->where('type', 'sales')
            ->get();
    }

    public function test_stock_movement_created_when_do_item_created()
    {
        [$do, $item, $stock] = $this->makeDeliveryOrderWithItem('TEST001', 300, 300);

        $movements = $this->salesMovements($item);
        $this->assertCount(1, $movements, 'Satu mutasi keluar dibuat saat item DO dibuat');
        $this->assertEquals(300, $movements->first()->quantity);
        $this->assertEquals($this->warehouse->id, $movements->first()->warehouse_id);
        $this->assertEquals(0, (float) $stock->fresh()->qty_available);
    }

    public function test_status_transitions_do_not_duplicate_stock_movement_or_reservation()
    {
        [$do, $item, $stock] = $this->makeDeliveryOrderWithItem('TEST002', 100, 100);

        $this->deliveryOrderService->updateStatus($do, 'request_approve');
        $this->deliveryOrderService->updateStatus($do, 'approved');
        $this->assertCount(0, StockReservation::where('delivery_order_id', $do->id)->get(), 'DO tidak menahan reservasi (stok sudah dipotong)');

        $this->deliveryOrderService->updateStatus($do, 'sent');

        $this->assertCount(1, $this->salesMovements($item), 'Tidak ada mutasi ganda setelah approve/sent');
        $this->assertEquals(0, (float) $stock->fresh()->qty_available, 'Stok fisik tidak dipotong dua kali');
    }
}
