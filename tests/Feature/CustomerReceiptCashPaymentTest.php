<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReceiptCashPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed Chart of Accounts for testing
        $this->seed(\Database\Seeders\ChartOfAccountSeeder::class);
    }

    public function test_cash_payment_creates_correct_journal_entries_and_updates_ar()
    {
        // Setup: Create customer, product, invoice, and AR
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);
        $user = User::factory()->create();

        // Create sale order
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $user->id,
        ]);

        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        // Create delivery order
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        // Create invoice - this will trigger InvoiceObserver to create AR and ageing schedule
        $invoice = Invoice::factory()->create([
            'from_model_type' => 'App\\Models\\SaleOrder',
            'from_model_id' => $saleOrder->id,
            'total' => 1000000,
            'status' => 'unpaid',
        ]);

        // Verify AR was created by InvoiceObserver
        $ar = AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($ar, 'Account Receivable should be created by InvoiceObserver');
        $this->assertEquals(1000000, $ar->total, 'AR total should match invoice total');
        $this->assertEquals(0, $ar->paid, 'AR paid should start at 0');
        $this->assertEquals(1000000, $ar->remaining, 'AR remaining should equal total initially');

        // Create customer receipt with cash payment
        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $customer->id,
            'selected_invoices' => [$invoice->id],
            'total_payment' => 1000000,
            'payment_method' => 'cash',
            'status' => 'Draft', // Start with draft status
        ]);

        // Create receipt item with selected_invoices
        $receiptItem = $receipt->customerReceiptItem()->create([
            'invoice_id' => $invoice->id,
            'method' => 'cash',
            'amount' => 1000000,
        ]);

        // Set selected_invoices on the item (required for AR updates)
        $receiptItem->selected_invoices = [$invoice->id];
        $receiptItem->save();

        // Before payment: Check initial state
        $this->assertEquals('unpaid', $invoice->fresh()->status, 'Invoice should be unpaid before payment');
        $ar->refresh();
        $this->assertEquals(1000000, $ar->remaining, 'AR remaining should be 1000000 before payment');

        // Process payment by updating receipt status to 'Paid'
        $receipt->update(['status' => 'Paid']);

        // Verify journal entries were created
        $journalEntries = JournalEntry::where('source_type', 'App\Models\CustomerReceipt')
                                      ->where('source_id', $receipt->id)
                                      ->get();

        $this->assertGreaterThan(0, $journalEntries->count(), 'Journal entries should be created for cash payment');

        // Check journal entry details
        $debitEntry = $journalEntries->where('debit', '>', 0)->first();
        $creditEntry = $journalEntries->where('credit', '>', 0)->first();

        $this->assertNotNull($debitEntry, 'Should have debit entry for cash payment');
        $this->assertNotNull($creditEntry, 'Should have credit entry for AR reduction');

        // Debit should be to cash/bank account
        $this->assertEquals(1000000, $debitEntry->debit, 'Debit amount should be 1000000');
        $this->assertEquals(0, $debitEntry->credit, 'Debit entry should have 0 credit');

        // Credit should be to accounts receivable (piutang dagang)
        $this->assertEquals(0, $creditEntry->debit, 'Credit entry should have 0 debit');
        $this->assertEquals(1000000, $creditEntry->credit, 'Credit amount should be 1000000');

        // Verify AR was updated
        $ar->refresh();
        $this->assertEquals(1000000, $ar->paid, 'AR paid should be updated to 1000000');
        $this->assertEquals(0, $ar->remaining, 'AR remaining should be 0 after full payment');

        // Verify invoice status was updated
        $this->assertEquals('paid', $invoice->fresh()->status, 'Invoice status should be updated to paid');

        // Output detailed information for verification
        echo "\n=== JOURNAL ENTRIES CREATED ===";
        foreach ($journalEntries as $entry) {
            echo "\nCOA: {$entry->coa->code} - {$entry->coa->name}";
            echo "\nDescription: {$entry->description}";
            echo "\nDebit: {$entry->debit}, Credit: {$entry->credit}";
            echo "\nReference: {$entry->reference}";
            echo "\n---";
        }

        echo "\n=== ACCOUNT RECEIVABLE STATUS ===";
        echo "\nTotal: {$ar->total}";
        echo "\nPaid: {$ar->paid}";
        echo "\nRemaining: {$ar->remaining}";
        echo "\nStatus: {$ar->status}";

        echo "\n=== INVOICE STATUS ===";
        echo "\nStatus: {$invoice->fresh()->status}";
    }
}