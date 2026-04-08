<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
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

        $this->seed([
            \Database\Seeders\CabangSeeder::class,
            \Database\Seeders\CurrencySeeder::class,
            \Database\Seeders\UnitOfMeasureSeeder::class,
        ]);

        ChartOfAccount::firstOrCreate([
            'code' => '1111.01',
        ], [
            'name' => 'Kas Kecil',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        ChartOfAccount::firstOrCreate([
            'code' => config('coa.accounts_receivable', '1120'),
        ], [
            'name' => 'Piutang Usaha',
            'type' => 'Asset',
            'is_active' => true,
        ]);

        ChartOfAccount::firstOrCreate([
            'code' => config('coa.customer_deposit'),
        ], [
            'name' => 'Deposit Pelanggan',
            'type' => 'Liability',
            'is_active' => true,
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
            'payment_method' => 'Cash',
            'coa_id' => ChartOfAccount::where('code', '1111.01')->value('id'),
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

    public function test_duplicate_selected_invoices_do_not_double_count_account_receivable_updates()
    {
        $customer = Customer::factory()->create();
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'confirmed',
        ]);

        $invoice = Invoice::withoutEvents(function () use ($saleOrder) {
            return Invoice::factory()->create([
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $saleOrder->id,
                'status' => 'unpaid',
            ]);
        });

        AccountReceivable::factory()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'total' => 1000000,
            'paid' => 0,
            'remaining' => 1000000,
            'status' => 'Belum Lunas',
        ]);

        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $customer->id,
            'selected_invoices' => [$invoice->id, $invoice->id],
            'total_payment' => 1000000,
            'payment_method' => 'Cash',
            'coa_id' => ChartOfAccount::where('code', '1111.01')->value('id'),
            'status' => 'draft',
        ]);

        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 1000000,
            'method' => 'Cash',
            'coa_id' => ChartOfAccount::where('code', '1111.01')->value('id'),
        ]);

        $receipt->update(['status' => 'paid']);

        $accountReceivable = AccountReceivable::where('invoice_id', $invoice->id)->firstOrFail();

        $this->assertSame(1000000.0, (float) $accountReceivable->paid);
        $this->assertSame(0.0, (float) $accountReceivable->remaining);
        $this->assertSame('Lunas', $accountReceivable->status);
        $this->assertSame('paid', $invoice->fresh()->status);

        $receiptEntries = JournalEntry::where('source_type', CustomerReceipt::class)
            ->where('source_id', $receipt->id)
            ->get();

        $this->assertCount(2, $receiptEntries);
        $this->assertSame(1000000.0, (float) $receiptEntries->sum('debit'));
        $this->assertSame(1000000.0, (float) $receiptEntries->sum('credit'));
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
            'payment_method' => 'Cash',
            'coa_id' => ChartOfAccount::where('code', '1111.01')->value('id'),
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
            'payment_method' => 'Cash',
            'coa_id' => ChartOfAccount::where('code', '1111.01')->value('id'),
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
