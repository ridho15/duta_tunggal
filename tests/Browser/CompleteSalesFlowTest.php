<?php

namespace Tests\Browser;

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class CompleteSalesFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic data
        $this->artisan('db:seed', ['--class' => 'ChartOfAccountSeeder']);
        $this->artisan('db:seed', ['--class' => 'ProductSeeder']);
        $this->artisan('db:seed', ['--class' => 'CustomerSeeder']);
        $this->artisan('db:seed', ['--class' => 'CashBankAccountsSeeder']);
        $this->artisan('db:seed', ['--class' => 'UserSeeder']);
    }

    /** @test */
    public function complete_sales_flow_from_quotation_to_payment()
    {
        $user = User::where('email', 'ralamzah@gmail.com')->first();
        $customer = Customer::first();
        $product = Product::first();
        $cashAccount = ChartOfAccount::where('type', 'Asset')->where('code', 'like', '111%')->first();

        $this->browse(function (Browser $browser) use ($user, $customer, $product, $cashAccount) {
            // 1. Login
            $browser->loginAs($user)
                ->visit('/admin')
                ->assertSee('Dashboard');

            // 2. Create Quotation
            $browser->visit('/admin/quotations')
                ->click('a[href="/admin/quotations/create"]')
                ->waitFor('#data\\.quotation_number')
                ->type('#data\\.quotation_number', 'QT-E2E-' . time())
                ->select('#data\\.customer_id', $customer->id)
                ->type('#data\\.date', now()->format('Y-m-d'))
                ->type('#data\\.valid_until', now()->addDays(30)->format('Y-m-d'))
                ->click('button[wire\\:click*="addItem"]') // Add item button
                ->waitFor('.repeater-item')
                ->select('select[name*="product_id"]', $product->id)
                ->type('input[name*="quantity"]', '10')
                ->type('input[name*="unit_price"]', '150000')
                ->press('Buat')
                ->assertSee('Berhasil');

            $quotation = Quotation::latest()->first();
            // Approve the quotation programmatically for the test
            $quotation->update(['status' => 'approve']);

            // 3. Create Sales Order from Quotation
            $browser->visit('/admin/sale-orders')
                ->click('a[href="/admin/sale-orders/create"]')
                ->waitFor('#data\\.so_number')
                ->select('#data\\.options_form', '2') // Refer Quotation
                ->waitFor('#data\\.quotation_id')
                ->select('#data\\.quotation_id', $quotation->id)
                ->press('Buat')
                ->assertSee('Berhasil');

            $so = SaleOrder::latest()->first();

            // 4. Create Delivery Order from Sales Order
            $browser->visit('/admin/delivery-orders')
                ->click('a[href="/admin/delivery-orders/create"]')
                ->waitFor('#data\\.do_number')
                ->select('#data\\.sales_order_id', [$so->id])
                ->waitFor('.repeater-item')
                ->press('Buat')
                ->assertSee('Berhasil');

            $do = DeliveryOrder::latest()->first();

            // 5. Create Invoice from Sales Order
            $browser->visit('/admin/sales-invoices')
                ->click('a[href="/admin/sales-invoices/create"]')
                ->waitFor('input[name="from_model_type"]')
                ->radio('from_model_type', 'App\\Models\\SaleOrder')
                ->waitFor('#data\\.from_model_id')
                ->select('#data\\.from_model_id', $so->id)
                ->press('Buat')
                ->assertSee('Berhasil');

            $invoice = Invoice::latest()->first();

            // 6. Create Customer Receipt
            $browser->visit('/admin/customer-receipts')
                ->click('a[href="/admin/customer-receipts/create"]')
                ->waitFor('#data\\.customer_id')
                ->select('#data\\.customer_id', $customer->id)
                ->waitFor('.invoice-selection-table')
                ->check("input[type='checkbox'][value='{$invoice->id}']")
                ->select('#data\\.payment_method', 'cash')
                ->select('#data\\.cash_bank_account_id', $cashAccount->id)
                ->press('Buat')
                ->assertSee('Berhasil');
        });

        // 7. Verify all journals
        $journals = JournalEntry::whereIn('source_type', [
            DeliveryOrder::class,
            Invoice::class,
            CustomerReceipt::class
        ])->get();

        expect($journals->count())->toBeGreaterThan(0);
        foreach ($journals as $journal) {
            expect($journal->isBalanced())->toBeTrue();
        }
    }
}