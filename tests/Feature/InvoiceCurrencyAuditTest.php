<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCurrencyAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idr = Currency::create(['name' => 'IDR', 'symbol' => 'Rp', 'code' => 'IDR', 'to_rupiah' => 1]);
        $this->usd = Currency::create(['name' => 'USD', 'symbol' => '$', 'code' => 'USD', 'to_rupiah' => 16000]);
        \App\Models\User::factory()->create();
    }

    public function test_invoice_amount_converted_or_has_amount_idr()
    {
        // Setup: create a PurchaseOrder with a PO-level currency (USD) so ledger posting can infer currency
        \App\Models\Supplier::factory()->create();
        $po = \App\Models\PurchaseOrder::factory()->create();
        \App\Models\PurchaseOrderCurrency::create([
            'purchase_order_id' => $po->id,
            'currency_id' => $this->usd->id,
            'nominal' => 16000,
        ]);

        // Create an Invoice linked to that PurchaseOrder with a total expressed in IDR so amount_original_currency = total / nominal
        $invoice = Invoice::create([
            'from_model_type' => \App\Models\PurchaseOrder::class,
            'from_model_id' => $po->id,
            'invoice_number' => 'INV-CR-001',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 16000000.00,
            'total' => 16000000.00,
            'status' => 'sent',
        ]);

        // Create a ChartOfAccount to attach to the JournalEntry (avoid FK issues)
        $coa = \App\Models\ChartOfAccount::create(['code' => '9999.01', 'name' => 'Test', 'type' => 'Expense', 'is_active' => true]);

        // Create a JournalEntry manually and let the JournalEntry::creating hook resolve currency/exchange_rate
        $entry = \App\Models\JournalEntry::create([
            'coa_id' => $coa->id,
            'date' => now(),
            'reference' => $invoice->invoice_number,
            'description' => 'Test invoice posting',
            'debit' => (float) $invoice->total,
            'credit' => 0,
            'journal_type' => 'test',
            'source_type' => Invoice::class,
            'source_id' => $invoice->id,
        ]);

        $this->assertNotNull($entry->currency_id, 'JournalEntry.currency_id should be set by creating hook');
        $this->assertEquals($this->usd->id, $entry->currency_id);

        $expectedOriginal = round((float) $invoice->total / (float) $this->usd->to_rupiah, 4);
        $this->assertEquals($expectedOriginal, (float) $entry->amount_original_currency);
    }
}
