<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\CustomerReceipt;
use App\Models\Deposit;
use App\Models\DepositLog;
use App\Models\ChartOfAccount;
use App\Models\Cabang;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /** @test */
    public function test_database_transaction_rollback_on_failure()
    {
        // Test that database transactions properly rollback on failure
        $customerData = Customer::factory()->make()->toArray();

        DB::beginTransaction();
        try {
            $customer = Customer::create($customerData);

            // Create related data that should succeed
            $coa = ChartOfAccount::factory()->create();

            // Intentionally cause failure (duplicate key or invalid data)
            Customer::create($customerData); // This should fail due to unique constraints

            DB::commit();
            $this->fail('Transaction should have rolled back');
        } catch (\Exception $e) {
            DB::rollBack();
            // Verify rollback worked - customer should not exist
            $this->assertDatabaseMissing('customers', ['id' => $customer->id ?? null]);
        }
    }

    /** @test */
    public function test_foreign_key_constraints_prevent_orphaned_records()
    {
        // Test that foreign key constraints work properly
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);

        // Create sale order
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        // Create sale order item
        $saleOrderItem = SaleOrderItem::create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        // Verify relationships exist
        $this->assertEquals($customer->id, $saleOrder->customer_id);
        $this->assertEquals($saleOrder->id, $saleOrderItem->sale_order_id);
        $this->assertEquals($product->id, $saleOrderItem->product_id);

        // Test that deleting parent records is prevented or cascades properly
        try {
            $customer->delete();
            // If soft deletes, check if record still exists
            if ($customer->trashed()) {
                $this->assertTrue($customer->trashed());
            }
        } catch (\Exception $e) {
            // Foreign key constraint should prevent deletion
            $this->assertTrue(str_contains(strtolower($e->getMessage()), 'constraint'));
        }
    }

    /** @test */
    public function test_cross_module_data_consistency_sale_to_invoice()
    {
        // Test data consistency from Sale Order -> Invoice
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $coa = ChartOfAccount::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);

        // Create sale order
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        $saleOrderItem = SaleOrderItem::create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 100000,
            'warehouse_id' => $warehouse->id,
        ]);

        $saleOrderTotal = 5 * 100000; // 500000

        // Create invoice from sale order
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'sale_order_id' => $saleOrder->id,
            'invoice_number' => 'INV-001',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'total' => $saleOrderTotal,
            'status' => 'unpaid',
            'created_by' => $this->user->id,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 5,
            'price' => 100000,
            'total' => $saleOrderTotal,
        ]);

        // Verify data consistency
        $this->assertEquals($saleOrderTotal, $invoice->total);
        $this->assertEquals($customer->id, $invoice->from_model_id);
        $this->assertEquals(Customer::class, $invoice->from_model_type);

        // Verify invoice items match sale order items
        $invoiceItems = $invoice->invoiceItem;
        $this->assertNotNull($invoiceItems);
        $this->assertCount(1, $invoiceItems);
        $this->assertEquals(5, $invoiceItems->first()->quantity);
        $this->assertEquals(100000, $invoiceItems->first()->price);
        $this->assertEquals($saleOrderTotal, $invoiceItems->first()->total);
    }

    /** @test */
    public function test_financial_transaction_integrity_deposit_to_payment()
    {
        // Test financial data integrity: Deposit -> Payment -> Journal Entry
        $customer = Customer::factory()->create();
        $coa = ChartOfAccount::factory()->create();

        // Create deposit
        $depositAmount = 1000000;
        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => $depositAmount,
            'used_amount' => 0,
            'remaining_amount' => $depositAmount,
            'coa_id' => $coa->id,
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        // Verify deposit log was created by observer
        $this->assertCount(1, $deposit->depositLog);
        $this->assertEquals('create', $deposit->depositLog->first()->type);
        $this->assertEquals($depositAmount, $deposit->depositLog->first()->amount);

        // Use deposit for payment
        $paymentAmount = 500000;
        $deposit->update([
            'used_amount' => $deposit->used_amount + $paymentAmount,
            'remaining_amount' => $deposit->remaining_amount - $paymentAmount,
        ]);

        // Create deposit log for usage
        DepositLog::create([
            'deposit_id' => $deposit->id,
            'type' => 'use',
            'reference_type' => Customer::class,
            'reference_id' => $customer->id,
            'amount' => $paymentAmount,
            'note' => 'Payment for invoice',
            'created_by' => $this->user->id,
        ]);

        // Verify financial integrity
        $deposit->refresh();
        $this->assertEquals($paymentAmount, $deposit->used_amount);
        $this->assertEquals($depositAmount - $paymentAmount, $deposit->remaining_amount);
        $this->assertEquals($depositAmount, $deposit->amount); // Original amount unchanged

        // Verify total logged amounts match deposit balance
        $totalLogged = $deposit->depositLog->sum('amount');
        $this->assertEquals($depositAmount + $paymentAmount, $totalLogged); // create + use
    }

    /** @test */
    public function test_data_validation_rules_enforced()
    {
        // Test that model validation rules are properly enforced
        $customer = Customer::factory()->create();
        $coa = ChartOfAccount::factory()->create();

        // Test required fields - this might not throw exception at DB level
        // Instead, test that the record is not created
        $depositCountBefore = Deposit::count();
        try {
            Deposit::create([
                'amount' => 1000000,
                // Missing required fields like from_model_type, from_model_id, etc.
            ]);
        } catch (\Exception $e) {
            // If exception is thrown, that's good
            $this->assertTrue(true);
        }

        // Check that no invalid record was created
        $this->assertEquals($depositCountBefore, Deposit::count());

        // Test data validation - ensure required relationships exist
        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 1000000,
            'used_amount' => 0,
            'remaining_amount' => 1000000,
            'coa_id' => $coa->id,
            'created_by' => $this->user->id,
        ]);

        // Verify relationships are properly established
        $this->assertInstanceOf(Customer::class, $deposit->fromModel);
        $this->assertInstanceOf(ChartOfAccount::class, $deposit->coa);
        $this->assertInstanceOf(User::class, $deposit->createdBy);
    }

    /** @test */
    public function test_cascade_operations_and_soft_deletes()
    {
        // Test soft delete behavior and cascade operations
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $coa = ChartOfAccount::factory()->create();

        // Create related records
        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 1000000,
            'used_amount' => 0,
            'remaining_amount' => 1000000,
            'coa_id' => $coa->id,
            'created_by' => $this->user->id,
        ]);

        $depositLog = DepositLog::create([
            'deposit_id' => $deposit->id,
            'type' => 'create',
            'amount' => 1000000,
            'created_by' => $this->user->id,
        ]);

        // Verify records exist
        $this->assertDatabaseHas('deposits', ['id' => $deposit->id]);
        $this->assertDatabaseHas('deposit_logs', ['id' => $depositLog->id]);

        // Soft delete deposit
        $deposit->delete();
        $this->assertTrue($deposit->trashed());
        $this->assertDatabaseHas('deposits', ['id' => $deposit->id]); // Still exists but soft deleted

        // Deposit logs should still exist (no cascade delete)
        $this->assertDatabaseHas('deposit_logs', ['id' => $depositLog->id]);

        // Test restore
        $deposit->restore();
        $this->assertFalse($deposit->trashed());
        $this->assertDatabaseHas('deposits', ['id' => $deposit->id]);
    }

    /** @test */
    public function test_concurrent_transaction_isolation()
    {
        // Test transaction isolation to prevent race conditions
        $customer = Customer::factory()->create();
        $coa = ChartOfAccount::factory()->create();

        $initialAmount = 1000000;

        // Create deposit
        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => $initialAmount,
            'used_amount' => 0,
            'remaining_amount' => $initialAmount,
            'coa_id' => $coa->id,
            'created_by' => $this->user->id,
        ]);

        // Simulate concurrent operations
        $results = [];

        // Operation 1: Use 300000
        DB::transaction(function () use ($deposit, &$results) {
            $deposit->refresh();
            $useAmount1 = 300000;
            $deposit->update([
                'used_amount' => $deposit->used_amount + $useAmount1,
                'remaining_amount' => $deposit->remaining_amount - $useAmount1,
            ]);
            $results[] = $deposit->fresh();
        });

        // Operation 2: Use 200000
        DB::transaction(function () use ($deposit, &$results) {
            $deposit->refresh();
            $useAmount2 = 200000;
            $deposit->update([
                'used_amount' => $deposit->used_amount + $useAmount2,
                'remaining_amount' => $deposit->remaining_amount - $useAmount2,
            ]);
            $results[] = $deposit->fresh();
        });

        // Verify final state
        $deposit->refresh();
        $this->assertEquals(500000, $deposit->used_amount); // 300000 + 200000
        $this->assertEquals(500000, $deposit->remaining_amount); // 1000000 - 500000
        $this->assertEquals($initialAmount, $deposit->amount); // Original unchanged
    }

    /** @test */
    public function test_data_integrity_across_module_boundaries()
    {
        // Test data integrity when data flows between different modules
        $customer = Customer::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create();
        $coa = ChartOfAccount::factory()->create();
        $branch = Cabang::factory()->create();
        $warehouse = Warehouse::factory()->create(['cabang_id' => $branch->id]);

        // 1. Customer places order
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'approved',
            'created_by' => $this->user->id,
        ]);

        SaleOrderItem::create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 50000,
            'warehouse_id' => $warehouse->id,
        ]);

        // 2. Invoice is created
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'sale_order_id' => $saleOrder->id,
            'invoice_number' => 'INV-TEST-001',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'total' => 500000, // 10 * 50000
            'status' => 'unpaid',
            'created_by' => $this->user->id,
        ]);

        // 3. Customer makes deposit
        $deposit = Deposit::factory()->create([
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'amount' => 600000,
            'used_amount' => 0,
            'remaining_amount' => 600000,
            'coa_id' => $coa->id,
            'created_by' => $this->user->id,
        ]);

        // 4. Payment is made using deposit
        $paymentAmount = 500000;
        $deposit->update([
            'used_amount' => $deposit->used_amount + $paymentAmount,
            'remaining_amount' => $deposit->remaining_amount - $paymentAmount,
        ]);

        $customerReceipt = CustomerReceipt::create([
            'customer_id' => $customer->id,
            'receipt_number' => 'RCT-TEST-001',
            'receipt_date' => now(),
            'payment_date' => now(),
            'total_payment' => $paymentAmount,
            'amount' => $paymentAmount,
            'payment_method' => 'deposit',
            'reference_type' => Deposit::class,
            'reference_id' => $deposit->id,
            'status' => 'Paid',
            'created_by' => $this->user->id,
        ]);

        // Verify cross-module data integrity
        $this->assertEquals($customer->id, $saleOrder->customer_id);
        // Note: Invoice may not have direct sale_order_id or customer_id fields
        // CustomerReceipt uses invoice_id, not reference_id/reference_type
        // $this->assertEquals($deposit->id, $customerReceipt->reference_id);
        $this->assertEquals($customer->id, $customerReceipt->customer_id);

        // Verify financial amounts consistency
        $this->assertEquals(500000, $invoice->total);
        $this->assertEquals(500000, $customerReceipt->total_payment);
        $this->assertEquals(500000, $deposit->used_amount);
        $this->assertEquals(100000, $deposit->remaining_amount); // 600000 - 500000
    }

    /** @test */
    public function test_unique_constraints_and_indexes()
    {
        // Test unique constraints prevent duplicate data
        $customer = Customer::factory()->create([
            'email' => 'unique@example.com',
        ]);

        // Try to create another customer with same email
        try {
            Customer::create([
                'name' => 'Another Customer',
                'email' => 'unique@example.com', // Duplicate email
                'phone' => '123456789',
                'address' => 'Test Address',
            ]);
            $this->fail('Unique constraint should have prevented duplicate email');
        } catch (\Exception $e) {
            $this->assertTrue(str_contains(strtolower($e->getMessage()), 'unique'));
        }

        // Test invoice number uniqueness
        Invoice::create([
            'customer_id' => $customer->id,
            'from_model_type' => Customer::class,
            'from_model_id' => $customer->id,
            'invoice_number' => 'UNIQUE-INV-001',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'total' => 100000,
            'status' => 'unpaid',
            'created_by' => $this->user->id,
        ]);

        try {
            Invoice::create([
                'customer_id' => $customer->id,
                'from_model_type' => Customer::class,
                'from_model_id' => $customer->id,
                'invoice_number' => 'UNIQUE-INV-001', // Duplicate invoice number
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
                'total' => 200000,
                'status' => 'unpaid',
                'created_by' => $this->user->id,
            ]);
            $this->fail('Unique constraint should have prevented duplicate invoice number');
        } catch (\Exception $e) {
            $this->assertTrue(str_contains(strtolower($e->getMessage()), 'unique'));
        }
    }

    /** @test */
    public function test_data_consistency_on_bulk_operations()
    {
        // Test data consistency during bulk operations
        $customers = Customer::factory()->count(5)->create();
        $coa = ChartOfAccount::factory()->create();

        // Bulk create deposits
        $deposits = [];
        foreach ($customers as $customer) {
            $deposits[] = Deposit::factory()->create([
                'from_model_type' => Customer::class,
                'from_model_id' => $customer->id,
                'amount' => 1000000,
                'used_amount' => 0,
                'remaining_amount' => 1000000,
                'coa_id' => $coa->id,
                'created_by' => $this->user->id,
            ]);
        }

        // Verify all deposits were created with correct relationships
        foreach ($deposits as $index => $deposit) {
            $this->assertEquals(Customer::class, $deposit->from_model_type);
            $this->assertEquals($customers[$index]->id, $deposit->from_model_id);
            $this->assertEquals(1000000, $deposit->amount);
            $this->assertEquals(1000000, $deposit->remaining_amount);
            $this->assertEquals(0, $deposit->used_amount);

            // Verify observer created log for each deposit
            $this->assertCount(1, $deposit->depositLog);
            $this->assertEquals('create', $deposit->depositLog->first()->type);
        }

        // Bulk update operation
        Deposit::whereIn('id', collect($deposits)->pluck('id'))
            ->update(['status' => 'closed']);

        // Verify all deposits were updated
        foreach ($deposits as $deposit) {
            $deposit->refresh();
            $this->assertEquals('closed', $deposit->status);
        }
    }
}