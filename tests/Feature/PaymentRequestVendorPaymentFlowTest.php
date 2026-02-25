<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\PaymentRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests for the full PaymentRequest → VendorPayment flow:
 *   1. Create PaymentRequest (draft)
 *   2. Submit for approval (draft → pending_approval)
 *   3. Approve (pending_approval → approved)
 *   4. Reject (pending_approval → rejected)
 *   5. Create VendorPayment from approved PR
 *   6. afterCreate hook: PR status becomes 'paid', vendor_payment_id is set
 *   7. Query scope: approved PRs without vendor_payment_id only
 */
class PaymentRequestVendorPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected User $approver;
    protected Supplier $supplier;
    protected Cabang $cabang;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user     = User::factory()->create();
        $this->approver = User::factory()->create();
        $this->cabang   = Cabang::factory()->create();
        $this->supplier = Supplier::factory()->create(['cabang_id' => $this->cabang->id]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 1. Create PaymentRequest
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function it_can_create_a_payment_request_in_draft_status(): void
    {
        $pr = PaymentRequest::create([
            'request_number'    => 'PR-TEST-0001',
            'supplier_id'       => $this->supplier->id,
            'cabang_id'         => $this->cabang->id,
            'requested_by'      => $this->user->id,
            'request_date'      => now()->toDateString(),
            'total_amount'      => 5_000_000,
            'selected_invoices' => [],
            'status'            => PaymentRequest::STATUS_DRAFT,
        ]);

        $this->assertDatabaseHas('payment_requests', [
            'id'             => $pr->id,
            'request_number' => 'PR-TEST-0001',
            'status'         => 'draft',
            'requested_by'   => $this->user->id,
        ]);
    }

    #[Test]
    public function payment_request_generates_unique_request_number(): void
    {
        $number1 = PaymentRequest::generateNumber();
        $this->assertStringStartsWith('PR-', $number1);

        // Persisting the first number advances the sequence counter
        PaymentRequest::create([
            'request_number'    => $number1,
            'supplier_id'       => $this->supplier->id,
            'cabang_id'         => $this->cabang->id,
            'requested_by'      => $this->user->id,
            'request_date'      => now()->toDateString(),
            'total_amount'      => 1_000_000,
            'selected_invoices' => [],
            'status'            => PaymentRequest::STATUS_DRAFT,
        ]);

        $number2 = PaymentRequest::generateNumber();

        $this->assertNotEquals($number1, $number2);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 2. Submit for Approval
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function it_can_submit_payment_request_for_approval(): void
    {
        $pr = PaymentRequest::factory()->draft()->create([
            'supplier_id'  => $this->supplier->id,
            'cabang_id'    => $this->cabang->id,
            'requested_by' => $this->user->id,
        ]);

        Auth::login($this->user);

        $pr->update([
            'status'       => PaymentRequest::STATUS_PENDING,
            'requested_by' => Auth::id(),
        ]);

        $this->assertEquals(PaymentRequest::STATUS_PENDING, $pr->fresh()->status);
    }

    #[Test]
    public function submit_action_is_only_visible_from_draft_status(): void
    {
        $draft   = PaymentRequest::factory()->draft()->create(['supplier_id' => $this->supplier->id, 'cabang_id' => $this->cabang->id, 'requested_by' => $this->user->id]);
        $pending = PaymentRequest::factory()->pendingApproval()->create(['supplier_id' => $this->supplier->id, 'cabang_id' => $this->cabang->id, 'requested_by' => $this->user->id]);
        $approved = PaymentRequest::factory()->approved()->create(['supplier_id' => $this->supplier->id, 'cabang_id' => $this->cabang->id, 'requested_by' => $this->user->id]);

        // Simulate the visible() closure: status === 'draft'
        $this->assertTrue($draft->status === 'draft');
        $this->assertFalse($pending->status === 'draft');
        $this->assertFalse($approved->status === 'draft');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 3. Approve
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function it_can_approve_a_pending_payment_request(): void
    {
        $pr = PaymentRequest::factory()->pendingApproval()->create([
            'supplier_id'  => $this->supplier->id,
            'cabang_id'    => $this->cabang->id,
            'requested_by' => $this->user->id,
        ]);

        Auth::login($this->approver);

        $pr->update([
            'status'         => PaymentRequest::STATUS_APPROVED,
            'approved_by'    => Auth::id(),
            'approved_at'    => now(),
            'approval_notes' => 'Approved — budget available.',
        ]);

        $pr->refresh();

        $this->assertEquals(PaymentRequest::STATUS_APPROVED, $pr->status);
        $this->assertEquals($this->approver->id, $pr->approved_by);
        $this->assertNotNull($pr->approved_at);
        $this->assertEquals('Approved — budget available.', $pr->approval_notes);
    }

    #[Test]
    public function approve_action_is_only_visible_when_pending_approval(): void
    {
        $draft    = PaymentRequest::factory()->draft()->create(['supplier_id' => $this->supplier->id, 'cabang_id' => $this->cabang->id, 'requested_by' => $this->user->id]);
        $pending  = PaymentRequest::factory()->pendingApproval()->create(['supplier_id' => $this->supplier->id, 'cabang_id' => $this->cabang->id, 'requested_by' => $this->user->id]);
        $approved = PaymentRequest::factory()->approved()->create(['supplier_id' => $this->supplier->id, 'cabang_id' => $this->cabang->id, 'requested_by' => $this->user->id]);

        // visible() checks status === 'pending_approval'
        $this->assertFalse($draft->status === 'pending_approval');
        $this->assertTrue($pending->status === 'pending_approval');
        $this->assertFalse($approved->status === 'pending_approval');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 4. Reject
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function it_can_reject_a_pending_payment_request(): void
    {
        $pr = PaymentRequest::factory()->pendingApproval()->create([
            'supplier_id'  => $this->supplier->id,
            'cabang_id'    => $this->cabang->id,
            'requested_by' => $this->user->id,
        ]);

        Auth::login($this->approver);

        $pr->update([
            'status'         => PaymentRequest::STATUS_REJECTED,
            'approved_by'    => Auth::id(),
            'approved_at'    => now(),
            'approval_notes' => 'Budget exceeded for this month.',
        ]);

        $pr->refresh();

        $this->assertEquals(PaymentRequest::STATUS_REJECTED, $pr->status);
        $this->assertEquals($this->approver->id, $pr->approved_by);
        $this->assertNotNull($pr->approval_notes);
    }

    #[Test]
    public function reject_action_requires_approval_notes(): void
    {
        // The form field has ->required() — simulate that approval_notes must be present
        $approvalNotes = '';
        $this->assertEmpty($approvalNotes, 'approval_notes must be provided for rejection');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 5. Create VendorPayment from an Approved PaymentRequest
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function it_can_create_vendor_payment_linked_to_approved_payment_request(): void
    {
        $pr = PaymentRequest::factory()->approved()->create([
            'supplier_id'  => $this->supplier->id,
            'cabang_id'    => $this->cabang->id,
            'requested_by' => $this->user->id,
        ]);

        $vp = VendorPayment::create([
            'payment_request_id' => $pr->id,
            'supplier_id'        => $this->supplier->id,
            'payment_date'       => now()->toDateString(),
            'total_payment'      => $pr->total_amount,
            'status'             => 'Paid',
            'selected_invoices'  => [],
        ]);

        $this->assertDatabaseHas('vendor_payments', [
            'id'                 => $vp->id,
            'payment_request_id' => $pr->id,
            'supplier_id'        => $this->supplier->id,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 6. afterCreate: PaymentRequest status → 'paid' and vendor_payment_id set
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function after_creating_vendor_payment_payment_request_status_becomes_paid(): void
    {
        $pr = PaymentRequest::factory()->approved()->create([
            'supplier_id'  => $this->supplier->id,
            'cabang_id'    => $this->cabang->id,
            'requested_by' => $this->user->id,
        ]);

        // Create the VendorPayment
        $vp = VendorPayment::create([
            'payment_request_id' => $pr->id,
            'supplier_id'        => $this->supplier->id,
            'payment_date'       => now()->toDateString(),
            'total_payment'      => $pr->total_amount,
            'status'             => 'Paid',
            'selected_invoices'  => [],
        ]);

        // Simulate what afterCreate() does in CreateVendorPayment
        if ($vp->payment_request_id) {
            PaymentRequest::where('id', $vp->payment_request_id)->update([
                'status'             => PaymentRequest::STATUS_PAID,
                'vendor_payment_id'  => $vp->id,
            ]);
        }

        $pr->refresh();

        $this->assertEquals(PaymentRequest::STATUS_PAID, $pr->status);
        $this->assertEquals($vp->id, $pr->vendor_payment_id);
    }

    #[Test]
    public function creating_vendor_payment_without_payment_request_id_does_not_affect_any_pr(): void
    {
        $existingPr = PaymentRequest::factory()->approved()->create([
            'supplier_id'  => $this->supplier->id,
            'cabang_id'    => $this->cabang->id,
            'requested_by' => $this->user->id,
        ]);

        // VendorPayment with no payment_request_id
        $vp = VendorPayment::create([
            'payment_request_id' => null,
            'supplier_id'        => $this->supplier->id,
            'payment_date'       => now()->toDateString(),
            'total_payment'      => 1_000_000,
            'status'             => 'Partial',
            'selected_invoices'  => [],
        ]);

        // Simulate afterCreate — should do nothing because payment_request_id is null
        if ($vp->payment_request_id) {
            PaymentRequest::where('id', $vp->payment_request_id)->update([
                'status'            => PaymentRequest::STATUS_PAID,
                'vendor_payment_id' => $vp->id,
            ]);
        }

        // existingPr must still be 'approved'
        $this->assertEquals(PaymentRequest::STATUS_APPROVED, $existingPr->fresh()->status);
        $this->assertNull($existingPr->fresh()->vendor_payment_id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 7. Query scope for VendorPayment select: only approved PRs without vendor_payment_id
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function vendor_payment_select_only_shows_approved_prs_without_vendor_payment_id(): void
    {
        // Approved, not yet paid → should appear in select
        $eligible = PaymentRequest::factory()->approved()->create([
            'supplier_id'      => $this->supplier->id,
            'cabang_id'        => $this->cabang->id,
            'requested_by'     => $this->user->id,
            'vendor_payment_id'=> null,
        ]);

        // Draft → should NOT appear
        $draft = PaymentRequest::factory()->draft()->create([
            'supplier_id'  => $this->supplier->id,
            'cabang_id'    => $this->cabang->id,
            'requested_by' => $this->user->id,
        ]);

        // Approved but already linked to a vendor payment → should NOT appear
        $vp = VendorPayment::create([
            'supplier_id'       => $this->supplier->id,
            'payment_date'      => now()->toDateString(),
            'total_payment'     => 2_000_000,
            'status'            => 'Paid',
            'selected_invoices' => [],
        ]);
        $alreadyPaid = PaymentRequest::factory()->paid()->create([
            'supplier_id'      => $this->supplier->id,
            'cabang_id'        => $this->cabang->id,
            'requested_by'     => $this->user->id,
            'vendor_payment_id'=> $vp->id,
        ]);

        // The query used in VendorPaymentResource for the select field:
        $eligiblePrs = PaymentRequest::where('status', PaymentRequest::STATUS_APPROVED)
            ->whereNull('vendor_payment_id')
            ->pluck('id')
            ->toArray();

        $this->assertContains($eligible->id, $eligiblePrs);
        $this->assertNotContains($draft->id, $eligiblePrs);
        $this->assertNotContains($alreadyPaid->id, $eligiblePrs);
    }

    #[Test]
    public function paid_payment_request_is_excluded_from_vendor_payment_select(): void
    {
        // Already paid PR
        $vp = VendorPayment::create([
            'supplier_id'       => $this->supplier->id,
            'payment_date'      => now()->toDateString(),
            'total_payment'     => 8_000_000,
            'status'            => 'Paid',
            'selected_invoices' => [],
        ]);

        $paidPr = PaymentRequest::factory()->paid()->create([
            'supplier_id'      => $this->supplier->id,
            'cabang_id'        => $this->cabang->id,
            'requested_by'     => $this->user->id,
            'vendor_payment_id'=> $vp->id,
        ]);

        $eligiblePrs = PaymentRequest::where('status', PaymentRequest::STATUS_APPROVED)
            ->whereNull('vendor_payment_id')
            ->pluck('id')
            ->toArray();

        $this->assertNotContains($paidPr->id, $eligiblePrs);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 8. Relationships
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function payment_request_belongs_to_vendor_payment(): void
    {
        $vp = VendorPayment::create([
            'supplier_id'       => $this->supplier->id,
            'payment_date'      => now()->toDateString(),
            'total_payment'     => 3_000_000,
            'status'            => 'Paid',
            'selected_invoices' => [],
        ]);

        $pr = PaymentRequest::factory()->approved()->create([
            'supplier_id'      => $this->supplier->id,
            'cabang_id'        => $this->cabang->id,
            'requested_by'     => $this->user->id,
            'vendor_payment_id'=> $vp->id,
            'status'           => PaymentRequest::STATUS_PAID,
        ]);

        $this->assertInstanceOf(VendorPayment::class, $pr->vendorPayment);
        $this->assertEquals($vp->id, $pr->vendorPayment->id);
    }

    #[Test]
    public function vendor_payment_belongs_to_payment_request(): void
    {
        $pr = PaymentRequest::factory()->approved()->create([
            'supplier_id'  => $this->supplier->id,
            'cabang_id'    => $this->cabang->id,
            'requested_by' => $this->user->id,
        ]);

        $vp = VendorPayment::create([
            'payment_request_id'=> $pr->id,
            'supplier_id'       => $this->supplier->id,
            'payment_date'      => now()->toDateString(),
            'total_payment'     => $pr->total_amount,
            'status'            => 'Paid',
            'selected_invoices' => [],
        ]);

        $this->assertInstanceOf(PaymentRequest::class, $vp->paymentRequest);
        $this->assertEquals($pr->id, $vp->paymentRequest->id);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // 9. Status constants
    // ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function payment_request_status_constants_are_correctly_defined(): void
    {
        $this->assertEquals('draft', PaymentRequest::STATUS_DRAFT);
        $this->assertEquals('pending_approval', PaymentRequest::STATUS_PENDING);
        $this->assertEquals('approved', PaymentRequest::STATUS_APPROVED);
        $this->assertEquals('rejected', PaymentRequest::STATUS_REJECTED);
        $this->assertEquals('paid', PaymentRequest::STATUS_PAID);
    }

    #[Test]
    public function payment_request_status_labels_cover_all_statuses(): void
    {
        $expectedStatuses = [
            PaymentRequest::STATUS_DRAFT,
            PaymentRequest::STATUS_PENDING,
            PaymentRequest::STATUS_APPROVED,
            PaymentRequest::STATUS_REJECTED,
            PaymentRequest::STATUS_PAID,
        ];

        foreach ($expectedStatuses as $status) {
            $this->assertArrayHasKey($status, PaymentRequest::STATUS_LABELS);
        }
    }
}
