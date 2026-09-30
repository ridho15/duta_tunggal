<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoiceResource\Pages\EditSalesInvoice;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Deposit;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\OrderRequest;
use App\Models\OrderRequestItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Observers\InvoiceObserver;
use App\Services\CustomerMerger;
use App\Services\LedgerPostingService;
use App\Services\StockAdjustmentService;
use App\Services\StockOpnameService;
use App\Services\StockTransferService;
use App\Services\SuratJalanService;
use App\Support\WarehouseStockOptions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Sprint1To4AdversarialNegativeAndEdgeTest extends TestCase
{
    private Cabang $cabang1;
    private Cabang $cabang2;
    private User $user;
    private Warehouse $warehouse1;
    private Warehouse $warehouse2;
    private Rak $rak1;
    private UnitOfMeasure $uom;
    private ProductCategory $category;
    private Product $product;
    private ChartOfAccount $invCoa;
    private ChartOfAccount $cogsCoa;
    private ChartOfAccount $goodsDeliveryCoa;
    private ChartOfAccount $revCoa;
    private ChartOfAccount $arCoa;
    private ChartOfAccount $apCoa;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::firstOrCreate(
            ['code' => 'IDR'],
            [
                'name' => 'Indonesian Rupiah',
                'symbol' => 'Rp',
                'exchange_rate' => 1.0,
                'is_default' => true,
                'status' => 1,
            ]
        );

        $this->cabang1 = Cabang::firstOrCreate(
            ['kode' => 'CBG-ADV-1'],
            ['nama' => 'Cabang Adversarial 1', 'alamat' => 'Jl. Uji Negatif 1', 'status' => 1, 'lihat_stok_cabang_lain' => true]
        );

        $this->cabang2 = Cabang::firstOrCreate(
            ['kode' => 'CBG-ADV-2'],
            ['nama' => 'Cabang Adversarial 2', 'alamat' => 'Jl. Uji Negatif 2', 'status' => 1, 'lihat_stok_cabang_lain' => true]
        );

        $this->user = User::factory()->create([
            'username' => 'adv_tester_' . uniqid(),
            'email' => 'adv_tester_' . uniqid() . '@example.com',
            'kode_user' => 'ADV' . strtoupper(substr(uniqid(), -3)),
            'cabang_id' => $this->cabang1->id,
            'manage_type' => 'all',
        ]);
        Auth::login($this->user);

        $this->customer = Customer::factory()->create([
            'cabang_id' => $this->cabang1->id,
        ]);

        $this->warehouse1 = Warehouse::firstOrCreate(
            ['name' => 'Gudang Adversarial 1'],
            ['kode' => 'WH-ADV-1', 'cabang_id' => $this->cabang1->id, 'location' => 'Lokasi 1', 'status' => 1]
        );

        $this->warehouse2 = Warehouse::firstOrCreate(
            ['name' => 'Gudang Adversarial 2'],
            ['kode' => 'WH-ADV-2', 'cabang_id' => $this->cabang1->id, 'location' => 'Lokasi 2', 'status' => 1]
        );

        $this->rak1 = Rak::firstOrCreate(
            ['warehouse_id' => $this->warehouse1->id, 'name' => 'Rak A1'],
            ['code' => 'R-A1', 'status' => 1]
        );

        $this->uom = UnitOfMeasure::firstOrCreate(
            ['name' => 'Adversarial Unit'],
            ['abbreviation' => 'ADVU']
        );

        $this->category = ProductCategory::firstOrCreate(
            ['name' => 'Kategori Adversarial'],
            ['kode' => 'KAT-ADV']
        );

        // Core Accounting COAs
        $this->invCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.10'],
            ['name' => 'Persediaan Barang Dagangan', 'type' => 'Asset', 'status' => 1]
        );
        $this->cogsCoa = ChartOfAccount::firstOrCreate(
            ['code' => '5000'],
            ['name' => 'Beban Pokok Penjualan', 'type' => 'Expense', 'status' => 1]
        );
        $this->goodsDeliveryCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.20'],
            ['name' => 'Pengiriman Barang Penjualan', 'type' => 'Asset', 'status' => 1]
        );
        $this->revCoa = ChartOfAccount::firstOrCreate(
            ['code' => '4000'],
            ['name' => 'Pendapatan Penjualan', 'type' => 'Revenue', 'status' => 1]
        );
        $this->arCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1120'],
            ['name' => 'Piutang Usaha', 'type' => 'Asset', 'status' => 1]
        );
        $this->apCoa = ChartOfAccount::firstOrCreate(
            ['code' => '2110'],
            ['name' => 'Hutang Dagang', 'type' => 'Liability', 'status' => 1]
        );

        $this->product = Product::create([
            'name' => 'Produk Adversarial ' . uniqid(),
            'sku' => 'SKU-ADV-' . uniqid(),
            'uom_id' => $this->uom->id,
            'product_category_id' => $this->category->id,
            'cost_price' => 50000,
            'sell_price' => 80000,
            'cabang_id' => $this->cabang1->id,
            'kode_merk' => 'ADV',
            'is_manufacture' => false,
            'is_raw_material' => false,
            'is_active' => true,
            'inventory_coa_id' => $this->invCoa->id,
            'cogs_coa_id' => $this->cogsCoa->id,
            'goods_delivery_coa_id' => $this->goodsDeliveryCoa->id,
            'sales_coa_id' => $this->revCoa->id,
        ]);
    }

    // =========================================================================
    // SECTION 1: SPRINT 1 FINANCIAL INTEGRITY (NEGATIVE & EDGE TESTS)
    // =========================================================================

    /**
     * Negative S1.1: Modifying/editing a finalized invoice (sent, paid, partially_paid) is forbidden.
     */
    public function test_negative_finalized_sales_invoice_edit_is_blocked(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-ADV-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang1->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 100000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-LOCKED-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'status' => Invoice::STATUS_SENT,
            'cabang_id' => $this->cabang1->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 100000,
            'total' => 100000,
        ]);

        $page = new class extends EditSalesInvoice {
            public $recordInstance;
            public function setRecordForTest($record) { $this->recordInstance = $record; }
            public function getRecord(): \Illuminate\Database\Eloquent\Model { return $this->recordInstance; }
            public function redirect($url, $navigate = false): void {
                throw new \Illuminate\Http\Exceptions\HttpResponseException(redirect($url));
            }
        };

        $page->setRecordForTest($invoice);

        $this->expectException(\Illuminate\Http\Exceptions\HttpResponseException::class);
        $page->authorizeAccess();
    }

    /**
     * Edge S1.2: Draft invoice must NEVER create GL journal entries or AP/AR records.
     */
    public function test_edge_draft_invoice_never_creates_gl_or_ap_ar(): void
    {
        $draftInvoice = Invoice::create([
            'invoice_number' => 'INV-DRAFT-' . uniqid(),
            'status' => Invoice::STATUS_DRAFT,
            'cabang_id' => $this->cabang1->id,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 500000,
            'total' => 500000,
            'from_model_type' => SaleOrder::class,
            'from_model_id' => 9999,
        ]);

        $this->assertSame(0, JournalEntry::where('source_type', Invoice::class)->where('source_id', $draftInvoice->id)->count());
        $this->assertDatabaseMissing('account_receivables', ['invoice_id' => $draftInvoice->id]);
        $this->assertDatabaseMissing('account_payables', ['invoice_id' => $draftInvoice->id]);
    }

    /**
     * Edge S1.3: Double posting of sales invoice is strictly idempotent (no duplicated debit/credit).
     */
    public function test_edge_double_posting_sales_invoice_is_strictly_idempotent(): void
    {
        $customer = Customer::factory()->create(['cabang_id' => $this->cabang1->id]);

        $so = SaleOrder::create([
            'so_number' => 'SO-IDEM-' . uniqid(),
            'customer_id' => $customer->id,
            'cabang_id' => $this->cabang1->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 80000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-IDEM-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'status' => Invoice::STATUS_SENT,
            'cabang_id' => $this->cabang1->id,
            'customer_name' => $customer->name,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 80000,
            'total' => 80000,
            'tax' => 0,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 80000,
            'subtotal' => 80000,
            'total' => 80000,
            'cost_price' => 50000,
        ]);

        $observer = new InvoiceObserver();
        $observer->postSalesInvoice($invoice);

        $initialJournalsCount = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->count();
        $this->assertGreaterThan(0, $initialJournalsCount);

        // Attempt second posting
        $observer->postSalesInvoice($invoice);

        $secondJournalsCount = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->count();
        $this->assertSame($initialJournalsCount, $secondJournalsCount, 'Double posting must be rejected idempotently without adding duplicate journals');
    }

    /**
     * Negative S1.4: Stock adjustment approval requires at least one item (cannot approve empty adjustment).
     */
    public function test_negative_stock_adjustment_cannot_be_approved_without_items(): void
    {
        $adj = StockAdjustment::create([
            'adjustment_number' => 'ADJ-EMPTY-' . uniqid(),
            'warehouse_id' => $this->warehouse1->id,
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'increase',
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Tambahkan minimal satu item');

        app(StockAdjustmentService::class)->approveStockAdjustment($adj);
    }

    /**
     * Negative S1.5: Stock adjustment approval fails if document is not in draft status.
     */
    public function test_negative_stock_adjustment_approval_fails_if_not_draft(): void
    {
        $adj = StockAdjustment::create([
            'adjustment_number' => 'ADJ-NOTDRAFT-' . uniqid(),
            'warehouse_id' => $this->warehouse1->id,
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'increase',
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adj->id,
            'product_id' => $this->product->id,
            'quantity_system' => 10,
            'quantity_actual' => 15,
            'difference_qty' => 5,
            'unit_cost' => 50000,
            'difference_value' => 250000,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Hanya stock adjustment berstatus draft yang dapat disetujui');

        app(StockAdjustmentService::class)->approveStockAdjustment($adj);
    }

    // =========================================================================
    // SECTION 2: SPRINT 2 LOGISTICS & STOCK CONTROL (NEGATIVE & EDGE TESTS)
    // =========================================================================

    /**
     * Negative S2.1: Requesting stock transfer without items must be rejected.
     */
    public function test_negative_stock_transfer_request_rejected_without_items(): void
    {
        $transfer = StockTransfer::create([
            'transfer_number' => StockTransfer::generateTransferNumber(),
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Draft',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Tambahkan minimal satu item');

        app(StockTransferService::class)->requestTransfer($transfer);
    }

    /**
     * Negative S2.2: Stock transfer item with quantity <= 0 must be rejected.
     */
    public function test_negative_stock_transfer_item_with_zero_or_negative_quantity_rejected(): void
    {
        $transfer = StockTransfer::create([
            'transfer_number' => StockTransfer::generateTransferNumber(),
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Draft',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'quantity' => 0,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Qty transfer harus lebih besar dari 0');

        app(StockTransferService::class)->requestTransfer($transfer);
    }

    /**
     * Negative S2.3: Stock transfer to identical source and destination warehouse & rack must be rejected.
     */
    public function test_negative_stock_transfer_to_same_warehouse_and_rak_rejected(): void
    {
        $transfer = StockTransfer::create([
            'transfer_number' => StockTransfer::generateTransferNumber(),
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse1->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Draft',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->warehouse1->id,
            'from_rak_id' => $this->rak1->id,
            'to_warehouse_id' => $this->warehouse1->id,
            'to_rak_id' => $this->rak1->id,
            'quantity' => 5,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Gudang dan rak tujuan harus berbeda dari gudang dan rak asal');

        app(StockTransferService::class)->requestTransfer($transfer);
    }

    /**
     * Negative S2.4: Stock transfer approval fails when source warehouse has insufficient stock.
     */
    public function test_negative_stock_transfer_approval_fails_on_insufficient_stock(): void
    {
        // Setup initial stock = 2
        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => $this->rak1->id],
            ['qty' => 2, 'qty_available' => 2, 'qty_reserved' => 0]
        );

        $transfer = StockTransfer::create([
            'transfer_number' => StockTransfer::generateTransferNumber(),
            'from_warehouse_id' => $this->warehouse1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Request',
        ]);

        StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->warehouse1->id,
            'from_rak_id' => $this->rak1->id,
            'to_warehouse_id' => $this->warehouse2->id,
            'to_rak_id' => null,
            'quantity' => 10, // Demands 10, available only 2
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Stok tidak cukup untuk produk');

        app(StockTransferService::class)->approveStockTransfer($transfer);
    }

    /**
     * Negative S2.5: Surat Jalan generation rejects Delivery Orders with non-approved status.
     */
    public function test_negative_surat_jalan_rejects_non_approved_delivery_orders(): void
    {
        $customer = Customer::factory()->create(['cabang_id' => $this->cabang1->id]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-DRAFT-' . uniqid(),
            'cabang_id' => $this->cabang1->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'draft', // Not approved
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Surat Jalan hanya dapat dibuat dari Delivery Order berstatus approved');

        app(SuratJalanService::class)->assertDeliveryOrdersUsable(collect([$do]));
    }

    /**
     * Negative S2.6: Surat Jalan generation rejects Delivery Orders from different branches.
     */
    public function test_negative_surat_jalan_rejects_multi_branch_delivery_orders(): void
    {
        $customer = Customer::factory()->create(['cabang_id' => $this->cabang1->id]);

        $do1 = DeliveryOrder::create([
            'do_number' => 'DO-BR1-' . uniqid(),
            'cabang_id' => $this->cabang1->id,
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse1->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $do2 = DeliveryOrder::create([
            'do_number' => 'DO-BR2-' . uniqid(),
            'cabang_id' => $this->cabang2->id, // Different branch
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse2->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'approved',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('harus berasal dari cabang yang sama');

        app(SuratJalanService::class)->assertDeliveryOrdersUsable(collect([$do1, $do2]));
    }

    // =========================================================================
    // SECTION 3: SPRINT 3 OPERATIONAL & UI/UX (NEGATIVE & EDGE TESTS)
    // =========================================================================

    /**
     * Negative S3.1: Direct outbound stock movement must prevent negative available stock.
     */
    public function test_negative_outbound_stock_movement_prevents_negative_inventory(): void
    {
        InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => $this->rak1->id],
            ['qty' => 5, 'qty_available' => 5, 'qty_reserved' => 0]
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Stok persediaan tidak mencukupi');

        // Outbound movement of 20 when stock is 5
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse1->id,
            'rak_id' => $this->rak1->id,
            'quantity' => 20,
            'type' => 'sales',
            'date' => now()->toDateString(),
        ]);
    }

    /**
     * Negative S3.2: Stock Opname approval requires status completed.
     */
    public function test_negative_stock_opname_approval_requires_status_completed(): void
    {
        $opname = StockOpname::create([
            'opname_number' => 'OPN-NOTCOMP-' . uniqid(),
            'warehouse_id' => $this->warehouse1->id,
            'opname_date' => now()->toDateString(),
            'status' => 'draft', // Draft, not completed
            'notes' => 'Opname draft test',
            'created_by' => $this->user->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Hanya stock opname berstatus selesai yang dapat disetujui');

        app(StockOpnameService::class)->approveStockOpname($opname);
    }

    /**
     * Negative S3.3: Stock Opname items on warehouse with raks requires rak.
     */
    public function test_negative_stock_opname_item_requires_rak_when_warehouse_has_raks(): void
    {
        $opname = StockOpname::create([
            'opname_number' => 'OPN-NORAK-' . uniqid(),
            'warehouse_id' => $this->warehouse1->id,
            'opname_date' => now()->toDateString(),
            'status' => 'completed',
            'notes' => 'Opname test',
            'created_by' => $this->user->id,
        ]);

        StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $this->product->id,
            'rak_id' => null, // Warehouse has raks, but rak_id is omitted
            'difference_qty' => 0,
            'difference_value' => 0,
            'total_value' => 0,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Setiap item stock opname pada gudang dengan rak harus memiliki rak');

        app(StockOpnameService::class)->approveStockOpname($opname);
    }

    /**
     * Edge S3.4: WarehouseStockOptions query with non-existent IDs returns array safely without fatal errors.
     */
    public function test_edge_warehouse_stock_options_returns_zero_on_invalid_keys(): void
    {
        $options = WarehouseStockOptions::forProduct(9999999, 8888888, true, 9999999);
        $this->assertIsArray($options);
    }

    // =========================================================================
    // SECTION 4: SPRINT 4 DATA RECONCILIATION & CLEANUP (NEGATIVE & EDGE TESTS)
    // =========================================================================

    /**
     * Negative S4.1: Customer merger blocks self-merge attempt.
     */
    public function test_negative_customer_merger_blocks_self_merge(): void
    {
        $customer = Customer::factory()->create(['cabang_id' => $this->cabang1->id]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Survivor dan customer yang digabung tidak boleh sama');

        app(CustomerMerger::class)->merge($customer, $customer);
    }

    /**
     * Negative S4.2: Customer merger blocks merging a candidate that was already merged into another.
     */
    public function test_negative_customer_merger_blocks_already_merged_candidate(): void
    {
        $custA = Customer::factory()->create(['cabang_id' => $this->cabang1->id]);
        $custB = Customer::factory()->create(['cabang_id' => $this->cabang1->id, 'merged_into' => 999]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('sudah digabung ke');

        app(CustomerMerger::class)->merge($custA, $custB);
    }

    /**
     * Negative S4.3: Customer merger blocks merge if BOTH customers possess active deposits.
     */
    public function test_negative_customer_merger_blocks_when_both_have_deposits(): void
    {
        $custA = Customer::factory()->create(['cabang_id' => $this->cabang1->id]);
        $custB = Customer::factory()->create(['cabang_id' => $this->cabang1->id]);

        Deposit::create([
            'deposit_number' => 'DEP-A-' . uniqid(),
            'from_model_type' => Customer::class,
            'from_model_id' => $custA->id,
            'coa_id' => $this->arCoa->id,
            'amount' => 500000,
            'remaining_amount' => 500000,
            'status' => 'Active',
            'created_by' => $this->user->id,
            'date' => now()->toDateString(),
        ]);

        Deposit::create([
            'deposit_number' => 'DEP-B-' . uniqid(),
            'from_model_type' => Customer::class,
            'from_model_id' => $custB->id,
            'coa_id' => $this->arCoa->id,
            'amount' => 300000,
            'remaining_amount' => 300000,
            'status' => 'Active',
            'created_by' => $this->user->id,
            'date' => now()->toDateString(),
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('sama-sama punya deposit');

        app(CustomerMerger::class)->merge($custA, $custB);
    }

    /**
     * Edge S4.4: Master Unit of measure sanitation cleans complex, nested, multiple brackets.
     */
    public function test_edge_master_unit_sanitation_handles_multiple_closing_brackets(): void
    {
        $uom = UnitOfMeasure::create([
            'name' => '[[KARTON]]]',
            'abbreviation' => '[[KTN]]]',
        ]);

        $exitCode = Artisan::call('system:reconcile-data', [
            '--task' => 'units',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $uom->refresh();
        $this->assertSame('[[KARTON', $uom->name);
        $this->assertSame('[[KTN', $uom->abbreviation);
    }

    /**
     * Edge S4.5: Reconcile historical data handles negative phantom reserved stock and restores 0.
     */
    public function test_edge_reconcile_handles_negative_phantom_reserved_stock(): void
    {
        $stock = InventoryStock::updateOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse1->id, 'rak_id' => null],
            ['qty' => 50, 'qty_available' => 50, 'qty_reserved' => -25] // Negative phantom reserved
        );
        $stock->update(['qty_reserved' => -25]);

        $exitCode = Artisan::call('system:reconcile-data', [
            '--task' => 'reserved-stock',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $stock->refresh();
        $this->assertSame(0.0, (float) $stock->qty_reserved, 'Negative phantom reserved stock must be reset to 0');
    }
}
