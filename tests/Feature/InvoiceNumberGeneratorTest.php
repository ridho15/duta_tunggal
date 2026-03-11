<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug #1 – Server error when generating invoice number
 *
 * Root cause: old code used rand() in a do-while loop and applied the
 * CabangScope, so numbers from other branches were invisible, risking
 * cross-branch duplicates and an infinite loop when all 10 000 numbers
 * for the day were taken.
 *
 * Fix: sequential numbering with Invoice::withoutGlobalScopes().
 */
class InvoiceNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvoiceService();
    }

    /** First number of the day should be INV-{date}-0001 */
    public function test_generates_first_number_when_no_invoices_exist(): void
    {
        $number = $this->service->generateInvoiceNumber();

        $date = now()->format('Ymd');
        $this->assertEquals("INV-{$date}-0001", $number);
    }

    /** Each subsequent call increments the suffix */
    public function test_generates_sequential_numbers(): void
    {
        $date   = now()->format('Ymd');
        $cabang = Cabang::factory()->create();
        $user   = User::factory()->create(['cabang_id' => $cabang->id]);
        $this->actingAs($user);

        // Simulate 3 existing invoices for today
        foreach (['0001', '0002', '0003'] as $suffix) {
            Invoice::factory()->create([
                'invoice_number' => "INV-{$date}-{$suffix}",
                'cabang_id'      => $cabang->id,
            ]);
        }

        $number = $this->service->generateInvoiceNumber();

        $this->assertEquals("INV-{$date}-0004", $number);
    }

    /**
     * Numbers from a DIFFERENT branch must be visible so that cross-branch
     * duplicates are prevented.  With the old CabangScope-scoped query a
     * second branch would have received 0001 again.
     */
    public function test_considers_invoices_from_other_branches(): void
    {
        $date    = now()->format('Ymd');
        $branch1 = Cabang::factory()->create();
        $branch2 = Cabang::factory()->create();

        // Branch 1 already has INV-{date}-0001 and 0002
        Invoice::factory()->create(['invoice_number' => "INV-{$date}-0001", 'cabang_id' => $branch1->id]);
        Invoice::factory()->create(['invoice_number' => "INV-{$date}-0002", 'cabang_id' => $branch1->id]);

        // Log in as a branch-2 user
        $user = User::factory()->create(['cabang_id' => $branch2->id]);
        $this->actingAs($user);

        // Should pick up from the global max, not restart at 0001
        $number = $this->service->generateInvoiceNumber();

        $this->assertEquals("INV-{$date}-0003", $number);
    }

    /** Numbers for different dates are independent */
    public function test_resets_counter_for_new_date(): void
    {
        $yesterday = now()->subDay()->format('Ymd');
        $today     = now()->format('Ymd');

        Invoice::factory()->create(['invoice_number' => "INV-{$yesterday}-9999"]);

        $number = $this->service->generateInvoiceNumber();

        $this->assertEquals("INV-{$today}-0001", $number);
    }

    /** No infinite loop risk: all slots up to 9999 are exhausted → counter just exceeds 9999 */
    public function test_does_not_loop_infinitely_when_all_slots_taken(): void
    {
        $date = now()->format('Ymd');

        // Simulate the highest possible "old-style" number
        Invoice::factory()->create(['invoice_number' => "INV-{$date}-9999"]);

        $number = $this->service->generateInvoiceNumber();

        // Rolls over to 10000 (5 digits) rather than looping forever
        $this->assertEquals("INV-{$date}-10000", $number);
    }
}
