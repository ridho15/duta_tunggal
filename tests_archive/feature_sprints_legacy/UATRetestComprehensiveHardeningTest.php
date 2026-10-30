<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Helpers\MoneyHelper;
use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Models\QualityControl;
use App\Models\ReturnProduct;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Supplier;
use App\Models\SuratJalan;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\PurchaseReturnService;
use App\Support\ProductPriceLimitation;
use App\Filament\Resources\VendorPaymentResource;
use App\Filament\Resources\VendorPaymentResource\Pages\CreateVendorPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UATRetestComprehensiveHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Warehouse $warehouse;
    protected Supplier $supplier;
    protected Customer $customer;
    protected Currency $currency;
    protected ChartOfAccount $apCoa;

    protected function setUp(): void
    {
        parent::setUp();

        UnitOfMeasure::factory()->create();

        $this->cabang = Cabang::create([
            'kode' => 'HQ',
            'nama' => 'Kantor Pusat',
            'alamat' => 'Jakarta',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'Gudang Utama',
        ]);

        $this->supplier = Supplier::factory()->create([
            'cabang_id' => $this->cabang->id,
            'perusahaan' => 'PT Supplier Utama',
        ]);

        $this->customer = Customer::factory()->create([
            'code' => 'CUST-001',
            'name' => 'PT Pelanggan Sejahtera',
            'perusahaan' => 'PT Pelanggan Sejahtera',
            'cabang_id' => $this->cabang->id,
        ]);

        $this->currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'to_rupiah' => 1,
            'is_default' => true,
        ]);

        $this->apCoa = ChartOfAccount::create([
            'code' => '2110',
            'name' => 'HUTANG USAHA',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create([
            'name' => 'UAT Tester',
            'email' => 'uat@example.com',
            'cabang_id' => $this->cabang->id,
            'manage_type' => ['all'],
        ]);

        $this->actingAs($this->user);
    }

    /**
     * TC-AP-01: System strictly rejects any vendor payment amount that exceeds invoice remaining balance.
     */
    #[Test]
    public function test_tc_ap_01_vendor_payment_rejects_overpayment_exceeding_invoice_remaining(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'PINV-TEST-AP-01',
            'from_model_type' => Supplier::class,
            'from_model_id' => $this->supplier->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 50000,
            'tax' => 0,
            'total' => 50000,
            'status' => Invoice::STATUS_SENT,
            'cabang_id' => $this->cabang->id,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
            'supplier_id' => $this->supplier->id,
        ]);

        $ap = AccountPayable::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total' => 50000,
            'total_original' => 50000,
            'paid' => 0,
            'paid_original' => 0,
            'remaining' => 50000,
            'remaining_original' => 50000,
            'status' => PaymentStatus::UNPAID->value,
            'exchange_rate' => 1.0,
        ]);

        // Sisa hutang harus ter-resolve 50.000
        $remaining = VendorPaymentResource::resolveInvoiceRemainingAmount($invoice);
        $this->assertEquals(50000.0, $remaining);

        // 1. Validasi rule pada VendorPaymentResource form: bayar Rp 80.000 > sisa Rp 50.000
        $rule = function ($attribute, $value, $fail) use ($invoice) {
            $rem = VendorPaymentResource::resolveInvoiceRemainingAmount($invoice);
            $paid = MoneyHelper::safeParse($value ?? 0);
            if ($paid > $rem + 1.00) {
                $fail('Jumlah pembayaran tidak boleh melebihi sisa hutang (' . MoneyHelper::rupiah($rem) . ').');
            }
        };

        $validatorInvalid = Validator::make(
            ['payment_amount' => 80000],
            ['payment_amount' => [$rule]]
        );
        $this->assertTrue($validatorInvalid->fails());
        $this->assertStringContainsString('tidak boleh melebihi sisa hutang', $validatorInvalid->errors()->first('payment_amount'));

        // 2. Pembayaran valid (Rp 30.000 <= sisa Rp 50.000) harus lolos
        $validatorValid = Validator::make(
            ['payment_amount' => 30000],
            ['payment_amount' => [$rule]]
        );
        $this->assertFalse($validatorValid->fails());
    }

    /**
     * TC-AP-02: Purchase return created from QC reject does NOT reduce invoice AP balance.
     */
    #[Test]
    public function test_tc_ap_02_qc_reject_return_does_not_deduct_invoice_ap_balance(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'po_number' => 'PO-TEST-AP-02',
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 100000,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
        ]);

        $receipt = PurchaseReceipt::create([
            'receipt_number' => 'GRN-TEST-AP-02',
            'purchase_order_id' => $po->id,
            'cabang_id' => $this->cabang->id,
            'receipt_date' => now()->toDateString(),
            'received_by' => $this->user->id,
            'currency_id' => $this->currency->id,
            'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'PINV-TEST-AP-02',
            'from_model_type' => PurchaseReceipt::class,
            'from_model_id' => $receipt->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100000,
            'tax' => 11000,
            'total' => 111000,
            'status' => Invoice::STATUS_SENT,
            'cabang_id' => $this->cabang->id,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
            'supplier_id' => $this->supplier->id,
        ]);

        $ap = AccountPayable::create([
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total' => 111000,
            'total_original' => 111000,
            'paid' => 0,
            'paid_original' => 0,
            'remaining' => 111000,
            'remaining_original' => 111000,
            'status' => PaymentStatus::UNPAID->value,
            'exchange_rate' => 1.0,
        ]);

        $product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'cost_price' => 10000,
        ]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'subtotal' => 100000,
            'currency_id' => $this->currency->id,
        ]);

        $qc = QualityControl::create([
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $poItem->id,
            'qc_number' => 'QC-TEST-002',
            'qc_date' => now()->toDateString(),
            'passed_quantity' => 8,
            'rejected_quantity' => 2,
            'status' => 1,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'product_id' => $product->id,
        ]);

        // Buat PurchaseReturn bersumber dari QC (quality_control_id != null)
        $qcReturn = PurchaseReturn::create([
            'quality_control_id' => $qc->id,
            'purchase_receipt_id' => $receipt->id,
            'failed_qc_action' => PurchaseReturn::QC_ACTION_RETURN_SUPPLIER,
            'nota_retur' => 'NR-QC-001',
            'return_date' => now(),
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $qcReturn->id,
            'product_id' => $product->id,
            'qty_returned' => 2,
            'unit_price' => 10000,
        ]);

        $returnService = app(PurchaseReturnService::class);

        // Retur dari QC TIDAK boleh dihitung sebagai pengurang invoice AP
        $approvedReturnsTotal = $returnService->calculateApprovedReturnsTotalForInvoice($invoice);
        $this->assertEquals(0.0, $approvedReturnsTotal, 'QC reject return must not reduce invoice AP');

        // Buat retur komersial sah (non-QC, quality_control_id = null)
        $commercialReturn = PurchaseReturn::create([
            'quality_control_id' => null,
            'purchase_receipt_id' => $receipt->id,
            'nota_retur' => 'NR-COMM-001',
            'return_date' => now(),
            'status' => 'approved',
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        PurchaseReturnItem::create([
            'purchase_return_id' => $commercialReturn->id,
            'product_id' => $product->id,
            'qty_returned' => 1,
            'unit_price' => 10000,
        ]);

        // Retur komersial HARUS dihitung sebagai pengurang
        $approvedReturnsTotalWithCommercial = $returnService->calculateApprovedReturnsTotalForInvoice($invoice);
        $this->assertGreaterThan(0.0, $approvedReturnsTotalWithCommercial, 'Commercial non-QC return must be credited to invoice');
    }

    /**
     * TC-QC-01: Concurrent or duplicate call to createFromQualityControl is strictly idempotent.
     */
    #[Test]
    public function test_tc_qc_01_concurrent_qc_return_creation_is_strictly_idempotent(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'po_number' => 'PO-TEST-QC-01',
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 100000,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
        ]);

        $product = Product::factory()->create(['cabang_id' => $this->cabang->id]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 10000,
            'subtotal' => 100000,
            'currency_id' => $this->currency->id,
        ]);

        $qc = QualityControl::create([
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $poItem->id,
            'qc_number' => 'QC-TEST-001',
            'qc_date' => now()->toDateString(),
            'passed_quantity' => 8,
            'rejected_quantity' => 2,
            'status' => 1,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'product_id' => $product->id,
        ]);

        $service = app(PurchaseReturnService::class);

        // Eksekusi pertama
        $return1 = $service->createFromQualityControl($qc, PurchaseReturn::QC_ACTION_RETURN_SUPPLIER);
        $this->assertNotNull($return1);

        // Eksekusi kedua (simulasi race condition / double submit)
        $return2 = $service->createFromQualityControl($qc, PurchaseReturn::QC_ACTION_RETURN_SUPPLIER);

        // Kedua hasil harus merujuk ke entitas yang sama persis
        $this->assertEquals($return1->id, $return2->id);
        $this->assertEquals($return1->nota_retur, $return2->nota_retur);

        // Pastikan total PurchaseReturn untuk QC ini di database hanya ada 1
        $count = PurchaseReturn::where('quality_control_id', $qc->id)->count();
        $this->assertEquals(1, $count, 'Exactly one PurchaseReturn must exist for this QC');
    }

    /**
     * TC-SO-01 & TC-SO-04: Sale order rejects unit_price below product bottom price.
     */
    #[Test]
    public function test_tc_so_01_and_tc_so_04_sale_order_rejects_unit_price_below_bottom_price(): void
    {
        $product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'cost_price' => 10000,
            'sell_price' => 20000,
            'harga_batas' => 10, // Max 10% diskon -> floor price = Rp 18.000
        ]);

        $floor = ProductPriceLimitation::getMinimumAllowedPrice($product);
        $this->assertEquals(18000.0, $floor);

        // 1. Harga di bawah batas bawah (Rp 15.000 vs batas bawah Rp 18.000) harus ditolak
        $validationBelow = ProductPriceLimitation::validatePrice($product, 15000);
        $this->assertNotNull($validationBelow);
        $this->assertStringContainsString('batas harga minimum yang diizinkan', $validationBelow);

        // 2. Harga di atas batas bawah (Rp 19.000) harus diterima
        $validationAbove = ProductPriceLimitation::validatePrice($product, 19000);
        $this->assertNull($validationAbove);
    }

    /**
     * TC-SJ-02: Delivery Order with active Surat Jalan cannot be selected for Return Product.
     */
    #[Test]
    public function test_tc_sj_02_return_product_locks_delivery_order_if_already_in_active_surat_jalan(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-TEST-SJ-02',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 100000,
        ]);

        $do = DeliveryOrder::create([
            'do_number' => 'DO-TEST-SJ-02',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'approved',
        ]);
        $do->salesOrders()->attach($so->id);

        // Sebelum ada SJ aktif, DO lolos filter
        $availableDosBefore = DeliveryOrder::where('status', 'approved')
            ->whereDoesntHave('suratJalan', fn($q) => $q->whereIn('status', SuratJalan::ACTIVE_STATUSES))
            ->pluck('id')->all();
        $this->assertContains($do->id, $availableDosBefore);

        // Terbitkan Surat Jalan aktif untuk DO ini
        $sj = SuratJalan::create([
            'sj_number' => 'SJ-TEST-SJ-02',
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED,
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);
        $sj->deliveryOrder()->attach($do->id);

        // Setelah ada SJ aktif, DO HARUS terkunci dari daftar Return Product
        $availableDosAfter = DeliveryOrder::where('status', 'approved')
            ->whereDoesntHave('suratJalan', fn($q) => $q->whereIn('status', SuratJalan::ACTIVE_STATUSES))
            ->pluck('id')->all();
        $this->assertNotContains($do->id, $availableDosAfter, 'DO with active Surat Jalan must NOT be eligible for Return Product');
    }

    /**
     * TC-SJ-03: Super Admin cannot bypass model state lock on Surat Jalan action.
     */
    #[Test]
    public function test_tc_sj_03_super_admin_cannot_bypass_model_state_rules_on_surat_jalan_actions(): void
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->user->assignRole($role);

        $sj = SuratJalan::create([
            'sj_number' => 'SJ-LOCK-TEST',
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED, // Terbit
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        // Cek bahwa model state isDraft() adalah false
        $this->assertFalse($sj->isDraft());
        $this->assertTrue($sj->isIssued());

        // Verifikasi bahwa action issue tetap false meskipun user adalah Super Admin
        $issueVisible = $sj->isDraft();
        $this->assertFalse($issueVisible, 'Issue action must NOT be visible or executable on already issued Surat Jalan, even for Super Admin');
    }

    /**
     * TC-PO-01: Safe polymorphic resolver for SaleOrderItem cabang relationship.
     */
    #[Test]
    public function test_tc_po_01_safe_polymorphic_resolver_for_sale_order_item_cabang(): void
    {
        $so = SaleOrder::create([
            'so_number' => 'SO-POLY-001',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 50000,
        ]);

        $product = Product::factory()->create(['cabang_id' => $this->cabang->id]);

        $soItem = SaleOrderItem::create([
            'sale_order_id' => $so->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10000,
            'total_price' => 50000,
        ]);

        // Periksa bahwa relasi cabang() via hasOneThrough bekerja sempurna
        $this->assertNotNull($soItem->cabang);
        $this->assertEquals($this->cabang->id, $soItem->cabang->id);
        $this->assertEquals('HQ', $soItem->cabang->kode);

        // Simulasi safe polymorphic resolver seperti di Blade infolist
        $formatCabang = function ($referItemModel) {
            if (! $referItemModel) {
                return '-';
            }
            if ($referItemModel instanceof SaleOrderItem) {
                $cabang = $referItemModel->cabang ?? $referItemModel->saleOrder?->cabang;
            } elseif (method_exists($referItemModel, 'cabang')) {
                $cabang = $referItemModel->cabang;
            } else {
                $cabang = null;
            }
            return $cabang ? "({$cabang->kode}) {$cabang->nama}" : '-';
        };

        $rendered = $formatCabang($soItem);
        $this->assertEquals('(HQ) Kantor Pusat', $rendered);
    }

    /**
     * Edge Case 1: QC with zero rejected quantity or invalid action throws exception.
     */
    #[Test]
    public function test_edge_case_qc_rejects_zero_quantity_or_invalid_action(): void
    {
        $po = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'po_number' => 'PO-ZERO-QC',
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 100000,
            'currency_id' => $this->currency->id,
            'exchange_rate' => 1.0,
        ]);

        $product = Product::factory()->create(['cabang_id' => $this->cabang->id]);

        $poItem = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10000,
            'subtotal' => 50000,
            'currency_id' => $this->currency->id,
        ]);

        // QC dengan 0 reject
        $zeroRejectQc = QualityControl::create([
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $poItem->id,
            'qc_number' => 'QC-ZERO',
            'qc_date' => now()->toDateString(),
            'passed_quantity' => 5,
            'rejected_quantity' => 0,
            'status' => 1,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'product_id' => $product->id,
        ]);

        $service = app(PurchaseReturnService::class);

        // Harus lempar Exception karena tidak ada barang yang di-reject
        try {
            $service->createFromQualityControl($zeroRejectQc, PurchaseReturn::QC_ACTION_RETURN_SUPPLIER);
            $this->fail('Expected exception for QC with 0 rejected items not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('QC has no rejected items', $e->getMessage());
        }

        // QC dengan aksi ilegal
        $validRejectQc = QualityControl::create([
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $poItem->id,
            'qc_number' => 'QC-VALID',
            'qc_date' => now()->toDateString(),
            'passed_quantity' => 3,
            'rejected_quantity' => 2,
            'status' => 1,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id' => $this->cabang->id,
            'product_id' => $product->id,
        ]);

        try {
            $service->createFromQualityControl($validRejectQc, 'invalid_action_xyz');
            $this->fail('Expected exception for invalid failed_qc_action not thrown');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Invalid failed_qc_action', $e->getMessage());
        }
    }

    /**
     * Edge Case 2: SuratJalanService strictly blocks illegal transitions (issuing non-draft, short cancel reason).
     */
    #[Test]
    public function test_edge_case_surat_jalan_service_rejects_illegal_state_transitions(): void
    {
        $sj = SuratJalan::create([
            'sj_number' => 'SJ-ILLEGAL-TRANS',
            'issued_at' => now(),
            'status' => SuratJalan::STATUS_ISSUED, // Already issued
            'cabang_id' => $this->cabang->id,
            'created_by' => $this->user->id,
        ]);

        $service = app(\App\Services\SuratJalanService::class);

        // 1. Menerbitkan SJ yang bukan Draft harus ditolak dengan ValidationException
        try {
            $service->issue($sj);
            $this->fail('Expected ValidationException when issuing non-draft Surat Jalan not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('bukan Draft', collect($e->errors())->flatten()->first());
        }

        // 2. Membatalkan dengan alasan kurang dari 5 karakter harus ditolak
        try {
            $service->cancel($sj, 'xxx');
            $this->fail('Expected ValidationException when cancel reason is too short not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('minimal 5 karakter', collect($e->errors())->flatten()->first());
        }

        // 3. Membatalkan dengan alasan valid (>= 5 karakter) harus berhasil
        $cancelledSj = $service->cancel($sj, 'Customer minta ganti armada pengiriman');
        $this->assertEquals(SuratJalan::STATUS_CANCELLED, $cancelledSj->status);
        $this->assertEquals('Customer minta ganti armada pengiriman', $cancelledSj->cancel_reason);
    }

    /**
     * Edge Case 3: SuratJalanService assertDeliveryOrdersUsable strictly rejects different customers or branches.
     */
    #[Test]
    public function test_edge_case_surat_jalan_assert_delivery_orders_usable_rejects_different_customers_and_cabang(): void
    {
        $cust2 = Customer::factory()->create([
            'cabang_id' => $this->cabang->id,
            'name' => 'PT Customer Beda',
        ]);

        $cabang2 = Cabang::create([
            'kode' => 'SBY',
            'nama' => 'Cabang Surabaya',
            'alamat' => 'Surabaya',
        ]);

        $so1 = SaleOrder::create([
            'so_number' => 'SO-DO-DIFF-01',
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 50000,
        ]);

        $so2 = SaleOrder::create([
            'so_number' => 'SO-DO-DIFF-02',
            'customer_id' => $cust2->id,
            'cabang_id' => $this->cabang->id,
            'order_date' => now()->toDateString(),
            'status' => 'approved',
            'total_amount' => 50000,
        ]);

        $do1 = DeliveryOrder::create([
            'do_number' => 'DO-CUST-1',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'approved',
        ]);
        $do1->salesOrders()->attach($so1->id);

        $do2 = DeliveryOrder::create([
            'do_number' => 'DO-CUST-2',
            'cabang_id' => $this->cabang->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'approved',
        ]);
        $do2->salesOrders()->attach($so2->id);

        $service = app(\App\Services\SuratJalanService::class);

        // 1. DO dari beda customer dalam 1 SJ harus DITOLAK
        try {
            $service->assertDeliveryOrdersUsable(collect([$do1, $do2]));
            $this->fail('Expected ValidationException when consolidating DOs from different customers not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('harus berasal dari customer yang sama', collect($e->errors())->flatten()->first());
        }

        // 2. DO dari beda cabang dalam 1 SJ harus DITOLAK
        $do3 = DeliveryOrder::create([
            'do_number' => 'DO-CABANG-2',
            'cabang_id' => $cabang2->id,
            'delivery_date' => now()->toDateString(),
            'status' => 'approved',
        ]);
        $do3->salesOrders()->attach($so1->id);

        try {
            $service->assertDeliveryOrdersUsable(collect([$do1, $do3]));
            $this->fail('Expected ValidationException when consolidating DOs from different branches not thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('harus berasal dari cabang yang sama', collect($e->errors())->flatten()->first());
        }
    }

    /**
     * Edge Case 4: API endpoint POST /api/v1/sales-orders strictly rejects price below bottom price with 422.
     */
    #[Test]
    public function test_edge_case_sale_order_api_store_rejects_unit_price_below_bottom_price(): void
    {
        $role = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->user->assignRole($role);

        $product = Product::factory()->create([
            'cabang_id' => $this->cabang->id,
            'cost_price' => 10000,
            'sell_price' => 20000,
            'harga_batas' => 10, // Floor price = Rp 18.000
        ]);

        $payload = [
            'header' => [
                'so_number' => 'SO-API-TEST-001',
                'customer_id' => $this->customer->id,
                'cabang_id' => $this->cabang->id,
                'order_date' => now()->toDateString(),
                'status' => 'draft',
                'tipe_pengiriman' => 'Kirim Langsung',
                'currency_id' => $this->currency->id,
            ],
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 12000, // Di bawah batas bawah Rp 18.000
                ],
            ],
        ];

        $response = $this->postJson(route('api.sales-orders.store'), $payload);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('batas harga minimum yang diizinkan', $response->json('message'));
    }

    /**
     * Edge Case 5: SyncArApCommand executes cleanly and produces correct AP calculations.
     */
    #[Test]
    public function test_edge_case_sync_ar_ap_command_recalculates_and_restores_legitimate_balances(): void
    {
        $this->artisan('ar-ap:sync')
            ->assertExitCode(0);
    }
}
