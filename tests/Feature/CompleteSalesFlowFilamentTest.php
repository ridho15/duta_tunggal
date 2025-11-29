<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerReceipt;
use App\Models\CustomerReceiptItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryStock;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DeliveryOrderService;
use Database\Seeders\ChartOfAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteSalesFlowFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Customer $customer;
    protected Warehouse $warehouse;
    protected Product $product;
    protected ProductCategory $productCategory;
    protected ChartOfAccount $arCoa;
    protected ChartOfAccount $revenueCoa;
    protected ChartOfAccount $inventoryCoa;
    protected ChartOfAccount $cogsCoa;
    protected ChartOfAccount $cashCoa;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ChartOfAccountSeeder::class);

        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create([
            'code' => 'CUST001',
            'tipe' => 'PKP',
            'tempo_kredit' => 30,
            'kredit_limit' => 10000000,
            'tipe_pembayaran' => 'Kredit',
        ]);
        $this->warehouse = Warehouse::factory()->create();
        $this->productCategory = ProductCategory::factory()->create([
            'kode' => 'PC001',
            'cabang_id' => 1,
        ]);
        $this->product = Product::factory()->create([
            'product_category_id' => $this->productCategory->id,
            'cost_price' => 10000,
            'sell_price' => 15000,
            'is_active' => true,
            'uom_id' => 1,
        ]);
        $this->currency = Currency::factory()->create(['code' => 'IDR']);

        // Set up COAs
        $arCoa = ChartOfAccount::where('code', '1120')->first();
        $revenueCoa = ChartOfAccount::where('code', '4000')->first();
        $inventoryCoa = ChartOfAccount::where('code', '1140.01')->first();
        $cogsCoa = ChartOfAccount::where('code', '5000')->first();
        $cashCoa = ChartOfAccount::where('code', '1111.01')->first(); // Kas Besar

        $this->arCoa = $arCoa;
        $this->revenueCoa = $revenueCoa;
        $this->inventoryCoa = $inventoryCoa;
        $this->cogsCoa = $cogsCoa;
        $this->cashCoa = $cashCoa;

        $this->product->update([
            'inventory_coa_id' => $inventoryCoa?->id,
            'cogs_coa_id' => $cogsCoa?->id,
        ]);

        $this->product->refresh();

        $this->actingAs($this->user);
    }

    /** @test */
    public function complete_sales_flow_from_quotation_to_customer_payment_with_form_simulation()
    {
        // ==========================================
        // STEP 1: CREATE QUOTATION (Filament Form Simulation)
        // ==========================================

        $quotation = Quotation::factory()->create([
            'quotation_number' => 'QO-20251107-0001',
            'customer_id' => $this->customer->id,
            'date' => now(),
            'valid_until' => now()->addDays(30),
            'status' => 'approve', // Approved quotation
            'created_by' => $this->user->id,
        ]);

        $quotationItem = QuotationItem::create([
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 15000,
            'discount' => 0,
            'tax' => 0,
        ]);

        $this->assertDatabaseHas('quotations', [
            'id' => $quotation->id,
            'quotation_number' => 'QO-20251107-0001',
            'status' => 'approve',
        ]);

        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 15000,
        ]);

        // ==========================================
        // STEP 2: CONVERT QUOTATION TO SALES ORDER (Filament Form Simulation)
        // ==========================================

        $saleOrder = SaleOrder::factory()->create([
            'so_number' => 'SO-20251107-0001',
            'quotation_id' => $quotation->id,
            'order_date' => now(),
            'delivery_date' => now()->addDays(7),
            'status' => 'approved',
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $saleOrderItem = SaleOrderItem::create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 15000,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->assertDatabaseHas('sale_orders', [
            'id' => $saleOrder->id,
            'so_number' => 'SO-20251107-0001',
            'quotation_id' => $quotation->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('sale_order_items', [
            'sale_order_id' => $saleOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'unit_price' => 15000,
        ]);

        // Create initial inventory stock
        $inventoryStock = InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_available' => 20,
            'qty_reserved' => 0,
        ]);

        // ==========================================
        // STEP 3: CREATE DELIVERY ORDER (Filament Form Simulation)
        // ==========================================

        $deliveryOrder = DeliveryOrder::factory()->create([
            'do_number' => 'DO-20251107-0001',
            'delivery_date' => now(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Attach sale order to delivery order
        $deliveryOrder->salesOrders()->attach($saleOrder->id);

        $deliveryOrderItem = DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseHas('delivery_orders', [
            'id' => $deliveryOrder->id,
            'do_number' => 'DO-20251107-0001',
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('delivery_order_items', [
            'delivery_order_id' => $deliveryOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        // ==========================================
        // STEP 4: POST DELIVERY ORDER (Reduce Inventory)
        // ==========================================

        $deliveryOrderService = app(DeliveryOrderService::class);
        $postResult = $deliveryOrderService->postDeliveryOrder($deliveryOrder);

        $this->assertEquals('posted', $postResult['status']);

        // Verify inventory stock reduced (automatically by StockMovementObserver)
        $inventoryStock->refresh();
        $this->assertEquals(10, $inventoryStock->qty_available); // 20 - 10
        $this->assertEquals(0, $inventoryStock->qty_reserved);

        // ==========================================
        // STEP 5: CREATE INVOICE (Filament Form Simulation)
        // ==========================================

        $invoice = Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_number' => 'INV-20251107-0001',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 150000,
            'tax' => 0,
            'total' => 150000,
            'status' => 'Unpaid',
            'delivery_orders' => [$deliveryOrder->id],
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'invoice_number' => 'INV-20251107-0001',
            'total' => 150000,
            'status' => 'Unpaid',
        ]);

        // Invoice observer should create AR and journal entries
        $accountReceivable = \App\Models\AccountReceivable::where('invoice_id', $invoice->id)->first();
        $this->assertNotNull($accountReceivable);
        $this->assertEquals(150000, $accountReceivable->total);
        $this->assertEquals(150000, $accountReceivable->remaining);

        // ==========================================
        // STEP 6: CREATE CUSTOMER RECEIPT (Payment)
        // ==========================================

        $customerReceipt = CustomerReceipt::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_date' => now(),
            'total_payment' => 150000,
            'status' => 'Paid',
        ]);

        $customerReceiptItem = CustomerReceiptItem::create([
            'customer_receipt_id' => $customerReceipt->id,
            'invoice_id' => $invoice->id,
            'method' => 'cash',
            'amount' => 150000,
            'coa_id' => $this->cashCoa->id,
            'payment_date' => now(),
        ]);

        $this->assertDatabaseHas('customer_receipts', [
            'id' => $customerReceipt->id,
            'total_payment' => 150000,
            'status' => 'Paid',
        ]);

        $this->assertDatabaseHas('customer_receipt_items', [
            'customer_receipt_id' => $customerReceipt->id,
            'amount' => 150000,
        ]);

        // CustomerReceiptItem observer should update AR and create journal entries
        $accountReceivable->refresh();
        $this->assertEquals(0, $accountReceivable->remaining);
        $this->assertEquals('Lunas', $accountReceivable->status);

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);

        // ==========================================
        // VERIFICATION: JOURNAL ENTRIES
        // ==========================================

        $journalEntries = JournalEntry::all();

        // Expected journal entries:
        // 1. InventoryStock opening balance: Debit Inventory, Credit Opening Balance (2 entries)
        // 2. Delivery Order: Goods Delivery Debit, Inventory Credit (2 entries)
        // 3. Invoice: AR Debit, Revenue Credit, COGS Debit, Goods Delivery Credit (4 entries)
        // 4. Payment: Cash Debit, AR Credit (2 entries)
        $this->assertCount(10, $journalEntries);

        // Check delivery order entries (COGS and Inventory)
        $cogsDebit = $journalEntries->where('coa_id', $this->cogsCoa->id)->where('debit', 100000)->where('credit', 0)->where('journal_type', 'sales')->first();
        $this->assertNotNull($cogsDebit);

        $inventoryCredit = $journalEntries->where('debit', 0)->where('credit', 100000)->where('journal_type', 'sales')->first();
        $this->assertNotNull($inventoryCredit);
        $this->assertEquals($this->inventoryCoa->id, $inventoryCredit->coa_id);

        // Check invoice entries (AR and Revenue)
        $arDebit = $journalEntries->where('debit', 150000)->where('credit', 0)->where('journal_type', 'sales')->first();
        $this->assertNotNull($arDebit);
        $this->assertEquals($this->arCoa->id, $arDebit->coa_id);

        $revenueCredit = $journalEntries->where('debit', 0)->where('credit', 150000)->where('journal_type', 'sales')->first();
        $this->assertNotNull($revenueCredit);
        $this->assertEquals($this->revenueCoa->id, $revenueCredit->coa_id);

        // Check payment entries (Cash and AR)
        $cashDebit = $journalEntries->where('debit', 150000)->where('credit', 0)->where('journal_type', 'Sales')->first();
        $this->assertNotNull($cashDebit);
        $this->assertEquals($this->cashCoa->id, $cashDebit->coa_id);

        $arCredit = $journalEntries->where('debit', 0)->where('credit', 150000)->where('journal_type', 'Sales')->first();
        $this->assertNotNull($arCredit);
        $this->assertEquals($this->arCoa->id, $arCredit->coa_id);

        // ==========================================
        // VERIFICATION: GENERAL LEDGER
        // ==========================================

        // Check COGS balance (expense)
        $this->cogsCoa->load('journalEntries');
        $this->assertEquals(100000, $this->cogsCoa->calculateEndingBalance());

        // Check Inventory balance (reduced asset)
        $this->inventoryCoa->load('journalEntries');
        $this->assertEquals(-100000, $this->inventoryCoa->calculateEndingBalance()); // Reduced by sales

        // Check AR balance (should be zero after payment)
        $this->arCoa->load('journalEntries');
        $this->assertEquals(0, $this->arCoa->calculateEndingBalance()); // Debit 150k - Credit 150k

        // Check Revenue balance (income - credit balance)
        $this->revenueCoa->load('journalEntries');
        $this->assertEquals(150000, $this->revenueCoa->calculateEndingBalance()); // Credit balance

        // Check Cash balance (increased asset)
        $this->cashCoa->load('journalEntries');
        $this->assertEquals(150000, $this->cashCoa->calculateEndingBalance());

        // ==========================================
        // VERIFICATION: BALANCE SHEET
        // ==========================================

        // Calculate total assets (inventory + cash)
        $totalAssets = $this->inventoryCoa->calculateEndingBalance() + $this->cashCoa->calculateEndingBalance();
        $this->assertEquals(50000, $totalAssets); // -100k inventory + 150k cash

        // Calculate total liabilities (AR should be zero)
        $totalLiabilities = $this->arCoa->calculateEndingBalance();
        $this->assertEquals(0, $totalLiabilities);

        // Calculate equity (revenue - cogs)
        $equity = $this->revenueCoa->calculateEndingBalance() - $this->cogsCoa->calculateEndingBalance();
        $this->assertEquals(50000, $equity); // 150k revenue - 100k cogs = 50k profit

        // Balance sheet should balance: Assets = Liabilities + Equity
        $this->assertEquals($totalAssets, $totalLiabilities + $equity);

        // ==========================================
        // VERIFICATION: CASH FLOW STATEMENT
        // ==========================================

        // Cash flow from operating activities (customer payment)
        $cashFlowEntries = JournalEntry::where('journal_type', 'Sales')->where('coa_id', $this->cashCoa->id)->get();
        $this->assertCount(1, $cashFlowEntries);
        $this->assertEquals(150000, $cashFlowEntries->first()->debit);

        // ==========================================
        // FINAL VERIFICATION: COMPLETE FLOW
        // ==========================================

        // Verify quotation status
        $quotation->refresh();
        $this->assertEquals('approve', $quotation->status);

        // Verify sale order status
        $saleOrder->refresh();
        $this->assertEquals('approved', $saleOrder->status);

        // Verify delivery order approved
        $deliveryOrder->refresh();
        $this->assertEquals('approved', $deliveryOrder->status);

        // Verify invoice paid
        $this->assertEquals('paid', $invoice->status);

        // Verify customer receipt completed
        $customerReceipt->refresh();
        $this->assertEquals('Paid', $customerReceipt->status);

        // Verify inventory reduced
        $this->assertEquals(10, $inventoryStock->qty_available);

        // Verify all journal entries are balanced
        $totalDebit = $journalEntries->sum('debit');
        $totalCredit = $journalEntries->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(600000, $totalDebit); // Opening 100k + Delivery 100k + AR 150k + COGS 100k + Cash 150k
        $this->assertEquals(600000, $totalCredit); // Opening 100k + Delivery 100k + Revenue 150k + Goods Delivery 100k + AR 150k
    }
}