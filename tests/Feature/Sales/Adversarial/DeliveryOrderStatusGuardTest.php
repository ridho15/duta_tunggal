<?php

namespace Tests\Feature\Sales\Adversarial;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DeliveryOrderStatusGuardTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Customer $customer;
    protected Product $product;
    protected Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::create([
            'kode' => 'CBG-GUARD',
            'nama' => 'Cabang Guard DO',
            'alamat' => 'Jl. Guard No. 1',
            'telepon' => '021-998877',
            'status' => true,
        ]);

        $roleAdmin = Role::firstOrCreate(['name' => 'Super Admin']);

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->user->assignRole($roleAdmin);

        $this->warehouse = Warehouse::create([
            'name' => 'Gudang Guard Test',
            'kode' => 'GDG-GRD-1',
            'location' => 'Jakarta Barat',
            'cabang_id' => $this->cabang->id,
            'status' => true,
        ]);

        $category = ProductCategory::withoutGlobalScopes()->first() ?? ProductCategory::create(['name' => 'Bahan Bangunan', 'kode' => 'BB']);
        $uom = UnitOfMeasure::withoutGlobalScopes()->first() ?? UnitOfMeasure::create(['name' => 'Batang', 'abbreviation' => 'btg']);

        $this->product = Product::create([
            'sku' => 'GRD-PIPE-' . uniqid(),
            'name' => 'Pipa PVC Guard ' . uniqid(),
            'sell_price' => 50000,
            'cost_price' => 35000,
            'product_category_id' => $category->id,
            'uom_id' => $uom->id,
            'is_active' => true,
            'cabang_id' => $this->cabang->id,
            'kode_merk' => 'MRK-GRD',
            'tipe_pajak' => 'Non Pajak',
            'pajak' => 0,
            'biaya' => 0,
            'item_value' => 35000,
            'jumlah_kelipatan_gudang_besar' => 1,
            'jumlah_jual_kategori_banyak' => 1,
        ]);

        InventoryStock::updateOrCreate(
            ['warehouse_id' => $this->warehouse->id, 'product_id' => $this->product->id],
            ['qty_available' => 100, 'qty_reserved' => 0]
        );

        ChartOfAccount::firstOrCreate(['code' => '1140.10'], ['name' => 'Persediaan Barang Dagangan', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '1140.20'], ['name' => 'Barang Terkirim', 'type' => 'Asset', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '4100.10'], ['name' => 'Penjualan Produk', 'type' => 'Revenue', 'is_active' => true]);
        ChartOfAccount::firstOrCreate(['code' => '5100.10'], ['name' => 'HPP Barang Dagangan', 'type' => 'Expense', 'is_active' => true]);

        $this->customer = Customer::factory()->create([
            'cabang_id' => $this->cabang->id,
            'tipe_pembayaran' => 'Kredit',
            'kredit_limit' => 50000000,
        ]);
    }

    private function createSaleOrderWithStatus(string $status, float $qty = 10): SaleOrder
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-GRD-' . strtoupper($status) . '-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => $status,
            'tipe_pembayaran' => 'Kredit',
            'tempo_pembayaran' => 30,
            'total_amount' => $qty * 50000,
            'grand_total' => $qty * 50000,
            'created_by' => $this->user->id,
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'unit_price' => 50000,
            'subtotal' => $qty * 50000,
        ]);

        return $so;
    }

    public function test_cannot_open_do_create_without_so_id(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.do.create'));

        $response->assertRedirect(route('penjualan.so.index'));
        $response->assertSessionHas('error');
    }

    public function test_cannot_open_do_create_for_draft_sale_order(): void
    {
        $draftSo = $this->createSaleOrderWithStatus('draft');

        $response = $this->actingAs($this->user)->get(route('penjualan.do.create', ['so_id' => $draftSo->id]));

        $response->assertRedirect(route('penjualan.so.show', $draftSo->id));
        $response->assertSessionHas('error');
    }

    public function test_cannot_open_do_create_for_rejected_sale_order(): void
    {
        $rejectSo = $this->createSaleOrderWithStatus('reject');

        $response = $this->actingAs($this->user)->get(route('penjualan.do.create', ['so_id' => $rejectSo->id]));

        $response->assertRedirect(route('penjualan.so.show', $rejectSo->id));
        $response->assertSessionHas('error');
    }

    public function test_cannot_store_do_for_draft_sale_order(): void
    {
        $draftSo = $this->createSaleOrderWithStatus('draft');
        $item = $draftSo->saleOrderItem->first();

        $payload = [
            'sale_order_id' => $draftSo->id,
            'items' => [
                [
                    'item_id' => $item->id,
                    'product_id' => $this->product->id,
                    'warehouses' => [
                        $this->warehouse->id => 5,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('penjualan.do.store'), $payload);

        $response->assertRedirect(route('penjualan.so.show', $draftSo->id));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('delivery_orders', [
            'cabang_id' => $draftSo->cabang_id,
        ]);
    }

    public function test_cannot_store_do_exceeding_so_available_quantity(): void
    {
        $approvedSo = $this->createSaleOrderWithStatus('approved', 10);
        $item = $approvedSo->saleOrderItem->first();

        $payload = [
            'sale_order_id' => $approvedSo->id,
            'items' => [
                [
                    'item_id' => $item->id,
                    'product_id' => $this->product->id,
                    'warehouses' => [
                        $this->warehouse->id => 15, // Minta 15 padahal SO hanya 10
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->from(route('penjualan.do.create', ['so_id' => $approvedSo->id]))
            ->post(route('penjualan.do.store'), $payload);

        $response->assertRedirect(route('penjualan.do.create', ['so_id' => $approvedSo->id]));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('delivery_orders', [
            'cabang_id' => $approvedSo->cabang_id,
        ]);
    }

    public function test_can_create_and_store_do_for_approved_sale_order(): void
    {
        $approvedSo = $this->createSaleOrderWithStatus('approved', 10);
        $item = $approvedSo->saleOrderItem->first();

        // 1. Akses form create
        $createResponse = $this->actingAs($this->user)->get(route('penjualan.do.create', ['so_id' => $approvedSo->id]));
        $createResponse->assertOk();
        $createResponse->assertSee($approvedSo->so_number);

        // 2. Submit form store
        $payload = [
            'sale_order_id' => $approvedSo->id,
            'items' => [
                [
                    'item_id' => $item->id,
                    'product_id' => $this->product->id,
                    'warehouses' => [
                        $this->warehouse->id => 10,
                    ],
                ],
            ],
        ];

        $storeResponse = $this->actingAs($this->user)->post(route('penjualan.do.store'), $payload);
        $storeResponse->assertRedirect(route('penjualan.so.show', $approvedSo->id));
        $storeResponse->assertSessionHas('success');

        $this->assertDatabaseHas('delivery_orders', [
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $approvedSo->cabang_id,
        ]);
    }
}

