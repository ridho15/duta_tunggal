<?php

namespace Tests\Feature\Purchasing;

use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BladePurchaseReturnWorkflowTest extends TestCase
{
    protected User $user;
    protected Cabang $cabang;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Currency $currency;
    protected ChartOfAccount $apCoa;
    protected ChartOfAccount $invCoa;
    protected ChartOfAccount $ppnCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabang = Cabang::withoutGlobalScopes()->first() ?? Cabang::create([
            'kode' => 'CBG-TEST-RET',
            'nama' => 'Cabang Test Retur',
            'alamat' => 'Jl. Test Retur No. 1',
            'status' => true,
        ]);

        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $perms = [
            'view any purchase return',
            'view purchase return',
            'create purchase return',
            'update purchase return',
            'delete purchase return',
            'approve purchase return',
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
            'code' => 'SUP-RET-01',
            'perusahaan' => 'PT Supplier Utama Retur',
            'nama_bank' => 'BCA',
            'nomor_rekening' => '8880192837',
            'atas_nama' => 'PT Supplier Utama Retur',
            'tempo_hutang' => 30,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->warehouse = Warehouse::where('status', 1)->first() ?? Warehouse::create([
            'kode' => 'GDG-RET-01',
            'name' => 'Gudang Utama Retur',
            'cabang_id' => $this->cabang->id,
            'status' => 1,
        ]);

        $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::create([
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'exchange_rate' => 1,
            'status' => 1,
        ]);

        $uom = UnitOfMeasure::first() ?? UnitOfMeasure::create(['name' => 'Batang', 'code' => 'BTG']);

        $this->apCoa = ChartOfAccount::firstOrCreate([
            'code' => config('coa.accounts_payable', '21100'),
        ], [
            'name' => 'Hutang Dagang',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $this->invCoa = ChartOfAccount::firstOrCreate([
            'code' => config('coa.inventory', '1140.01'),
        ], [
            'name' => 'Persediaan Barang Dagang',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->ppnCoa = ChartOfAccount::firstOrCreate([
            'code' => config('coa.ppn_masukan', '1170.06'),
        ], [
            'name' => 'PPN Masukan',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->product = Product::first() ?? Product::create([
            'code' => 'PRD-RET-001',
            'name' => 'Besi Beton Ulir 12mm',
            'uom_id' => $uom->id,
            'cabang_id' => $this->cabang->id,
            'status' => 1,
            'inventory_coa_id' => $this->invCoa->id,
        ]);

        // Pastikan stok awal tersedia di gudang
        InventoryStock::updateOrCreate([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
        ], [
            'qty_available' => 150,
            'qty_reserved' => 0,
            'qty_min' => 0,
        ]);
    }

    /**
     * Helper membuat PurchaseOrder + PurchaseReceipt selesai
     */
    protected function createCompletedReceipt(float $qty = 50, float $unitPrice = 100000): array
    {
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'completed',
            'total_amount' => $qty * $unitPrice,
        ]);

        \App\Models\PurchaseOrderCurrency::create([
            'purchase_order_id' => $po->id,
            'currency_id' => $this->currency->id,
            'nominal' => 1.0,
            'type' => 'base',
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'original_unit_price' => $unitPrice,
            'discount' => 0,
            'tax' => 0,
            'tipe_pajak' => 'none',
            'currency_id' => $this->currency->id,
            'subtotal' => $qty * $unitPrice,
        ]);

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'GRN-TEST-' . uniqid(),
            'purchase_order_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'receipt_date' => now()->subDays(3),
            'currency_id' => $this->currency->id,
            'status' => 'completed',
            'received_by' => $this->user->id,
        ]);

        $receiptItem = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_received' => $qty,
            'qty_accepted' => $qty,
            'status' => 'completed',
        ]);

        return [$po, $receipt, $receiptItem];
    }

    /**
     * Test 1: User dapat melihat halaman index retur pembelian dengan 4 KPI cards & tabs
     */
    public function test_user_can_view_purchase_returns_index_with_kpi_and_status_tabs(): void
    {
        $response = $this->actingAs($this->user)->get(route('pembelian.returns.index'));

        $response->assertOk();
        $response->assertViewIs('pembelian.retur.index');
        $response->assertSee('Retur Pembelian &amp; Debit Note', false);
        $response->assertSee('Total Nilai Disetujui');
        $response->assertSee('Retur Bulan Ini');
        $response->assertSee('Menunggu Persetujuan');
        $response->assertSee('Draft Disimpan');
        $response->assertSee('+ Buat Retur Pembelian');
    }

    /**
     * Test 2: User dapat memfilter data index berdasarkan tab dan supplier
     */
    public function test_user_can_filter_purchase_returns_by_tab_and_supplier(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(30, 50000);

        $retDraft = PurchaseReturn::create([
            'nota_retur' => 'NR-DRAFT-' . uniqid(),
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $retApproved = PurchaseReturn::create([
            'nota_retur' => 'NR-APP-' . uniqid(),
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        // Filter tab=draft
        $respDraft = $this->actingAs($this->user)->get(route('pembelian.returns.index', ['tab' => 'draft']));
        $respDraft->assertOk();
        $respDraft->assertSee($retDraft->nota_retur);
        $respDraft->assertDontSee($retApproved->nota_retur);

        // Filter tab=approved
        $respApp = $this->actingAs($this->user)->get(route('pembelian.returns.index', ['tab' => 'approved']));
        $respApp->assertOk();
        $respApp->assertSee($retApproved->nota_retur);
        $respApp->assertDontSee($retDraft->nota_retur);
    }

    /**
     * Test 3: User dapat melihat halaman pembuatan retur pembelian
     */
    public function test_user_can_view_create_return_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('pembelian.returns.create'));

        $response->assertOk();
        $response->assertViewIs('pembelian.retur.create');
        $response->assertSee('Buat Dokumen Retur Pembelian &amp; Debit Note', false);
        $response->assertSee('Pemasok (Supplier / Vendor)');
        $response->assertSee('Surat Jalan Masuk / Penerimaan Barang (GRN)');
    }

    /**
     * Test 4: Endpoint AJAX supplier-receipts mengembalikan GRN berstatus completed/partial
     */
    public function test_ajax_supplier_receipts_returns_completed_or_partial_grn(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(40, 75000);

        $response = $this->actingAs($this->user)->getJson(route('pembelian.returns.supplier-receipts', $this->supplier->id));

        $response->assertOk();
        $response->assertJsonStructure([
            'supplier' => ['id', 'code', 'perusahaan'],
            'receipts' => [
                '*' => ['id', 'receipt_number', 'po_number', 'receipt_date', 'display_label'],
            ],
        ]);

        $receiptIds = collect($response->json('receipts'))->pluck('id')->all();
        $this->assertContains($receipt->id, $receiptIds);
    }

    /**
     * Test 5: Endpoint AJAX receipt-items mengembalikan item penerimaan dengan batas max_returnable
     */
    public function test_ajax_receipt_items_returns_items_with_max_returnable_qty(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(50, 100000);

        $response = $this->actingAs($this->user)->getJson(route('pembelian.returns.receipt-items', $receipt->id));

        $response->assertOk();
        $response->assertJsonStructure([
            'receipt' => ['id', 'receipt_number', 'supplier_name', 'warehouse_name'],
            'items' => [
                '*' => [
                    'purchase_receipt_item_id',
                    'product_id',
                    'product_code',
                    'product_name',
                    'uom_name',
                    'qty_accepted',
                    'already_returned',
                    'max_returnable',
                    'unit_price',
                    'unit_price_formatted',
                ],
            ],
        ]);

        $firstItem = $response->json('items.0');
        $this->assertEquals($rcpItem->id, $firstItem['purchase_receipt_item_id']);
        $this->assertEquals(50.0, (float)$firstItem['max_returnable']);
    }

    /**
     * Test 6: Validasi gagal saat tidak ada item barang yang dipilih
     */
    public function test_store_purchase_return_fails_when_items_empty(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(50, 100000);

        $payload = [
            'supplier_id' => $this->supplier->id,
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'items' => [],
        ];

        $response = $this->actingAs($this->user)->post(route('pembelian.returns.store'), $payload);

        $response->assertSessionHasErrors(['items']);
    }

    /**
     * Test 7: Validasi gagal jika qty_returned melebihi max_returnable
     */
    public function test_store_purchase_return_fails_when_qty_returned_exceeds_max_returnable(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(10, 100000);

        $payload = [
            'supplier_id' => $this->supplier->id,
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_receipt_item_id' => $rcpItem->id,
                    'product_id' => $this->product->id,
                    'qty_returned' => 15, // Melebihi 10
                    'unit_price' => 100000,
                    'reason' => 'Barang retak',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('pembelian.returns.store'), $payload);

        $response->assertSessionHasErrors(['items.0.qty_returned']);
    }

    /**
     * Test 8: User dapat menyimpan draft retur pembelian
     */
    public function test_user_can_store_draft_purchase_return(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(20, 100000);

        $payload = [
            'supplier_id' => $this->supplier->id,
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'action' => 'save_draft',
            'delivery_note' => 'SJ-RET-001',
            'notes' => 'Catatan retur pembelian test draft',
            'items' => [
                [
                    'purchase_receipt_item_id' => $rcpItem->id,
                    'product_id' => $this->product->id,
                    'qty_returned' => 5,
                    'unit_price' => 100000,
                    'reason' => 'Salah ukuran',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('pembelian.returns.store'), $payload);

        $this->assertDatabaseHas('purchase_returns', [
            'purchase_receipt_id' => $receipt->id,
            'status' => 'draft',
            'delivery_note' => 'SJ-RET-001',
        ]);

        $created = PurchaseReturn::latest('id')->first();
        $response->assertRedirect(route('pembelian.returns.show', $created->id));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('purchase_return_items', [
            'purchase_return_id' => $created->id,
            'qty_returned' => 5,
            'unit_price' => 100000,
        ]);
    }

    /**
     * Test 9: User dapat langsung menyimpan dan mengajukan retur (save_and_submit)
     */
    public function test_user_can_store_and_submit_purchase_return_immediately(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(20, 100000);

        $payload = [
            'supplier_id' => $this->supplier->id,
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'action' => 'save_and_submit',
            'items' => [
                [
                    'purchase_receipt_item_id' => $rcpItem->id,
                    'product_id' => $this->product->id,
                    'qty_returned' => 4,
                    'unit_price' => 100000,
                    'reason' => 'Barang rusak',
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post(route('pembelian.returns.store'), $payload);

        $this->assertDatabaseHas('purchase_returns', [
            'purchase_receipt_id' => $receipt->id,
            'status' => 'pending_approval',
        ]);

        $created = PurchaseReturn::latest('id')->first();
        $response->assertRedirect(route('pembelian.returns.show', $created->id));
    }

    /**
     * Test 10: User dapat mengajukan persetujuan atas retur berstatus draft (submit for approval)
     */
    public function test_user_can_submit_draft_purchase_return_for_approval(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(20, 100000);

        $ret = PurchaseReturn::create([
            'nota_retur' => 'NR-SUBMIT-' . uniqid(),
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $ret->id,
            'purchase_receipt_item_id' => $rcpItem->id,
            'product_id' => $this->product->id,
            'qty_returned' => 3,
            'unit_price' => 100000,
            'reason' => 'Cacat pabrik',
        ]);

        $response = $this->actingAs($this->user)->post(route('pembelian.returns.submit', $ret->id));

        $response->assertRedirect(route('pembelian.returns.show', $ret->id));
        $this->assertEquals('pending_approval', $ret->fresh()->status);
    }

    /**
     * Test 11: User dapat menyetujui retur (approve), memicu pengurangan stok & posting jurnal GL
     */
    public function test_user_can_approve_purchase_return_triggering_stock_reduction_and_gl_journal(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(20, 100000);

        // Buat Invoice dan AccountPayable terkait agar pemotongan AP dan jurnal Hutang Dagang aktif
        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-' . uniqid(),
            'supplier_id' => $this->supplier->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => 2000000,
            'total' => 2000000,
            'status' => Invoice::STATUS_SENT,
            'due_date' => now()->addDays(30)->toDateString(),
            'cabang_id' => $this->cabang->id,
            'from_model_type' => PurchaseReceipt::class,
            'from_model_id' => $receipt->id,
            'purchase_receipts' => [$receipt->id],
        ]);

        $ap = AccountPayable::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total' => 2000000,
            'remaining' => 2000000,
            'paid' => 0,
            'status' => 'unpaid',
            'cabang_id' => $this->cabang->id,
        ]);

        $ret = PurchaseReturn::create([
            'nota_retur' => 'NR-APP-' . uniqid(),
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'status' => 'pending_approval',
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $ret->id,
            'purchase_receipt_item_id' => $rcpItem->id,
            'product_id' => $this->product->id,
            'qty_returned' => 5,
            'unit_price' => 100000,
            'reason' => 'Barang retak',
        ]);

        $stockBefore = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('qty_available');

        $response = $this->actingAs($this->user)->post(route('pembelian.returns.approve', $ret->id), [
            'approval_notes' => 'Disetujui untuk dipotongkan ke tagihan faktur.',
        ]);

        $response->assertRedirect(route('pembelian.returns.show', $ret->id));
        $response->assertSessionHas('success');

        $freshRet = $ret->fresh();
        $this->assertEquals('approved', $freshRet->status);
        $this->assertEquals($this->user->id, $freshRet->approved_by);

        // Verifikasi pengurangan stok gudang
        $stockAfter = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('qty_available');
        $this->assertEquals($stockBefore - 5, $stockAfter);

        // Verifikasi mutasi stok tercatat
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $this->product->id,
            'type' => 'purchase_return',
            'quantity' => 5,
        ]);

        // Verifikasi pemotongan hutang AP (5 * 100.000 = 500.000)
        $this->assertEquals(1500000, (float)$ap->fresh()->remaining);

        // Verifikasi Jurnal Akuntansi Buku Besar terbit
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => PurchaseReturn::class,
            'source_id' => $ret->id,
            'journal_type' => 'purchase_return',
        ]);
    }

    /**
     * Test 12: User dapat menolak retur (reject) dengan catatan alasan penolakan wajib
     */
    public function test_user_can_reject_purchase_return_with_mandatory_rejection_notes(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(20, 100000);

        $ret = PurchaseReturn::create([
            'nota_retur' => 'NR-REJ-' . uniqid(),
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'status' => 'pending_approval',
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        // Validasi gagal jika tanpa alasan
        $failResp = $this->actingAs($this->user)->post(route('pembelian.returns.reject', $ret->id), [
            'rejection_notes' => '',
        ]);
        $failResp->assertSessionHasErrors(['rejection_notes']);

        // Berhasil dengan alasan valid
        $successResp = $this->actingAs($this->user)->post(route('pembelian.returns.reject', $ret->id), [
            'rejection_notes' => 'Barang sudah digunakan di lapangan, tidak dapat diretur.',
        ]);

        $successResp->assertRedirect(route('pembelian.returns.show', $ret->id));
        $this->assertEquals('rejected', $ret->fresh()->status);
        $this->assertEquals('Barang sudah digunakan di lapangan, tidak dapat diretur.', $ret->fresh()->rejection_notes);
    }

    /**
     * Test 13: User dapat memperbarui data resolusi vendor & pelacakan debit note
     */
    public function test_user_can_update_vendor_resolution_and_credit_note_tracking(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(20, 100000);

        $ret = PurchaseReturn::create([
            'nota_retur' => 'NR-RES-' . uniqid(),
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $payload = [
            'supplier_response' => 'potong_hutang',
            'credit_note_number' => 'CN-VEND-8899',
            'credit_note_date' => now()->toDateString(),
            'credit_note_amount' => 500000,
            'delivery_note' => 'SJ-PENGEMBALIAN-009',
            'shipping_details' => 'Kurir Internal - Pak Mamat',
            'tracking_notes' => 'Supplier telah mengonfirmasi pemotongan pada invoice berikutnya.',
        ];

        $response = $this->actingAs($this->user)->post(route('pembelian.returns.resolution', $ret->id), $payload);

        $response->assertRedirect(route('pembelian.returns.show', $ret->id));
        $this->assertDatabaseHas('purchase_returns', [
            'id' => $ret->id,
            'credit_note_number' => 'CN-VEND-8899',
            'supplier_response' => 'potong_hutang',
        ]);
    }

    /**
     * Test 14: User dapat menghapus draft retur, tetapi dilarang menghapus retur yang sudah disetujui
     */
    public function test_user_can_delete_draft_return_and_cannot_delete_approved_return(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(20, 100000);

        $retDraft = PurchaseReturn::create([
            'nota_retur' => 'NR-DEL-DRAFT-' . uniqid(),
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $delDraftResp = $this->actingAs($this->user)->delete(route('pembelian.returns.destroy', $retDraft->id));
        $delDraftResp->assertRedirect(route('pembelian.returns.index'));
        $this->assertSoftDeleted('purchase_returns', ['id' => $retDraft->id]);

        $retApproved = PurchaseReturn::create([
            'nota_retur' => 'NR-DEL-APP-' . uniqid(),
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $delAppResp = $this->actingAs($this->user)->delete(route('pembelian.returns.destroy', $retApproved->id));
        $delAppResp->assertSessionHas('error');
        $this->assertDatabaseHas('purchase_returns', ['id' => $retApproved->id]);
    }

    /**
     * Test 15: User dapat mengunduh atau streaming PDF Nota Retur Pembelian
     */
    public function test_user_can_stream_pdf_for_purchase_return(): void
    {
        [$po, $receipt, $rcpItem] = $this->createCompletedReceipt(10, 120000);

        $ret = PurchaseReturn::create([
            'nota_retur' => 'NR-PDF-' . uniqid(),
            'purchase_receipt_id' => $receipt->id,
            'return_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'cabang_id' => $this->cabang->id,
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $ret->id,
            'purchase_receipt_item_id' => $rcpItem->id,
            'product_id' => $this->product->id,
            'qty_returned' => 2,
            'unit_price' => 120000,
            'reason' => 'Uji PDF Retur',
        ]);

        $response = $this->actingAs($this->user)->get(route('pembelian.returns.pdf', $ret->id));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
