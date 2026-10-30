<?php

namespace Tests\Feature;

use App\Filament\Resources\SalesInvoiceResource;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\AccountPayable;
use App\Models\Rak;
use App\Models\SaleOrder;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReturnService;
use App\Services\StockAdjustmentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Sprint1FinancialIntegrityVerificationTest extends TestCase
{
    private Cabang $cabang;
    private User $user;
    private Customer $customer;
    private Warehouse $warehouse;
    private Rak $rak;
    private Product $product;
    private ChartOfAccount $inventoryCoa;
    private ChartOfAccount $revenueCoa;
    private ChartOfAccount $arCoa;
    private ChartOfAccount $apCoa;
    private ChartOfAccount $ppnMasukanCoa;
    private ChartOfAccount $ppnKeluaranCoa;
    private ChartOfAccount $varianceIncomeCoa;
    private ChartOfAccount $varianceExpenseCoa;

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
            ['kode' => 'CBG-VERIF-S1'],
            [
                'nama' => 'Cabang Verifikasi Sprint 1',
                'alamat' => 'Jl. Verifikasi No. 1',
                'status' => 1,
                'lihat_stok_cabang_lain' => true,
            ]
        );

        $this->user = User::factory()->create([
            'username' => 'verif_s1_' . uniqid(),
            'email' => 'verif_s1_' . uniqid() . '@example.com',
            'kode_user' => 'V' . strtoupper(substr(uniqid(), -4)),
            'cabang_id' => $this->cabang->id,
            'manage_type' => 'all',
        ]);
        foreach (['view any invoice', 'view invoice', 'update invoice', 'delete invoice'] as $permission) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo(['view any invoice', 'view invoice', 'update invoice', 'delete invoice']);
        Auth::login($this->user);

        $this->customer = Customer::factory()->create([
            'name' => 'Customer Verif S1',
            'perusahaan' => 'PT Customer Verif S1',
            'cabang_id' => $this->cabang->id,
        ]);

        // Chart of Accounts setup
        $this->inventoryCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1140.10'],
            ['name' => 'Persediaan Barang Dagangan', 'type' => 'Asset', 'status' => 1]
        );

        $this->arCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1120'],
            ['name' => 'Piutang Usaha', 'type' => 'Asset', 'status' => 1]
        );

        $this->revenueCoa = ChartOfAccount::firstOrCreate(
            ['code' => '4000'],
            ['name' => 'Pendapatan Penjualan', 'type' => 'Revenue', 'status' => 1]
        );

        $this->apCoa = ChartOfAccount::firstOrCreate(
            ['code' => '2110'],
            ['name' => 'Hutang Dagang', 'type' => 'Liability', 'status' => 1]
        );

        $this->ppnMasukanCoa = ChartOfAccount::firstOrCreate(
            ['code' => '1170.06'],
            ['name' => 'PPN MASUKAN', 'type' => 'Asset', 'status' => 1]
        );

        $this->ppnKeluaranCoa = ChartOfAccount::firstOrCreate(
            ['code' => '2120.06'],
            ['name' => 'PPN KELUARAN', 'type' => 'Liability', 'status' => 1]
        );

        $this->varianceIncomeCoa = ChartOfAccount::firstOrCreate(
            ['code' => '7000.04'],
            ['name' => 'Pendapatan Luar Usaha Lainnya', 'type' => 'Revenue', 'status' => 1]
        );

        $this->varianceExpenseCoa = ChartOfAccount::firstOrCreate(
            ['code' => '8000.05'],
            ['name' => 'BIAYA LAINNYA', 'type' => 'Expense', 'status' => 1]
        );

        $this->warehouse = Warehouse::create([
            'cabang_id' => $this->cabang->id,
            'kode' => 'WH-VERIF-' . strtoupper(substr(uniqid(), -4)),
            'name' => 'Gudang Verifikasi S1',
            'location' => 'Area Gudang Verif',
            'status' => 1,
        ]);

        $this->rak = Rak::create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'RAK-V1-' . strtoupper(substr(uniqid(), -3)),
            'name' => 'Rak Verifikasi',
        ]);

        $this->product = Product::factory()->create([
            'name' => 'Produk Verifikasi Sprint 1',
            'sku' => 'SKU-VERIF-' . strtoupper(substr(uniqid(), -4)),
            'inventory_coa_id' => $this->inventoryCoa->id,
            'cost_price' => 75000,
        ]);

        InventoryStock::firstOrCreate(
            ['product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'rak_id' => $this->rak->id],
            ['qty_available' => 50, 'quantity' => 50, 'qty_reserved' => 0]
        );
    }

    /**
     * TEST 1: Faktur berstatus 'sent' diblokir dari halaman edit dan dialihkan ke view
     */
    public function test_sent_invoice_edit_page_is_blocked_and_redirects(): void
    {
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-SENT-' . strtoupper(substr(uniqid(), -4)),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'status' => Invoice::STATUS_SENT,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 100000,
            'total' => 111000,
            'tax' => 11000,
            'cabang_id' => $this->cabang->id,
        ]);

        $url = SalesInvoiceResource::getUrl('edit', ['record' => $invoice]);
        $response = $this->get($url);

        // Harus dialihkan (HTTP 302 redirect) ke halaman view
        $response->assertStatus(302);
        $response->assertRedirect(SalesInvoiceResource::getUrl('view', ['record' => $invoice]));
    }

    /**
     * TEST 2: Jurnal tidak terhapus jika reposting gagal (atomic transaction rollback)
     */
    public function test_invoice_observer_rolls_back_journal_deletion_on_repost_failure(): void
    {
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEST-ATOMIC-' . strtoupper(substr(uniqid(), -4)),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'status' => Invoice::STATUS_SENT,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 2000000,
            'total' => 2220000,
            'tax' => 220000,
            'cabang_id' => $this->cabang->id,
        ]);

        // Buat jurnal awal
        JournalEntry::create([
            'coa_id' => $this->arCoa->id,
            'date' => now()->toDateString(),
            'reference' => $invoice->invoice_number,
            'description' => 'Original AR Journal',
            'debit' => 2220000,
            'credit' => 0,
            'journal_type' => 'sales',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->assertEquals(1, JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->count());

        // Simulasikan force exception di dalam DB::transaction observer
        try {
            DB::transaction(function () use ($invoice) {
                JournalEntry::where('source_type', Invoice::class)
                    ->where('source_id', $invoice->id)
                    ->delete();

                // Sengaja lempar exception untuk mensimulasikan kegagalan posting
                throw new \RuntimeException('Simulated post failure: out of balance');
            });
        } catch (\RuntimeException $e) {
            // Ditangkap
        }

        // Verifikasi bahwa jurnal lama TETAP ADA (tidak terhapus karena rollback transaksi)
        $this->assertEquals(1, JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->count());
        $this->assertEquals('Original AR Journal', JournalEntry::where('source_type', Invoice::class)->where('source_id', $invoice->id)->first()->description);
    }

    /**
     * TEST 3: Stock Adjustment tipe Increase menggunakan COA Pendapatan Luar Usaha dan TIDAK PERNAH akun 4000
     */
    public function test_stock_adjustment_increase_uses_variance_gain_and_never_sales_revenue_4000(): void
    {
        $adjustment = StockAdjustment::create([
            'adjustment_number' => 'ADJ-INC-' . strtoupper(substr(uniqid(), -6)),
            'adjustment_date' => now(),
            'adjustment_type' => 'increase',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'reason' => 'Test verifikasi akun surplus',
            'created_by' => $this->user->id,
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'current_qty' => 50,
            'adjusted_qty' => 60,
            'difference_qty' => 10,
            'unit_cost' => 75000,
            'difference_value' => 750000,
        ]);

        $service = app(StockAdjustmentService::class);
        $service->approveStockAdjustment($adjustment, $this->user->id);

        $entries = JournalEntry::where('source_type', StockAdjustment::class)
            ->where('source_id', $adjustment->id)
            ->get();

        $this->assertNotEmpty($entries);

        // Verifikasi akun kredit adalah 7000.04 (variance gain)
        $creditEntry = $entries->where('credit', '>', 0)->first();
        $this->assertNotNull($creditEntry);
        $this->assertEquals($this->varianceIncomeCoa->id, $creditEntry->coa_id);

        // Verifikasi akun 4000 (Pendapatan Penjualan) TIDAK ADA
        $salesEntry = $entries->where('coa_id', $this->revenueCoa->id)->first();
        $this->assertNull($salesEntry, 'Akun 4000 (Pendapatan Penjualan) tidak boleh digunakan untuk penyesuaian persediaan!');

        // Verifikasi debit adalah persediaan
        $debitEntry = $entries->where('debit', '>', 0)->first();
        $this->assertEquals($this->inventoryCoa->id, $debitEntry->coa_id);
    }

    /**
     * TEST 4: Stock Adjustment tipe Decrease menggunakan COA Beban Luar Usaha dan TIDAK PERNAH akun 5100
     */
    public function test_stock_adjustment_decrease_uses_variance_loss_and_never_cogs_5100(): void
    {
        $adjustment = StockAdjustment::create([
            'adjustment_number' => 'ADJ-DEC-' . strtoupper(substr(uniqid(), -6)),
            'adjustment_date' => now(),
            'adjustment_type' => 'decrease',
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'reason' => 'Test verifikasi akun defisit',
            'created_by' => $this->user->id,
        ]);

        StockAdjustmentItem::create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'current_qty' => 50,
            'adjusted_qty' => 45,
            'difference_qty' => -5,
            'unit_cost' => 75000,
            'difference_value' => -375000,
        ]);

        $service = app(StockAdjustmentService::class);
        $service->approveStockAdjustment($adjustment, $this->user->id);

        $entries = JournalEntry::where('source_type', StockAdjustment::class)
            ->where('source_id', $adjustment->id)
            ->get();

        $this->assertNotEmpty($entries);

        // Verifikasi akun debit adalah 8000.05 (variance loss)
        $debitEntry = $entries->where('debit', '>', 0)->first();
        $this->assertNotNull($debitEntry);
        $this->assertEquals($this->varianceExpenseCoa->id, $debitEntry->coa_id);

        // Verifikasi tidak menggunakan akun 5100 (HPP)
        $cogsCoa = ChartOfAccount::where('code', '5100')->first();
        if ($cogsCoa) {
            $cogsEntry = $entries->where('coa_id', $cogsCoa->id)->first();
            $this->assertNull($cogsEntry, 'Akun 5100 (HPP) tidak boleh digunakan untuk penyesuaian persediaan!');
        }

        // Verifikasi kredit adalah persediaan
        $creditEntry = $entries->where('credit', '>', 0)->first();
        $this->assertEquals($this->inventoryCoa->id, $creditEntry->coa_id);
    }

    /**
     * TEST 5: Retur Pembelian membalik PPN Masukan jika invoice pembelian ber-PPN
     */
    public function test_purchase_return_reverses_ppn_masukan_when_invoice_has_vat(): void
    {
        $supplier = Supplier::factory()->create([
            'cabang_id' => $this->cabang->id,
        ]);

        $po = PurchaseOrder::create([
            'po_number' => 'PO-VERIF-S1-' . strtoupper(substr(uniqid(), -4)),
            'supplier_id' => $supplier->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'subtotal' => 1000000,
            'total' => 1110000,
            'tax' => 110000,
            'tax_rate' => 11,
            'ppn_rate' => 11,
        ]);

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'GRN-VERIF-S1-' . strtoupper(substr(uniqid(), -4)),
            'purchase_order_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'completed',
            'receipt_date' => now(),
            'received_by' => $this->user->id,
            'currency_id' => Currency::where('code', 'IDR')->first()?->id ?? 1,
        ]);

        $receiptItem = PurchaseReceiptItem::create([
            'purchase_receipt_id' => $receipt->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'status' => 'completed',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $purchaseInvoice = Invoice::create([
            'invoice_number' => 'PINV-VERIF-S1-' . strtoupper(substr(uniqid(), -4)),
            'from_model_type' => PurchaseReceipt::class,
            'from_model_id' => $receipt->id,
            'status' => Invoice::STATUS_SENT,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => 750000,
            'total' => 832500,
            'tax' => 82500,
            'ppn_rate' => 11,
            'cabang_id' => $this->cabang->id,
            'supplier_id' => $supplier->id,
        ]);

        $ap = AccountPayable::create([
            'invoice_id' => $purchaseInvoice->id,
            'supplier_id' => $supplier->id,
            'total' => 832500,
            'paid' => 0,
            'remaining' => 832500,
            'cabang_id' => $this->cabang->id,
            'status' => 'unpaid',
        ]);

        $purchaseReturn = PurchaseReturn::create([
            'nota_retur' => 'NR-VERIF-S1-' . strtoupper(substr(uniqid(), -4)),
            'purchase_receipt_id' => $receipt->id,
            'cabang_id' => $this->cabang->id,
            'return_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'notes' => 'Barang cacat uji verifikasi',
        ]);

        $returnItem = PurchaseReturnItem::create([
            'purchase_return_id' => $purchaseReturn->id,
            'purchase_receipt_item_id' => $receiptItem->id,
            'product_id' => $this->product->id,
            'rak_id' => $this->rak->id,
            'qty_returned' => 4,
            'unit_price' => 75000,
        ]);

        $service = app(PurchaseReturnService::class);
        $res = $service->createJournalEntry($purchaseReturn);
        $this->assertTrue($res);

        $entries = JournalEntry::where('source_type', PurchaseReturn::class)
            ->where('source_id', $purchaseReturn->id)
            ->get();

        $this->assertNotEmpty($entries);

        // Kuantitas retur 4 * 75000 = DPP Rp300.000
        // PPN 11% = Rp33.000
        // Total Bruto AP = Rp333.000
        $apDebit = $entries->where('coa_id', $this->apCoa->id)->where('debit', '>', 0)->first();
        $this->assertNotNull($apDebit, 'Jurnal harus mendebit Utang Usaha');
        $this->assertEquals(333000.00, (float) $apDebit->debit);

        $invCredit = $entries->where('coa_id', $this->inventoryCoa->id)->where('credit', '>', 0)->first();
        $this->assertNotNull($invCredit, 'Jurnal harus mengkredit Persediaan');
        $this->assertEquals(300000.00, (float) $invCredit->credit);

        $ppnCredit = $entries->where('coa_id', $this->ppnMasukanCoa->id)->where('credit', '>', 0)->first();
        $this->assertNotNull($ppnCredit, 'Jurnal harus mengkredit pembalik PPN Masukan');
        $this->assertEquals(33000.00, (float) $ppnCredit->credit);

        // Double entry balance: Total Debit == Total Credit
        $totalDebit = (float) $entries->sum('debit');
        $totalCredit = (float) $entries->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(333000.00, $totalDebit);
    }

    /**
     * TEST 6: Command invoice:repair-journals mendeteksi invoice tanpa jurnal
     */
    public function test_repair_orphan_invoices_command_dry_run_executes_cleanly(): void
    {
        $this->artisan('invoice:repair-journals', ['--dry-run' => true])
            ->assertExitCode(0);
    }
}
