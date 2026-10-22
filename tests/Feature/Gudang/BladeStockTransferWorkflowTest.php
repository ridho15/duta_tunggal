<?php

namespace Tests\Feature\Gudang;

use App\Models\Cabang;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeStockTransferWorkflowTest extends TestCase
{
    protected User $authorizedUser;
    protected User $unauthorizedUser;
    protected Cabang $cabang;
    protected Warehouse $fromWarehouse;
    protected Warehouse $toWarehouse;
    protected ProductCategory $category;
    protected UnitOfMeasure $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-TF',
            'nama' => 'Cabang Test Transfer',
            'alamat' => 'Jl. Test Transfer No. 1',
            'status' => true,
        ]);

        $this->fromWarehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::create([
                'kode' => 'WH-ORIGIN',
                'name' => 'Gudang Asal Test',
                'location' => 'Area Gudang Asal',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $this->toWarehouse = Warehouse::withoutGlobalScopes()->where('id', '!=', $this->fromWarehouse->id)->first()
            ?? Warehouse::create([
                'kode' => 'WH-DEST',
                'name' => 'Gudang Tujuan Test',
                'location' => 'Area Gudang Tujuan',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $this->category = ProductCategory::firstOrCreate(
            ['kode' => 'CAT-TF'],
            ['name' => 'Kategori Uji Transfer', 'kenaikan_harga' => 10]
        );

        $this->uom = UnitOfMeasure::firstOrCreate(
            ['name' => 'Pieces'],
            ['abbreviation' => 'Pcs']
        );

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any stock transfer',
            'create stock transfer',
            'update stock transfer',
            'delete stock transfer',
            'request stock transfer',
            'response stock transfer',
            'view stock transfer',
        ];
        foreach ($perms as $p) {
            $perm = Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            $role->givePermissionTo($perm);
        }

        $this->authorizedUser = User::withoutGlobalScopes()->first() ?? User::create([
            'username' => 'tf_tester',
            'first_name' => 'Transfer',
            'last_name' => 'Tester',
            'email' => 'tf-tester@dutatunggal.co.id',
            'name' => 'Transfer Tester',
            'password' => bcrypt('password'),
            'cabang_id' => $this->cabang->id,
        ]);

        if (! $this->authorizedUser->hasRole('Super Admin')) {
            $this->authorizedUser->assignRole($role);
        }

        $this->unauthorizedUser = User::withoutGlobalScopes()
            ->where('id', '!=', $this->authorizedUser->id)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))
            ->first() ?? User::create([
                'username' => 'unauth_user_tf',
                'first_name' => 'Unauth',
                'last_name' => 'User',
                'email' => 'unauth-tf@dutatunggal.co.id',
                'name' => 'Unauth User',
                'password' => bcrypt('password'),
                'cabang_id' => $this->cabang->id,
            ]);
    }

    protected function createTestProduct(string $sku, string $name, float $buyPrice = 50000): Product
    {
        return Product::firstOrCreate(
            ['sku' => $sku],
            [
                'name' => $name,
                'kode_merk' => 'MRK-TF',
                'product_category_id' => $this->category->id,
                'uom_id' => $this->uom->id,
                'status' => 1,
                'price_buy' => $buyPrice,
                'cost_price' => $buyPrice,
                'price_sell' => $buyPrice * 1.3,
            ]
        );
    }

    public function test_user_can_access_stock_transfer_index_page_and_see_kpi_cards(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.transfer.index'));

        $response->assertOk();
        $response->assertViewIs('gudang.transfer.index');
        $response->assertSee('Pindah Stok Antar Gudang');
        $response->assertSee('Total Dokumen');
        $response->assertSee('Draft Persiapan');
        $response->assertSee('Menunggu Approval');
        $response->assertSee('Disetujui / Selesai');
    }

    public function test_user_can_access_create_stock_transfer_page(): void
    {
        $response = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.transfer.create'));

        $response->assertOk();
        $response->assertViewIs('gudang.transfer.create');
        $response->assertSee('Buat Transfer Stok Antar Gudang');
        $response->assertSee('Gudang Asal (Sumber Stok)');
        $response->assertSee('Gudang Tujuan');
    }

    public function test_user_can_store_stock_transfer_draft(): void
    {
        $product = $this->createTestProduct('TF-SKU-01', 'Barang Pindah Test 1');

        // Beri stok 100 pada gudang asal
        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->fromWarehouse->id, 'product_id' => $product->id, 'rak_id' => null],
            ['qty_available' => 100, 'qty_reserved' => 0]
        );

        $payload = [
            'transfer_number' => 'ST-TEST-' . uniqid(),
            'transfer_date' => now()->format('Y-m-d'),
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 25,
                    'from_rak_id' => null,
                    'to_rak_id' => null,
                ],
            ],
            'action' => 'save_draft',
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.transfer.store'), $payload);

        $response->assertRedirect();

        $transfer = StockTransfer::where('transfer_number', $payload['transfer_number'])->first();
        $this->assertNotNull($transfer);
        $this->assertEquals('Draft', $transfer->status);
        $this->assertCount(1, $transfer->stockTransferItem);
        $this->assertEquals(25, (float) $transfer->stockTransferItem->first()->quantity);

        // Stok belum terpotong saat status masih Draft
        $stockFrom = InventoryStock::where('warehouse_id', $this->fromWarehouse->id)
            ->where('product_id', $product->id)->first();
        $this->assertEquals(100, (float) $stockFrom->qty_available);
    }

    public function test_stock_transfer_validates_quantity_against_source_warehouse_stock(): void
    {
        $product = $this->createTestProduct('TF-SKU-02', 'Barang Pindah Test 2');

        // Beri stok hanya 10 pada gudang asal
        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->fromWarehouse->id, 'product_id' => $product->id, 'rak_id' => null],
            ['qty_available' => 10, 'qty_reserved' => 0]
        );

        $payload = [
            'transfer_number' => 'ST-TEST-EXCEED-' . uniqid(),
            'transfer_date' => now()->format('Y-m-d'),
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 50, // Melebihi stok yang ada
                    'from_rak_id' => null,
                    'to_rak_id' => null,
                ],
            ],
            'action' => 'save_draft',
        ];

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.transfer.store'), $payload);

        $response->assertSessionHasErrors('items.0.quantity');
    }

    public function test_user_can_request_transfer_from_draft(): void
    {
        $product = $this->createTestProduct('TF-SKU-03', 'Barang Pindah Test 3');

        $transfer = StockTransfer::create([
            'transfer_number' => 'ST-REQ-' . uniqid(),
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => 'Draft',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.transfer.request', $transfer));

        $response->assertRedirect(route('gudang.transfer.show', $transfer));
        $this->assertEquals('Request', $transfer->fresh()->status);
    }

    public function test_approving_stock_transfer_moves_inventory_from_source_to_destination(): void
    {
        $product = $this->createTestProduct('TF-SKU-04', 'Barang Pindah Real Move');

        // Setup stok awal: Asal = 50, Tujuan = 0
        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->fromWarehouse->id, 'product_id' => $product->id, 'rak_id' => null],
            ['qty_available' => 50, 'qty_reserved' => 0]
        );
        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->toWarehouse->id, 'product_id' => $product->id, 'rak_id' => null],
            ['qty_available' => 0, 'qty_reserved' => 0]
        );

        $transfer = StockTransfer::create([
            'transfer_number' => 'ST-MOVE-' . uniqid(),
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => 'Request',
        ]);

        $transferItem = StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity' => 20,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        // Eksekusi Approval
        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.transfer.approve', $transfer));

        $response->assertRedirect(route('gudang.transfer.show', $transfer));

        $freshTransfer = $transfer->fresh();
        $this->assertEquals('Approved', $freshTransfer->status);

        // Verifikasi mutasi stok fisik
        $stockFrom = InventoryStock::where('warehouse_id', $this->fromWarehouse->id)
            ->where('product_id', $product->id)->first();
        $stockTo = InventoryStock::where('warehouse_id', $this->toWarehouse->id)
            ->where('product_id', $product->id)->first();

        // 50 - 20 = 30
        $this->assertEquals(30, (float) $stockFrom->qty_available);
        // 0 + 20 = 20
        $this->assertEquals(20, (float) $stockTo->qty_available);

        // Verifikasi record StockMovement
        $movements = StockMovement::where('from_model_type', StockTransfer::class)
            ->where('from_model_id', $transfer->id)
            ->get();

        $this->assertCount(2, $movements);
        $this->assertTrue($movements->contains('type', 'transfer_out'));
        $this->assertTrue($movements->contains('type', 'transfer_in'));
    }

    public function test_user_can_reject_stock_transfer(): void
    {
        $product = $this->createTestProduct('TF-SKU-05', 'Barang Pindah Reject');

        $transfer = StockTransfer::create([
            'transfer_number' => 'ST-REJ-' . uniqid(),
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => 'Request',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->post(route('gudang.transfer.reject', $transfer), ['reason' => 'Barang rusak']);

        $response->assertRedirect(route('gudang.transfer.show', $transfer));
        $this->assertEquals('Reject', $transfer->fresh()->status);
    }

    public function test_user_can_stream_stock_transfer_pdf(): void
    {
        $product = $this->createTestProduct('TF-SKU-PDF', 'Barang Pindah PDF');

        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->fromWarehouse->id, 'product_id' => $product->id, 'rak_id' => null],
            ['qty_available' => 50, 'qty_reserved' => 0]
        );

        $transfer = StockTransfer::create([
            'transfer_number' => 'ST-PDF-' . uniqid(),
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'transfer_date' => now()->format('Y-m-d'),
            'status' => 'Approved',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.transfer.pdf', $transfer));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }

    public function test_user_can_fetch_available_products_json(): void
    {
        $product = $this->createTestProduct('TF-SKU-JSON', 'Barang JSON Ready');

        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->fromWarehouse->id, 'product_id' => $product->id, 'rak_id' => null],
            ['qty_available' => 77, 'qty_reserved' => 0]
        );

        $response = $this->actingAs($this->authorizedUser)
            ->get(route('gudang.transfer.available-products', $this->fromWarehouse));

        $response->assertOk();
        $response->assertJsonFragment([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'qty_available' => 77,
        ]);
    }

    public function test_user_without_permission_cannot_access_stock_transfer(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->get(route('gudang.transfer.index'));

        $this->assertContains($response->status(), [403, 302]);
    }
}
