<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReceiptJournalTest extends TestCase
{
    use RefreshDatabase;

    /** @var \App\Models\Customer */
    protected $customer;
    /** @var \App\Models\User */
    protected $user;
    /** @var \App\Models\ChartOfAccount */
    protected $cashCoa;
    /** @var \App\Models\ChartOfAccount */
    protected $bankCoa;
    /** @var \App\Models\ChartOfAccount */
    protected $accountsReceivableCoa;
    /** @var \App\Models\ChartOfAccount */
    protected $depositCoa;

    protected function setUp(): void
    {
        parent::setUp();

        // Run essential seeders
        $this->seed([
            \Database\Seeders\CabangSeeder::class,
        ]);

        // Create test user
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create COAs
        $this->cashCoa = ChartOfAccount::factory()->create([
            'code' => '1111.01',
            'name' => 'Kas Kecil',
            'type' => 'asset',
        ]);

        $this->bankCoa = ChartOfAccount::factory()->create([
            'code' => '1112.01',
            'name' => 'Bank BCA',
            'type' => 'asset',
        ]);

        $this->accountsReceivableCoa = ChartOfAccount::factory()->create([
            'code' => '1120',
            'name' => 'Piutang Usaha',
            'type' => 'asset',
        ]);

        $this->depositCoa = ChartOfAccount::factory()->create([
            'code' => '1150.01',
            'name' => 'Hutang Titipan Konsumen',
            'type' => 'liability',
        ]);

        // Debug: check COA ids
        // dd([
        //     'cashCoa' => ['id' => $this->cashCoa->id, 'code' => $this->cashCoa->code],
        //     'bankCoa' => ['id' => $this->bankCoa->id, 'code' => $this->bankCoa->code],
        //     'accountsReceivableCoa' => ['id' => $this->accountsReceivableCoa->id, 'code' => $this->accountsReceivableCoa->code],
        //     'depositCoa' => ['id' => $this->depositCoa->id, 'code' => $this->depositCoa->code],
        // ]);

        // Create customer
        $this->customer = Customer::factory()->create([
            'name' => 'Test Customer',
        ]);
    }

    /** @test */
    public function it_creates_correct_journal_entries_for_cash_bank_customer_receipt()
    {
        // Create invoice
        $invoice = Invoice::factory()->create([
            'customer_name' => $this->customer->name,
            'total' => 1382000.00,
            'status' => 'unpaid',
        ]);

        // Create account receivable
        AccountReceivable::factory()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'total' => 1382000.00,
            'paid' => 0,
            'remaining' => 1382000.00,
            'status' => 'Belum Lunas',
            'created_by' => $this->user->id,
        ]);

        // Create customer receipt with bank payment
        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'total_payment' => 1382000.00,
            'payment_method' => 'cash',
            'coa_id' => $this->cashCoa->id,
            'status' => 'paid',
        ]);

        // Create customer receipt item
        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'method' => 'cash',
            'amount' => 1382000.00,
            'coa_id' => $this->cashCoa->id,
        ]);

        // Trigger journal posting by updating status
        $receipt->update(['status' => 'paid']);

        // Assert journal entries created
        $journalEntries = JournalEntry::where('source_type', CustomerReceipt::class)
            ->where('source_id', $receipt->id)
            ->orderBy('id')
            ->get();

        expect($journalEntries->count())->toBe(2);

        // Check balances - should be balanced
        $totalDebit = $journalEntries->sum('debit');
        $totalCredit = $journalEntries->sum('credit');
        expect($totalDebit)->toBe($totalCredit);
        expect($totalDebit)->toBe(1382000.00);

        // 1. Debit: Kas/Bank (Cash)
        $cashEntry = $journalEntries->where('coa_id', $this->cashCoa->id)->first();
        expect($cashEntry)->not->toBeNull();
        expect($cashEntry->debit)->toBe('1382000.00');
        expect($cashEntry->credit)->toBe('0.00');
        expect($cashEntry->description)->toContain('Bank/Cash for receipt id');

        // 2. Credit: Piutang Usaha (Accounts Receivable)
        $arEntry = $journalEntries->where('coa_id', $this->accountsReceivableCoa->id)->first();
        expect($arEntry)->not->toBeNull();
        expect($arEntry->debit)->toBe('0.00');
        expect($arEntry->credit)->toBe('1382000.00');
        expect($arEntry->description)->toContain('Customer receipt for receipt id');
    }

    /** @test */
    public function it_creates_correct_journal_entries_for_bank_customer_receipt()
    {
        // Create invoice
        $invoice = Invoice::factory()->create([
            'customer_name' => $this->customer->name,
            'total' => 1382000.00,
            'status' => 'unpaid',
        ]);

        // Create account receivable
        AccountReceivable::factory()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'total' => 1382000.00,
            'paid' => 0,
            'remaining' => 1382000.00,
            'status' => 'Belum Lunas',
            'created_by' => $this->user->id,
        ]);

        // Create customer receipt with cash payment
        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'total_payment' => 1382000.00,
            'payment_method' => 'bank_transfer',
            'coa_id' => $this->bankCoa->id,
            'status' => 'paid',
        ]);

        // Create customer receipt item
        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'method' => 'bank_transfer',
            'amount' => 1382000.00,
            'coa_id' => $this->bankCoa->id,
        ]);

        // Trigger journal posting by updating status
        $receipt->update(['status' => 'paid']);

        // Assert journal entries created
        $journalEntries = JournalEntry::where('source_type', CustomerReceipt::class)
            ->where('source_id', $receipt->id)
            ->orderBy('id')
            ->get();

        expect($journalEntries->count())->toBe(2);

        // Check balances - should be balanced
        $totalDebit = $journalEntries->sum('debit');
        $totalCredit = $journalEntries->sum('credit');
        expect($totalDebit)->toBe($totalCredit);
        expect($totalDebit)->toBe(1382000.00);

        // 1. Debit: Bank (Bank BCA)
        $bankEntry = $journalEntries->where('coa_id', $this->bankCoa->id)->first();
        expect($bankEntry)->not->toBeNull();
        expect($bankEntry->debit)->toBe('1382000.00');
        expect($bankEntry->credit)->toBe('0.00');
        expect($bankEntry->description)->toContain('Bank/Cash for receipt id');

        // 2. Credit: Piutang Usaha (Accounts Receivable)
        $arEntry = $journalEntries->where('coa_id', $this->accountsReceivableCoa->id)->first();
        expect($arEntry)->not->toBeNull();
        expect($arEntry->debit)->toBe('0.00');
        expect($arEntry->credit)->toBe('1382000.00');
        expect($arEntry->description)->toContain('Customer receipt for receipt id');
    }

    /** @test */
    public function it_creates_correct_journal_entries_for_deposit_customer_receipt()
    {
        // Create deposit for customer
        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $this->customer->id,
            'coa_id' => $this->depositCoa->id,
            'amount' => 2000000.00,
            'remaining_amount' => 2000000.00,
        ]);

        // Create invoice
        $invoice = Invoice::factory()->create([
            'customer_name' => $this->customer->name,
            'total' => 1382000.00,
            'status' => 'unpaid',
        ]);

        // Create account receivable
        AccountReceivable::factory()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'total' => 1382000.00,
            'paid' => 0,
            'remaining' => 1382000.00,
            'status' => 'Belum Lunas',
            'created_by' => $this->user->id,
        ]);

        // Create customer receipt with deposit payment
        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'total_payment' => 1382000.00,
            'payment_method' => 'deposit',
            'status' => 'paid',
        ]);

        // Create customer receipt item with deposit method
        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'method' => 'deposit',
            'amount' => 1382000.00,
        ]);

        // Trigger journal posting by updating status
        $receipt->update(['status' => 'paid']);

        // Assert journal entries created
        $journalEntries = JournalEntry::where('source_type', CustomerReceipt::class)
            ->where('source_id', $receipt->id)
            ->orderBy('id')
            ->get();

        expect($journalEntries->count())->toBe(2);

        // Check balances - should be balanced
        $totalDebit = $journalEntries->sum('debit');
        $totalCredit = $journalEntries->sum('credit');
        expect($totalDebit)->toBe($totalCredit);
        expect($totalDebit)->toBe(1382000.00);

        // 1. Debit: Hutang Titipan Konsumen (Customer Deposit Liability)
        $depositEntry = $journalEntries->where('coa_id', $this->depositCoa->id)->first();
        expect($depositEntry)->not->toBeNull();
        expect($depositEntry->debit)->toBe('1382000.00');
        expect($depositEntry->credit)->toBe('0.00');
        expect($depositEntry->description)->toContain('Deposit / Uang Muka usage for receipt id');

        // 2. Credit: Piutang Usaha (Accounts Receivable)
        $arEntry = $journalEntries->where('coa_id', $this->accountsReceivableCoa->id)->first();
        expect($arEntry)->not->toBeNull();
        expect($arEntry->debit)->toBe('0.00');
        expect($arEntry->credit)->toBe('1382000.00');
        expect($arEntry->description)->toContain('Customer receipt for receipt id');
    }

    /** @test */
    public function it_creates_correct_journal_entries_for_mixed_payment_methods()
    {
        // Create deposit for customer
        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $this->customer->id,
            'coa_id' => $this->depositCoa->id,
            'amount' => 2000000.00,
            'remaining_amount' => 2000000.00,
        ]);

        // Debug: check deposit and its COA
        // dd([
        //     'deposit' => $deposit->toArray(),
        //     'deposit_coa' => $deposit->coa ? $deposit->coa->toArray() : null,
        //     'test_deposit_coa' => $this->depositCoa->toArray(),
        //     'cash_coa' => $this->cashCoa->toArray(),
        //     'bank_coa' => $this->bankCoa->toArray(),
        //     'accounts_receivable_coa' => $this->accountsReceivableCoa->toArray(),
        // ]);

        // Create invoice
        $invoice = Invoice::factory()->create([
            'customer_name' => $this->customer->name,
            'total' => 1382000.00,
            'status' => 'unpaid',
        ]);

        // Create account receivable
        AccountReceivable::factory()->create([
            'invoice_id' => $invoice->id,
            'customer_id' => $this->customer->id,
            'total' => 1382000.00,
            'paid' => 0,
            'remaining' => 1382000.00,
            'status' => 'Belum Lunas',
            'created_by' => $this->user->id,
        ]);

        // Create customer receipt with mixed payment methods
        $receipt = CustomerReceipt::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_date' => now()->toDateString(),
            'total_payment' => 1382000.00,
            'status' => 'draft',
        ]);

        // Create customer receipt items - 50% deposit, 50% cash
        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'method' => 'deposit',
            'amount' => 691000.00,
        ]);

        CustomerReceiptItem::factory()->create([
            'customer_receipt_id' => $receipt->id,
            'invoice_id' => $invoice->id,
            'method' => 'cash',
            'amount' => 691000.00,
            'coa_id' => $this->cashCoa->id,
        ]);

        // Trigger journal posting by updating status
        $receipt->update(['status' => 'paid']);

        // Assert journal entries created
        $journalEntries = JournalEntry::where('source_type', CustomerReceipt::class)
            ->where('source_id', $receipt->id)
            ->orderBy('id')
            ->get();

        expect($journalEntries)->toHaveCount(3);
        $totalDebit = $journalEntries->sum('debit');
        $totalCredit = $journalEntries->sum('credit');
        expect($totalDebit)->toBe($totalCredit);
        expect($totalDebit)->toBe(1382000.00);

        // Should have exactly 3 journal entries for mixed payment
        expect($journalEntries)->toHaveCount(3);

        // 1. Credit: Piutang Usaha (Accounts Receivable)
        $arEntry = $journalEntries->where('coa_id', $this->accountsReceivableCoa->id)->first();
        expect($arEntry)->not->toBeNull();
        expect($arEntry->debit)->toBe('0.00');
        expect($arEntry->credit)->toBe('1382000.00');
        expect($arEntry->description)->toContain('Customer receipt for receipt id');

        // 2. Debit: Hutang Titipan Konsumen (Customer Deposit Liability)
        $depositEntry = $journalEntries->where('coa_id', $this->depositCoa->id)->first();
        expect($depositEntry)->not->toBeNull();
        expect($depositEntry->debit)->toBe('691000.00');
        expect($depositEntry->credit)->toBe('0.00');
        expect($depositEntry->description)->toContain('Deposit / Uang Muka usage for receipt id');

        // 3. Debit: Kas Kecil (Cash)
        $cashEntry = $journalEntries->where('coa_id', $this->cashCoa->id)->first();
        expect($cashEntry)->not->toBeNull();
        expect($cashEntry->debit)->toBe('691000.00');
        expect($cashEntry->credit)->toBe('0.00');
        expect($cashEntry->description)->toContain('Bank/Cash for receipt id');
    }
}