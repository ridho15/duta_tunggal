<?php

namespace Tests\Feature\Sales;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeSalesOrderWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Customer $customer;
    protected Product $product1;
    protected Product $product2;
    protected Warehouse $warehouse1;
    protected Warehouse $warehouse2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::first() ?? Cabang::create([
            'kode' => 'CBG-TEST',
            'nama' => 'Cabang Test',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Sales Manager']);
        $permissions = [
            'view any sales order',
            'view sales order',
            'create sales order',
            'update sales order',
            'response sales order',
        ];
        foreach ($permissions as $p) {
            $perm = Permission::firstOrCreate(['name' => $p]);
            $role->givePermissionTo($perm);
        }

        $this->user = User::first() ?? User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        $this->user->assignRole($role);

        $category = ProductCategory::first() ?? ProductCategory::create(['name' => 'Umum', 'kode' => 'CAT-01']);
        $supplier = Supplier::first() ?? Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create(['name' => 'Pieces', 'abbreviation' => 'PCS']);

        $this->customer = Customer::where('code', 'CUST-DTM')->first() ?? Customer::factory()->create([
            'code' => 'CUST-DTM',
            'name' => 'DAYA TEKNIK MEDIKA',
            'cabang_id' => $this->cabang->id,
            'kredit_limit' => 50000000,
            'tipe_pembayaran' => 'Kredit',
        ]);

        $this->warehouse1 = Warehouse::where('name', 'KENARI 11 LT 4')->first() ?? Warehouse::factory()->create([
            'name' => 'KENARI 11 LT 4',
            'kode' => 'WH-01',
            'cabang_id' => $this->cabang->id,
            'location' => 'Lt 4',
        ]);
        $this->warehouse2 = Warehouse::where('name', 'DUTA-K01.2')->first() ?? Warehouse::factory()->create([
            'name' => 'DUTA-K01.2',
            'kode' => 'WH-02',
            'cabang_id' => $this->cabang->id,
            'location' => 'K01.2',
        ]);

        $this->product1 = Product::where('sku', 'ETBG000016')->first() ?? Product::create([
            'sku' => 'ETBG000016',
            'name' => "COPPER ELBO 5/8''",
            'sell_price' => 7000,
            'cost_price' => 5000,
            'harga_batas' => 5667,
            'product_category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'uom_id' => $uom->id,
            'is_active' => true,
            'cabang_id' => $this->cabang->id,
            'kode_merk' => 'MRK-01',
            'tipe_pajak' => 'Non Pajak',
            'pajak' => 0,
            'biaya' => 0,
            'item_value' => 5000,
            'jumlah_kelipatan_gudang_besar' => 1,
            'jumlah_jual_kategori_banyak' => 1,
        ]);
    }

    public function test_user_can_view_sales_order_list()
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.so.index'));

        $response->assertStatus(200);
        $response->assertSee('Pesanan Penjualan');
        $response->assertSee('Perlu tindakan');
        $response->assertSee('Buat SO');
    }

    public function test_user_can_view_create_sales_order_form()
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.so.create'));

        $response->assertStatus(200);
        $response->assertSee('Buat Pesanan Penjualan');
        $response->assertSee('Customer & Pengiriman', false);
        $response->assertSee('Barang');
        $response->assertSee('Simpan & Ajukan Approve', false);
    }

    public function test_user_can_store_sales_order_as_draft()
    {
        $currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'to_rupiah' => 1,
        ]);

        $payload = [
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->format('Y-m-d'),
            'tipe_pengiriman' => 'Kirim Langsung',
            'currency_id' => $currency->id,
            'shipping_cost' => 10000,
            'action' => 'draft',
            'items' => [
                [
                    'product_id' => $this->product1->id,
                    'quantity' => 5,
                    'unit_price' => 7000,
                    'discount' => 0,
                    'tax_type' => 'None',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('penjualan.so.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('sale_orders', [
            'customer_id' => $this->customer->id,
            'status' => 'draft',
            'shipping_cost' => 10000,
        ]);
    }

    public function test_user_can_view_sales_order_detail()
    {
        $so = SaleOrder::first();
        if (!$so) {
            $this->markTestSkipped('No SO in database.');
        }

        $response = $this->actingAs($this->user)->get(route('penjualan.so.show', $so->id));

        $response->assertStatus(200);
        $response->assertSee($so->so_number);
        $response->assertSee('SO dibuat');
        $response->assertSee('Barang yang Dipesan');
    }

    public function test_user_can_view_do_create_page()
    {
        $so = SaleOrder::where('status', 'approved')->first();
        if (!$so) {
            $currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
                'code' => 'IDR',
                'name' => 'Rupiah',
                'symbol' => 'Rp',
                'to_rupiah' => 1,
            ]);
            $so = SaleOrder::create([
                'so_number' => 'SO-TEST-APPROVED-' . time(),
                'customer_id' => $this->customer->id,
                'cabang_id' => $this->cabang->id,
                'order_date' => now()->format('Y-m-d'),
                'status' => 'approved',
                'payment_type' => 'Kredit',
                'currency_id' => $currency->id,
                'created_by' => $this->user->id,
                'subtotal' => 35000,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 35000,
            ]);
            SaleOrderItem::create([
                'sale_order_id' => $so->id,
                'product_id' => $this->product1->id,
                'quantity' => 5,
                'unit_price' => 7000,
                'discount' => 0,
                'tax_type' => 'None',
            ]);
        }

        $response = $this->actingAs($this->user)->get(route('penjualan.do.create', ['so_id' => $so->id]));

        $response->assertStatus(200);
        $response->assertSee('Buat Perintah Kirim');
        $response->assertSee($so->so_number);
    }
}
