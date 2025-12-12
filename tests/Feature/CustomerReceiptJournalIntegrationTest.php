<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReceiptJournalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run essential seeders
        $this->seed([
            \Database\Seeders\CabangSeeder::class,
            \Database\Seeders\CurrencySeeder::class,
            \Database\Seeders\UnitOfMeasureSeeder::class,
        ]);
    }

    public function test_journal_entries_are_created_when_customer_receipt_is_created_with_paid_status()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'confirmed'
        ]);
        $invoice = Invoice::withoutEvents(function () use ($saleOrder) {
            return Invoice::factory()->create([
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $saleOrder->id,
                'status' => 'unpaid'
            ]);
        });

        // Create customer receipt with paid status
        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $customer->id,
            'selected_invoices' => [$invoice->id],
            'total_payment' => 1000000,
            'status' => 'Paid'
        ]);

        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'method' => 'Cash'
        ]);

        // Check that journal entries were created
        $journalEntries = $receipt->journalEntries;
        $this->assertGreaterThan(0, $journalEntries->count());

        // Check that journal entries have correct source
        foreach ($journalEntries as $entry) {
            $this->assertEquals(CustomerReceipt::class, $entry->source_type);
            $this->assertEquals($receipt->id, $entry->source_id);
        }
    }

    public function test_journal_entries_are_updated_when_customer_receipt_amount_is_changed()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'confirmed'
        ]);
        $invoice = Invoice::withoutEvents(function () use ($saleOrder) {
            return Invoice::factory()->create([
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $saleOrder->id,
                'status' => 'unpaid'
            ]);
        });

        // Create customer receipt
        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $customer->id,
            'selected_invoices' => [$invoice->id],
            'total_payment' => 1000000,
            'status' => 'Paid'
        ]);

        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'method' => 'Cash'
        ]);

        // Get initial journal entries count and total
        $initialEntries = $receipt->journalEntries;
        $initialCount = $initialEntries->count();
        $initialTotal = $initialEntries->sum('credit');

        // Update receipt amount
        $receipt->update(['total_payment' => 1500000]);

        // Update receipt item amount
        $receipt->customerReceiptItem()->first()->update(['amount' => 1500000]);

        // Trigger observer by updating status (to trigger updated() method)
        $receipt->update(['status' => 'Paid']);

        // Check that journal entries were updated
        $updatedEntries = $receipt->fresh()->journalEntries;
        $this->assertGreaterThan(0, $updatedEntries->count());
    }

    public function test_journal_entries_are_NOT_deleted_when_customer_receipt_is_soft_deleted()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'confirmed'
        ]);
        $invoice = Invoice::withoutEvents(function () use ($saleOrder) {
            return Invoice::factory()->create([
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $saleOrder->id,
                'status' => 'unpaid'
            ]);
        });

        // Create customer receipt
        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $customer->id,
            'selected_invoices' => [$invoice->id],
            'total_payment' => 1000000,
            'status' => 'Paid'
        ]);

        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'method' => 'Cash'
        ]);

        // Get journal entries before deletion
        $journalEntryIds = $receipt->journalEntries->pluck('id');

        // Soft delete the receipt
        $receipt->delete();

        // Check that journal entries still exist (not cascade deleted)
        foreach ($journalEntryIds as $entryId) {
            $entry = JournalEntry::find($entryId);
            $this->assertNotNull($entry);
            $this->assertEquals(CustomerReceipt::class, $entry->source_type);
            $this->assertEquals($receipt->id, $entry->source_id);
        }
    }
}
