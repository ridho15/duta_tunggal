<?php

namespace Tests\Feature;

use App\Exceptions\ClosedPeriodException;
use App\Models\AccountingPeriod;
use App\Models\AccountPayable;
use App\Models\Cabang;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use App\Policies\AccountingPeriodPolicy;
use App\Policies\AccountPayablePolicy;
use App\Policies\CustomerReceiptPolicy;
use App\Policies\PaymentRequestPolicy;
use App\Policies\VendorPaymentPolicy;
use App\Services\AccountingPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceAndAccountingIntegrityP0Test extends TestCase
{
    use RefreshDatabase;

    protected Cabang $cabangA;
    protected Cabang $cabangB;
    protected User $userA;
    protected User $userB;
    protected User $superAdmin;
    protected Supplier $supplier;
    protected Customer $customer;
    protected Currency $currency;
    protected ChartOfAccount $cashCoa;
    protected ChartOfAccount $arCoa;
    protected ChartOfAccount $apCoa;
    protected ChartOfAccount $revenueCoa;
    protected ChartOfAccount $expenseCoa;
    protected ChartOfAccount $retainedEarningsCoa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cabangA = Cabang::create([
            'kode' => 'CBA',
            'nama' => 'Cabang Alfa',
            'alamat' => 'Alamat A',
        ]);

        $this->cabangB = Cabang::create([
            'kode' => 'CBB',
            'nama' => 'Cabang Beta',
            'alamat' => 'Alamat B',
        ]);

        $this->currency = Currency::create([
            'code' => 'IDR',
            'name' => 'Rupiah',
            'symbol' => 'Rp',
            'exchange_rate' => 1.0,
            'is_default' => true,
        ]);

        $this->cashCoa = ChartOfAccount::create([
            'code' => '1110',
            'name' => 'Kas dan Bank',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->arCoa = ChartOfAccount::create([
            'code' => '1120',
            'name' => 'Piutang Usaha',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        $this->apCoa = ChartOfAccount::create([
            'code' => '2110',
            'name' => 'Hutang Usaha',
            'type' => 'Liability',
            'is_active' => true,
        ]);

        $this->revenueCoa = ChartOfAccount::create([
            'code' => '4100',
            'name' => 'Pendapatan Penjualan',
            'type' => 'Revenue',
            'is_active' => true,
        ]);

        $this->expenseCoa = ChartOfAccount::create([
            'code' => '5100',
            'name' => 'Beban Operasional',
            'type' => 'Expense',
            'is_active' => true,
        ]);

        $this->retainedEarningsCoa = ChartOfAccount::create([
            'code' => '3400',
            'name' => 'Laba Ditahan',
            'type' => 'Equity',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::factory()->create([
            'cabang_id' => $this->cabangA->id,
        ]);

        $this->customer = Customer::factory()->create([
            'cabang_id' => $this->cabangA->id,
            'tipe_pembayaran' => 'Kredit',
        ]);

        $this->userA = User::factory()->create([
            'cabang_id' => $this->cabangA->id,
        ]);

        $this->userB = User::factory()->create([
            'cabang_id' => $this->cabangB->id,
        ]);

        $this->superAdmin = User::factory()->create([
            'cabang_id' => $this->cabangA->id,
        ]);

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $this->superAdmin->assignRole('Super Admin');

        // Grant required Spatie permissions to test users
        $permissions = [
            'view any account payable', 'view account payable', 'create account payable', 'update account payable', 'delete account payable',
            'view any vendor payment', 'view vendor payment', 'create vendor payment', 'update vendor payment', 'delete vendor payment',
            'view any payment request', 'view payment request', 'create payment request', 'update payment request', 'delete payment request',
            'view any customer receipt', 'view customer receipt', 'create customer receipt', 'update customer receipt', 'delete customer receipt',
            'view any accounting period', 'view accounting period', 'create accounting period', 'update accounting period', 'delete accounting period',
            'close accounting period', 'reopen accounting period',
            'view journal entry',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
            $this->userA->givePermissionTo($perm);
            $this->userB->givePermissionTo($perm);
        }
    }

    /**
     * Test 1: CabangScope enforces branch isolation on AccountPayable, PaymentRequest, and VendorPayment
     */
    public function test_cabang_scope_isolates_account_payable_payment_request_and_vendor_payment(): void
    {
        // 1. AccountPayable
        $invoiceA = Invoice::factory()->create(['cabang_id' => $this->cabangA->id]);
        $invoiceB = Invoice::factory()->create(['cabang_id' => $this->cabangB->id]);

        $apA = AccountPayable::create([
            'supplier_id' => $this->supplier->id,
            'invoice_id' => $invoiceA->id,
            'cabang_id' => $this->cabangA->id,
            'total' => 1000000,
            'paid' => 0,
            'remaining' => 1000000,
            'status' => 'belum lunas',
        ]);

        $apB = AccountPayable::create([
            'supplier_id' => $this->supplier->id,
            'invoice_id' => $invoiceB->id,
            'cabang_id' => $this->cabangB->id,
            'total' => 2000000,
            'paid' => 0,
            'remaining' => 2000000,
            'status' => 'belum lunas',
        ]);

        // 2. PaymentRequest
        $prA = PaymentRequest::create([
            'request_number' => 'PR-A-001',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangA->id,
            'total_amount' => 500000,
            'status' => PaymentRequest::STATUS_DRAFT,
            'requested_by' => $this->userA->id,
            'request_date' => now()->toDateString(),
        ]);

        $prB = PaymentRequest::create([
            'request_number' => 'PR-B-001',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangB->id,
            'total_amount' => 750000,
            'status' => PaymentRequest::STATUS_DRAFT,
            'requested_by' => $this->userB->id,
            'request_date' => now()->toDateString(),
        ]);

        // 3. VendorPayment
        $vpA = VendorPayment::create([
            'payment_number' => 'VP-A-001',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangA->id,
            'total_payment' => 500000,
            'status' => VendorPayment::STATUS_DRAFT,
            'payment_date' => now()->toDateString(),
        ]);

        $vpB = VendorPayment::create([
            'payment_number' => 'VP-B-001',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangB->id,
            'total_payment' => 750000,
            'status' => VendorPayment::STATUS_DRAFT,
            'payment_date' => now()->toDateString(),
        ]);

        // Authenticate as User A (Cabang A)
        $this->actingAs($this->userA);

        $visibleAps = AccountPayable::all();
        $this->assertTrue($visibleAps->contains('id', $apA->id));
        $this->assertFalse($visibleAps->contains('id', $apB->id));

        $visiblePrs = PaymentRequest::all();
        $this->assertTrue($visiblePrs->contains('id', $prA->id));
        $this->assertFalse($visiblePrs->contains('id', $prB->id));

        $visibleVps = VendorPayment::all();
        $this->assertTrue($visibleVps->contains('id', $vpA->id));
        $this->assertFalse($visibleVps->contains('id', $vpB->id));

        // Authenticate as User B (Cabang B)
        $this->actingAs($this->userB);

        $visibleApsB = AccountPayable::all();
        $this->assertFalse($visibleApsB->contains('id', $apA->id));
        $this->assertTrue($visibleApsB->contains('id', $apB->id));

        $visiblePrsB = PaymentRequest::all();
        $this->assertFalse($visiblePrsB->contains('id', $prA->id));
        $this->assertTrue($visiblePrsB->contains('id', $prB->id));

        $visibleVpsB = VendorPayment::all();
        $this->assertFalse($visibleVpsB->contains('id', $vpA->id));
        $this->assertTrue($visibleVpsB->contains('id', $vpB->id));
    }

    /**
     * Test 2: Policies deny cross-branch access for AccountPayable, PaymentRequest, and VendorPayment
     */
    public function test_policies_block_cross_branch_access(): void
    {
        $apPolicy = app(AccountPayablePolicy::class);
        $prPolicy = app(PaymentRequestPolicy::class);
        $vpPolicy = app(VendorPaymentPolicy::class);

        $invoiceB = Invoice::factory()->create(['cabang_id' => $this->cabangB->id]);

        $apB = AccountPayable::create([
            'supplier_id' => $this->supplier->id,
            'invoice_id' => $invoiceB->id,
            'cabang_id' => $this->cabangB->id,
            'total' => 2000000,
            'paid' => 0,
            'remaining' => 2000000,
            'status' => 'belum lunas',
        ]);

        $prB = PaymentRequest::create([
            'request_number' => 'PR-B-002',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangB->id,
            'total_amount' => 750000,
            'status' => PaymentRequest::STATUS_DRAFT,
            'requested_by' => $this->userB->id,
            'request_date' => now()->toDateString(),
        ]);

        $vpB = VendorPayment::create([
            'payment_number' => 'VP-B-002',
            'supplier_id' => $this->supplier->id,
            'cabang_id' => $this->cabangB->id,
            'total_payment' => 750000,
            'status' => VendorPayment::STATUS_DRAFT,
            'payment_date' => now()->toDateString(),
        ]);

        // User A must not be allowed to view, update, or delete Cabang B records
        $this->assertFalse($apPolicy->view($this->userA, $apB));
        $this->assertFalse($apPolicy->update($this->userA, $apB));
        $this->assertFalse($apPolicy->delete($this->userA, $apB));

        $this->assertFalse($prPolicy->view($this->userA, $prB));
        $this->assertFalse($prPolicy->update($this->userA, $prB));
        $this->assertFalse($prPolicy->delete($this->userA, $prB));

        $this->assertFalse($vpPolicy->view($this->userA, $vpB));
        $this->assertFalse($vpPolicy->update($this->userA, $vpB));
        $this->assertFalse($vpPolicy->delete($this->userA, $vpB));
    }

    /**
     * Test 3: CustomerReceipt deletion guard blocks deleting posted/paid receipts at Model and Policy levels
     */
    public function test_customer_receipt_deletion_guard_protects_posted_receipts(): void
    {
        $receipt = CustomerReceipt::create([
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabangA->id,
            'coa_id' => $this->cashCoa->id,
            'total_payment' => 1500000,
            'status' => 'Draft',
            'payment_date' => now()->toDateString(),
        ]);

        // Attach a journal entry simulating ledger posting
        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => now()->toDateString(),
            'reference' => 'REC-' . $receipt->id,
            'description' => 'Test receipt journal',
            'debit' => 1500000,
            'credit' => 0,
            'journal_type' => 'receipt',
            'cabang_id' => $this->cabangA->id,
            'source_type' => CustomerReceipt::class,
            'source_id' => $receipt->id,
        ]);

        $receipt->updateQuietly(['status' => 'Paid']);

        // Policy must block delete
        $policy = app(CustomerReceiptPolicy::class);
        $this->assertFalse($policy->delete($this->userA, $receipt));
        $this->assertFalse($policy->forceDelete($this->userA, $receipt));

        // Model deleting hook must throw Exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Penerimaan customer yang sudah diproses atau memiliki jurnal tidak dapat dihapus');
        $receipt->delete();
    }

    /**
     * Test 4: Draft CustomerReceipt without journals can be safely deleted
     */
    public function test_draft_customer_receipt_can_be_safely_deleted(): void
    {
        $draftReceipt = CustomerReceipt::create([
            'customer_id' => $this->customer->id,
            'cabang_id' => $this->cabangA->id,
            'total_payment' => 500000,
            'status' => 'Draft',
            'payment_date' => now()->toDateString(),
        ]);

        $policy = app(CustomerReceiptPolicy::class);
        $this->assertTrue($policy->delete($this->userA, $draftReceipt));

        $deleted = $draftReceipt->delete();
        $this->assertTrue($deleted);
        $this->assertSoftDeleted('customer_receipts', ['id' => $draftReceipt->id]);
    }

    /**
     * Test 5: Closed AccountingPeriod blocks journal creation and update with ClosedPeriodException
     */
    public function test_closed_accounting_period_blocks_journal_entries(): void
    {
        $period = AccountingPeriod::create([
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => 'open',
            'cabang_id' => $this->cabangA->id,
        ]);

        $service = app(AccountingPeriodService::class);
        $closedPeriod = $service->closePeriod($period, $this->userA->id, false);

        $this->assertEquals('closed', $closedPeriod->status);
        $this->assertNotNull($closedPeriod->closed_at);

        // Attempting to create a journal entry dated in closed period must throw ClosedPeriodException
        $this->expectException(ClosedPeriodException::class);
        $this->expectExceptionMessage('sudah ditutup');

        JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => '2026-08-15',
            'reference' => 'TEST-CLOSE-001',
            'description' => 'Should be blocked by closed period',
            'debit' => 100000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabangA->id,
        ]);
    }

    /**
     * Test 6: Reopening an AccountingPeriod allows new journal entries
     */
    public function test_reopening_accounting_period_allows_transactions(): void
    {
        $period = AccountingPeriod::create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => 'closed',
            'cabang_id' => $this->cabangA->id,
            'closed_at' => now(),
            'closed_by' => $this->userA->id,
        ]);

        $service = app(AccountingPeriodService::class);
        $reopenedPeriod = $service->reopenPeriod($period);

        $this->assertEquals('open', $reopenedPeriod->status);
        $this->assertNull($reopenedPeriod->closed_at);
        $this->assertNull($reopenedPeriod->closed_by);

        // Can now create journal entry in that period
        $entry = JournalEntry::create([
            'coa_id' => $this->cashCoa->id,
            'date' => '2026-07-15',
            'reference' => 'TEST-REOPEN-001',
            'description' => 'Allowed in reopened period',
            'debit' => 250000,
            'credit' => 0,
            'journal_type' => 'manual',
            'cabang_id' => $this->cabangA->id,
        ]);

        $this->assertNotNull($entry->id);
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'reference' => 'TEST-REOPEN-001',
        ]);
    }

    /**
     * Test 7: AccountingPeriodPolicy permits and restricts close/reopen actions based on status and branch
     */
    public function test_accounting_period_policy_enforces_branch_and_status_restrictions(): void
    {
        $policy = app(AccountingPeriodPolicy::class);

        $openPeriodA = AccountingPeriod::create([
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'open',
            'cabang_id' => $this->cabangA->id,
        ]);

        $closedPeriodA = AccountingPeriod::create([
            'period_start' => '2026-05-01',
            'period_end' => '2026-05-31',
            'status' => 'closed',
            'cabang_id' => $this->cabangA->id,
            'closed_at' => now(),
        ]);

        $openPeriodB = AccountingPeriod::create([
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'status' => 'open',
            'cabang_id' => $this->cabangB->id,
        ]);

        // User A can close open period in Cabang A, but cannot close an already closed period
        $this->assertTrue($policy->close($this->userA, $openPeriodA));
        $this->assertFalse($policy->close($this->userA, $closedPeriodA));

        // User A cannot close open period in Cabang B (cross-branch denied)
        $this->assertFalse($policy->close($this->userA, $openPeriodB));

        // User A can reopen closed period in Cabang A, but cannot reopen an already open period
        $this->assertTrue($policy->reopen($this->userA, $closedPeriodA));
        $this->assertFalse($policy->reopen($this->userA, $openPeriodA));
    }
}
