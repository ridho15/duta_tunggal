<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\Cabang;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create and authenticate a user for all tests
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_create_customer_deposit()
    {
        // Setup
        $customer = Customer::factory()->create();
        $coa = \App\Models\ChartOfAccount::factory()->create();

        // Create customer deposit
        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 5000000,
            'used_amount' => 0,
            'remaining_amount' => 5000000,
            'coa_id' => $coa->id,
            'note' => 'Advance payment for future orders',
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Deposit::class, $deposit);
        $this->assertEquals(5000000, $deposit->amount);
        $this->assertEquals(0, $deposit->used_amount);
        $this->assertEquals(5000000, $deposit->remaining_amount);
        $this->assertEquals('active', $deposit->status);
        $this->assertEquals(Customer::class, $deposit->from_model_type);
        $this->assertEquals($customer->id, $deposit->from_model_id);
    }

    public function test_can_create_supplier_deposit()
    {
        // Setup
        $supplier = Supplier::factory()->create();
        $coa = \App\Models\ChartOfAccount::factory()->create();

        // Create supplier deposit
        $deposit = Deposit::factory()->create([
            'from_model_type' => Supplier::class,
            'from_model_id' => $supplier->id,
            'amount' => 10000000,
            'used_amount' => 0,
            'remaining_amount' => 10000000,
            'coa_id' => $coa->id,
            'note' => 'Prepayment for bulk order',
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(Deposit::class, $deposit);
        $this->assertEquals(10000000, $deposit->amount);
        $this->assertEquals(Supplier::class, $deposit->from_model_type);
        $this->assertEquals($supplier->id, $deposit->from_model_id);
    }

    public function test_can_use_customer_deposit_for_sale_order()
    {
        // Setup customer and deposit
        $customer = Customer::factory()->create();
        $coa = \App\Models\ChartOfAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 5000000,
            'used_amount' => 0,
            'remaining_amount' => 5000000,
            'coa_id' => $coa->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Create sale order that uses deposit
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        // Use deposit for payment
        $usedAmount = 1000000;
        $deposit->update([
            'used_amount' => $deposit->used_amount + $usedAmount,
            'remaining_amount' => $deposit->remaining_amount - $usedAmount,
        ]);

        // Create deposit log
        $depositLog = DepositLog::create([
            'deposit_id' => $deposit->id,
            'type' => 'use',
            'reference_type' => SaleOrder::class,
            'reference_id' => $saleOrder->id,
            'amount' => $usedAmount,
            'note' => 'Used for sale order payment',
            'created_by' => $this->user->id,
        ]);

        // Assertions
        $deposit->refresh();
        $this->assertEquals(1000000, $deposit->used_amount);
        $this->assertEquals(4000000, $deposit->remaining_amount);
        $this->assertInstanceOf(DepositLog::class, $depositLog);
        $this->assertEquals('use', $depositLog->type);
        $this->assertEquals($usedAmount, $depositLog->amount);
    }

    public function test_can_use_customer_deposit_for_invoice_payment()
    {
        // Setup customer, invoice, and deposit
        $customer = Customer::factory()->create();
        $user = User::factory()->create();
        $coa = \App\Models\ChartOfAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 5000000,
            'used_amount' => 0,
            'remaining_amount' => 5000000,
            'coa_id' => $coa->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Create invoice
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);

        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $saleOrder->saleOrderItem()->create([
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'confirmed',
            'warehouse_id' => $warehouse->id,
        ]);

        $invoice = Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'total' => 1000000,
            'status' => 'unpaid',
        ]);

        // Use deposit for invoice payment
        $usedAmount = 1000000;
        $deposit->update([
            'used_amount' => $deposit->used_amount + $usedAmount,
            'remaining_amount' => $deposit->remaining_amount - $usedAmount,
        ]);

        // Create deposit log
        $depositLog = DepositLog::create([
            'deposit_id' => $deposit->id,
            'type' => 'use',
            'reference_type' => Invoice::class,
            'reference_id' => $invoice->id,
            'amount' => $usedAmount,
            'note' => 'Used for invoice payment',
            'created_by' => $this->user->id,
        ]);

        // Update invoice status
        $invoice->update(['status' => 'paid']);

        // Assertions
        $deposit->refresh();
        $this->assertEquals(1000000, $deposit->used_amount);
        $this->assertEquals(4000000, $deposit->remaining_amount);
        $this->assertEquals('paid', $invoice->fresh()->status);
        $this->assertInstanceOf(DepositLog::class, $depositLog);
        $this->assertEquals('use', $depositLog->type);
    }

    public function test_can_refund_customer_deposit()
    {
        // Setup customer and deposit
        $customer = Customer::factory()->create();
        $user = User::factory()->create();
        $coa = \App\Models\ChartOfAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 5000000,
            'used_amount' => 1000000,
            'remaining_amount' => 4000000,
            'coa_id' => $coa->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Refund deposit
        $refundAmount = 2000000;
        $deposit->update([
            'used_amount' => $deposit->used_amount - $refundAmount,
            'remaining_amount' => $deposit->remaining_amount + $refundAmount,
        ]);

        // Create deposit log for refund
        $depositLog = DepositLog::create([
            'deposit_id' => $deposit->id,
            'type' => 'return',
            'reference_type' => Customer::class,
            'reference_id' => $customer->id,
            'amount' => $refundAmount,
            'note' => 'Deposit refund requested by customer',
            'created_by' => $this->user->id,
        ]);

        // If fully refunded, close deposit
        if ($deposit->remaining_amount <= 0) {
            $deposit->update(['status' => 'closed']);
        }

        // Assertions
        $deposit->refresh();
        $this->assertEquals(6000000, $deposit->remaining_amount); // 4000000 + 2000000 refund = 6000000
        $this->assertInstanceOf(DepositLog::class, $depositLog);
        $this->assertEquals('return', $depositLog->type);
        $this->assertEquals($refundAmount, $depositLog->amount);
    }

    public function test_deposit_tracking_logs_all_transactions()
    {
        // Setup customer and deposit
        $customer = Customer::factory()->create();
        $user = User::factory()->create();
        $coa = \App\Models\ChartOfAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 5000000,
            'used_amount' => 0,
            'remaining_amount' => 5000000,
            'coa_id' => $coa->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Create initial deposit log (skip create log since observer creates it automatically)
        // $createLog = DepositLog::create([
        //     'deposit_id' => $deposit->id,
        //     'type' => 'create',
        //     'reference_type' => Customer::class,
        //     'reference_id' => $customer->id,
        //     'amount' => 5000000,
        //     'note' => 'Initial deposit creation',
        //     'created_by' => $this->user->id,
        // ]);

        // Use deposit
        $useLog = DepositLog::create([
            'deposit_id' => $deposit->id,
            'type' => 'use',
            'reference_type' => SaleOrder::class,
            'reference_id' => 1, // Mock reference
            'amount' => 1000000,
            'note' => 'Used for sale order',
            'created_by' => $this->user->id,
        ]);

        // Refund deposit
        $refundLog = DepositLog::create([
            'deposit_id' => $deposit->id,
            'type' => 'return',
            'reference_type' => Customer::class,
            'reference_id' => $customer->id,
            'amount' => 500000,
            'note' => 'Partial refund',
            'created_by' => $this->user->id,
        ]);

        // Check logs (should be 3: create from observer, use, return)
        $logs = $deposit->depositLog;
        $this->assertCount(3, $logs);

        $logTypes = $logs->pluck('type')->toArray();
        $this->assertContains('create', $logTypes);
        $this->assertContains('use', $logTypes);
        $this->assertContains('return', $logTypes);

        // Check total logged amounts
        $totalLogged = $logs->sum('amount');
        $this->assertEquals(6500000, $totalLogged); // 5000000 + 1000000 + 500000
    }

    public function test_deposit_status_changes_based_on_balance()
    {
        // Setup customer and deposit
        $customer = Customer::factory()->create();
        $user = User::factory()->create();
        $coa = \App\Models\ChartOfAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 5000000,
            'used_amount' => 0,
            'remaining_amount' => 5000000,
            'coa_id' => $coa->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Use entire deposit
        $deposit->update([
            'used_amount' => 5000000,
            'remaining_amount' => 0,
            'status' => 'closed',
        ]);

        $this->assertEquals(0, $deposit->remaining_amount);
        $this->assertEquals('closed', $deposit->status);
    }

    public function test_can_filter_deposits_by_customer_supplier()
    {
        // Setup multiple customers and suppliers
        $customer1 = Customer::factory()->create();
        $customer2 = Customer::factory()->create();
        $supplier1 = Supplier::factory()->create();
        $user = User::factory()->create();
        $coa = \App\Models\ChartOfAccount::factory()->create();

        // Create deposits
        $customerDeposit1 = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer1->id,
            'amount' => 1000000,
            'coa_id' => $coa->id,
            'created_by' => $this->user->id,
        ]);

        $customerDeposit2 = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer2->id,
            'amount' => 2000000,
            'coa_id' => $coa->id,
            'created_by' => $this->user->id,
        ]);

        $supplierDeposit = Deposit::factory()->create([
            'from_model_type' => Supplier::class,
            'from_model_id' => $supplier1->id,
            'amount' => 3000000,
            'coa_id' => $coa->id,
            'created_by' => $this->user->id,
        ]);

        // Filter by customer deposits
        $customerDeposits = Deposit::where('from_model_type', Customer::class)->get();
        $this->assertCount(2, $customerDeposits);

        // Filter by supplier deposits
        $supplierDeposits = Deposit::where('from_model_type', Supplier::class)->get();
        $this->assertCount(1, $supplierDeposits);
        $this->assertEquals($supplierDeposit->id, $supplierDeposits->first()->id);
    }

    public function test_deposit_summary_page_shows_correct_data()
    {
        // Setup customer and deposit with logs
        $customer = Customer::factory()->create();
        $user = User::factory()->create();
        $coa = \App\Models\ChartOfAccount::factory()->create();

        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 5000000,
            'used_amount' => 1000000,
            'remaining_amount' => 4000000,
            'coa_id' => $coa->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Create some logs (skip create log since observer creates it automatically)
        // DepositLog::create([
        //     'deposit_id' => $deposit->id,
        //     'type' => 'create',
        //     'amount' => 5000000,
        //     'created_by' => $this->user->id,
        // ]);

        DepositLog::create([
            'deposit_id' => $deposit->id,
            'type' => 'use',
            'amount' => 1000000,
            'created_by' => $this->user->id,
        ]);

        // Test summary data
        $this->assertEquals(5000000, $deposit->amount);
        $this->assertEquals(4000000, $deposit->remaining_amount);
        $this->assertEquals(1000000, $deposit->used_amount);
        $this->assertCount(2, $deposit->depositLog); // create (observer) + use
        $this->assertEquals('active', $deposit->status);
    }

    public function test_can_add_additional_deposit_to_existing_customer()
    {
        // Setup customer
        $customer = Customer::factory()->create();
        $user = User::factory()->create();
        $coa = \App\Models\ChartOfAccount::factory()->create();

        // First deposit
        $deposit1 = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 2000000,
            'used_amount' => 0,
            'remaining_amount' => 2000000,
            'coa_id' => $coa->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Second deposit
        $deposit2 = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 3000000,
            'used_amount' => 0,
            'remaining_amount' => 3000000,
            'coa_id' => $coa->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Check customer has multiple deposits
        $customerDeposits = Deposit::where('from_model_type', Customer::class)
                                  ->where('from_model_id', $customer->id)
                                  ->get();

        $this->assertCount(2, $customerDeposits);
        $this->assertEquals(5000000, $customerDeposits->sum('remaining_amount'));
    }
}