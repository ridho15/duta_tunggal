<?php

namespace Tests\Feature\Sales;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\DeliveryOrderItemWarehouseSource;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Rak;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\Warehouse;
use App\Filament\Resources\DeliveryOrderResource\Pages\CreateDeliveryOrder;
use App\Services\DeliveryOrderReturnProcessor;
use App\Services\ReturnProductService;
use App\Models\StockAdjustment;
use App\Services\StockAvailability;
use Filament\Facades\Filament;
use Livewire\Livewire;
use App\Services\DeliveryOrderTransitions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Wave3ReturnByDeliveryOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected User $user;
    protected Warehouse $warehouse1;
    protected Warehouse $warehouse2;
    protected Customer $customer;
    protected Product $product;
    protected UnitOfMeasure $uom;
    protected ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sales.stock.strict_dispatch' => true,
            'sales.stock.require_surat_jalan' => true,
            'sales.stock.ledger' => true,
        ]);

        foreach ([
            ['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset'],
            ['code' => '4000', 'name' => 'Penjualan', 'type' => 'Revenue'],
            ['code' => '1140.01', 'name' => 'Persediaan Barang', 'type' => 'Asset'],
            ['code' => '1140.10', 'name' => 'Persediaan Barang Dagangan', 'type' => 'Asset'],
            ['code' => '1140.20', 'name' => 'Barang Terkirim', 'type' => 'Asset'],
            ['code' => '4100.10', 'name' => 'Penjualan Produk', 'type' => 'Revenue'],
            ['code' => '4110.10', 'name' => 'Diskon Penjualan Produk', 'type' => 'Expense'],
            ['code' => '4120.10', 'name' => 'Retur Penjualan Produk', 'type' => 'Expense'],
            ['code' => '5100.10', 'name' => 'HPP Barang Dagangan', 'type' => 'Expense'],
            ['code' => '2100.10', 'name' => 'Pembelian Belum Tertagih', 'type' => 'Liability'],
            ['code' => '1400.01', 'name' => 'Persediaan Sementara', 'type' => 'Asset'],
        ] as $account) {
            ChartOfAccount::firstOrCreate(['code' => $account['code']], array_merge($account, ['is_active' => true]));
        }

        $this->cabang = Cabang::create([
            'nama' => 'Cabang Utama',
            'kode' => 'CBU',
            'alamat' => 'Jl. Merdeka No. 10',
            'telepon' => '021-5551234',
        ]);

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);
        $this->actingAs($this->user);

        $this->warehouse1 = Warehouse::create([
            'name' => 'Gudang Pusat',
            'kode' => 'WH-01',
            'location' => 'Jakarta Barat',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);

        $this->warehouse2 = Warehouse::create([
            'name' => 'Gudang Cabang',
            'kode' => 'WH-02',
            'location' => 'Jakarta Timur',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);

        $this->customer = $this->createCustomer([
            'name' => 'PT Sumber Rejeki',
            'perusahaan' => 'PT Sumber Rejeki',
            'tipe' => 'PKP',
        ]);

        $this->uom = UnitOfMeasure::create(['name' => 'Pcs', 'abbreviation' => 'pcs']);
        $this->category = ProductCategory::create([
            'name' => 'Bahan Bangunan',
            'kode' => 'BB',
            'code' => 'BB',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->product = Product::create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Semen Padang 50kg',
            'sku' => 'SMP-50KG',
            'code' => 'SMP-50KG',
            'uom_id' => $this->uom->id,
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $this->category->id,
            'kode_merk' => 'PADANG',
            'sell_price' => 75000,
            'cost_price' => 50000,
            'harga_batas' => 5,
        ]);
    }

    protected function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'cabang_id' => $this->cabang->id,
            'name' => 'Customer Test',
            'code' => 'CUST-' . uniqid(),
            'address' => 'Jl. Test No. 1',
            'telephone' => '021-123',
            'phone' => '08123456789',
            'email' => 'customer' . uniqid() . '@test.com',
            'perusahaan' => 'PT Test',
            'tipe' => 'PKP',
            'fax' => '021-1234',
            'nik_npwp' => '1234567890',
            'tipe_pembayaran' => 'Bebas',
            'tempo_kredit' => 30,
            'credit_limit' => 500000000,
        ], $attributes));
    }

    private function stock(int $warehouseId, float $available, float $reserved = 0, ?int $rakId = null): InventoryStock
    {
        return InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $warehouseId, 'rak_id' => $rakId],
            ['qty_available' => $available, 'qty_reserved' => $reserved]
        );
    }

    private function approvedSo(float $qty, string $number): array
    {
        $so = SaleOrder::create([
            'so_number' => $number,
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'delivery_type' => 'kirim',
            'payment_type' => 'kredit',
            'shipped_to' => 'Jl. Tujuan 1',
        ]);
        $item = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'price_per_unit' => 75000,
            'subtotal' => $qty * 75000,
            'total' => $qty * 75000,
        ]);

        return [$so, $item];
    }

    private function deliveredDo(string $suffix, array $sources): array
    {
        [$so, $soItem] = $this->approvedSo(array_sum(array_column($sources, 1)), 'SO-' . $suffix);
        $do = DeliveryOrder::create([
            'do_number' => 'DO-' . $suffix,
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now(),
            'status' => 'approved',
        ]);
        $do->salesOrders()->attach($so->id);
        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => array_sum(array_column($sources, 1)),
        ]);
        foreach ($sources as [$warehouseId, $qty]) {
            DeliveryOrderItemWarehouseSource::create(['delivery_order_item_id' => $doItem->id, 'warehouse_id' => $warehouseId, 'quantity' => $qty]);
        }

        return [$do, $doItem];
    }

    private function returnFor(DeliveryOrder $do, DeliveryOrderItem $doItem, string $number, float $qty, string $condition = 'good', string $action = 'reduce_quantity_only'): ReturnProduct
    {
        $return = ReturnProduct::create([
            'return_number' => $number,
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'warehouse_id' => $this->warehouse1->id,
            'status' => 'draft',
            'reason' => 'Reject saat diterima',
            'return_action' => $action,
        ]);
        ReturnProductItem::create([
            'return_product_id' => $return->id,
            'from_item_model_type' => DeliveryOrderItem::class,
            'from_item_model_id' => $doItem->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'condition' => $condition,
        ]);

        return $return;
    }

    private function processor(): DeliveryOrderReturnProcessor
    {
        return app(DeliveryOrderReturnProcessor::class);
    }

    private function stockAt(int $warehouseId): float
    {
        return (float) InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $warehouseId)->sum('qty_available');
    }

    // ---------------------------------------------------------------- B13 nomor retur mengikuti DO

    public function test_return_number_follows_the_origin_delivery_order(): void
    {
        $this->stock($this->warehouse1->id, 20);
        [$do] = $this->deliveredDo('NUM', [[$this->warehouse1->id, 5]]);
        $service = app(ReturnProductService::class);

        $this->assertSame('DO-NUMR', $service->generateReturnNumber(DeliveryOrder::class, $do->id));

        ReturnProduct::create(['return_number' => 'DO-NUMR', 'from_model_type' => DeliveryOrder::class, 'from_model_id' => $do->id, 'warehouse_id' => $this->warehouse1->id, 'status' => 'draft', 'reason' => 'x', 'return_action' => 'reduce_quantity_only']);
        $this->assertSame('DO-NUMR2', $service->generateReturnNumber(DeliveryOrder::class, $do->id));

        // Sumber lain tetap RN-…
        $this->assertMatchesRegularExpression('/^RN-\d{8}-\d{4}$/', $service->generateReturnNumber());

        $this->assertTrue($service->isValidReturnNumber('DO-NUMR', DeliveryOrder::class, $do->id));
        $this->assertTrue($service->isValidReturnNumber('DO-NUMR2', DeliveryOrder::class, $do->id));
        $this->assertTrue($service->isValidReturnNumber('RN-20261014-0001'));
        $this->assertFalse($service->isValidReturnNumber('DO-LAINR', DeliveryOrder::class, $do->id));
        $this->assertFalse($service->isValidReturnNumber('DO-NUMRX', DeliveryOrder::class, $do->id));
    }

    public function test_draft_created_from_receiving_reject_uses_the_do_based_number(): void
    {
        $this->stock($this->warehouse1->id, 20);
        [$do, $doItem] = $this->deliveredDo('RCV', [[$this->warehouse1->id, 5]]);

        $draft = app(ReturnProductService::class)->createFromDeliveryOrderReceiving($do, [['item_id' => $doItem->id, 'qty_rejected' => 2, 'notes' => 'Pecah']]);

        $this->assertSame('DO-RCVR', $draft->return_number);
        $this->assertSame('damage', $draft->returnProductItem->first()->condition);
    }

    // ---------------------------------------------------------------- B15/B17 stok lewat Stock Adjustment, jenis mutasi do_return

    public function test_good_return_is_recorded_as_stock_adjustment_with_return_number_and_own_movement_type(): void
    {
        $this->stock($this->warehouse1->id, 20);
        [$do, $doItem] = $this->deliveredDo('GOOD', [[$this->warehouse1->id, 8]]);   // stok 20 → 12
        $return = $this->returnFor($do, $doItem, 'DO-GOODR', 3, 'good');

        $this->processor()->restock($return->fresh());

        $adjustment = StockAdjustment::where('adjustment_number', 'DO-GOODR')->first();
        $this->assertNotNull($adjustment);
        $this->assertSame('approved', $adjustment->status);
        $this->assertSame('increase', $adjustment->adjustment_type);
        $this->assertSame($this->warehouse1->id, (int) $adjustment->warehouse_id);
        $this->assertEquals(3, $adjustment->items()->sum('difference_qty'));

        $movement = StockMovement::where('type', 'do_return')->first();
        $this->assertNotNull($movement);
        $this->assertSame(StockAdjustment::class, $movement->from_model_type);
        $this->assertEquals($adjustment->id, $movement->from_model_id);
        $this->assertEquals(3, $movement->quantity);
        $this->assertSame(0, StockMovement::where('type', 'customer_return')->count());

        // Stok gudang asal bertambah sesuai qty retur
        $this->assertEquals(15.0, $this->stockAt($this->warehouse1->id));
    }

    public function test_restock_is_idempotent(): void
    {
        $this->stock($this->warehouse1->id, 20);
        [$do, $doItem] = $this->deliveredDo('IDEM', [[$this->warehouse1->id, 8]]);
        $return = $this->returnFor($do, $doItem, 'DO-IDEMR', 3);

        $this->processor()->restock($return->fresh());
        $this->processor()->restock($return->fresh());

        $this->assertSame(1, StockAdjustment::count());
        $this->assertSame(1, StockMovement::where('type', 'do_return')->count());
        $this->assertEquals(15.0, $this->stockAt($this->warehouse1->id));
    }

    public function test_multi_warehouse_return_creates_one_adjustment_per_warehouse_with_suffixed_numbers(): void
    {
        $this->stock($this->warehouse1->id, 10);
        $this->stock($this->warehouse2->id, 10);
        [$do, $doItem] = $this->deliveredDo('MULTI', [[$this->warehouse1->id, 6], [$this->warehouse2->id, 4]]);
        $return = $this->returnFor($do, $doItem, 'DO-MULTIR', 8);

        $this->processor()->restock($return->fresh());

        $this->assertSame(['DO-MULTIR-01', 'DO-MULTIR-02'], StockAdjustment::orderBy('id')->pluck('adjustment_number')->all());
        $this->assertEquals(10.0, $this->stockAt($this->warehouse1->id));   // 4 + 6
        $this->assertEquals(8.0, $this->stockAt($this->warehouse2->id));    // 6 + 2
    }

    // ---------------------------------------------------------------- B16 karantina

    public function test_damaged_goods_go_to_quarantine_warehouse_not_to_sellable_stock(): void
    {
        $this->stock($this->warehouse1->id, 20);
        [$do, $doItem] = $this->deliveredDo('DMG', [[$this->warehouse1->id, 8]]);   // stok 12
        $return = $this->returnFor($do, $doItem, 'DO-DMGR', 3, 'damage');

        $this->processor()->restock($return->fresh());

        $quarantine = Warehouse::where('is_quarantine', true)->first();
        $this->assertNotNull($quarantine);
        $this->assertEquals($this->cabang->id, $quarantine->cabang_id);

        $this->assertEquals(12.0, $this->stockAt($this->warehouse1->id));   // gudang asal TIDAK bertambah
        $this->assertEquals(3.0, $this->stockAt($quarantine->id));          // masuk karantina

        $movement = StockMovement::where('type', 'do_return')->first();
        $this->assertSame($quarantine->id, (int) $movement->warehouse_id);
        $this->assertTrue((bool) $movement->meta['quarantine']);
        $this->assertSame($quarantine->id, (int) StockAdjustment::where('adjustment_number', 'DO-DMGR')->value('warehouse_id'));
    }

    public function test_quarantine_stock_is_never_offered_as_sellable_source(): void
    {
        $this->stock($this->warehouse1->id, 20);
        [$do, $doItem] = $this->deliveredDo('QSEL', [[$this->warehouse1->id, 8]]);
        $this->processor()->restock($this->returnFor($do, $doItem, 'DO-QSELR', 5, 'damage')->fresh());
        $quarantine = Warehouse::where('is_quarantine', true)->first();

        $availability = app(StockAvailability::class);
        $this->assertNotContains($quarantine->id, $availability->accessibleWarehouseIds($this->cabang->id));

        [, $soItem] = $this->approvedSo(10, 'SO-QSEL');
        $rows = collect($availability->sourceRowsForItem($this->product->id, $soItem->id, 10, $this->cabang->id));
        $this->assertNotContains($quarantine->id, $rows->pluck('warehouse_id')->all());

        $this->assertEquals(12.0, $availability->freeForCabang($this->product->id, $this->cabang->id));   // hanya gudang 1
    }

    public function test_one_quarantine_warehouse_is_reused_per_branch(): void
    {
        $first = $this->processor()->quarantineWarehouse($this->cabang->id);
        $second = $this->processor()->quarantineWarehouse($this->cabang->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Warehouse::where('is_quarantine', true)->count());
    }

    // ---------------------------------------------------------------- B14 DO ditandai diretur + DO pengganti

    public function test_approved_return_marks_the_do_and_creates_replacement_with_stock_allocated(): void
    {
        $this->stock($this->warehouse1->id, 30);
        [$do, $doItem] = $this->deliveredDo('REPL', [[$this->warehouse1->id, 10]]);   // stok 20
        $return = $this->returnFor($do, $doItem, 'DO-REPLR', 4, 'good');

        app(ReturnProductService::class)->updateQuantityFromModel($return->fresh());

        $do->refresh();
        $this->assertNotNull($do->returned_at);
        $this->assertEquals(6, $doItem->fresh()->quantity);   // 10 − 4 retur

        $replacement = DeliveryOrder::where('replacement_for_delivery_order_id', $do->id)->first();
        $this->assertNotNull($replacement);
        $this->assertSame('DO-REPLC', $replacement->do_number);
        $this->assertSame('request_stock', $replacement->status);
        $this->assertSame($do->id, $replacement->replacementFor->id);
        $this->assertEquals([$do->salesOrders->first()->id], $replacement->salesOrders()->pluck('sale_orders.id')->all());

        $replacementItem = $replacement->deliveryOrderItem()->with('warehouseSources')->first();
        $this->assertEquals(4, $replacementItem->quantity);
        $this->assertEquals(4, $replacementItem->warehouseSources->sum('quantity'));

        // Stok: 20 → +4 kembali (24) → −4 untuk DO pengganti (20)
        $this->assertEquals(20.0, $this->stockAt($this->warehouse1->id));
        $this->assertSame(1, \App\Models\WarehouseConfirmation::where('confirmable_type', DeliveryOrder::class)->where('confirmable_id', $replacement->id)->count());
    }

    public function test_replacement_falls_back_to_draft_without_sources_when_stock_is_not_enough(): void
    {
        $this->stock($this->warehouse1->id, 10);
        [$do, $doItem] = $this->deliveredDo('DRAFT', [[$this->warehouse1->id, 10]]);   // stok habis (0)
        // Barang retur rusak → karantina, jadi tidak ada stok jual untuk penggantinya
        $return = $this->returnFor($do, $doItem, 'DO-DRAFTR', 4, 'damage');

        app(ReturnProductService::class)->updateQuantityFromModel($return->fresh());

        $replacement = DeliveryOrder::where('replacement_for_delivery_order_id', $do->id)->first();
        $this->assertNotNull($replacement);
        $this->assertSame('DO-DRAFTC', $replacement->do_number);
        $this->assertSame('draft', $replacement->status);
        $this->assertEquals(4, $replacement->deliveryOrderItem()->sum('quantity'));
        $this->assertSame(0, \App\Models\DeliveryOrderItemWarehouseSource::whereIn('delivery_order_item_id', $replacement->deliveryOrderItem()->pluck('id'))->count());
        $this->assertEquals(0.0, $this->stockAt($this->warehouse1->id));   // tidak ada pemotongan stok untuk draf

        $text = ReturnProductService::approvalSummaryText();
        $this->assertStringContainsString('DO-DRAFTC', $text);
        $this->assertStringContainsString('draf', $text);
        $this->assertStringContainsString('karantina', $text);
    }

    public function test_force_close_return_actions_mark_the_do_but_create_no_replacement(): void
    {
        // Menutup DO paksa memindahkan status ke completed — aturan SJ terbit (default hidup) memblokirnya; dimatikan agar yang diuji hanya efek retur
        config(['sales.stock.require_surat_jalan' => false]);
        $this->stock($this->warehouse1->id, 30);
        [$do, $doItem] = $this->deliveredDo('CLOSE', [[$this->warehouse1->id, 10]]);
        $return = $this->returnFor($do, $doItem, 'DO-CLOSER', 4, 'good', 'close_do_partial');

        app(ReturnProductService::class)->updateQuantityFromModel($return->fresh());

        $this->assertNotNull($do->fresh()->returned_at);
        $this->assertSame(0, DeliveryOrder::where('replacement_for_delivery_order_id', $do->id)->count());
    }

    public function test_replacement_is_created_only_once_per_return(): void
    {
        $this->stock($this->warehouse1->id, 30);
        [$do, $doItem] = $this->deliveredDo('ONCE', [[$this->warehouse1->id, 10]]);
        $return = $this->returnFor($do, $doItem, 'DO-ONCER', 4);

        app(ReturnProductService::class)->updateQuantityFromModel($return->fresh());
        $this->processor()->afterApproval($return->fresh());   // pemanggilan ulang

        $this->assertSame(1, DeliveryOrder::where('replacement_for_delivery_order_id', $do->id)->count());
    }

    // ---------------------------------------------------------------- konsistensi sumber gudang & stok setelah retur

    public function test_return_trims_the_do_source_rows_in_step_with_the_reduced_item_quantity(): void
    {
        $this->stock($this->warehouse1->id, 10);
        $this->stock($this->warehouse2->id, 10);
        [$do, $doItem] = $this->deliveredDo('TRIM', [[$this->warehouse1->id, 6], [$this->warehouse2->id, 4]]);
        $return = $this->returnFor($do, $doItem, 'DO-TRIMR', 8, 'good');

        app(ReturnProductService::class)->updateQuantityFromModel($return->fresh());

        $sources = $doItem->fresh()->warehouseSources()->get();
        $this->assertEquals(2, $doItem->fresh()->quantity);
        $this->assertSame([$this->warehouse2->id], $sources->pluck('warehouse_id')->map(fn ($id) => (int) $id)->all());   // sumber gudang1 (6) habis diretur → dihapus
        $this->assertEquals(2, $sources->sum('quantity'));                                                        // sumber gudang2: 4 − 2
    }

    public function test_two_return_items_on_the_same_do_item_do_not_allocate_the_same_source_twice(): void
    {
        $this->stock($this->warehouse1->id, 20);
        [$do, $doItem] = $this->deliveredDo('SHARE', [[$this->warehouse1->id, 8]]);
        $return = $this->returnFor($do, $doItem, 'DO-SHARER', 5, 'good');
        ReturnProductItem::create(['return_product_id' => $return->id, 'from_item_model_type' => DeliveryOrderItem::class, 'from_item_model_id' => $doItem->id, 'product_id' => $this->product->id, 'quantity' => 2, 'condition' => 'damage']);

        $this->processor()->restock($return->fresh());

        $this->assertEquals(1, $doItem->fresh()->warehouseSources()->sum('quantity'));   // 8 − 5 − 2
        $quarantine = Warehouse::where('is_quarantine', true)->first();
        $this->assertEquals(2.0, $this->stockAt($quarantine->id));
        $this->assertEquals(12 + 5.0, $this->stockAt($this->warehouse1->id));            // 12 setelah DO + 5 kembali (rusak tidak kembali)
    }

    public function test_cancelling_the_original_do_after_a_return_restores_only_what_is_still_out(): void
    {
        $this->stock($this->warehouse1->id, 30);
        [$do, $doItem] = $this->deliveredDo('CANCEL', [[$this->warehouse1->id, 10]]);   // stok 20
        $return = $this->returnFor($do, $doItem, 'DO-CANCELR', 4, 'good');
        app(ReturnProductService::class)->updateQuantityFromModel($return->fresh());
        // 20 + 4 kembali − 4 untuk DO pengganti = 20
        $this->assertEquals(20.0, $this->stockAt($this->warehouse1->id));

        // Membatalkan DO asal: hanya 6 yang masih di luar gudang yang dikembalikan (bukan 10)
        app(\App\Services\DeliveryShipments::class)->reverse($do->fresh(), 'uji batal');

        $this->assertEquals(26.0, $this->stockAt($this->warehouse1->id));
    }

    public function test_editing_do_quantities_after_a_return_does_not_restore_stock_twice(): void
    {
        $this->stock($this->warehouse1->id, 30);
        [$do, $doItem] = $this->deliveredDo('EDITAFTER', [[$this->warehouse1->id, 10]]);
        $return = $this->returnFor($do, $doItem, 'DO-EDITAFTERR', 4, 'good');
        app(ReturnProductService::class)->updateQuantityFromModel($return->fresh());
        $before = $this->stockAt($this->warehouse1->id);

        app(\App\Observers\DeliveryOrderObserver::class)->reconcileStockMovementsForDeliveryOrder($do->fresh());

        $this->assertEquals($before, $this->stockAt($this->warehouse1->id));   // selaras: item 6, sumber 6, net 6
    }
}
