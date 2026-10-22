<?php

namespace Tests\Feature\Pembelian;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeOrderRequestWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Currency $currency;
    protected Supplier $supplier;
    protected Product $productA;
    protected Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-OR',
            'nama' => 'Cabang Test Order Request',
            'alamat' => 'Jl. Pengadaan No. 12',
            'status' => true,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->first() ?? Warehouse::create([
            'kode' => 'GDG-TEST-OR',
            'name' => 'Gudang Utama OR Test',
            'location' => 'Area Gudang Utama',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);

        $this->currency = Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Rupiah', 'symbol' => 'Rp', 'to_rupiah' => 1]
        );

        $this->supplier = Supplier::withoutGlobalScopes()->first() ?? Supplier::create([
            'code' => 'SUP-TEST-OR',
            'perusahaan' => 'PT Mitra Pengadaan Sejahtera',
            'kontak_person' => 'Budi Santoso',
            'phone' => '021-555666',
            'email' => 'supplier.test@example.com',
            'handphone' => '08123456789',
            'fax' => '021-555667',
            'npwp' => '01.234.567.8-901.000',
            'address' => 'Kawasan Industri Cikarang',
            'top_type' => 'cod',
            'tempo_hutang' => 14,
            'cabang_id' => $this->cabang->id,
        ]);

        $category = ProductCategory::firstOrCreate(
            ['kode' => 'CAT-OR'],
            ['name' => 'Kategori OR Test', 'kenaikan_harga' => 10]
        );

        $uom = UnitOfMeasure::firstOrCreate(
            ['name' => 'Pieces'],
            ['abbreviation' => 'Pcs']
        );

        $this->productA = Product::withoutGlobalScopes()->where('sku', 'PVC-05-TEST')->first() ?? Product::create([
            'name' => 'Pipa PVC 1/2 Test OR',
            'sku' => 'PVC-05-TEST',
            'kode_merk' => 'MRK-OR',
            'product_category_id' => $category->id,
            'unit_of_measure_id' => $uom->id,
            'uom_id' => $uom->id,
            'cost_price' => 25000,
            'selling_price' => 35000,
            'price_sell' => 35000,
            'price_buy' => 25000,
            'status' => 1,
            'is_active' => true,
        ]);

        $this->productB = Product::withoutGlobalScopes()->where('sku', 'PVC-075-TEST')->first() ?? Product::create([
            'name' => 'Pipa PVC 3/4 Test OR',
            'sku' => 'PVC-075-TEST',
            'kode_merk' => 'MRK-OR',
            'product_category_id' => $category->id,
            'unit_of_measure_id' => $uom->id,
            'uom_id' => $uom->id,
            'cost_price' => 35000,
            'selling_price' => 48000,
            'price_sell' => 48000,
            'price_buy' => 35000,
            'status' => 1,
            'is_active' => true,
        ]);

        $superRole = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);

        $this->user = User::withoutGlobalScopes()->where('email', 'purchasing.tester@dutatunggal.co.id')->first()
            ?? User::create([
                'username' => 'purchasing_tester',
                'first_name' => 'Tester',
                'last_name' => 'Purchasing',
                'email' => 'purchasing.tester@dutatunggal.co.id',
                'name' => 'Tester Purchasing',
                'password' => bcrypt('password123'),
                'cabang_id' => $this->cabang->id,
                'manage_type' => ['all'],
            ]);

        if (!$this->user->hasRole('Super Admin')) {
            $this->user->assignRole($superRole);
        }
    }

    /**
     * 1. Pengguna dapat membuka halaman indeks OR dan melihat KPI cards
     */
    public function test_user_can_view_order_request_index_and_kpi(): void
    {
        $response = $this->actingAs($this->user)->get(route('pembelian.or.index'));

        $response->assertStatus(200);
        $response->assertViewIs('pembelian.order-request.index');
        $response->assertSee('Permintaan Pembelian (Order Request)');
        $response->assertSee('Total Permintaan');
        $response->assertSee('Menunggu Otorisasi');
        $response->assertSee('Disetujui / Siap PO');
        $response->assertSee('Ditolak');
    }

    /**
     * 2. Pengguna dapat membuat Permintaan Pembelian baru sebagai Draf
     */
    public function test_user_can_create_order_request_as_draft(): void
    {
        $payload = [
            'cabang_id' => $this->cabang->id,
            'request_number' => 'OR-TEST-' . uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'required_date' => now()->addDays(7)->format('Y-m-d'),
            'purpose' => 'Persediaan Stok Awal Bulan Cabang',
            'note' => 'Mohon diprioritaskan',
            'action' => 'draft',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 50,
                    'unit_price' => 25000,
                    'supplier_id' => $this->supplier->id,
                    'note' => 'Spesifikasi standar SNI',
                ],
                [
                    'product_id' => $this->productB->id,
                    'quantity' => 30,
                    'unit_price' => 35000,
                    'supplier_id' => $this->supplier->id,
                    'note' => 'Ketebalan AW',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('pembelian.or.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('order_requests', [
            'request_number' => $payload['request_number'],
            'status' => 'draft',
            'purpose' => 'Persediaan Stok Awal Bulan Cabang',
        ]);

        $this->assertDatabaseHas('order_request_items', [
            'product_id' => $this->productA->id,
            'quantity' => 50,
            'unit_price' => 25000,
        ]);
    }

    /**
     * 3. Pengguna dapat membuat dan langsung mengajukan persetujuan (request_approve)
     */
    public function test_user_can_create_and_submit_for_approval(): void
    {
        $payload = [
            'cabang_id' => $this->cabang->id,
            'request_number' => 'OR-TEST-REQ-' . uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'required_date' => now()->addDays(5)->format('Y-m-d'),
            'purpose' => 'Permintaan Mendesak Cabang',
            'action' => 'request_approve',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 20,
                    'unit_price' => 25000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('pembelian.or.store'), $payload);

        $response->assertRedirect();
        $this->assertDatabaseHas('order_requests', [
            'request_number' => $payload['request_number'],
            'status' => 'request_approve',
        ]);
    }

    /**
     * 4. Validasi input: wajib cabang, tanggal, dan minimal 1 item dengan kuantitas > 0
     */
    public function test_validation_requires_at_least_one_item_and_positive_qty(): void
    {
        $invalidPayload = [
            'cabang_id' => '',
            'request_date' => '',
            'items' => [],
        ];

        $response = $this->actingAs($this->user)->post(route('pembelian.or.store'), $invalidPayload);
        $response->assertSessionHasErrors(['cabang_id', 'request_date', 'items']);
    }

    /**
     * 5. Pengguna dapat mengubah Draf Permintaan Pembelian
     */
    public function test_user_can_edit_draft_order_request(): void
    {
        $orderRequest = OrderRequest::create([
            'request_number' => 'OR-EDIT-' . uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'cabang_id' => $this->cabang->id,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->productA->id,
            'quantity' => 10,
            'unit_price' => 25000,
            'status' => 'draft',
        ]);

        $updatePayload = [
            'cabang_id' => $this->cabang->id,
            'request_date' => now()->format('Y-m-d'),
            'purpose' => 'Revisi Kebutuhan Cabang',
            'note' => 'Kuantitas ditambah',
            'action' => 'draft',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'quantity' => 45,
                    'unit_price' => 26000,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->put(route('pembelian.or.update', $orderRequest), $updatePayload);

        $response->assertRedirect(route('pembelian.or.show', $orderRequest));
        $this->assertDatabaseHas('order_requests', [
            'id' => $orderRequest->id,
            'purpose' => 'Revisi Kebutuhan Cabang',
        ]);

        $this->assertDatabaseHas('order_request_items', [
            'order_request_id' => $orderRequest->id,
            'quantity' => 45,
            'unit_price' => 26000,
        ]);
    }

    /**
     * 6. Pengguna berwenang dapat menyetujui Permintaan Pembelian (Approved)
     */
    public function test_authorized_user_can_approve_order_request(): void
    {
        $orderRequest = OrderRequest::create([
            'request_number' => 'OR-APPROVE-' . uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'cabang_id' => $this->cabang->id,
            'status' => 'request_approve',
            'created_by' => $this->user->id,
        ]);

        $item = OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->productA->id,
            'quantity' => 25,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->post(route('pembelian.or.approve', $orderRequest));

        $response->assertRedirect(route('pembelian.or.show', $orderRequest));
        $this->assertDatabaseHas('order_requests', [
            'id' => $orderRequest->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('order_request_items', [
            'id' => $item->id,
            'status' => 'approved',
            'approved_by' => $this->user->id,
        ]);
    }

    /**
     * 7. Pengguna berwenang dapat menolak Permintaan Pembelian dengan mencatat alasan (Rejected)
     */
    public function test_authorized_user_can_reject_order_request_with_reason(): void
    {
        $orderRequest = OrderRequest::create([
            'request_number' => 'OR-REJECT-' . uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'cabang_id' => $this->cabang->id,
            'status' => 'request_approve',
            'created_by' => $this->user->id,
        ]);

        $item = OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->productA->id,
            'quantity' => 15,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)->post(route('pembelian.or.reject', $orderRequest), [
            'rejection_note' => 'Stok di gudang pusat masih mencukupi, harap lakukan transfer gudang.',
        ]);

        $response->assertRedirect(route('pembelian.or.show', $orderRequest));
        $this->assertDatabaseHas('order_requests', [
            'id' => $orderRequest->id,
            'status' => 'rejected',
        ]);

        $this->assertDatabaseHas('order_request_items', [
            'id' => $item->id,
            'status' => 'rejected',
            'rejected_by' => $this->user->id,
            'rejection_note' => 'Stok di gudang pusat masih mencukupi, harap lakukan transfer gudang.',
        ]);
    }

    /**
     * 8. Halaman pembuatan PO dapat dibuka dengan prefilled data dari Order Request yang disetujui
     */
    public function test_user_can_open_po_create_prefilled_from_approved_order_request(): void
    {
        $orderRequest = OrderRequest::create([
            'request_number' => 'OR-FOR-PO-' . uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->productA->id,
            'supplier_id' => $this->supplier->id,
            'quantity' => 40,
            'unit_price' => 25000,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->user)->get(route('pembelian.po.create', [
            'order_request_id' => $orderRequest->id,
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('pembelian.po.create');
        $response->assertSee($orderRequest->request_number);
        $response->assertSee('Menerbitkan PO dari Permintaan');
    }

    /**
     * 9. Penerbitan PO menautkan refer_model ke Order Request dan mengupdate fulfilled_quantity
     */
    public function test_po_creation_links_to_order_request_and_updates_fulfillment(): void
    {
        $orderRequest = OrderRequest::create([
            'request_number' => 'OR-FULFILL-' . uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $orItem = OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->productA->id,
            'quantity' => 50,
            'fulfilled_quantity' => 0,
            'unit_price' => 25000,
            'status' => 'approved',
        ]);

        $poPayload = [
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => now()->format('Y-m-d'),
            'po_number' => 'PO-OR-TEST-' . uniqid(),
            'order_request_id' => $orderRequest->id,
            'action' => 'save_draft',
            'items' => [
                [
                    'product_id' => $this->productA->id,
                    'order_request_item_id' => $orItem->id,
                    'quantity' => 50,
                    'unit_price' => 25000,
                    'discount' => 0,
                    'tax' => 0,
                    'tipe_pajak' => 'none',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('pembelian.po.store'), $poPayload);

        $response->assertRedirect();

        $this->assertDatabaseHas('purchase_orders', [
            'po_number' => $poPayload['po_number'],
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $orderRequest->id,
        ]);

        $this->assertDatabaseHas('purchase_order_items', [
            'product_id' => $this->productA->id,
            'refer_item_model_type' => OrderRequestItem::class,
            'refer_item_model_id' => $orItem->id,
            'quantity' => 50,
        ]);

        // Pastikan fulfilled_quantity pada OR item terupdate
        $orItem->refresh();
        $this->assertEquals(50, (float) $orItem->fulfilled_quantity);

        // Pastikan status OR bertransisi ke complete
        $orderRequest->refresh();
        $this->assertEquals('complete', $orderRequest->status);
    }

    /**
     * 10. Streaming PDF dokumen Order Request menghasilkan HTTP 200
     */
    public function test_order_request_pdf_can_be_streamed(): void
    {
        $orderRequest = OrderRequest::create([
            'request_number' => 'OR-PDF-' . uniqid(),
            'request_date' => now()->format('Y-m-d'),
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        OrderRequestItem::create([
            'order_request_id' => $orderRequest->id,
            'product_id' => $this->productA->id,
            'quantity' => 10,
            'unit_price' => 25000,
            'status' => 'approved',
        ]);

        $response = $this->actingAs($this->user)->get(route('pembelian.or.pdf', $orderRequest));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}
