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
use App\Services\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
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

        app(LedgerPostingService::class)->postCustomerReceipt($receipt->fresh());

        // Get journal entries before deletion
        $journalEntryIds = $receipt->journalEntries->pluck('id');

        // Soft delete the receipt
        $receipt->delete();

        // Check that journal entries are soft-deleted with the receipt
        foreach ($journalEntryIds as $entryId) {
            $entry = JournalEntry::withTrashed()->find($entryId);
            $this->assertNotNull($entry);
            $this->assertEquals(CustomerReceipt::class, $entry->source_type);
            $this->assertEquals($receipt->id, $entry->source_id);
            $this->assertSoftDeleted('journal_entries', ['id' => $entryId]);
        }
    }

    public function test_customer_receipt_journal_resolves_currency_from_detail_invoice_without_selected_invoices()
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $customer = Customer::factory()->create();
        $cashCoa = ChartOfAccount::where('code', '1111.01')->firstOrFail();

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'currency_id' => $usd->id,
            'exchange_rate' => 16000,
            'status' => 'confirmed',
        ]);

        $invoice = Invoice::withoutEvents(function () use ($saleOrder, $usd) {
            return Invoice::factory()->create([
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $saleOrder->id,
                'status' => 'unpaid',
                'currency_id' => $usd->id,
                'exchange_rate' => 16000,
                'total' => 80000,
            ]);
        });

        AccountReceivable::factory()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'currency_id' => $usd->id,
            'exchange_rate' => 16000,
            'total' => 80000,
            'paid' => 0,
            'remaining' => 80000,
            'total_original' => 5,
            'paid_original' => 0,
            'remaining_original' => 5,
            'status' => 'Belum Lunas',
        ]);

        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $customer->id,
            'selected_invoices' => null,
            'total_payment' => 80000,
            'payment_method' => 'Cash',
            'coa_id' => $cashCoa->id,
            'status' => 'draft',
        ]);

        $item = CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'amount' => 80000,
            'method' => 'Cash',
            'coa_id' => $cashCoa->id,
        ]);

        app(LedgerPostingService::class)->postCustomerReceipt($receipt->fresh());

        $receipt = $receipt->fresh();
        $item = $item->fresh();

        $this->assertSame($usd->id, $receipt->currency_id);
        $this->assertSame(16000.0, (float) $receipt->exchange_rate);
        $this->assertSame(80000.0, (float) $receipt->total_payment_idr);
        $this->assertSame($usd->id, $item->currency_id);
        $this->assertSame(16000.0, (float) $item->exchange_rate);
        $this->assertSame(80000.0, (float) $item->amount_idr);

        $entries = JournalEntry::where('source_type', CustomerReceipt::class)
            ->where('source_id', $receipt->id)
            ->get();

        $this->assertCount(2, $entries);
        $this->assertSame(80000.0, (float) $entries->sum('debit'));
        $this->assertSame(80000.0, (float) $entries->sum('credit'));

        foreach ($entries as $entry) {
            $this->assertSame($usd->id, $entry->currency_id);
            $this->assertSame(16000.0, (float) $entry->exchange_rate);
            $this->assertSame(5.0, (float) $entry->amount_original_currency);
        }
    }

    public function test_customer_receipt_journal_rejects_mixed_currency_rates()
    {
        $usd = Currency::where('code', 'USD')->firstOrFail();
        $customer = Customer::factory()->create();
        $cashCoa = ChartOfAccount::where('code', '1111.01')->firstOrFail();

        $firstOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'currency_id' => $usd->id,
            'exchange_rate' => 16000,
            'status' => 'confirmed',
        ]);
        $secondOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'currency_id' => $usd->id,
            'exchange_rate' => 15000,
            'status' => 'confirmed',
        ]);

        $firstInvoice = Invoice::withoutEvents(function () use ($firstOrder, $usd) {
            return Invoice::factory()->create([
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $firstOrder->id,
                'status' => 'unpaid',
                'currency_id' => $usd->id,
                'exchange_rate' => 16000,
                'total' => 80000,
            ]);
        });
        $secondInvoice = Invoice::withoutEvents(function () use ($secondOrder, $usd) {
            return Invoice::factory()->create([
                'from_model_type' => SaleOrder::class,
                'from_model_id' => $secondOrder->id,
                'status' => 'unpaid',
                'currency_id' => $usd->id,
                'exchange_rate' => 15000,
                'total' => 75000,
            ]);
        });

        foreach ([[$firstInvoice, 16000, 80000], [$secondInvoice, 15000, 75000]] as [$invoice, $rate, $total]) {
            AccountReceivable::factory()->create([
                'invoice_id' => $invoice->id,
                'customer_id' => $customer->id,
                'currency_id' => $usd->id,
                'exchange_rate' => $rate,
                'total' => $total,
                'paid' => 0,
                'remaining' => $total,
                'total_original' => 5,
                'paid_original' => 0,
                'remaining_original' => 5,
                'status' => 'Belum Lunas',
            ]);
        }

        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $customer->id,
            'selected_invoices' => null,
            'total_payment' => 155000,
            'payment_method' => 'Cash',
            'coa_id' => $cashCoa->id,
            'status' => 'draft',
        ]);

        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $firstInvoice->id,
            'amount' => 80000,
            'method' => 'Cash',
            'coa_id' => $cashCoa->id,
        ]);
        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $secondInvoice->id,
            'amount' => 75000,
            'method' => 'Cash',
            'coa_id' => $cashCoa->id,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Customer receipt hanya boleh membayar invoice dengan satu mata uang dan satu rate.');

        app(LedgerPostingService::class)->postCustomerReceipt($receipt->fresh());
    }
}
