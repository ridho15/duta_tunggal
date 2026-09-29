<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockReservation;
use App\Models\StockTransfer;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Sprint4DataCleanupAndReconciliationTest extends TestCase
{
    private Cabang $cabang;
    private User $user;
    private Warehouse $warehouse;
    private UnitOfMeasure $uom;
    private ProductCategory $category;
    private Product $product;

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

        $this->cabang = Cabang::firstOrCreate(
            ['kode' => 'CBG-S4-VERIF'],
            [
                'nama' => 'Cabang Verifikasi Sprint 4',
                'alamat' => 'Jl. Verifikasi Sprint 4',
                'status' => 1,
                'lihat_stok_cabang_lain' => true,
            ]
        );

        $this->user = User::factory()->create([
            'username' => 'verif_s4_' . uniqid(),
            'email' => 'verif_s4_' . uniqid() . '@example.com',
            'kode_user' => 'S4' . strtoupper(substr(uniqid(), -3)),
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);
        Auth::login($this->user);

        $this->warehouse = Warehouse::firstOrCreate(
            ['name' => 'Gudang Sprint 4'],
            [
                'kode' => 'WH-S4',
                'cabang_id' => $this->cabang->id,
                'location' => 'Gudang Pusat S4',
                'status' => 1,
            ]
        );

        $this->uom = UnitOfMeasure::firstOrCreate(
            ['name' => 'Pieces'],
            ['abbreviation' => 'PCS']
        );

        $this->category = ProductCategory::firstOrCreate(
            ['name' => 'Material S4'],
            ['kode' => 'MAT-S4']
        );

        // COAs needed for transactions
        $invCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.10'],
            ['name' => 'Persediaan Barang Dagangan', 'type' => 'Asset', 'status' => 1]
        );
        $cogsCoa = ChartOfAccount::firstOrCreate(
            ['code' => '5000'],
            ['name' => 'Beban Pokok Penjualan', 'type' => 'Expense', 'status' => 1]
        );
        $goodsDeliveryCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.20'],
            ['name' => 'Pengiriman Barang Penjualan', 'type' => 'Asset', 'status' => 1]
        );
        $revCoa = ChartOfAccount::firstOrCreate(
            ['code' => '4000'],
            ['name' => 'Pendapatan Penjualan', 'type' => 'Revenue', 'status' => 1]
        );
        $arCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1120'],
            ['name' => 'Piutang Usaha', 'type' => 'Asset', 'status' => 1]
        );
        $adjGainCoa = ChartOfAccount::firstOrCreate(
            ['code' => '7000.04'],
            ['name' => 'Pendapatan Selisih Penyesuaian Persediaan', 'type' => 'Revenue', 'status' => 1]
        );
        $adjLossCoa = ChartOfAccount::firstOrCreate(
            ['code' => '8000.05'],
            ['name' => 'Beban Selisih Penyesuaian Persediaan', 'type' => 'Expense', 'status' => 1]
        );

        $this->product = Product::create([
            'name' => 'Produk Sprint 4 ' . uniqid(),
            'sku' => 'SKU-S4-' . uniqid(),
            'uom_id' => $this->uom->id,
            'product_category_id' => $this->category->id,
            'cost_price' => 50000,
            'sell_price' => 75000,
            'cabang_id' => $this->cabang->id,
            'kode_merk' => 'TEST',
            'is_manufacture' => false,
            'is_raw_material' => false,
            'is_active' => true,
            'inventory_coa_id' => $invCoa->id,
            'cogs_coa_id' => $cogsCoa->id,
            'goods_delivery_coa_id' => $goodsDeliveryCoa->id,
            'sales_coa_id' => $revCoa->id,
        ]);
    }

    /**
     * Test 1: Dry run mode does NOT mutate any tables.
     */
    public function test_dry_run_mode_does_not_mutate_database(): void
    {
        $testUom = UnitOfMeasure::create([
            'name' => 'Karton]',
            'abbreviation' => 'KTN]',
        ]);

        $exitCode = Artisan::call('system:reconcile-data', [
            '--dry-run' => true,
            '--task' => 'units',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('unit_of_measures', [
            'id' => $testUom->id,
            'name' => 'Karton]',
            'abbreviation' => 'KTN]',
        ]);
    }

    /**
     * Test 2: Reconciling orphan sales invoices creates balanced GL entries.
     */
    public function test_orphan_sales_invoice_reconciliation_creates_balanced_gl_entries(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Customer Invoice S4 ' . uniqid(),
            'cabang_id' => $this->cabang->id,
        ]);

        $so = SaleOrder::create([
            'so_number' => 'SO-S4-' . uniqid(),
            'customer_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'confirmed',
            'total_amount' => 150000,
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'unit_price' => 75000,
            'subtotal' => 150000,
            'total' => 150000,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-S4-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'cabang_id' => $this->cabang->id,
            'customer_name' => $customer->name,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 150000,
            'tax' => 0,
            'ppn_rate' => 0,
            'total' => 150000,
            'status' => Invoice::STATUS_SENT,
        ]);

        $invItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 75000,
            'subtotal' => 150000,
            'total' => 150000,
            'cost_price' => 50000,
        ]);

        // Verify currently no journal entries exist
        $this->assertSame(0, JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->count());

        $exitCode = Artisan::call('system:reconcile-data', [
            '--task' => 'journals',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);

        // Verify balanced journal entries now exist
        $journals = JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->get();
        $this->assertNotEmpty($journals);
        $totalDebit = (float) $journals->sum('debit');
        $totalCredit = (float) $journals->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.01, 'Sales invoice journal entries must be balanced');
    }

    /**
     * Test 3: Approved stock adjustments without GL receive balanced entries.
     */
    public function test_approved_stock_adjustments_without_gl_receive_balanced_entries(): void
    {
        $adj = StockAdjustment::create([
            'adjustment_number' => 'ADJ-S4-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'adjustment_date' => now()->toDateString(),
            'adjustment_type' => 'increase',
            'status' => 'approved',
            'created_by' => $this->user->id,
            'notes' => 'Penyesuaian stok pengujian Sprint 4',
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

        $this->assertSame(0, JournalEntry::where('source_type', StockAdjustment::class)->where('source_id', $adj->id)->count());

        $exitCode = Artisan::call('system:reconcile-data', [
            '--task' => 'adjustments',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $journals = JournalEntry::where('source_type', StockAdjustment::class)->where('source_id', $adj->id)->get();
        $this->assertNotEmpty($journals);
        $totalDebit = (float) $journals->sum('debit');
        $totalCredit = (float) $journals->sum('credit');
        $this->assertEqualsWithDelta(250000, $totalDebit, 0.01);
        $this->assertEqualsWithDelta(250000, $totalCredit, 0.01);
    }

    /**
     * Test 4: Clean empty stock transfers without items.
     */
    public function test_empty_stock_transfers_are_force_deleted(): void
    {
        $w2 = Warehouse::firstOrCreate(
            ['name' => 'Gudang Tujuan S4'],
            [
                'kode' => 'WH-S4-2',
                'cabang_id' => $this->cabang->id,
                'location' => 'Gudang Kedua S4',
                'status' => 1,
            ]
        );

        $transfer = StockTransfer::create([
            'transfer_number' => StockTransfer::generateTransferNumber(),
            'from_warehouse_id' => $this->warehouse->id,
            'to_warehouse_id' => $w2->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'Pending',
        ]);

        $this->assertDatabaseHas('stock_transfers', ['id' => $transfer->id]);

        $exitCode = Artisan::call('system:reconcile-data', [
            '--task' => 'transfers',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertNull(StockTransfer::withTrashed()->find($transfer->id));
    }

    /**
     * Test 5: Reconciling reserved stock cleans orphan reservations and resets inventory_stocks.
     */
    public function test_orphan_reservations_cleaned_and_inventory_stock_qty_reserved_reconciled(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Customer Reserve S4 ' . uniqid(),
            'cabang_id' => $this->cabang->id,
        ]);

        $canceledSo = SaleOrder::create([
            'so_number' => 'SO-CANC-' . uniqid(),
            'customer_id' => $customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'canceled',
            'total_amount' => 100000,
        ]);

        $orphanRes = StockReservation::create([
            'sale_order_id' => $canceledSo->id,
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
        ]);

        // Manually set inventory_stocks with phantom qty_reserved = 10
        $stock = InventoryStock::firstOrCreate(
            [
                'product_id' => $this->product->id,
                'warehouse_id' => $this->warehouse->id,
                'rak_id' => null,
            ],
            [
                'qty' => 50,
                'qty_reserved' => 10,
            ]
        );
        $stock->update(['qty_reserved' => 10]);

        $exitCode = Artisan::call('system:reconcile-data', [
            '--task' => 'reserved-stock',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);

        // Orphan reservation deleted
        $this->assertDatabaseMissing('stock_reservations', ['id' => $orphanRes->id]);

        // InventoryStock reset to 0
        $stock->refresh();
        $this->assertEqualsWithDelta(0, (float) $stock->qty_reserved, 0.001);
    }

    /**
     * Test 6: Sanitizing master units of measure strips closing bracket.
     */
    public function test_sanitize_master_units_strips_closing_bracket(): void
    {
        $uom = UnitOfMeasure::create([
            'name' => 'Bal]',
            'abbreviation' => 'BL]',
        ]);

        $exitCode = Artisan::call('system:reconcile-data', [
            '--task' => 'units',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $uom->refresh();
        $this->assertSame('Bal', $uom->name);
        $this->assertSame('BL', $uom->abbreviation);
    }

    /**
     * Test 7: Duplicate customer consolidation merges records and documents.
     */
    public function test_duplicate_customers_consolidation_merges_records_and_documents(): void
    {
        $perusahaan = 'PT Mitra Abadi Reconcile ' . uniqid();

        $custA = Customer::factory()->create([
            'name' => 'Mitra Abadi A',
            'perusahaan' => $perusahaan,
            'cabang_id' => $this->cabang->id,
        ]);

        $custB = Customer::factory()->create([
            'name' => 'Mitra Abadi B',
            'perusahaan' => $perusahaan,
            'cabang_id' => $this->cabang->id,
        ]);

        $so = SaleOrder::create([
            'so_number' => 'SO-DUP-' . uniqid(),
            'customer_id' => $custB->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'total_amount' => 500000,
        ]);

        $exitCode = Artisan::call('system:reconcile-data', [
            '--task' => 'customers',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);

        // Document migrated to custA
        $so->refresh();
        $this->assertSame($custA->id, $so->customer_id);

        // custB is soft deleted and merged_into custA
        $custB->refresh();
        $this->assertTrue($custB->trashed());
        $this->assertSame($custA->id, $custB->merged_into);
        $this->assertStringContainsString('DUPLIKAT - MERGED KE ID', (string) $custB->keterangan);
    }

    /**
     * Test 8: Full command run with all tasks and force option succeeds.
     */
    public function test_full_command_run_with_all_tasks_and_force_option(): void
    {
        $exitCode = Artisan::call('system:reconcile-data', [
            '--task' => 'all',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode);
    }
}
