<?php

namespace Tests\Feature\Purchasing;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladePurchaseOrderWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-SBY',
            'nama' => 'Cabang Surabaya',
            'alamat' => 'Jl. Pemuda No. 1, Surabaya',
            'telepon' => '031-5551234',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin']);
        $perms = [
            'view any purchase order',
            'view purchase order',
            'create purchase order',
            'update purchase order',
            'delete purchase order',
            'response purchase order',
            'request purchase order',
        ];
        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }
        $role->givePermissionTo($perms);

        $this->user = User::withoutGlobalScopes()->first() ?? User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (!$this->user->hasRole('Super Admin')) {
            $this->user->assignRole('Super Admin');
        }

        $this->supplier = Supplier::first() ?? Supplier::create([
            'code' => 'SUP-TEST-001',
            'perusahaan' => 'PT Sumber Logam Sejahtera',
            'kontak_person' => 'Bapak Budianto',
            'phone' => '081234567890',
            'handphone' => '081234567890',
            'fax' => '031-123456',
            'npwp' => '01.234.567.8-901.000',
            'email' => 'budi@sumberlogam.com',
            'address' => 'Kawasan Industri Rungkut Surabaya',
            'tempo_hutang' => 30,
            'keterangan' => 'Supplier Test',
            'nama_bank' => 'BCA',
            'nomor_rekening' => '8291029381',
            'nama_rekening' => 'PT Sumber Logam Sejahtera',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->first() ?? Warehouse::create([
            'kode' => 'GDG-UTM',
            'name' => 'Gudang Utama Surabaya',
            'location' => 'Rungkut Industri Surabaya',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
            'tipe' => 'Besar',
            'telepon' => '031-1234567',
        ]);

        $this->product = Product::withoutGlobalScopes()->first() ?? Product::factory()->create([
            'sku' => 'BSH-4040',
            'name' => 'Besi Hollow 40x40 Tebal 2mm',
            'cost_price' => 85000,
            'sell_price' => 105000,
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $this->supplier->id,
            'is_active' => true,
        ]);

        $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah Indonesia',
            'symbol' => 'Rp',
            'to_rupiah' => 1.0,
        ]);
    }

    protected function tearDown(): void
    {
        $testPos = PurchaseOrder::withoutGlobalScopes()->where('po_number', 'like', 'PO-TEST-%')->get();
        foreach ($testPos as $po) {
            $po->forceDelete();
        }

        parent::tearDown();
    }

    /**
     * 1. Halaman index PO berhasil dimuat dengan 4 KPI card
     */
    public function test_po_index_page_loads_and_displays_kpis(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('pembelian.po.index'));

        $response->assertStatus(200);
        $response->assertViewIs('pembelian.po.index');
        $response->assertViewHas(['purchaseOrders', 'counts', 'kpi', 'activeTab']);
        $response->assertSee('Pesanan Pembelian (Purchase Order)');
        $response->assertSee('Total Nilai PO');
        $response->assertSee('Draf PO');
    }

    /**
     * 2. Form create PO memuat data master (supplier, warehouse, products, cabang)
     */
    public function test_po_create_page_loads_with_suppliers_and_warehouses(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('pembelian.po.create'));

        $response->assertStatus(200);
        $response->assertViewIs('pembelian.po.create');
        $response->assertViewHas(['suppliers', 'warehouses', 'cabangs', 'products', 'currencies', 'defaultPoNumber']);
        $response->assertSee($this->supplier->perusahaan);
    }

    /**
     * 3. Endpoint AJAX detail supplier dan detail produk
     */
    public function test_ajax_supplier_and_product_details(): void
    {
        // AJAX Supplier
        $supplierRes = $this->actingAs($this->user)
            ->getJson(route('pembelian.supplier-details', $this->supplier));

        $supplierRes->assertStatus(200);
        $supplierRes->assertJson([
            'id' => $this->supplier->id,
            'perusahaan' => $this->supplier->perusahaan,
            'tempo_hutang' => (int) $this->supplier->tempo_hutang,
            'kontak_person' => $this->supplier->kontak_person,
        ]);

        // AJAX Product
        $productRes = $this->actingAs($this->user)
            ->getJson(route('pembelian.product-details', $this->product));

        $productRes->assertStatus(200);
        $productRes->assertJson([
            'id' => $this->product->id,
            'name' => $this->product->name,
            'cost_price' => (float) $this->product->cost_price,
        ]);
    }

    /**
     * 4. Validasi form menolak jika item kosong atau kuantitas 0
     */
    public function test_validation_blocks_empty_items_or_zero_quantity(): void
    {
        // Tanpa item
        $resEmpty = $this->actingAs($this->user)
            ->post(route('pembelian.po.store'), [
                'supplier_id' => $this->supplier->id,
                'order_date' => now()->toDateString(),
                'items' => [],
            ]);

        $resEmpty->assertSessionHasErrors(['items']);

        // Kuantitas 0
        $resZeroQty = $this->actingAs($this->user)
            ->post(route('pembelian.po.store'), [
                'supplier_id' => $this->supplier->id,
                'order_date' => now()->toDateString(),
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 0,
                        'unit_price' => 50000,
                    ],
                ],
            ]);

        $resZeroQty->assertSessionHasErrors(['items.0.quantity']);
    }

    /**
     * 5. Berhasil membuat draf PO dengan kalkulasi total yang akurat
     */
    public function test_can_create_draft_purchase_order_with_calculated_totals(): void
    {
        $poNumber = 'PO-TEST-' . strtoupper(uniqid());
        $payload = [
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'po_number' => $poNumber,
            'order_date' => now()->toDateString(),
            'expected_date' => now()->addDays(7)->toDateString(),
            'top_type' => 'credit_days',
            'tempo_hutang' => 30,
            'currency_id' => $this->currency->id,
            'note' => 'Catatan pesanan pembelian unit test',
            'action' => 'save_draft',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 10,
                    'unit_price' => 85000,
                    'discount' => 0,
                    'tax' => 11,
                    'tipe_pajak' => 'eklusif',
                    'currency_id' => $this->currency->id,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('pembelian.po.store'), $payload);

        $po = PurchaseOrder::where('po_number', $poNumber)->first();
        $this->assertNotNull($po);
        $this->assertEquals('draft', $po->status);
        $this->assertEquals($this->supplier->id, $po->supplier_id);
        $this->assertEquals(1, $po->purchaseOrderItem()->count());

        // 10 * 85000 = 850,000 + 11% PPN = 943,500
        $this->assertEquals(943500.0, (float) $po->total_amount);

        $response->assertRedirect(route('pembelian.po.show', $po));
        $response->assertSessionHas('success');
    }

    /**
     * 6. Alur approval PO: status berubah menjadi approved dan mencatat date_approved & approved_by
     */
    public function test_approve_purchase_order_flow(): void
    {
        $poNumber = 'PO-TEST-' . strtoupper(uniqid());
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'po_number' => $poNumber,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
            'total_amount' => 100000,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'original_unit_price' => 50000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'none',
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('pembelian.po.approve', $po));

        $response->assertSessionHas('success');

        $po->refresh();
        $this->assertEquals('approved', $po->status);
        $this->assertNotNull($po->date_approved);
        $this->assertEquals($this->user->id, $po->approved_by);
    }

    /**
     * 7. PO yang sudah disetujui tidak boleh dihapus
     */
    public function test_approved_po_cannot_be_deleted(): void
    {
        $poNumber = 'PO-TEST-' . strtoupper(uniqid());
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'po_number' => $poNumber,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'total_amount' => 100000,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('pembelian.po.destroy', $po));

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('purchase_orders', ['id' => $po->id]);
    }

    /**
     * 8. Alur penutupan PO (Close PO) dan request close
     */
    public function test_request_close_and_close_purchase_order_workflow(): void
    {
        $poNumber = 'PO-TEST-' . strtoupper(uniqid());
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'po_number' => $poNumber,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'total_amount' => 100000,
        ]);

        // Request Close
        $resRequest = $this->actingAs($this->user)
            ->post(route('pembelian.po.request-close', $po), [
                'reason' => 'Supplier kehabisan stok bahan baku',
            ]);

        $resRequest->assertSessionHas('success');
        $po->refresh();
        $this->assertEquals('request_close', $po->status);
        $this->assertEquals('Supplier kehabisan stok bahan baku', $po->close_reason);

        // Close PO
        $resClose = $this->actingAs($this->user)
            ->post(route('pembelian.po.close', $po), [
                'reason' => 'Disetujui penutupan oleh manajemen',
            ]);

        $resClose->assertSessionHas('success');
        $po->refresh();
        $this->assertEquals('closed', $po->status);
        $this->assertEquals('Disetujui penutupan oleh manajemen', $po->close_reason);
        $this->assertNotNull($po->closed_at);
        $this->assertEquals($this->user->id, $po->closed_by);
    }

    /**
     * 9. Endpoint cetak PDF PO merespons status 200
     */
    public function test_stream_purchase_order_pdf(): void
    {
        $poNumber = 'PO-TEST-' . strtoupper(uniqid());
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'po_number' => $poNumber,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'total_amount' => 100000,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'original_unit_price' => 100000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'none',
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('pembelian.po.pdf', $po));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * 10. Halaman show PO memuat visual stepper, ringkasan pemenuhan, dan tabel item
     */
    public function test_po_show_page_renders_stepper_and_tabs(): void
    {
        $poNumber = 'PO-TEST-' . strtoupper(uniqid());
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'po_number' => $poNumber,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'total_amount' => 250000,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 50000,
            'original_unit_price' => 50000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'none',
            'currency_id' => $this->currency->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('pembelian.po.show', $po));

        $response->assertStatus(200);
        $response->assertViewIs('pembelian.po.show');
        $response->assertSee($poNumber);
        $response->assertSee('Alur Pemrosesan Pesanan (Workflow)');
        $response->assertSee('Informasi Supplier');
        $response->assertSee('Rincian Barang Pesanan');
        $response->assertSee('Penerimaan Gudang');
    }

    /**
     * 11. Halaman edit PO dapat diakses untuk PO draf dan diblokir untuk PO disetujui
     */
    public function test_po_edit_page_loads_for_draft_and_blocks_approved(): void
    {
        $draftPo = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'po_number' => 'PO-TEST-' . strtoupper(uniqid()),
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
            'total_amount' => 50000,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $draftPo->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'unit_price' => 50000,
            'original_unit_price' => 50000,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'none',
            'currency_id' => $this->currency->id,
        ]);

        $resDraft = $this->actingAs($this->user)
            ->get(route('pembelian.po.edit', $draftPo));

        $resDraft->assertStatus(200);
        $resDraft->assertViewIs('pembelian.po.edit');
        $resDraft->assertSee('Ubah Pesanan Pembelian');
        $resDraft->assertSee($draftPo->po_number);

        // Edit PO approved harus diredirect dengan error
        $draftPo->update(['status' => 'approved']);
        $resApproved = $this->actingAs($this->user)
            ->get(route('pembelian.po.edit', $draftPo));

        $resApproved->assertRedirect(route('pembelian.po.show', $draftPo));
        $resApproved->assertSessionHas('error');
    }
}

