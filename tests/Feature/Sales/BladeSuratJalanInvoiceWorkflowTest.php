<?php

namespace Tests\Feature\Sales;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Driver;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeSuratJalanInvoiceWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Customer $customer;
    protected Product $product;
    protected Warehouse $warehouse;
    protected Rak $rak;
    protected Driver $driver;
    protected Vehicle $vehicle;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST',
            'nama' => 'Cabang Test',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin']);

        $this->user = User::withoutGlobalScopes()->first() ?? User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($role);
        }

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::withoutGlobalScopes()->first()
            ?? Warehouse::create([
                'name' => 'Gudang Utama Test',
                'kode' => 'GDG-TEST',
                'location' => 'Jakarta',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $this->rak = Rak::withoutGlobalScopes()->where('warehouse_id', $this->warehouse->id)->first()
            ?? Rak::withoutGlobalScopes()->first()
            ?? Rak::create([
                'warehouse_id' => $this->warehouse->id,
                'name' => 'A-01',
                'code' => 'RAK-A-01',
            ]);

        $category = ProductCategory::withoutGlobalScopes()->first() ?? ProductCategory::create(['name' => 'General', 'kode' => 'GEN']);
        $uom = UnitOfMeasure::withoutGlobalScopes()->first() ?? UnitOfMeasure::create(['name' => 'PCS', 'abbreviation' => 'PCS']);

        $this->product = Product::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Product::withoutGlobalScopes()->first()
            ?? Product::create([
                'sku' => 'TEST-PROD-01',
                'name' => 'Test Product Fase 3',
                'sell_price' => 50000,
                'cost_price' => 30000,
                'product_category_id' => $category->id,
                'uom_id' => $uom->id,
                'is_active' => true,
                'cabang_id' => $this->cabang->id,
                'kode_merk' => 'MRK-TEST',
                'tipe_pajak' => 'Non Pajak',
                'pajak' => 0,
                'biaya' => 0,
                'item_value' => 30000,
                'jumlah_kelipatan_gudang_besar' => 1,
                'jumlah_jual_kategori_banyak' => 1,
            ]);

        $invCoa = ChartOfAccount::firstOrCreate(['code' => '1140.10'], [
            'name' => 'Persediaan Barang Dagangan',
            'type' => 'Asset',
            'is_active' => true,
        ]);
        $transitCoa = ChartOfAccount::firstOrCreate(['code' => '1140.20'], [
            'name' => 'Barang Terkirim',
            'type' => 'Asset',
            'is_active' => true,
        ]);
        $salesCoa = ChartOfAccount::firstOrCreate(['code' => '4100.10'], [
            'name' => 'Penjualan Produk',
            'type' => 'Revenue',
            'is_active' => true,
        ]);
        $cogsCoa = ChartOfAccount::firstOrCreate(['code' => '5100.10'], [
            'name' => 'HPP Barang Dagangan',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $this->currency = Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Rupiah', 'symbol' => 'Rp', 'is_default' => true, 'exchange_rate' => 1]
        );

        $this->product->update([
            'inventory_coa_id' => $invCoa->id,
            'goods_delivery_coa_id' => $transitCoa->id,
            'sales_coa_id' => $salesCoa->id,
            'cogs_coa_id' => $cogsCoa->id,
        ]);

        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'rak_id' => null],
            ['qty_available' => 1000, 'qty_reserved' => 0]
        );
        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'rak_id' => $this->rak->id],
            ['qty_available' => 1000, 'qty_reserved' => 0]
        );

        $this->customer = Customer::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Customer::withoutGlobalScopes()->first()
            ?? Customer::create([
                'name' => 'PT Mitra Sejahtera Test',
                'perusahaan' => 'PT Mitra Sejahtera Test',
                'code' => 'CUST-TEST-01',
                'email' => 'testcustfase3@example.com',
                'phone' => '081299990001',
                'telephone' => '021-99990001',
                'fax' => '-',
                'address' => 'Jl. Industri No. 99 Jakarta',
                'tipe' => 'PKP',
                'tipe_pembayaran' => 'Kredit',
                'tempo_kredit' => 30,
                'kredit_limit' => 100000000,
                'isSpecial' => 0,
                'nik_npwp' => '01.234.567.8-901.000',
                'keterangan' => 'Customer test',
                'cabang_id' => $this->cabang->id,
            ]);

        $this->driver = Driver::withoutGlobalScopes()->first() ?? Driver::create([
            'name' => 'Driver UAT',
            'phone' => '0812340001',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->vehicle = Vehicle::withoutGlobalScopes()->first() ?? Vehicle::create([
            'plate' => 'B 1111 UAT',
            'type' => 'Van GranMax',
            'capacity' => 1000,
            'cabang_id' => $this->cabang->id,
        ]);
    }

    public function test_surat_jalan_index_page_and_tabs(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.sj.index'));
        $response->assertStatus(200);
        $response->assertSee('Surat Jalan');
        $response->assertSee('Semua');
        $response->assertSee('Terbit');
        $response->assertSee('Dibatalkan');

        // Test tab terbit & search query
        $resTab = $this->actingAs($this->user)->get(route('penjualan.sj.index', ['tab' => 'terbit']));
        $resTab->assertStatus(200);

        $resSearch = $this->actingAs($this->user)->get(route('penjualan.sj.index', ['q' => 'SJ']));
        $resSearch->assertStatus(200);
    }

    public function test_surat_jalan_create_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.sj.create'));
        $response->assertStatus(200);
        $response->assertSee('Surat Jalan');
        $response->assertSee('Terbitkan Surat Jalan');
    }

    public function test_can_create_surat_jalan_and_dispatch(): void
    {
        // Setup SO
        $so = SaleOrder::create([
            'so_number' => 'SO-TEST-SJ-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'approve_at' => now(),
            'approve_by' => $this->user->id,
            'created_by' => $this->user->id,
            'total_amount' => 100000,
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'delivered_quantity' => 0,
            'unit_price' => 50000,
        ]);

        // Setup DO approved
        $do = DeliveryOrder::create([
            'do_number' => 'DO-TEST-SJ-' . uniqid(),
            'delivery_date' => now(),
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);
        $do->salesOrders()->sync([$so->id]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'sale_order_item_id' => $soItem->id,
        ]);

        // Post create SJ
        $payload = [
            'delivery_order_ids' => [$do->id],
            'issued_at' => now()->format('Y-m-d H:i:s'),
            'delivery_method' => 'internal',
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->vehicle->id,
            'cabang_id' => $this->cabang->id,
        ];

        $response = $this->actingAs($this->user)->post(route('penjualan.sj.store'), $payload);
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        $sj = SuratJalan::where('sj_number', 'not like', 'SJ-CANCEL%')->latest('id')->first();
        $this->assertNotNull($sj);
        $this->assertEquals(SuratJalan::STATUS_ISSUED, $sj->status);
        $response->assertRedirect(route('penjualan.sj.show', $sj));

        // Delivery Order status updated
        $do->refresh();
        $this->assertEquals('received', $do->status);
    }

    public function test_surat_jalan_show_and_pdf_download(): void
    {
        $sj = SuratJalan::first() ?? SuratJalan::create([
            'sj_number' => 'SJ-TEST-PDF-' . uniqid(),
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED,
            'signed_by' => $this->user->id,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $resShow = $this->actingAs($this->user)->get(route('penjualan.sj.show', $sj));
        $resShow->assertStatus(200);
        $resShow->assertSee($sj->sj_number);

        $resPdf = $this->actingAs($this->user)->get(route('penjualan.sj.pdf', $sj));
        $resPdf->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resPdf->headers->get('Content-Type'));
    }

    public function test_surat_jalan_cancel_workflow(): void
    {
        $sj = SuratJalan::create([
            'sj_number' => 'SJ-CANCEL-' . uniqid(),
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED,
            'signed_by' => $this->user->id,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('penjualan.sj.cancel', $sj), [
            'cancel_reason' => 'Salah input tujuan kirim',
        ]);

        $response->assertSessionHasNoErrors();
        $sj->refresh();
        $this->assertEquals(SuratJalan::STATUS_CANCELLED, $sj->status);
        $this->assertEquals('Salah input tujuan kirim', $sj->cancel_reason);
    }

    public function test_gudang_kiriman_index_and_picking_list(): void
    {
        $resIndex = $this->actingAs($this->user)->get(route('gudang.kiriman.index'));
        $resIndex->assertStatus(200);
        $resIndex->assertSee('Siapkan & Kirim');
        $resIndex->assertSee('Cetak picking list');

        $resPick = $this->actingAs($this->user)->get(route('gudang.kiriman.pickingList'));
        $resPick->assertStatus(200);
        $resPick->assertSee('PICKING LIST GUDANG');
    }

    public function test_gudang_kiriman_checker_and_retur_screens(): void
    {
        // Setup SO & DO approved
        $so = SaleOrder::create([
            'so_number' => 'SO-TEST-CHK-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'approve_at' => now(),
            'approve_by' => $this->user->id,
            'created_by' => $this->user->id,
            'total_amount' => 50000,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-TEST-CHK-' . uniqid(),
            'delivery_date' => now(),
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);
        $do->salesOrders()->sync([$so->id]);

        $resCheck = $this->actingAs($this->user)->get(route('gudang.kiriman.check', $do));
        $resCheck->assertStatus(200);
        $resCheck->assertSee($do->do_number);
        $resCheck->assertSee('CHECKER');

        // Check retur screen
        $resRetur = $this->actingAs($this->user)->get(route('gudang.kiriman.retur', $do));
        $resRetur->assertStatus(200);
        $resRetur->assertSee($do->do_number);
        $resRetur->assertSee('Proses Retur / Selisih DO');
    }

    public function test_sales_invoice_index_page_and_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.invoices.index'));
        $response->assertStatus(200);
        $response->assertSee('Invoice Penjualan');
        $response->assertSee('Total Piutang Belum Tertagih');
        $response->assertSee('Menunggu Pembayaran');
        $response->assertSee('Jatuh Tempo');

        // Tab filters
        $resTab1 = $this->actingAs($this->user)->get(route('penjualan.invoices.index', ['tab' => 'unpaid']));
        $resTab1->assertStatus(200);

        $resTab2 = $this->actingAs($this->user)->get(route('penjualan.invoices.index', ['tab' => 'paid']));
        $resTab2->assertStatus(200);
    }

    public function test_sales_invoice_create_and_store_workflow(): void
    {
        // 1. Setup SO & DO & SJ issued
        $so = SaleOrder::create([
            'so_number' => 'SO-TEST-INV-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'approve_at' => now(),
            'approve_by' => $this->user->id,
            'created_by' => $this->user->id,
            'total_amount' => 100000,
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'delivered_quantity' => 0,
            'unit_price' => 50000,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-TEST-INV-' . uniqid(),
            'delivery_date' => now(),
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'received',
            'created_by' => $this->user->id,
        ]);
        $do->salesOrders()->sync([$so->id]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'sale_order_item_id' => $soItem->id,
        ]);

        $sj = SuratJalan::create([
            'sj_number' => 'SJ-TEST-INV-' . uniqid(),
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED,
            'signed_by' => $this->user->id,
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);
        $sj->deliveryOrder()->sync([$do->id]);

        $resCreate = $this->actingAs($this->user)->get(route('penjualan.invoices.create', ['so_id' => $so->id]));
        $resCreate->assertStatus(200);
        $resCreate->assertSee('Buat Faktur Penjualan');

        $payload = [
            'surat_jalan_ids' => [$sj->id],
            'invoice_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'cabang_id' => $this->cabang->id,
            'other_fee' => 15000,
        ];

        $response = $this->actingAs($this->user)->post(route('penjualan.invoices.store'), $payload);
        $response->assertSessionHasNoErrors();

        $inv = Invoice::latest('id')->first();
        $this->assertNotNull($inv);
        $response->assertRedirect(route('penjualan.invoices.show', $inv));
    }

    public function test_sales_invoice_show_and_pdf_download(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-TEST-PDF-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'approve_at' => now(),
            'approve_by' => $this->user->id,
            'created_by' => $this->user->id,
            'total_amount' => 100000,
        ]);

        $inv = Invoice::first() ?? Invoice::create([
            'invoice_number' => 'INV-TEST-PDF-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'cabang_id' => $this->cabang->id,
            'customer_name' => $this->customer->name,
            'subtotal' => 100000,
            'dpp' => 100000,
            'tax' => 0,
            'total' => 100000,
            'status' => Invoice::STATUS_SENT,
        ]);

        $resShow = $this->actingAs($this->user)->get(route('penjualan.invoices.show', $inv));
        $resShow->assertStatus(200);
        $resShow->assertSee($inv->invoice_number);

        $resPdf = $this->actingAs($this->user)->get(route('penjualan.invoices.pdf', $inv));
        $resPdf->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $resPdf->headers->get('Content-Type'));
    }
}
