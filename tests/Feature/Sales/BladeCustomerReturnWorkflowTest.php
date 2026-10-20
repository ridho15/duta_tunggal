<?php

namespace Tests\Feature\Sales;

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\StockMovement;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladeCustomerReturnWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;
    protected SaleOrder $saleOrder;
    protected Invoice $invoice;
    protected InvoiceItem $invoiceItem;

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

        $this->user = User::withoutGlobalScopes()->first() ?? User::factory()->create([
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);
        if (! $this->user->hasRole('Super Admin')) {
            $this->user->assignRole($role);
        }

        $this->customer = Customer::withoutGlobalScopes()->first() ?? Customer::factory()->create([
            'name' => 'PT Pelanggan Sejahtera',
            'code' => 'CUST-001',
            'email' => 'pelanggan@example.com',
            'phone' => '081234567890',
            'address' => 'Jl. Basuki Rahmat No. 10',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->warehouse = Warehouse::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Warehouse::withoutGlobalScopes()->first()
            ?? Warehouse::create([
                'name' => 'Gudang Utama Surabaya',
                'kode' => 'GDG-SBY',
                'location' => 'Surabaya',
                'cabang_id' => $this->cabang->id,
                'status' => true,
            ]);

        $uom = UnitOfMeasure::withoutGlobalScopes()->first() ?? UnitOfMeasure::create([
            'name' => 'Pcs',
            'abbreviation' => 'pcs',
        ]);

        $category = ProductCategory::withoutGlobalScopes()->first() ?? ProductCategory::create([
            'name' => 'Umum',
            'code' => 'GEN',
        ]);

        $this->product = Product::withoutGlobalScopes()->where('cabang_id', $this->cabang->id)->first()
            ?? Product::withoutGlobalScopes()->first()
            ?? Product::create([
                'name' => 'Produk Tes Retur Penjualan',
                'sku' => 'SKU-RET-001',
                'uom_id' => $uom->id,
                'product_category_id' => $category->id,
                'sell_price' => 25000,
                'cost_price' => 15000,
                'status' => true,
                'cabang_id' => $this->cabang->id,
            ]);

        // Pastikan stok awal ada di gudang
        InventoryStock::firstOrCreate([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ], [
            'qty_available' => 50,
            'qty_reserved' => 0,
            'qty_min' => 5,
        ]);

        // Pastikan Chart of Accounts lengkap untuk CustomerReturnService
        foreach ([
            ['code' => '1140.01', 'name' => 'Persediaan Produk', 'type' => 'Asset'],
            ['code' => '1140.10', 'name' => 'Persediaan Barang Dagangan', 'type' => 'Asset'],
            ['code' => '5100.10', 'name' => 'Harga Pokok Penjualan (HPP)', 'type' => 'Expense'],
            ['code' => '4120.10', 'name' => 'Retur Penjualan', 'type' => 'Revenue'],
            ['code' => '2120.06', 'name' => 'PPN Keluaran', 'type' => 'Liability'],
            ['code' => '2110.01', 'name' => 'PPN Keluaran Standar', 'type' => 'Liability'],
            ['code' => '1120', 'name' => 'Piutang Dagang', 'type' => 'Asset'],
            ['code' => '1120.01', 'name' => 'Piutang Dagang Cabang', 'type' => 'Asset'],
        ] as $account) {
            ChartOfAccount::firstOrCreate(['code' => $account['code']], array_merge($account, ['is_active' => true]));
        }

        // Buat Sale Order sebagai rujukan invoice
        $this->saleOrder = SaleOrder::create([
            'so_number' => 'SO-RET-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'payment_term' => 'net_30',
            'subtotal' => 250000,
            'total_amount' => 277500,
            'created_by' => $this->user->id,
        ]);

        // Buat Invoice dan InvoiceItem untuk pengetesan retur
        $this->invoice = Invoice::create([
            'invoice_number' => 'INV-RET-' . strtoupper(uniqid()),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'cabang_id' => $this->cabang->id,
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $this->saleOrder->id,
            'status' => Invoice::STATUS_SENT,
            'subtotal' => 250000,
            'tax' => 27500,
            'total' => 277500,
            'created_by' => $this->user->id,
        ]);

        $this->invoiceItem = InvoiceItem::create([
            'invoice_id' => $this->invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 25000,
            'net_unit_price' => 25000,
            'cost_price' => 15000,
            'subtotal' => 250000,
            'tax_amount' => 27500,
            'total' => 277500,
        ]);

        // Pastikan AccountReceivable tersedia
        AccountReceivable::firstOrCreate(
            ['invoice_id' => $this->invoice->id],
            [
                'customer_id' => $this->customer->id,
                'total' => 277500,
                'paid' => 0,
                'remaining' => 277500,
                'total_original' => 277500,
                'paid_original' => 0,
                'remaining_original' => 277500,
                'exchange_rate' => 1.0,
                'status' => 'Belum Lunas',
                'cabang_id' => $this->cabang->id,
            ]
        );
    }

    public function test_customer_returns_index_page_loads_and_displays_kpis(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.returns.index'));

        $response->assertStatus(200);
        $response->assertSee('Retur Penjualan (Customer Return)');
        $response->assertSee('Ajukan Retur Baru');
        $response->assertSee('Total Pengajuan Retur');
        $response->assertSee('Menunggu Penerimaan', false);
        $response->assertSee('Disetujui (Siap Selesai)');
        $response->assertSee('Nilai Retur Selesai');
    }

    public function test_customer_returns_create_page_loads_with_customers_and_warehouses(): void
    {
        $response = $this->actingAs($this->user)->get(route('penjualan.returns.create'));

        $response->assertStatus(200);
        $response->assertSee('Ajukan Retur Penjualan');
        $response->assertSee($this->customer->name);
        $response->assertSee('Pilih Produk yang Dikembalikan');
    }

    public function test_customer_invoices_api_returns_invoices_with_returnable_quantities(): void
    {
        $response = $this->actingAs($this->user)->getJson(
            route('penjualan.returns.customerInvoices', $this->customer->id)
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'customer' => ['id', 'name', 'phone'],
            'invoices' => [
                '*' => [
                    'id',
                    'invoice_number',
                    'invoice_date',
                    'total',
                    'total_formatted',
                    'items' => [
                        '*' => [
                            'invoice_item_id',
                            'product_id',
                            'product_name',
                            'invoiced_qty',
                            'already_returned',
                            'max_returnable',
                        ],
                    ],
                ],
            ],
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['invoices']);
        $match = collect($data['invoices'])->firstWhere('id', $this->invoice->id);
        $this->assertNotNull($match);
        $this->assertEquals(10, $match['items'][0]['max_returnable']);
    }

    public function test_can_create_customer_return_and_validates_over_quantity(): void
    {
        // 1. Coba submit retur dengan quantity melebihi batas (15 > 10) -> Harus gagal
        $invalidPayload = [
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Barang rusak kemasan',
            'notes' => 'Pengajuan via test',
            'items' => [
                [
                    'invoice_item_id' => $this->invoiceItem->id,
                    'product_id' => $this->product->id,
                    'quantity' => 15,
                    'problem_description' => 'Kerusakan fisik',
                ],
            ],
        ];

        $responseInvalid = $this->actingAs($this->user)->post(route('penjualan.returns.store'), $invalidPayload);
        $responseInvalid->assertSessionHas('error');

        // 2. Submit retur dengan kuantitas sah (3 pcs <= 10 pcs)
        $validPayload = [
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Barang mengalami cacat manufaktur',
            'notes' => 'Retur sah 3 pcs',
            'items' => [
                [
                    'invoice_item_id' => $this->invoiceItem->id,
                    'product_id' => $this->product->id,
                    'quantity' => 3,
                    'problem_description' => 'Cacat fungsi',
                ],
            ],
        ];

        $responseValid = $this->actingAs($this->user)->post(route('penjualan.returns.store'), $validPayload);
        $responseValid->assertRedirect();

        $return = CustomerReturn::where('customer_id', $this->customer->id)
            ->where('invoice_id', $this->invoice->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($return);
        $this->assertEquals(CustomerReturn::STATUS_PENDING, $return->status);
        $this->assertCount(1, $return->customerReturnItems);
        $this->assertEquals(3, (float)$return->customerReturnItems->first()->quantity);
    }

    public function test_workflow_mark_received_and_start_qc(): void
    {
        $return = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber($this->cabang->id),
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Kemasan penyok',
            'status' => CustomerReturn::STATUS_PENDING,
        ]);

        $item = CustomerReturnItem::create([
            'customer_return_id' => $return->id,
            'invoice_item_id' => $this->invoiceItem->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'problem_description' => 'Kemasan penyok saat diterima',
        ]);

        // 1. Mark Received
        $respReceive = $this->actingAs($this->user)->post(route('penjualan.returns.markReceived', $return->id));
        $respReceive->assertRedirect();
        $this->assertEquals(CustomerReturn::STATUS_RECEIVED, $return->fresh()->status);
        $this->assertNotNull($return->fresh()->received_at);

        // 2. Start QC
        $respQc = $this->actingAs($this->user)->post(route('penjualan.returns.startQc', $return->id));
        $respQc->assertRedirect();
        $this->assertEquals(CustomerReturn::STATUS_QC_INSPECTION, $return->fresh()->status);
        $this->assertNotNull($return->fresh()->qc_inspected_at);
    }

    public function test_save_qc_inspection_results_and_decisions(): void
    {
        $return = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber($this->cabang->id),
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Test inspeksi QC',
            'status' => CustomerReturn::STATUS_QC_INSPECTION,
        ]);

        $item = CustomerReturnItem::create([
            'customer_return_id' => $return->id,
            'invoice_item_id' => $this->invoiceItem->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
        ]);

        $qcPayload = [
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'id' => $item->id,
                    'qc_result' => CustomerReturnItem::QC_RESULT_PASS,
                    'decision' => CustomerReturnItem::DECISION_REPLACE,
                    'qc_notes' => 'Barang lolos uji fisik, disetujui untuk penggantian',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('penjualan.returns.saveQc', $return->id), $qcPayload);
        $response->assertRedirect();

        $freshItem = $item->fresh();
        $this->assertEquals(CustomerReturnItem::QC_RESULT_PASS, $freshItem->qc_result);
        $this->assertEquals(CustomerReturnItem::DECISION_REPLACE, $freshItem->decision);
        $this->assertEquals('Barang lolos uji fisik, disetujui untuk penggantian', $freshItem->qc_notes);
    }

    public function test_approve_blocks_if_qc_incomplete_and_succeeds_when_complete(): void
    {
        $return = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber($this->cabang->id),
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Test approval',
            'status' => CustomerReturn::STATUS_QC_INSPECTION,
        ]);

        $item = CustomerReturnItem::create([
            'customer_return_id' => $return->id,
            'invoice_item_id' => $this->invoiceItem->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'qc_result' => null, // Belum QC
            'decision' => null,
        ]);

        // Coba approve tanpa QC -> Ditolak
        $respFail = $this->actingAs($this->user)->post(route('penjualan.returns.approve', $return->id));
        $respFail->assertSessionHas('error');
        $this->assertEquals(CustomerReturn::STATUS_QC_INSPECTION, $return->fresh()->status);

        // Lengkapi QC
        $item->update([
            'qc_result' => CustomerReturnItem::QC_RESULT_PASS,
            'decision' => CustomerReturnItem::DECISION_REPLACE,
        ]);

        // Approve kembali -> Sukses
        $respSuccess = $this->actingAs($this->user)->post(route('penjualan.returns.approve', $return->id));
        $respSuccess->assertRedirect();
        $this->assertEquals(CustomerReturn::STATUS_APPROVED, $return->fresh()->status);
        $this->assertNotNull($return->fresh()->approved_at);
    }

    public function test_complete_restores_inventory_stock_and_posts_balanced_journals(): void
    {
        $stockBefore = (float) InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('qty_available');

        $return = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber($this->cabang->id),
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Pengembalian barang ke stok',
            'status' => CustomerReturn::STATUS_APPROVED,
        ]);

        $item = CustomerReturnItem::create([
            'customer_return_id' => $return->id,
            'invoice_item_id' => $this->invoiceItem->id,
            'product_id' => $this->product->id,
            'quantity' => 4,
            'qc_result' => CustomerReturnItem::QC_RESULT_PASS,
            'decision' => CustomerReturnItem::DECISION_REPLACE,
        ]);

        $arBefore = AccountReceivable::where('invoice_id', $this->invoice->id)->first();
        $remainingBefore = (float) $arBefore->remaining;

        // Eksekusi complete
        $response = $this->actingAs($this->user)->post(route('penjualan.returns.complete', $return->id));
        $response->assertRedirect();

        $freshReturn = $return->fresh();
        $this->assertEquals(CustomerReturn::STATUS_COMPLETED, $freshReturn->status);
        $this->assertNotNull($freshReturn->stock_restored_at);

        // Verifikasi stok dipulihkan (+4)
        $stockAfter = (float) InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('qty_available');
        $this->assertEquals($stockBefore + 4, $stockAfter);

        // Verifikasi stock movement tercatat
        $hasMovement = StockMovement::where('from_model_type', CustomerReturn::class)
            ->where('from_model_id', $return->id)
            ->where('quantity', 4)
            ->exists();
        $this->assertTrue($hasMovement);

        // Verifikasi AccountReceivable dikurangi
        $arAfter = AccountReceivable::where('invoice_id', $this->invoice->id)->first();
        $this->assertLessThan($remainingBefore, (float)$arAfter->remaining);

        // Verifikasi jurnal akuntansi terposting seimbang
        $journals = JournalEntry::where('source_type', CustomerReturn::class)
            ->where('source_id', $return->id)
            ->get();
        $this->assertNotEmpty($journals);

        $totDebit = $journals->where('type', 'debit')->sum('amount');
        $totCredit = $journals->where('type', 'credit')->sum('amount');
        $this->assertEqualsWithDelta($totDebit, $totCredit, 0.05);
    }

    public function test_reject_customer_return_with_reason(): void
    {
        $return = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber($this->cabang->id),
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Test penolakan',
            'status' => CustomerReturn::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->user)->post(route('penjualan.returns.reject', $return->id), [
            'rejection_reason' => 'Barang rusak karena kelalaian pengguna saat pemakaian',
        ]);

        $response->assertRedirect();
        $freshReturn = $return->fresh();
        $this->assertEquals(CustomerReturn::STATUS_REJECTED, $freshReturn->status);
        $this->assertStringContainsString('Barang rusak karena kelalaian pengguna', $freshReturn->notes);
    }

    public function test_stream_customer_return_pdf(): void
    {
        $return = CustomerReturn::create([
            'return_number' => CustomerReturn::generateReturnNumber($this->cabang->id),
            'customer_id' => $this->customer->id,
            'invoice_id' => $this->invoice->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'return_date' => now()->toDateString(),
            'reason' => 'Test cetak PDF retur',
            'status' => CustomerReturn::STATUS_APPROVED,
        ]);

        CustomerReturnItem::create([
            'customer_return_id' => $return->id,
            'invoice_item_id' => $this->invoiceItem->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'qc_result' => CustomerReturnItem::QC_RESULT_PASS,
            'decision' => CustomerReturnItem::DECISION_REPLACE,
        ]);

        $response = $this->actingAs($this->user)->get(route('penjualan.returns.pdf', $return->id));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }
}
