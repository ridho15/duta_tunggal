<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCurrencyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idr = Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'code' => 'IDR', 'to_rupiah' => 1]);
        $this->eur = Currency::create(['name' => 'EUR', 'symbol' => '€', 'code' => 'EUR', 'to_rupiah' => 17000]);
        \App\Models\User::factory()->create();
    }

    public function test_payment_recorded_in_idr_or_has_amount_idr()
    {
        // Create a PurchaseOrder with PO currency (EUR) so VendorPayment posting can resolve currency
        \App\Models\Supplier::factory()->create();
        $po = \App\Models\PurchaseOrder::factory()->create();
        \App\Models\PurchaseOrderCurrency::create([
            'purchase_order_id' => $po->id,
            'currency_id' => $this->eur->id,
            'nominal' => 17000,
        ]);

        // Create Invoice (purchase) linked to PO so AP exists
        $invoice = \App\Models\Invoice::create([
            'from_model_type' => \App\Models\PurchaseOrder::class,
            'from_model_id' => $po->id,
            'invoice_number' => 'INV-PAY-001',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 17000000.00,
            'total' => 17000000.00,
            'status' => 'sent',
        ]);

        // Create VendorPayment that pays this invoice (status 'paid' to force posting)
        $vp = \App\Models\VendorPayment::create([
            'supplier_id' => $po->supplier_id,
            'selected_invoices' => [$invoice->id],
            'payment_date' => now(),
            'total_payment' => (float) $invoice->total,
            'status' => 'paid',
        ]);

        // Create a ChartOfAccount and a JournalEntry manually to let the JournalEntry hook resolve currency
        $coa = \App\Models\ChartOfAccount::create(['code' => '9999.02', 'name' => 'Test Pay', 'type' => 'Liability', 'is_active' => true]);

        $entry = \App\Models\JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => now(),
            'reference' => 'VP-' . $vp->id,
            'description' => 'Test vendor payment posting',
            'debit' => (float) $vp->total_payment,
            'credit' => 0,
            'journal_type' => 'payment',
            'source_type' => \App\Models\VendorPayment::class,
            'source_id' => $vp->id,
        ]);

        $this->assertNotNull($entry->currency_id, 'JournalEntry.currency_id should be set by creating hook');
        $this->assertEquals($this->eur->id, $entry->currency_id);

        $expectedOriginal = round((float) $vp->total_payment / (float) $this->eur->to_rupiah, 4);
        $this->assertEquals($expectedOriginal, (float) $entry->amount_original_currency);
    }
}
