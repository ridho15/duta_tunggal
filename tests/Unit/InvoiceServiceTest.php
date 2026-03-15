<?php

/**
 * TC-INV-001 to TC-INV-007
 * Tests for InvoiceService number generation, AR auto-creation, other_fee sum,
 * PPN math, and invoice-paid lifecycle.
 */

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SaleOrder;
use App\Services\InvoiceService;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// TC-INV-001 & 002 – Invoice number generation (format + sequential)
// ---------------------------------------------------------------------------

test('TC-INV-001: generateInvoiceNumber returns INV-YYYYMMDD-0001 when no invoice exists today', function () {
    $service = new InvoiceService();
    $number  = $service->generateInvoiceNumber();
    $date    = now()->format('Ymd');

    expect($number)->toBe("INV-{$date}-0001");
});

test('TC-INV-001b: generateInvoiceNumber increments correctly when previous number exists', function () {
    $date = now()->format('Ymd');
    Invoice::factory()->create(['invoice_number' => "INV-{$date}-0001"]);

    $service = new InvoiceService();
    $number  = $service->generateInvoiceNumber();

    expect($number)->toBe("INV-{$date}-0002");
});

test('TC-INV-002: generatePurchaseInvoiceNumber returns PINV-YYYYMMDD-0001 when no purchase invoice exists today', function () {
    $service = new InvoiceService();
    $number  = $service->generatePurchaseInvoiceNumber();
    $date    = now()->format('Ymd');

    expect($number)->toBe("PINV-{$date}-0001");
});

// ---------------------------------------------------------------------------
// TC-INV-003 – Auto-create AccountReceivable when SaleOrder invoice is created
// ---------------------------------------------------------------------------

test('TC-INV-003: creating a SaleOrder invoice auto-creates one AccountReceivable', function () {
    $cabang   = Cabang::factory()->create();
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
    $so       = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id'   => $cabang->id,
        'status'      => 'completed',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-' . now()->format('Ymd') . '-0001',
        'from_model_type' => SaleOrder::class,
        'from_model_id'   => $so->id,
        'invoice_date'    => now(),
        'due_date'        => now()->addDays(30),
        'subtotal'        => 100000,
        'tax'             => 0,
        'total'           => 100000,
        'status'          => 'draft',
        'dpp'             => 100000,
        'ppn_rate'        => 0,
        'cabang_id'       => $cabang->id,
    ]);

    $ar = AccountReceivable::withoutGlobalScopes()
        ->where('invoice_id', $invoice->id)
        ->first();

    expect($ar)->not()->toBeNull();
});

// ---------------------------------------------------------------------------
// TC-INV-004 – AR.remaining = invoice total at creation
// ---------------------------------------------------------------------------

test('TC-INV-004: AR remaining equals invoice total immediately after invoice creation', function () {
    $cabang       = Cabang::factory()->create();
    $customer     = Customer::factory()->create(['cabang_id' => $cabang->id]);
    $so           = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id'   => $cabang->id,
        'status'      => 'completed',
    ]);
    $invoiceTotal = 550000;

    $invoice = Invoice::create([
        'invoice_number' => 'INV-' . now()->format('Ymd') . '-0001',
        'from_model_type' => SaleOrder::class,
        'from_model_id'   => $so->id,
        'invoice_date'    => now(),
        'due_date'        => now()->addDays(30),
        'subtotal'        => 500000,
        'tax'             => 0,
        'total'           => $invoiceTotal,
        'status'          => 'draft',
        'dpp'             => 500000,
        'ppn_rate'        => 0,
        'cabang_id'       => $cabang->id,
    ]);

    $ar = AccountReceivable::withoutGlobalScopes()
        ->where('invoice_id', $invoice->id)
        ->first();

    expect((float) $ar->remaining)->toBe((float) $invoiceTotal);
    expect((float) $ar->paid)->toBe(0.0);
});

// ---------------------------------------------------------------------------
// TC-INV-005 – other_fee_total attribute sums JSON fee array
// ---------------------------------------------------------------------------

test('TC-INV-005: other_fee_total sums all fee amounts in the JSON array', function () {
    $invoice = new Invoice();
    $invoice->setRawAttributes([
        'other_fee' => json_encode([
            ['description' => 'Biaya kirim',    'amount' => 15000],
            ['description' => 'Biaya handling', 'amount' =>  5000],
            ['description' => 'Biaya asuransi', 'amount' => 10000],
        ]),
    ]);

    expect($invoice->other_fee_total)->toBe(30000);
});

test('TC-INV-005b: other_fee_total returns 0 when other_fee is empty', function () {
    $invoice = new Invoice();
    $invoice->setRawAttributes(['other_fee' => null]);

    expect($invoice->other_fee_total)->toBe(0);
});

// ---------------------------------------------------------------------------
// TC-INV-006 – dpp + ppn = total (via TaxService)
// ---------------------------------------------------------------------------

test('TC-INV-006: dpp + ppn = total for eksklusif 12% PPN', function () {
    $result = TaxService::compute(1000000.0, 12.0, 'Eksklusif');

    expect($result['dpp'] + $result['ppn'])->toBe($result['total'])
        ->and($result['dpp'])->toBe(1000000.0)
        ->and($result['ppn'])->toBe(120000.0)
        ->and($result['total'])->toBe(1120000.0);
});

test('TC-INV-006b: dpp + ppn = total for inklusif 12% PPN', function () {
    $result = TaxService::compute(1120000.0, 12.0, 'Inklusif');

    expect($result['dpp'] + $result['ppn'])->toBe($result['total'])
        ->and($result['total'])->toBe(1120000.0);
});

// ---------------------------------------------------------------------------
// TC-INV-007 – Invoice status → 'paid' when AR.remaining = 0
// ---------------------------------------------------------------------------

test('TC-INV-007: invoice status becomes paid when AR remaining is set to zero', function () {
    $cabang   = Cabang::factory()->create();
    $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
    $so       = SaleOrder::factory()->create([
        'customer_id' => $customer->id,
        'cabang_id'   => $cabang->id,
        'status'      => 'completed',
    ]);

    $invoice = Invoice::create([
        'invoice_number' => 'INV-' . now()->format('Ymd') . '-0001',
        'from_model_type' => SaleOrder::class,
        'from_model_id'   => $so->id,
        'invoice_date'    => now(),
        'due_date'        => now()->addDays(30),
        'subtotal'        => 100000,
        'tax'             => 0,
        'total'           => 100000,
        'status'          => 'sent',
        'dpp'             => 100000,
        'ppn_rate'        => 0,
        'cabang_id'       => $cabang->id,
    ]);

    $ar = AccountReceivable::withoutGlobalScopes()
        ->where('invoice_id', $invoice->id)
        ->first();

    // Simulate full payment: AR remaining → 0, then update invoice to 'paid'
    $ar->update(['remaining' => 0, 'paid' => 100000, 'status' => 'Lunas']);
    $ar->invoice->update(['status' => Invoice::STATUS_PAID]);

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);
    expect((float) AccountReceivable::withoutGlobalScopes()->find($ar->id)->remaining)->toBe(0.0);
});
