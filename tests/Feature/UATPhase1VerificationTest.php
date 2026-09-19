<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Filament\Resources\PurchaseReceiptResource;
use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\JournalEntry;
use App\Models\OrderRequest;
use App\Models\PaymentRequest;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\VendorPaymentDetail;
use App\Models\Warehouse;
use App\Policies\InvoicePolicy;
use App\Policies\OrderRequestPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\SaleOrderPolicy;
use App\Policies\VendorPaymentPolicy;
use App\Services\PurchaseInvoiceAccountingService;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UATPhase1VerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Supplier $supplier;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        UnitOfMeasure::factory()->create();
        Currency::factory()->create();
        $this->cabang = Cabang::factory()->create();
        $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->product = Product::factory()->create();

        ChartOfAccount::firstOrCreate([
            'code' => config('coa.accounts_payable', '2110'),
        ], [
            'name' => 'Hutang Usaha',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        ChartOfAccount::firstOrCreate([
            'code' => config('coa.unbilled_purchase', '2120'),
        ], [
            'name' => 'Hutang Belum Difakturkan',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        ChartOfAccount::firstOrCreate([
            'code' => config('coa.ppn_masukan', '1180'),
        ], [
            'name' => 'PPN Masukan',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        ChartOfAccount::firstOrCreate([
            'code' => config('coa.inventory', '1140.01'),
        ], [
            'name' => 'Persediaan Barang',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        ChartOfAccount::firstOrCreate([
            'code' => config('coa.cash_and_bank', '1112.01'),
        ], [
            'name' => 'Kas & Bank',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->actingAs($this->user);
    }

    protected function givePermission(string $permissionName): void
    {
        Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        $this->user->givePermissionTo($permissionName);
    }

    // =========================================================================
    // Issue 4: Isolasi Invoice Draft dari Jurnal & AP/AR
    // =========================================================================

    public function test_issue_4_draft_invoice_does_not_create_ap_and_does_not_post_journal(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'PINV-TEST-DRAFT-01',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100000,
            'tax' => 0,
            'total' => 100000,
            'status' => Invoice::STATUS_DRAFT,
            'cabang_id' => $this->cabang->id,
        ]);

        $this->assertDatabaseMissing('account_payables', [
            'invoice_id' => $invoice->id,
        ]);

        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
        ]);
    }

    public function test_issue_4_posting_draft_invoice_creates_ap_and_posts_journal(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'completed',
        ]);

        PurchaseReceipt::create([
            'receipt_number' => 'RN-TEST-DRAFT-01',
            'purchase_order_id' => $po->id,
            'receipt_date' => now(),
            'status' => 'completed',
            'received_by' => $this->user->id,
            'currency_id' => Currency::first()->id,
            'cabang_id' => $this->cabang->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number' => 'PINV-TEST-DRAFT-02',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 100000,
            'tax' => 11000,
            'total' => 111000,
            'status' => Invoice::STATUS_DRAFT,
            'cabang_id' => $this->cabang->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
            'price' => 100000,
            'tax_rate' => 11,
            'tax_amount' => 11000,
            'subtotal' => 100000,
            'total' => 100000,
        ]);

        // Post and approve via the new service method
        app(PurchaseInvoiceAccountingService::class)->postAndApproveInvoice($invoice);

        $this->assertSame(Invoice::STATUS_SENT, $invoice->fresh()->status);

        $this->assertDatabaseHas('account_payables', [
            'invoice_id' => $invoice->id,
            'supplier_id' => $this->supplier->id,
            'total' => 111000,
            'remaining' => 111000,
        ]);

        $entries = JournalEntry::where('source_type', Invoice::class)
            ->where('source_id', $invoice->id)
            ->get();

        $this->assertGreaterThan(0, $entries->count());
        $this->assertSame((float) $entries->sum('debit'), (float) $entries->sum('credit'));
    }

    public function test_issue_4_invoice_policy_restricts_edit_and_delete_to_draft_only(): void
    {
        $this->givePermission('update invoice');
        $this->givePermission('delete invoice');

        $policy = new InvoicePolicy();

        $draftInvoice = new Invoice(['status' => 'draft']);
        $this->assertTrue($policy->update($this->user, $draftInvoice));
        $this->assertTrue($policy->delete($this->user, $draftInvoice));

        $sentInvoice = new Invoice(['status' => 'sent']);
        $this->assertFalse($policy->update($this->user, $sentInvoice));
        $this->assertFalse($policy->delete($this->user, $sentInvoice));

        $paidInvoice = new Invoice(['status' => 'paid']);
        $this->assertFalse($policy->update($this->user, $paidInvoice));
        $this->assertFalse($policy->delete($this->user, $paidInvoice));
    }

    // =========================================================================
    // Issue 6: State Locking (PO, SO, OR, Vendor Payment)
    // =========================================================================

    public function test_issue_6_purchase_order_policy_locks_approved_and_completed_po(): void
    {
        $this->givePermission('update purchase order');
        $this->givePermission('delete purchase order');

        $policy = new PurchaseOrderPolicy();

        $draftPo = new PurchaseOrder(['status' => 'draft']);
        $this->assertTrue($policy->update($this->user, $draftPo));
        $this->assertTrue($policy->delete($this->user, $draftPo));

        $reqApprovePo = new PurchaseOrder(['status' => 'request_approve']);
        $this->assertTrue($policy->update($this->user, $reqApprovePo));
        $this->assertFalse($policy->delete($this->user, $reqApprovePo));

        $approvedPo = new PurchaseOrder(['status' => 'approved']);
        $this->assertFalse($policy->update($this->user, $approvedPo));
        $this->assertFalse($policy->delete($this->user, $approvedPo));

        $completedPo = new PurchaseOrder(['status' => 'completed']);
        $this->assertFalse($policy->update($this->user, $completedPo));
        $this->assertFalse($policy->delete($this->user, $completedPo));

        $closedPo = new PurchaseOrder(['status' => 'closed']);
        $this->assertFalse($policy->update($this->user, $closedPo));
        $this->assertFalse($policy->delete($this->user, $closedPo));
    }

    public function test_issue_6_sales_order_policy_locks_approved_and_completed_so(): void
    {
        $this->givePermission('update sales order');
        $this->givePermission('delete sales order');

        $policy = new SaleOrderPolicy();

        $draftSo = new SaleOrder(['status' => 'draft']);
        $this->assertTrue($policy->update($this->user, $draftSo));
        $this->assertTrue($policy->delete($this->user, $draftSo));

        $reqApproveSo = new SaleOrder(['status' => 'request_approve']);
        $this->assertTrue($policy->update($this->user, $reqApproveSo));
        $this->assertFalse($policy->delete($this->user, $reqApproveSo));

        $approvedSo = new SaleOrder(['status' => 'approved']);
        $this->assertFalse($policy->update($this->user, $approvedSo));
        $this->assertFalse($policy->delete($this->user, $approvedSo));

        $completedSo = new SaleOrder(['status' => 'completed']);
        $this->assertFalse($policy->update($this->user, $completedSo));
        $this->assertFalse($policy->delete($this->user, $completedSo));

        $closedSo = new SaleOrder(['status' => 'closed']);
        $this->assertFalse($policy->update($this->user, $closedSo));
        $this->assertFalse($policy->delete($this->user, $closedSo));
    }

    public function test_issue_6_order_request_policy_locks_when_po_exists_or_status_locked(): void
    {
        $this->givePermission('update order request');
        $this->givePermission('delete order request');

        $policy = new OrderRequestPolicy();

        $or = OrderRequest::create([
            'request_number' => 'OR-TEST-LOCK-01',
            'request_date' => now(),
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->assertTrue($policy->update($this->user, $or));
        $this->assertTrue($policy->delete($this->user, $or));

        // Create PO from this OR
        $po = PurchaseOrder::factory()->create([
            'refer_model_type' => OrderRequest::class,
            'refer_model_id' => $or->id,
            'supplier_id' => $this->supplier->id,
            'status' => 'draft',
        ]);

        $or->refresh();
        $this->assertFalse($policy->update($this->user, $or));
        $this->assertFalse($policy->delete($this->user, $or));
    }

    public function test_issue_6_vendor_payment_policy_locks_non_draft_payments(): void
    {
        $this->givePermission('update vendor payment');
        $this->givePermission('delete vendor payment');

        $policy = new VendorPaymentPolicy();

        $draftPayment = new VendorPayment(['status' => 'draft']);
        $this->assertTrue($policy->update($this->user, $draftPayment));
        $this->assertTrue($policy->delete($this->user, $draftPayment));

        $postedPayment = new VendorPayment(['status' => 'posted']);
        $this->assertFalse($policy->update($this->user, $postedPayment));
        $this->assertFalse($policy->delete($this->user, $postedPayment));

        $paidPayment = new VendorPayment(['status' => 'paid']);
        $this->assertFalse($policy->update($this->user, $paidPayment));
        $this->assertFalse($policy->delete($this->user, $paidPayment));
    }

    // =========================================================================
    // Issue 7: Proteksi Status Purchase Receipt di Tabel (TextColumn badge, not SelectColumn)
    // =========================================================================

    public function test_issue_7_purchase_receipt_table_status_column_is_not_editable_select_column(): void
    {
        $resourceFile = file_get_contents(app_path('Filament/Resources/PurchaseReceiptResource.php'));

        $this->assertStringNotContainsString(
            "SelectColumn::make('status')",
            $resourceFile,
            'Status column in PurchaseReceiptResource must not be an editable SelectColumn.'
        );

        $this->assertStringContainsString(
            "TextColumn::make('status')",
            $resourceFile,
            'Status column in PurchaseReceiptResource must be a read-only TextColumn.'
        );
    }

    // =========================================================================
    // Issue 5: Perhitungan & Sinkronisasi Payment Request
    // =========================================================================

    public function test_issue_5_payment_request_calculates_real_remaining_debt_minus_active_prs(): void
    {
        $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

        $invoice = Invoice::create([
            'invoice_number' => 'PINV-PR-CALC-01',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 1000000,
            'tax' => 0,
            'total' => 1000000,
            'status' => Invoice::STATUS_SENT,
            'cabang_id' => $this->cabang->id,
        ]);

        // InvoiceObserver automatically created AP on invoice creation. Update it to simulate partial payment.
        $ap = AccountPayable::where('invoice_id', $invoice->id)->firstOrFail();
        $ap->update([
            'total' => 1000000,
            'paid' => 400000,
            'remaining' => 600000,
            'total_original' => 1000000,
            'paid_original' => 400000,
            'remaining_original' => 600000,
            'status' => PaymentStatus::UNPAID->value,
        ]);

        // Step 1: Without any active PR, sisa hutang riil should be 600,000 (not 1,000,000)
        $remaining = PaymentRequest::getInvoiceRemainingPayable($invoice);
        $this->assertSame(600000.0, $remaining);

        // Step 2: Create another active PR for 250,000
        PaymentRequest::create([
            'request_number' => 'PR-TEST-001',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'request_date' => now(),
            'payment_date' => now()->addDays(7),
            'total_amount' => 250000,
            'selected_invoices' => [$invoice->id],
            'status' => PaymentRequest::STATUS_PENDING,
            'requested_by' => $this->user->id,
        ]);

        // Sisa hutang riil should now be 600,000 - 250,000 = 350,000
        $netRemaining = PaymentRequest::getInvoiceRemainingPayable($invoice);
        $this->assertSame(350000.0, $netRemaining);
    }

    public function test_issue_5_vendor_payment_settlement_auto_closes_related_payment_request(): void
    {
        $po = PurchaseOrder::factory()->create(['supplier_id' => $this->supplier->id]);

        $invoice = Invoice::create([
            'invoice_number' => 'PINV-PR-AUTO-01',
            'from_model_type' => PurchaseOrder::class,
            'from_model_id' => $po->id,
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 500000,
            'tax' => 0,
            'total' => 500000,
            'status' => Invoice::STATUS_SENT,
            'cabang_id' => $this->cabang->id,
        ]);

        // AP was automatically created by InvoiceObserver
        $ap = AccountPayable::where('invoice_id', $invoice->id)->firstOrFail();

        $pr = PaymentRequest::create([
            'request_number' => 'PR-AUTO-CLOSE-01',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabang->id,
            'request_date' => now(),
            'payment_date' => now()->addDays(3),
            'total_amount' => 500000,
            'selected_invoices' => [$invoice->id],
            'status' => PaymentRequest::STATUS_APPROVED,
            'requested_by' => $this->user->id,
        ]);

        $this->assertSame(PaymentRequest::STATUS_APPROVED, $pr->status);

        // Make full vendor payment (VendorPaymentObserver automatically creates details, updates AP, and syncs PR)
        VendorPayment::factory()->create([
            'supplier_id' => $this->supplier->id,
            'total_payment' => 500000,
            'status' => 'paid',
            'payment_date' => now(),
            'payment_method' => 'Cash',
            'selected_invoices' => [$invoice->id],
        ]);

        // Observer runs and updates AP and syncs PR
        $ap->refresh();
        $this->assertLessThanOrEqual(0.01, (float) $ap->remaining);

        $pr->refresh();
        $this->assertSame(PaymentRequest::STATUS_PAID, $pr->status);
    }
}
