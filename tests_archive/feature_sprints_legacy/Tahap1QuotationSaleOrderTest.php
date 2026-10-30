<?php

namespace Tests\Feature\Sales;

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
use App\Services\DeliveryOrderSourceValidator;
use App\Support\ProductPriceLimitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Tahap1QuotationSaleOrderTest extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabang;
    protected User $user;
    protected UnitOfMeasure $uom;
    protected ProductCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

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
            'nama' => 'Cabang Test',
            'kode' => 'CT1',
            'alamat' => 'Jl. Test 1',
            'telepon' => '021-123456',
        ]);

        $this->user = User::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        $this->actingAs($this->user);

        $this->uom = UnitOfMeasure::create([
            'name' => 'Piece',
            'abbreviation' => 'pcs',
        ]);

        $this->category = ProductCategory::create([
            'name' => 'General',
            'kode' => 'GEN',
            'code' => 'GEN',
            'cabang_id' => $this->cabang->id,
        ]);
    }

    public function test_product_price_limitation_floor_price_calculation(): void
    {
        // 1. Case: sell_price = 100,000, harga_batas = 10%, cost_price = 80,000 -> floor = 90,000
        $product1 = Product::create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Product A',
            'sku' => 'SKU-A',
            'code' => 'PROD-A',
            'uom_id' => $this->uom->id,
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $this->category->id,
            'kode_merk' => 'TEST',
            'sell_price' => 100000,
            'cost_price' => 80000,
            'harga_batas' => 10,
        ]);

        $this->assertEquals(90000, ProductPriceLimitation::getMinimumAllowedPrice($product1));
        $this->assertNotNull(ProductPriceLimitation::validatePrice($product1, 85000));
        $this->assertNull(ProductPriceLimitation::validatePrice($product1, 90000));
        $this->assertNull(ProductPriceLimitation::validatePrice($product1, 95000));

        // 2. Case: sell_price = 100,000, harga_batas = 30%, cost_price = 80,000 -> floor is max(70,000, 80,000) = 80,000 (cannot sell below cost)
        $product2 = Product::create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Product B',
            'sku' => 'SKU-B',
            'code' => 'PROD-B',
            'uom_id' => $this->uom->id,
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $this->category->id,
            'kode_merk' => 'TEST',
            'sell_price' => 100000,
            'cost_price' => 80000,
            'harga_batas' => 30,
        ]);

        $this->assertEquals(80000, ProductPriceLimitation::getMinimumAllowedPrice($product2));
        $this->assertNotNull(ProductPriceLimitation::validatePrice($product2, 75000));
        $this->assertNull(ProductPriceLimitation::validatePrice($product2, 80000));
    }

    protected function createCustomer(array $attrs = []): Customer
    {
        return Customer::create(array_merge([
            'cabang_id' => $this->cabang->id,
            'name' => 'Customer Test',
            'code' => 'CUST-' . uniqid(),
            'address' => 'Jl. Test',
            'telephone' => '021-123',
            'phone' => '08123456789',
            'email' => 'customer' . uniqid() . '@test.com',
            'perusahaan' => 'PT Test',
            'tipe' => 'PKP',
            'fax' => '021-1234',
            'nik_npwp' => '1234567890',
            'tipe_pembayaran' => 'Bebas',
        ], $attrs));
    }

    public function test_sale_order_cod_requires_cashier_approval_for_deliverability(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Gudang Utama',
            'code' => 'WH01',
            'kode' => 'WH01',
            'location' => 'Jakarta',
            'address' => 'Jl. Gudang',
            'cabang_id' => $this->cabang->id,
        ]);

        $product = Product::create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Product C',
            'sku' => 'SKU-C',
            'code' => 'PROD-C',
            'uom_id' => $this->uom->id,
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $this->category->id,
            'kode_merk' => 'TEST',
            'sell_price' => 50000,
            'cost_price' => 40000,
        ]);

        InventoryStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
        ]);

        $customerCod = $this->createCustomer([
            'name' => 'Customer COD',
            'code' => 'CUST-COD',
            'tipe_pembayaran' => 'COD (Bayar Lunas)',
        ]);

        $customerKredit = $this->createCustomer([
            'name' => 'Customer Kredit',
            'code' => 'CUST-KREDIT',
            'tipe_pembayaran' => 'Kredit',
        ]);

        // SO 1: COD, Approved, tapi BELUM ada persetujuan Kasir
        $soCodUnapproved = SaleOrder::create([
            'cabang_id' => $this->cabang->id,
            'customer_id' => $customerCod->id,
            'so_number' => 'SO-COD-001',
            'status' => 'approved',
            'order_date' => now(),
            'total_amount' => 500000,
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $soCodUnapproved->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'subtotal' => 500000,
            'total' => 500000,
        ]);

        $this->assertTrue($soCodUnapproved->isCod());
        $this->assertFalse($soCodUnapproved->isCashierApproved());
        $this->assertFalse($soCodUnapproved->canCreateDeliveryOrder());

        // SO 2: COD, Approved, SUDAH ada persetujuan Kasir
        $soCodApproved = SaleOrder::create([
            'cabang_id' => $this->cabang->id,
            'customer_id' => $customerCod->id,
            'so_number' => 'SO-COD-002',
            'status' => 'approved',
            'order_date' => now(),
            'total_amount' => 500000,
            'tipe_pengiriman' => 'Kirim Langsung',
            'cashier_approved_by' => $this->user->id,
            'cashier_approved_at' => now(),
            'cashier_note' => 'DP diterima',
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $soCodApproved->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'subtotal' => 500000,
            'total' => 500000,
        ]);

        $this->assertTrue($soCodApproved->isCod());
        $this->assertTrue($soCodApproved->isCashierApproved());
        $this->assertTrue($soCodApproved->canCreateDeliveryOrder());

        // SO 3: Kredit, Approved, tidak butuh persetujuan kasir
        $soKredit = SaleOrder::create([
            'cabang_id' => $this->cabang->id,
            'customer_id' => $customerKredit->id,
            'so_number' => 'SO-KREDIT-001',
            'status' => 'approved',
            'order_date' => now(),
            'total_amount' => 500000,
            'tipe_pengiriman' => 'Kirim Langsung',
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $soKredit->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'subtotal' => 500000,
            'total' => 500000,
        ]);

        $this->assertFalse($soKredit->isCod());
        $this->assertTrue($soKredit->canCreateDeliveryOrder());

        // SO 4: Ambil Sendiri (harus tetap bisa masuk deliverable)
        $soAmbilSendiri = SaleOrder::create([
            'cabang_id' => $this->cabang->id,
            'customer_id' => $customerKredit->id,
            'so_number' => 'SO-AMBIL-001',
            'status' => 'approved',
            'order_date' => now(),
            'total_amount' => 500000,
            'tipe_pengiriman' => 'Ambil Sendiri',
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $soAmbilSendiri->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'subtotal' => 500000,
            'total' => 500000,
        ]);

        // Uji scopeDeliverable
        $deliverableIds = SaleOrder::deliverable()->pluck('id')->all();

        // soCodUnapproved TIDAK boleh muncul
        $this->assertNotContains($soCodUnapproved->id, $deliverableIds);

        // soCodApproved HARUS muncul
        $this->assertContains($soCodApproved->id, $deliverableIds);

        // soKredit HARUS muncul
        $this->assertContains($soKredit->id, $deliverableIds);

        // soAmbilSendiri HARUS muncul (Ambil Sendiri wajib DO)
        $this->assertContains($soAmbilSendiri->id, $deliverableIds);

        // Uji DeliveryOrderSourceValidator
        $validator = app(DeliveryOrderSourceValidator::class);

        $unapprovedErrors = $validator->errors([$soCodUnapproved->id]);
        $this->assertNotEmpty($unapprovedErrors);
        $this->assertStringContainsString('COD dan belum disetujui/diverifikasi oleh Kasir', $unapprovedErrors[0]);

        $approvedErrors = $validator->errors([$soCodApproved->id]);
        $this->assertEmpty($approvedErrors);

        $kreditErrors = $validator->errors([$soKredit->id]);
        $this->assertEmpty($kreditErrors);

        $ambilSendiriErrors = $validator->errors([$soAmbilSendiri->id]);
        $this->assertEmpty($ambilSendiriErrors);
    }

    public function test_sale_order_completion_does_not_create_invoice_or_deduct_stock_directly(): void
    {
        $customer = $this->createCustomer([
            'name' => 'Customer Self Pickup',
            'code' => 'CUST-PICKUP',
            'tipe_pembayaran' => 'Kredit',
        ]);

        $product = Product::create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Product D',
            'sku' => 'SKU-D',
            'code' => 'PROD-D',
            'uom_id' => $this->uom->id,
            'unit_of_measure_id' => $this->uom->id,
            'product_category_id' => $this->category->id,
            'kode_merk' => 'TEST',
            'sell_price' => 50000,
            'cost_price' => 40000,
        ]);

        $so = SaleOrder::create([
            'cabang_id' => $this->cabang->id,
            'customer_id' => $customer->id,
            'so_number' => 'SO-PICKUP-001',
            'status' => 'approved',
            'order_date' => now(),
            'total_amount' => 500000,
            'tipe_pengiriman' => 'Ambil Sendiri',
        ]);

        $item = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'subtotal' => 500000,
            'total' => 500000,
        ]);

        // Complete the SO directly
        $so->update(['status' => 'completed']);

        // Assert NO invoice was created directly from this SaleOrder
        $this->assertEquals(0, \App\Models\Invoice::where('from_model_type', SaleOrder::class)->where('from_model_id', $so->id)->count());

        // Assert NO stock movement was created directly from this SaleOrderItem
        $this->assertEquals(0, \App\Models\StockMovement::where('from_model_type', SaleOrderItem::class)->where('from_model_id', $item->id)->count());
    }
}

