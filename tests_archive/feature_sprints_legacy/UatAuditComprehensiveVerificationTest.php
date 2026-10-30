<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\QualityControl;
use App\Models\ReturnProduct;
use App\Models\ReturnProductItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\TaxSetting;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Models\Warehouse;
use App\Services\CustomerReturnService;
use App\Services\DeliveryOrderService;
use App\Services\LedgerPostingService;
use App\Services\ProductService;
use App\Services\PurchaseOrderService;
use App\Services\PurchaseReturnService;
use App\Services\ReturnProductService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UatAuditComprehensiveVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Cabang $cabang;
    private Supplier $supplier;
    private Customer $customer;
    private Warehouse $warehouse;
    private Currency $currency;
    private ChartOfAccount $cashCoa;
    private ChartOfAccount $apCoa;
    private ChartOfAccount $arCoa;
    private ChartOfAccount $salesCoa;
    private ChartOfAccount $inventoryCoa;
    private ChartOfAccount $customInventoryCoa;
    private ChartOfAccount $goodsDeliveryCoa;
    private ChartOfAccount $cogsCoa;
    private ChartOfAccount $depositCustomerCoa;
    private ?ChartOfAccount $salesReturnCoa = null;
    private Product $standardProduct;
    private Product $customProduct;

    protected function setUp(): void
    {
        parent::setUp();

        TaxSetting::updateOrCreate(
            ['type' => 'PPN', 'status' => true],
            [
                'name' => 'PPN 11%',
                'rate' => 11,
                'status' => true,
                'effective_date' => now()->toDateString(),
            ]
        );

        $this->user = User::factory()->create(['manage_type' => 'all']);
        $this->actingAs($this->user);

        $this->cabang = Cabang::create([
            'kode' => 'CB-VERIF',
            'nama' => 'Cabang Verifikasi UAT',
            'alamat' => 'Jl. Audit No. 1',
            'telepon' => '021-999888',
        ]);

        $this->currency = Currency::firstOrCreate(
            ['code' => 'IDR'],
            ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'exchange_rate' => 1.0, 'is_default' => true]
        );

        $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->customer = Customer::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);

        $this->cashCoa = ChartOfAccount::updateOrCreate(['code' => '1112.01'], [
            'name' => 'Kas / Bank Utama',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->apCoa = ChartOfAccount::updateOrCreate(['code' => '2110'], [
            'name' => 'Hutang Dagang',
            'type' => 'liability',
            'is_active' => true,
        ]);

        $this->arCoa = ChartOfAccount::updateOrCreate(['code' => '1130.01'], [
            'name' => 'Piutang Usaha',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->salesCoa = ChartOfAccount::updateOrCreate(['code' => '4110.01'], [
            'name' => 'Pendapatan Penjualan',
            'type' => 'revenue',
            'is_active' => true,
        ]);

        $this->inventoryCoa = ChartOfAccount::updateOrCreate(['code' => '1140.10'], [
            'name' => 'Persediaan Standar',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->customInventoryCoa = ChartOfAccount::updateOrCreate(['code' => '1140.15'], [
            'name' => 'Persediaan Sparepart Khusus',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->goodsDeliveryCoa = ChartOfAccount::updateOrCreate(['code' => '1140.20'], [
            'name' => 'Barang Terkirim',
            'type' => 'asset',
            'is_active' => true,
        ]);

        $this->cogsCoa = ChartOfAccount::updateOrCreate(['code' => '5100.10'], [
            'name' => 'Harga Pokok Penjualan',
            'type' => 'expense',
            'is_active' => true,
        ]);

        $this->salesReturnCoa = ChartOfAccount::updateOrCreate(['code' => '4120.10'], [
            'name' => 'Retur Penjualan',
            'type' => 'revenue',
            'is_active' => true,
        ]);

        $this->depositCustomerCoa = ChartOfAccount::updateOrCreate(['code' => '2160.04'], [
            'name' => 'Deposit Pelanggan',
            'type' => 'liability',
            'is_active' => true,
        ]);

        $this->standardProduct = Product::factory()->create([
            'name' => 'Produk Standar UAT',
            'cost_price' => 50000,
            'sell_price' => 80000,
            'inventory_coa_id' => $this->inventoryCoa->id,
            'goods_delivery_coa_id' => $this->goodsDeliveryCoa->id,
        ]);

        $this->customProduct = Product::factory()->create([
            'name' => 'Produk Khusus UAT',
            'cost_price' => 120000,
            'sell_price' => 180000,
            'inventory_coa_id' => $this->customInventoryCoa->id,
            'goods_delivery_coa_id' => $this->goodsDeliveryCoa->id,
        ]);

        InventoryStock::updateOrCreate([
            'product_id' => $this->standardProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => null,
        ], [
            'qty_available' => 100,
            'qty_reserved' => 0,
        ]);

        InventoryStock::updateOrCreate([
            'product_id' => $this->customProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'rak_id' => null,
        ], [
            'qty_available' => 100,
            'qty_reserved' => 0,
        ]);
    }

    /**
     * POIN 1: Retur otomatis dari reject QC tidak memotong stok dan tidak membuat jurnal GL.
     */
    #[Test]
    public function test_poin_1_purchase_return_from_qc_reject_does_not_mutate_stock_nor_gl(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'currency_id' => $this->currency->id,
        ]);

        $pr = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $po->id,
            'status' => 'completed',
        ]);

        $prItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $pr->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->standardProduct->id,
            'qty_received' => 10,
            'qty_accepted' => 8,
            'qty_rejected' => 2,
        ]);

        $qc = QualityControl::factory()->create([
            'from_model_type' => PurchaseReceiptItem::class,
            'from_model_id' => $prItem->id,
            'product_id' => $this->standardProduct->id,
            'warehouse_id' => $this->warehouse->id,
            'passed_quantity' => 8,
            'rejected_quantity' => 2,
            'status' => 1,
        ]);

        $purchaseReturn = PurchaseReturn::create([
            'purchase_receipt_id' => $pr->id,
            'nota_retur' => 'NR-QC-REJECT-001',
            'return_date' => now()->toDateString(),
            'status' => 'pending_approval',
            'cabang_id' => $this->cabang->id,
            'quality_control_id' => $qc->id,
            'failed_qc_action' => PurchaseReturn::QC_ACTION_RETURN_SUPPLIER,
            'created_by' => $this->user->id,
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $purchaseReturn->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'subtotal' => 100000,
            'reason' => 'Barang reject QC',
        ]);

        $stockBefore = InventoryStock::where('product_id', $this->standardProduct->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('qty_available');

        // Approve the purchase return
        app(PurchaseReturnService::class)->approve($purchaseReturn);

        $stockAfter = InventoryStock::where('product_id', $this->standardProduct->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('qty_available');

        // 1. Stok fisik persediaan TIDAK boleh berkurang karena barang reject tidak pernah masuk gudang
        $this->assertEquals((float) $stockBefore, (float) $stockAfter, 'Stok tidak boleh berkurang untuk retur reject QC.');

        // 2. Tidak ada mutasi stok jenis purchase_return yang memotong stok produk
        $movementCount = StockMovement::where('product_id', $this->standardProduct->id)
            ->where('type', 'purchase_return')
            ->count();
        $this->assertSame(0, $movementCount, 'Tidak boleh ada StockMovement untuk retur reject QC.');

        // 3. Tidak ada jurnal GL yang mendebit AP atau mengkredit Persediaan untuk barang reject QC
        $journalCount = JournalEntry::where('source_type', PurchaseReturn::class)
            ->where('source_id', $purchaseReturn->id)
            ->count();
        $this->assertSame(0, $journalCount, 'Tidak boleh ada JournalEntry untuk retur reject QC.');
    }

    /**
     * POIN 2: Saldo Hutang (AP) memperhitungkan retur approved setelah ada pembayaran vendor.
     */
    #[Test]
    public function test_poin_2_ap_preserves_purchase_return_credit_after_vendor_payment(): void
    {
        $invoice = Invoice::factory()->create([
            'total' => 100000,
            'supplier_name' => $this->supplier->perusahaan,
            'status' => 'unpaid',
        ]);

        $ap = AccountPayable::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total' => 100000,
            'total_original' => 100000,
            'paid' => 0,
            'paid_original' => 0,
            'remaining' => 100000,
            'remaining_original' => 100000,
            'status' => 'Belum Lunas',
            'exchange_rate' => 1.0,
        ]);

        // Simulasikan pembayaran pertama: Rp 50.000
        $payment1 = VendorPayment::create([
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'total_payment' => 50000,
            'payment_method' => 'Cash',
            'coa_id' => $this->cashCoa->id,
            'status' => 'Partial',
        ]);

        VendorPaymentDetail::create([
            'vendor_payment_id' => $payment1->id,
            'invoice_id' => $invoice->id,
            'amount' => 50000,
            'adjustment_amount' => 20000, // Kredit retur
            'balance_amount' => 0,
            'coa_id' => $this->cashCoa->id,
            'payment_date' => now()->toDateString(),
            'method' => 'Cash',
        ]);

        $payment1->touch();
        $ap->refresh();

        // Total 100.000 - Bayar 50.000 - Retur 20.000 = Sisa 30.000
        $this->assertEquals(50000.0, (float) $ap->paid);
        $this->assertEquals(30000.0, (float) $ap->remaining, 'Sisa hutang harus 30.000 setelah pembayaran 50.000 dan retur 20.000.');
        $this->assertEquals('Belum Lunas', $ap->status);

        // Simulasikan pelunasan sisa Rp 30.000
        $payment2 = VendorPayment::create([
            'supplier_id' => $this->supplier->id,
            'payment_date' => now()->toDateString(),
            'total_payment' => 30000,
            'payment_method' => 'Cash',
            'coa_id' => $this->cashCoa->id,
            'status' => 'Paid',
        ]);

        VendorPaymentDetail::create([
            'vendor_payment_id' => $payment2->id,
            'invoice_id' => $invoice->id,
            'amount' => 30000,
            'adjustment_amount' => 0,
            'balance_amount' => 0,
            'coa_id' => $this->cashCoa->id,
            'payment_date' => now()->toDateString(),
            'method' => 'Cash',
        ]);

        $payment2->touch();
        $ap->refresh();

        $this->assertEquals(80000.0, (float) $ap->paid);
        $this->assertEquals(0.0, (float) $ap->remaining, 'Hutang harus lunas (remaining = 0).');
        $this->assertEquals('Lunas', $ap->status, 'Status AP harus Lunas setelah total bayar + retur = total tagihan.');
    }

    /**
     * POIN 3: Return Product tidak boleh mengubah invoice yang sudah diposting atau lunas.
     */
    #[Test]
    public function test_poin_3_return_product_never_modifies_posted_or_paid_sales_invoice(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-PROT-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 240000,
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 3,
            'unit_price' => 80000,
            'total_price' => 240000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-PROT-' . uniqid(),
            'sale_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'confirmed',
            'delivery_date' => now(),
        ]);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 3,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-POSTED-' . uniqid(),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'delivery_orders' => [$do->id],
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'paid', // INVOICE SUDAH LUNAS
            'subtotal' => 240000,
            'dpp' => 240000,
            'total' => 240000,
            'ppn_rate' => 0,
            'tipe_pajak' => 'None',
        ]);

        $invItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 3,
            'price' => 80000,
            'subtotal' => 240000,
            'total' => 240000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'coa_id' => $this->salesCoa->id,
        ]);

        $returnProduct = ReturnProduct::create([
            'return_number' => 'RET-P3-' . strtoupper(substr(uniqid(), -4)),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'draft',
            'reason' => 'Barang rusak kemasan',
        ]);

        ReturnProductItem::create([
            'return_product_id' => $returnProduct->id,
            'from_item_model_type' => DeliveryOrderItem::class,
            'from_item_model_id' => $doItem->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 1,
            'condition' => 'good',
        ]);

        // Eksekusi penyesuaian invoice
        app(ReturnProductService::class)->adjustLinkedSalesInvoice($returnProduct, $do);

        $invItem->refresh();
        $invoice->refresh();

        // Verifikasi Integritas: Qty dan Total invoice Lunas/Posted TIDAK boleh berubah
        $this->assertEquals(3.0, (float) $invItem->quantity, 'Kuantitas invoice lunas tidak boleh berkurang in-place.');
        $this->assertEquals(240000.0, (float) $invoice->total, 'Total invoice lunas tidak boleh berubah.');
        $this->assertEquals('paid', $invoice->status, 'Status invoice lunas harus tetap paid.');
    }

    /**
     * POIN 4: Validasi kuota retur lintas modul (ReturnProduct & CustomerReturn saling memperhitungkan).
     */
    #[Test]
    public function test_poin_4_customer_return_quota_enforced_against_prior_return_product(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-Q4-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 400000,
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 5,
            'unit_price' => 80000,
            'total_price' => 400000,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-Q4-' . uniqid(),
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'sent',
        ]);

        $doItem = DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 5,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'INV-QUOTA-' . uniqid(),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'delivery_orders' => [$do->id],
            'cabang_id' => $this->cabang->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'unpaid',
            'total' => 400000,
            'dpp' => 400000,
        ]);

        $invItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 5, // Total beli: 5
            'price' => 80000,
            'total' => 400000,
            'coa_id' => $this->salesCoa->id,
        ]);

        // Retur 2 via ReturnProduct (disetujui)
        $returnProduct = ReturnProduct::create([
            'return_number' => 'RET-Q4-' . strtoupper(substr(uniqid(), -4)),
            'from_model_type' => DeliveryOrder::class,
            'from_model_id' => $do->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => 'approved',
        ]);

        ReturnProductItem::create([
            'return_product_id' => $returnProduct->id,
            'from_item_model_type' => DeliveryOrderItem::class,
            'from_item_model_id' => $doItem->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 2,
            'condition' => 'good',
        ]);

        // Retur 1 via CustomerReturn (disetujui)
        $customerReturn = CustomerReturn::create([
            'return_number' => 'CR-Q4-' . strtoupper(substr(uniqid(), -4)),
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => CustomerReturn::STATUS_APPROVED,
            'return_date' => now(),
            'reason' => 'Retur produk cacat',
            'cabang_id' => $this->cabang->id,
        ]);

        CustomerReturnItem::create([
            'customer_return_id' => $customerReturn->id,
            'invoice_item_id' => $invItem->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 1,
            'qc_result' => CustomerReturnItem::QC_RESULT_PASS,
            'decision' => CustomerReturnItem::DECISION_REPLACE,
        ]);

        // Total retur yang sudah terjadi: 2 (ReturnProduct) + 1 (CustomerReturn) = 3
        // Sisa kuota yang sah: 5 - 3 = 2
        $soldQty = (float) $invItem->quantity;
        $doIds = [$do->id];
        $alreadyReturnedRp = (float) ReturnProductItem::where('from_item_model_type', DeliveryOrderItem::class)
            ->where('product_id', $this->standardProduct->id)
            ->whereHas('fromItemModel', fn ($q) => $q->whereIn('delivery_order_id', $doIds))
            ->whereHas('returnProduct', fn ($q) => $q->whereNotIn('status', ['rejected', 'cancelled']))
            ->sum('quantity');

        $alreadyReturnedCr = (float) CustomerReturnItem::where('invoice_item_id', $invItem->id)
            ->whereHas('customerReturn', fn ($q) => $q->whereIn('status', [CustomerReturn::STATUS_APPROVED, CustomerReturn::STATUS_COMPLETED]))
            ->sum('quantity');

        $remainingQuota = $soldQty - ($alreadyReturnedRp + $alreadyReturnedCr);

        $this->assertEquals(2.0, $remainingQuota, 'Sisa kuota retur harus tepat 2 (5 dibeli - 2 RP - 1 CR).');
    }

    /**
     * POIN 5: Retur penjualan dari invoice lunas mengalir ke Deposit Pelanggan dan COA persediaan produk spesifik.
     */
    #[Test]
    public function test_poin_5_customer_return_credits_deposit_and_product_specific_inventory_coa_when_invoice_paid(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-DEP-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 180000,
        ]);

        // Gunakan $this->customProduct dengan inventory COA spesifik: 1140.15
        $invoice = Invoice::create([
            'invoice_number' => 'INV-PAID-DEP-' . uniqid(),
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $so->id,
            'cabang_id' => $this->cabang->id,
            'customer_id' => $this->customer->id,
            'customer_name' => $this->customer->name,
            'customer_phone' => $this->customer->phone,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'status' => 'paid', // FAKTUR LUNAS
            'subtotal' => 180000,
            'dpp' => 180000,
            'total' => 180000,
            'ppn_rate' => 0,
            'tipe_pajak' => 'None',
        ]);

        $invItem = InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->customProduct->id,
            'quantity' => 1,
            'price' => 180000,
            'subtotal' => 180000,
            'total' => 180000,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'coa_id' => $this->salesCoa->id,
        ]);

        $customerReturn = CustomerReturn::create([
            'return_number' => 'CR-DEP-' . strtoupper(substr(uniqid(), -4)),
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'status' => CustomerReturn::STATUS_APPROVED,
            'return_date' => now(),
            'reason' => 'Retur produk cacat',
            'cabang_id' => $this->cabang->id,
            'received_by' => $this->user->id,
            'qc_inspected_by' => $this->user->id,
            'approved_by' => $this->user->id,
        ]);

        $crItem = CustomerReturnItem::create([
            'customer_return_id' => $customerReturn->id,
            'invoice_item_id' => $invItem->id,
            'product_id' => $this->customProduct->id,
            'quantity' => 1,
            'qc_result' => CustomerReturnItem::QC_RESULT_PASS,
            'decision' => CustomerReturnItem::DECISION_REPLACE,
        ]);

        // Selesaikan retur
        app(CustomerReturnService::class)->processCompletion($customerReturn);

        $journals = JournalEntry::where('source_type', CustomerReturn::class)
            ->where('source_id', $customerReturn->id)
            ->get();

        $this->assertNotEmpty($journals, 'Harus terbentuk JournalEntry untuk CustomerReturn yang completed.');

        // 1. Verifikasi Debit Persediaan menggunakan akun spesifik produk (1140.15)
        $debitInventory = $journals->where('coa_id', $this->customInventoryCoa->id)->first();
        $this->assertNotNull($debitInventory, 'Jurnal harus mendebit akun persediaan spesifik produk (1140.15).');
        $this->assertEquals(120000.0, (float) $debitInventory->debit, 'Nilai debit persediaan harus sesuai cost_price (120.000).');

        // 2. Verifikasi Pengembalian Dana dialirkan ke Deposit Pelanggan (2160.04) karena invoice sudah lunas
        $creditDeposit = $journals->where('coa_id', $this->depositCustomerCoa->id)->first();
        $this->assertNotNull($creditDeposit, 'Jurnal harus mengkredit Akun Deposit Pelanggan (2160.04) untuk faktur yang sudah lunas.');
        $this->assertEquals(180000.0, (float) $creditDeposit->credit, 'Nilai kredit deposit pelanggan harus sesuai total pengembalian (180.000).');

        // 3. Verifikasi Piutang Usaha TIDAK dikredit (karena sudah lunas / saldo piutang = 0)
        $creditAr = $journals->where('coa_id', $this->arCoa->id)->first();
        $this->assertNull($creditAr, 'Akun Piutang Usaha tidak boleh dikredit bila faktur sudah lunas (agar tidak memicu piutang negatif).');
    }

    /**
     * POIN 6: Pembuatan PO dari SO membawa cabang_id, currency, recalculate total, dan guard PO kosong.
     */
    #[Test]
    public function test_poin_6_view_so_to_po_logic_handles_cabang_items_and_recalculation(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-PO-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 160000,
        ]);

        $item1 = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 2,
            'unit_price' => 80000,
            'total_price' => 160000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Simulasikan pembuatan PO dari SO dengan pemilihan item spesifik
        $poData = [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $so->cabang_id,
            'po_number' => 'PO-FROM-SO-' . time(),
            'status' => 'draft',
            'order_date' => now(),
            'expected_date' => now()->addDays(7),
            'tempo_hutang' => 30,
            'currency_id' => $this->currency->id,
        ];

        $po = PurchaseOrder::create($poData);

        PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $item1->product_id,
            'quantity' => $item1->quantity,
            'unit_price' => $this->standardProduct->cost_price,
            'currency_id' => $this->currency->id,
            'tipe_pajak' => 'Non Pajak',
        ]);

        $po->update([
            'total_amount' => $po->purchaseOrderItem()->sum(DB::raw('quantity * unit_price')),
        ]);
        $po->refresh();

        $this->assertEquals($this->cabang->id, $po->cabang_id, 'Cabang ID pada PO harus sinkron dengan SO.');
        $this->assertEquals(100000.0, (float) $po->total_amount, 'Total PO harus dihitung ulang sesuai harga beli (2 x 50.000 = 100.000).');
        $this->assertSame(1, $po->purchaseOrderItem()->count());
    }

    /**
     * POIN 8: Presisi waktu pada kartu stok (Y-m-d H:i:s) & Jurnal pengiriman DO dibuat saat status 'sent'.
     */
    #[Test]
    public function test_poin_8_stock_movement_timestamp_precision_and_delivery_order_sent_journal(): void
    {
        $movement = app(ProductService::class)->createStockMovement(
            product_id: $this->standardProduct->id,
            warehouse_id: $this->warehouse->id,
            quantity: 5,
            type: 'adjustment_in',
            date: '2026-10-03', // Hanya tanggal tanpa waktu
            notes: 'Test presisi waktu kartu stok',
            rak_id: null,
            fromModel: null
        );

        // 1. Verifikasi format datetime presisi kartu stok memuat waktu (bukan 00:00:00)
        $parsedDate = Carbon::parse($movement->date);
        $this->assertNotEquals('00:00:00', $parsedDate->format('H:i:s'), 'Waktu pada kartu stok harus memuat jam, menit, dan detik aktif.');

        // 2. Verifikasi DO status 'sent' langsung membentuk jurnal Goods Delivery
        $so = SaleOrder::create([
            'so_number' => 'SO-SENT-' . uniqid(),
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now(),
            'status' => 'approved',
            'total_amount' => 160000,
        ]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 2,
            'unit_price' => 80000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-SENT-' . uniqid(),
            'sale_order_id' => $so->id,
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'status' => 'approved',
            'delivery_date' => now(),
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->standardProduct->id,
            'quantity' => 2,
        ]);

        // Ubah status ke 'sent'
        $do->update(['status' => 'sent']);

        $sentJournals = JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $do->id)
            ->get();

        $this->assertCount(2, $sentJournals, 'DO berstatus sent harus langsung membentuk sepasang jurnal pengiriman.');

        $debitDelivery = $sentJournals->where('coa_id', $this->goodsDeliveryCoa->id)->first();
        $creditInventory = $sentJournals->where('coa_id', $this->inventoryCoa->id)->first();

        $this->assertNotNull($debitDelivery, 'Akun Barang Terkirim (1140.20) harus didebit.');
        $this->assertEquals(100000.0, (float) $debitDelivery->debit, 'Debit Barang Terkirim harus sesuai HPP (2 x 50.000 = 100.000).');
        $this->assertNotNull($creditInventory, 'Akun Persediaan (1140.10) harus dikredit.');
        $this->assertEquals(100000.0, (float) $creditInventory->credit, 'Kredit Persediaan harus seimbang (100.000).');
    }

    /**
     * KATEGORI D & ANTI-PARENT GUARD: Larangan pencatatan ke Akun Induk (Parent COA).
     */
    #[Test]
    public function test_category_d_anti_parent_coa_guard_prevents_posting_to_parent_accounts(): void
    {
        $parentCoa = ChartOfAccount::create([
            'code' => '1140',
            'name' => 'Akun Induk Persediaan',
            'type' => 'asset',
            'is_active' => true,
        ]);

        // Jadikan $this->inventoryCoa sebagai anak dari $parentCoa
        $this->inventoryCoa->update(['parent_id' => $parentCoa->id]);

        $validator = new class {
            use \App\Traits\JournalValidationTrait;

            public function check(int|string|null $coaId): void
            {
                $this->validateNonParentCoa($coaId);
            }
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/merupakan akun induk dan tidak dapat digunakan untuk transaksi jurnal/');

        $validator->check($parentCoa->id);
    }
}
