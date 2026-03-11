<?php

namespace Tests\Feature\ERP;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MODULE 8 — INVOICE
 *
 * Tests items #25, #26:
 *  #25 Invoice number generation is unique and sequential
 *  #26 Editing invoice must not trigger server error (unique ignore record)
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Cabang $cabang;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::factory()->create(['code' => 'IDR']);

        $this->cabang    = Cabang::factory()->create();
        $this->warehouse = Warehouse::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->supplier  = Supplier::factory()->create();
        $this->user      = User::factory()->create(['cabang_id' => $this->cabang->id]);
        $this->service   = app(InvoiceService::class);
        $this->actingAs($this->user);
    }

    // ─── #25 INVOICE NUMBER GENERATION ───────────────────────────────────────

    /** @test */
    public function invoice_number_generation_produces_correct_format(): void
    {
        $number = $this->service->generateInvoiceNumber();

        $this->assertStringStartsWith(
            'INV-' . now()->format('Ymd') . '-',
            $number,
            'Invoice number must start with INV-YYYYMMDD-'
        );
    }

    /** @test */
    public function invoice_number_is_sequential_and_unique(): void
    {
        $first  = $this->service->generateInvoiceNumber();
        $po     = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
        ]);

        // Persist the first invoice so the generator knows it exists
        Invoice::create([
            'invoice_number'  => $first,
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'from_model_id'   => $po->id,
            'invoice_date'    => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'subtotal'        => 100000,
            'tax'             => 0,
            'total'           => 100000,
            'dpp'             => 100000,
            'status'          => 'draft',
            'cabang_id'       => $this->cabang->id,
        ]);

        $second = $this->service->generateInvoiceNumber();

        $this->assertNotEquals($first, $second,
            'Consecutive calls to generateInvoiceNumber must produce different numbers');

        // Extract sequence numbers
        $prefix    = 'INV-' . now()->format('Ymd') . '-';
        $seqFirst  = (int) substr($first, strlen($prefix));
        $seqSecond = (int) substr($second, strlen($prefix));

        $this->assertGreaterThan($seqFirst, $seqSecond,
            'Second invoice number must have a higher sequence than the first');
    }

    /** @test */
    public function invoice_number_has_four_digit_sequence_padding(): void
    {
        $number = $this->service->generateInvoiceNumber();
        $prefix = 'INV-' . now()->format('Ymd') . '-';
        $seq    = substr($number, strlen($prefix));

        $this->assertEquals(4, strlen($seq),
            'Invoice sequence suffix must be zero-padded to 4 digits');
    }

    /** @test */
    public function invoice_number_generation_never_returns_duplicate(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
        ]);

        $generated = [];
        for ($i = 0; $i < 5; $i++) {
            $number = $this->service->generateInvoiceNumber();

            $this->assertNotContains($number, $generated,
                "Duplicate invoice number detected: {$number}");

            $generated[] = $number;

            // Persist to simulate real concurrent usage
            Invoice::create([
                'invoice_number'  => $number,
                'from_model_type' => 'App\\Models\\PurchaseOrder',
                'from_model_id'   => $po->id,
                'invoice_date'    => now()->toDateString(),
                'due_date'        => now()->addDays(30)->toDateString(),
                'subtotal'        => 100000 * ($i + 1),
                'tax'             => 0,
                'total'           => 100000 * ($i + 1),
                'dpp'             => 100000 * ($i + 1),
                'status'          => 'draft',
                'cabang_id'       => $this->cabang->id,
            ]);
        }

        $this->assertCount(5, array_unique($generated),
            'All 5 generated invoice numbers must be unique');
    }

    /** @test */
    public function invoice_number_generator_skips_existing_numbers(): void
    {
        $date   = now()->format('Ymd');
        $prefix = "INV-{$date}-";

        $po = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
        ]);

        // Manually create INV-YYYYMMDD-0001 to simulate a gap
        Invoice::create([
            'invoice_number'  => "{$prefix}0001",
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'from_model_id'   => $po->id,
            'invoice_date'    => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'subtotal'        => 100000,
            'tax'             => 0,
            'total'           => 100000,
            'dpp'             => 100000,
            'status'          => 'draft',
            'cabang_id'       => $this->cabang->id,
        ]);

        $next = $this->service->generateInvoiceNumber();

        $this->assertEquals("{$prefix}0002", $next,
            'Generator must produce 0002 when 0001 already exists');
    }

    // ─── #26 EDITING INVOICE MUST NOT TRIGGER SERVER ERROR ───────────────────

    /** @test */
    public function invoice_can_be_updated_without_unique_constraint_violation(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
        ]);

        $invoice = Invoice::create([
            'invoice_number'  => 'INV-EDIT-001',
            'from_model_type' => 'App\\Models\\PurchaseOrder',
            'from_model_id'   => $po->id,
            'invoice_date'    => now()->toDateString(),
            'due_date'        => now()->addDays(30)->toDateString(),
            'subtotal'        => 300000,
            'tax'             => 11,
            'total'           => 333000,
            'dpp'             => 300000,
            'status'          => 'draft',
            'cabang_id'       => $this->cabang->id,
        ]);

        // Updating the same invoice keeping the same invoice_number must not fail
        $exception = null;
        try {
            $invoice->update([
                'subtotal' => 400000,
                'total'    => 444000,
            ]);
        } catch (\Exception $e) {
            $exception = $e;
        }

        $this->assertNull($exception,
            'Updating an invoice must not throw a unique constraint exception');

        $this->assertDatabaseHas('invoices', [
            'id'       => $invoice->id,
            'subtotal' => 400000,
        ]);
    }

    /** @test */
    public function invoice_service_never_produces_a_duplicate_number_even_with_concurrent_seeding(): void
    {
        $po = PurchaseOrder::factory()->create([
            'supplier_id'  => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'cabang_id'    => $this->cabang->id,
        ]);

        $date   = now()->format('Ymd');
        $prefix = "INV-{$date}-";

        // Seed several existing invoice numbers to simulate a busy day
        for ($seq = 1; $seq <= 3; $seq++) {
            Invoice::create([
                'invoice_number'  => sprintf('%s%04d', $prefix, $seq),
                'from_model_type' => 'App\\Models\\PurchaseOrder',
                'from_model_id'   => $po->id,
                'invoice_date'    => now()->toDateString(),
                'due_date'        => now()->addDays(30)->toDateString(),
                'subtotal'        => 100000 * $seq,
                'tax'             => 0,
                'total'           => 100000 * $seq,
                'dpp'             => 100000 * $seq,
                'status'          => 'draft',
                'cabang_id'       => $this->cabang->id,
            ]);
        }

        // The service must skip 0001-0003 and return 0004
        $next = $this->service->generateInvoiceNumber();

        $this->assertEquals("{$prefix}0004", $next,
            'Service must skip existing numbers and return the first unused sequence');

        // Calling again without persisting must still return 0004 (idempotent if not saved)
        $again = $this->service->generateInvoiceNumber();
        $this->assertEquals("{$prefix}0004", $again,
            'Without persisting, service returns same next sequence on repeated calls');
    }
}
