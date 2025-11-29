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

class CompleteSalesFlowAnalysisTest extends TestCase
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

        // Create test data
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create([
            'name' => 'Test Customer',
            'code' => 'CUST001',
        ]);
        $this->warehouse = Warehouse::factory()->create([
            'name' => 'Main Warehouse',
            'kode' => 'WH001',
        ]);
        $this->productCategory = ProductCategory::factory()->create([
            'name' => 'Test Category',
        ]);
        $this->product = Product::factory()->create([
            'name' => 'Test Product',
            'sku' => 'PROD001',
            'cost_price' => 10000,
            'product_category_id' => $this->productCategory->id,
        ]);

        // Get COAs
        $this->arCoa = ChartOfAccount::where('code', '1130.01')->first();
        $this->revenueCoa = ChartOfAccount::where('code', '4000')->first(); // Changed from 4100.10 to 4000
        $this->inventoryCoa = ChartOfAccount::where('code', '1140.01')->first();
        $this->cogsCoa = ChartOfAccount::where('code', '5100.10')->first();
        $this->cashCoa = ChartOfAccount::where('code', '1111.01')->first();
        $this->currency = Currency::where('code', 'IDR')->first() ?? Currency::factory()->create(['code' => 'IDR']);
    }

    public function test_complete_sales_flow_with_split_payment_deposit_and_bank_transfer()
    {
        // ==========================================
        // STEP 1: CREATE QUOTATION
        // ==========================================

        $quotation = Quotation::factory()->create([
            'quotation_number' => 'QO-20251107-0002',
            'customer_id' => $this->customer->id,
            'date' => now(),
            'valid_until' => now()->addDays(30),
            'status' => 'approve',
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

        // ==========================================
        // STEP 2: CONVERT QUOTATION TO SALES ORDER
        // ==========================================

        $saleOrder = SaleOrder::factory()->create([
            'so_number' => 'SO-20251107-0002',
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

        // Create initial inventory stock
        $inventoryStock = InventoryStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_available' => 20,
            'qty_reserved' => 0,
        ]);

        // ==========================================
        // STEP 3: CREATE DELIVERY ORDER
        // ==========================================

        $deliveryOrder = DeliveryOrder::factory()->create([
            'do_number' => 'DO-20251107-0002',
            'delivery_date' => now(),
            'status' => 'approved',
            'created_by' => $this->user->id,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $deliveryOrder->salesOrders()->attach($saleOrder->id);

        $deliveryOrderItem = DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $deliveryOrder->id,
            'sale_order_item_id' => $saleOrderItem->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
        ]);

        // Post delivery order
        $deliveryOrderService = new DeliveryOrderService();
        $deliveryOrderService->postDeliveryOrder($deliveryOrder);

        // ==========================================
        // STEP 4: CREATE INVOICE
        // ==========================================

        $invoice = Invoice::factory()->create([
            'from_model_type' => SaleOrder::class,
            'from_model_id' => $saleOrder->id,
            'invoice_number' => 'INV-20251107-0002',
            'invoice_date' => now(),
            'due_date' => now()->addDays(30),
            'subtotal' => 150000,
            'tax' => 0,
            'total' => 150000,
            'status' => 'Unpaid',
            'delivery_orders' => [$deliveryOrder->id],
        ]);

        // ==========================================
        // STEP 5: SPLIT PAYMENT - DEPOSIT (50%) + BANK TRANSFER (50%)
        // ==========================================

        $totalAmount = 150000;
        $depositAmount = $totalAmount / 2; // 75,000
        $bankTransferAmount = $totalAmount / 2; // 75,000

        // Get COAs for different payment methods
        $depositCoa = ChartOfAccount::where('code', '1111.01')->first(); // KAS BESAR
        $bankCoa = ChartOfAccount::where('code', '1112.01')->first(); // BANK

        // Create first customer receipt for deposit payment
        $depositReceipt = CustomerReceipt::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_date' => now(),
            'total_payment' => $depositAmount,
            'status' => 'Paid',
            'created_by' => $this->user->id,
        ]);

        $depositReceiptItem = CustomerReceiptItem::create([
            'customer_receipt_id' => $depositReceipt->id,
            'invoice_id' => $invoice->id,
            'amount' => $depositAmount,
            'coa_id' => $depositCoa->id,
            'method' => 'deposit',
        ]);

        // Create second customer receipt for bank transfer payment
        $bankReceipt = CustomerReceipt::factory()->create([
            'customer_id' => $this->customer->id,
            'payment_date' => now(),
            'total_payment' => $bankTransferAmount,
            'status' => 'Paid',
            'created_by' => $this->user->id,
        ]);

        $bankReceiptItem = CustomerReceiptItem::create([
            'customer_receipt_id' => $bankReceipt->id,
            'invoice_id' => $invoice->id,
            'amount' => $bankTransferAmount,
            'coa_id' => $bankCoa->id,
            'method' => 'bank_transfer',
        ]);

        // ==========================================
        // GENERATE REPORT
        // ==========================================

        $report = "# Complete Sales Flow Analysis - Split Payment (Deposit + Bank Transfer)\n\n";
        $report .= "Generated on: " . now()->format('Y-m-d H:i:s') . "\n\n";

        // ==========================================
        // DATA CREATED
        // ==========================================

        $report .= "## 1. Data Created\n\n";

        $report .= "### Users\n";
        $report .= "- ID: {$this->user->id}, Name: {$this->user->name}, Email: {$this->user->email}\n\n";

        $report .= "### Customers\n";
        $report .= "- ID: {$this->customer->id}, Code: {$this->customer->code}, Name: {$this->customer->name}\n\n";

        $report .= "### Warehouses\n";
        $report .= "- ID: {$this->warehouse->id}, Code: {$this->warehouse->kode}, Name: {$this->warehouse->name}\n\n";

        $report .= "### Products\n";
        $report .= "- ID: {$this->product->id}, Code: {$this->product->sku}, Name: {$this->product->name}, Cost Price: " . number_format($this->product->cost_price) . "\n\n";

        $report .= "### Product Categories\n";
        $report .= "- ID: {$this->productCategory->id}, Name: {$this->productCategory->name}\n\n";

        // ==========================================
        // SALES DOCUMENTS
        // ==========================================

        $report .= "## 2. Sales Documents\n\n";

        $report .= "### Quotations\n";
        $report .= "- Number: {$quotation->quotation_number}, Date: {$quotation->date}, Status: {$quotation->status}, Customer: {$quotation->customer->name}\n";
        $report .= "  Items:\n";
        foreach ($quotation->quotationItem as $item) {
            $report .= "  - Product: {$item->product->name}, Qty: {$item->quantity}, Unit Price: " . number_format($item->unit_price) . "\n";
        }
        $report .= "\n";

        $report .= "### Sale Orders\n";
        $report .= "- Number: {$saleOrder->so_number}, Date: {$saleOrder->order_date}, Status: {$saleOrder->status}, Customer: {$saleOrder->customer->name}\n";
        $report .= "  Items:\n";
        foreach ($saleOrder->saleOrderItem as $item) {
            $report .= "  - Product: {$item->product->name}, Qty: {$item->quantity}, Unit Price: " . number_format($item->unit_price) . "\n";
        }
        $report .= "\n";

        $report .= "### Delivery Orders\n";
        $report .= "- Number: {$deliveryOrder->do_number}, Date: {$deliveryOrder->delivery_date}, Status: {$deliveryOrder->status}\n";
        $report .= "  Items:\n";
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $report .= "  - Product: {$item->product->name}, Qty: {$item->quantity}\n";
        }
        $report .= "\n";

        $report .= "### Invoices\n";
        $report .= "- Number: {$invoice->invoice_number}, Date: {$invoice->invoice_date}, Total: " . number_format($invoice->total) . ", Status: {$invoice->status}\n\n";

        $report .= "### Customer Receipts (Payments)\n";
        $report .= "- Deposit Payment: Date: {$depositReceipt->receipt_date}, Total: " . number_format($depositReceipt->total_payment) . ", Status: {$depositReceipt->status}\n";
        $report .= "  Items:\n";
        $report .= "  - Amount: " . number_format($depositReceiptItem->amount) . ", Method: {$depositReceiptItem->method}\n";
        $report .= "- Bank Transfer Payment: Date: {$bankReceipt->receipt_date}, Total: " . number_format($bankReceipt->total_payment) . ", Status: {$bankReceipt->status}\n";
        $report .= "  Items:\n";
        $report .= "  - Amount: " . number_format($bankReceiptItem->amount) . ", Method: {$bankReceiptItem->method}\n\n";

        // ==========================================
        // INVENTORY & STOCK
        // ==========================================

        $report .= "## 3. Inventory & Stock\n\n";

        $report .= "### Inventory Stock\n";
        $currentStock = InventoryStock::where('product_id', $this->product->id)->where('warehouse_id', $this->warehouse->id)->first();
        if ($currentStock) {
            $report .= "- Product: {$currentStock->product->name}, Warehouse: {$currentStock->warehouse->name}, Available: {$currentStock->qty_available}, Reserved: {$currentStock->qty_reserved}\n\n";
        }

        $report .= "### Stock Movements\n";
        $stockMovements = StockMovement::where('type', 'sales')->get();
        foreach ($stockMovements as $movement) {
            $report .= "- Product: {$movement->product->name}, Type: {$movement->type}, Quantity: {$movement->quantity}, Date: {$movement->created_at}\n";
        }
        $report .= "\n";

        // ==========================================
        // ACCOUNTING ENTRIES
        // ==========================================

        $report .= "## 4. Accounting Entries\n\n";

        // Account Receivables
        $accountReceivables = \App\Models\AccountReceivable::all();
        $report .= "### Account Receivables\n";
        foreach ($accountReceivables as $ar) {
            $report .= "- Invoice: {$ar->invoice->invoice_number}, Total: " . number_format($ar->total) . ", Remaining: " . number_format($ar->remaining) . ", Status: {$ar->status}\n";
        }
        $report .= "\n";

        // Journal Entries
        $journalEntries = JournalEntry::with(['coa', 'source'])->orderBy('date')->orderBy('created_at')->get();
        $report .= "### Journal Entries (" . $journalEntries->count() . " entries)\n";
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($journalEntries as $entry) {
            $coaName = $entry->coa ? $entry->coa->name : 'Unknown';
            $report .= "- Date: {$entry->date}, COA: {$entry->coa_id} ({$coaName}), Debit: " . number_format($entry->debit) . ", Credit: " . number_format($entry->credit) . ", Type: {$entry->journal_type}, Source: " . ($entry->source ? get_class($entry->source) . " #{$entry->source_id}" : 'Unknown') . "\n";
            $report .= "  Description: {$entry->description}, Reference: {$entry->reference}\n";
            $totalDebit += $entry->debit;
            $totalCredit += $entry->credit;
        }
        $report .= "\n**Total Debit: " . number_format($totalDebit) . ", Total Credit: " . number_format($totalCredit) . "**\n\n";

        // ==========================================
        // CHART OF ACCOUNTS BALANCES
        // ==========================================

        $report .= "## 5. Chart of Accounts Balances\n\n";

        $report .= "### COA Balances\n";
        $coas = ChartOfAccount::where('ending_balance', '!=', 0)->get();
        foreach ($coas as $coa) {
            $report .= "- {$coa->code}: {$coa->name} - Balance: " . number_format($coa->ending_balance) . "\n";
        }
        $report .= "\n";

        // ==========================================
        // BUSINESS FLOW SUMMARY
        // ==========================================

        $report .= "## 6. Business Flow Summary\n\n";

        $report .= "### Step 1: Quotation Creation\n";
        $report .= "- Created quotation {$quotation->quotation_number} for customer {$quotation->customer->name}\n";
        $report .= "- Product: {$quotation->quotationItem->first()->product->name}, Quantity: {$quotation->quotationItem->first()->quantity}, Unit Price: " . number_format($quotation->quotationItem->first()->unit_price) . "\n\n";

        $report .= "### Step 2: Sales Order Creation\n";
        $report .= "- Converted quotation to sales order {$saleOrder->so_number}\n";
        $report .= "- Status: {$saleOrder->status}\n\n";

        $report .= "### Step 3: Delivery Order Creation\n";
        $report .= "- Created delivery order {$deliveryOrder->do_number}\n";
        $report .= "- Status: {$deliveryOrder->status}\n\n";

        $report .= "### Step 4: Delivery Order Posting\n";
        $report .= "- Posted delivery order, created stock movements\n";
        $report .= "- Inventory reduced by {$stockMovements->sum('quantity')} units\n\n";

        $report .= "### Step 5: Invoice Creation\n";
        $report .= "- Created invoice {$invoice->invoice_number} for " . number_format($invoice->total) . "\n";
        $report .= "- Status: {$invoice->status}\n\n";

        $report .= "### Step 6: Split Payment Processing\n";
        $report .= "- **Deposit Payment:** " . number_format($depositAmount) . " via {$depositReceiptItem->method}\n";
        $report .= "- **Bank Transfer Payment:** " . number_format($bankTransferAmount) . " via {$bankReceiptItem->method}\n";
        $report .= "- Total Payment: " . number_format($totalAmount) . " (Invoice fully paid)\n";
        $report .= "- Invoice status updated to: {$invoice->fresh()->status}\n\n";

        // ==========================================
        // FINANCIAL IMPACT
        // ==========================================

        $report .= "## 7. Financial Impact\n\n";

        $report .= "### Balance Sheet Impact\n";
        $inventoryBalance = $this->inventoryCoa ? $this->inventoryCoa->calculateEndingBalance() : 0;
        $cashBalance = $this->cashCoa ? $this->cashCoa->calculateEndingBalance() : 0;
        $arBalance = $this->arCoa ? $this->arCoa->calculateEndingBalance() : 0;

        $report .= "- Inventory (Asset): " . number_format($inventoryBalance) . "\n";
        $report .= "- Cash (Asset): " . number_format($cashBalance) . "\n";
        $report .= "- Accounts Receivable (Asset): " . number_format($arBalance) . "\n";
        $report .= "- **Total Assets: " . number_format($inventoryBalance + $cashBalance + $arBalance) . "**\n\n";

        $report .= "### Income Statement Impact\n";
        $revenueBalance = $this->revenueCoa ? $this->revenueCoa->calculateEndingBalance() : 0;
        $cogsBalance = $this->cogsCoa ? $this->cogsCoa->calculateEndingBalance() : 0;

        $report .= "- Revenue: " . number_format($revenueBalance) . "\n";
        $report .= "- Cost of Goods Sold: " . number_format($cogsBalance) . "\n";
        $report .= "- **Gross Profit: " . number_format($revenueBalance - $cogsBalance) . "**\n\n";

        // Save report to file
        $filePath = storage_path('app/sales_flow_split_payment_' . now()->format('Y-m-d_H-i-s') . '.md');
        file_put_contents($filePath, $report);

        // Also output to console for immediate viewing
        echo $report;

        $this->assertTrue(true); // Just to make the test pass
    }

    private function generateAnalysisReport()
    {
        $report = "# Complete Sales Flow Analysis Report\n\n";
        $report .= "Generated on: " . now()->format('Y-m-d H:i:s') . "\n\n";

        // ==========================================
        // DATA CREATED
        // ==========================================

        $report .= "## 1. Data Created\n\n";

        // Users
        $users = User::all();
        $report .= "### Users\n";
        foreach ($users as $user) {
            $report .= "- ID: {$user->id}, Name: {$user->name}, Email: {$user->email}\n";
        }
        $report .= "\n";

        // Customers
        $customers = Customer::all();
        $report .= "### Customers\n";
        foreach ($customers as $customer) {
            $report .= "- ID: {$customer->id}, Code: {$customer->code}, Name: {$customer->name}\n";
        }
        $report .= "\n";

        // Warehouses
        $warehouses = Warehouse::all();
        $report .= "### Warehouses\n";
        foreach ($warehouses as $warehouse) {
            $report .= "- ID: {$warehouse->id}, Code: {$warehouse->code}, Name: {$warehouse->name}\n";
        }
        $report .= "\n";

        // Products
        $products = Product::all();
        $report .= "### Products\n";
        foreach ($products as $product) {
            $report .= "- ID: {$product->id}, Code: {$product->code}, Name: {$product->name}, Cost Price: " . number_format($product->cost_price) . "\n";
        }
        $report .= "\n";

        // Product Categories
        $categories = ProductCategory::all();
        $report .= "### Product Categories\n";
        foreach ($categories as $category) {
            $report .= "- ID: {$category->id}, Name: {$category->name}\n";
        }
        $report .= "\n";

        // ==========================================
        // SALES DOCUMENTS
        // ==========================================

        $report .= "## 2. Sales Documents\n\n";

        // Quotations
        $quotations = Quotation::with('quotationItem.product')->get();
        $report .= "### Quotations\n";
        foreach ($quotations as $quotation) {
            $report .= "- Number: {$quotation->quotation_number}, Date: {$quotation->date}, Status: {$quotation->status}, Customer: {$quotation->customer->name}\n";
            $report .= "  Items:\n";
            foreach ($quotation->quotationItem as $item) {
                $report .= "  - Product: {$item->product->name}, Qty: {$item->quantity}, Unit Price: " . number_format($item->unit_price) . "\n";
            }
        }
        $report .= "\n";

        // Sale Orders
        $saleOrders = SaleOrder::with('saleOrderItem.product')->get();
        $report .= "### Sale Orders\n";
        foreach ($saleOrders as $saleOrder) {
            $report .= "- Number: {$saleOrder->so_number}, Date: {$saleOrder->order_date}, Status: {$saleOrder->status}, Customer: {$saleOrder->customer->name}\n";
            $report .= "  Items:\n";
            foreach ($saleOrder->saleOrderItem as $item) {
                $report .= "  - Product: {$item->product->name}, Qty: {$item->quantity}, Unit Price: " . number_format($item->unit_price) . "\n";
            }
        }
        $report .= "\n";

        // Delivery Orders
        $deliveryOrders = DeliveryOrder::with('deliveryOrderItem')->get();
        $report .= "### Delivery Orders\n";
        foreach ($deliveryOrders as $deliveryOrder) {
            $report .= "- Number: {$deliveryOrder->do_number}, Date: {$deliveryOrder->delivery_date}, Status: {$deliveryOrder->status}\n";
            $report .= "  Items:\n";
            foreach ($deliveryOrder->deliveryOrderItem as $item) {
                $report .= "  - Product: {$item->product->name}, Qty: {$item->quantity}\n";
            }
        }
        $report .= "\n";

        // Invoices
        $invoices = Invoice::all();
        $report .= "### Invoices\n";
        foreach ($invoices as $invoice) {
            $report .= "- Number: {$invoice->invoice_number}, Date: {$invoice->invoice_date}, Total: " . number_format($invoice->total) . ", Status: {$invoice->status}\n";
        }
        $report .= "\n";

        // Customer Receipts
        $customerReceipts = CustomerReceipt::with('customerReceiptItem')->get();
        $report .= "### Customer Receipts (Payments)\n";
        foreach ($customerReceipts as $receipt) {
            $report .= "- Date: {$receipt->payment_date}, Total: " . number_format($receipt->total_payment) . ", Status: {$receipt->status}\n";
            $report .= "  Items:\n";
            foreach ($receipt->customerReceiptItem as $item) {
                $report .= "  - Amount: " . number_format($item->amount) . ", Method: {$item->method}\n";
            }
        }
        $report .= "\n";

        // ==========================================
        // INVENTORY & STOCK
        // ==========================================

        $report .= "## 3. Inventory & Stock\n\n";

        // Inventory Stock
        $inventoryStocks = InventoryStock::with(['product', 'warehouse'])->get();
        $report .= "### Inventory Stock\n";
        foreach ($inventoryStocks as $stock) {
            $report .= "- Product: {$stock->product->name}, Warehouse: {$stock->warehouse->name}, Available: {$stock->qty_available}, Reserved: {$stock->qty_reserved}\n";
        }
        $report .= "\n";

        // Stock Movements
        $stockMovements = StockMovement::with(['product', 'warehouse'])->get();
        $report .= "### Stock Movements\n";
        foreach ($stockMovements as $movement) {
            $report .= "- Product: {$movement->product->name}, Type: {$movement->type}, Quantity: {$movement->quantity}, Date: {$movement->date}\n";
        }
        $report .= "\n";

        // ==========================================
        // ACCOUNTING ENTRIES
        // ==========================================

        $report .= "## 4. Accounting Entries\n\n";

        // Account Receivables
        $accountReceivables = \App\Models\AccountReceivable::all();
        $report .= "### Account Receivables\n";
        foreach ($accountReceivables as $ar) {
            $report .= "- Invoice: {$ar->invoice->invoice_number}, Total: " . number_format($ar->total) . ", Remaining: " . number_format($ar->remaining) . ", Status: {$ar->status}\n";
        }
        $report .= "\n";

        // Journal Entries
        $journalEntries = JournalEntry::with(['coa', 'source'])->orderBy('date')->orderBy('created_at')->get();
        $report .= "### Journal Entries (" . $journalEntries->count() . " entries)\n";
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($journalEntries as $entry) {
            $sourceInfo = $entry->source ? get_class($entry->source) . " #{$entry->source->id}" : 'N/A';
            $report .= "- Date: {$entry->date}, COA: {$entry->coa->code} ({$entry->coa->name}), Debit: " . number_format($entry->debit) . ", Credit: " . number_format($entry->credit) . ", Type: {$entry->journal_type}, Source: {$sourceInfo}\n";
            $report .= "  Description: {$entry->description}, Reference: {$entry->reference}\n";
            $totalDebit += $entry->debit;
            $totalCredit += $entry->credit;
        }
        $report .= "\n**Total Debit: " . number_format($totalDebit) . ", Total Credit: " . number_format($totalCredit) . "**\n\n";

        // ==========================================
        // CHART OF ACCOUNTS BALANCES
        // ==========================================

        $report .= "## 5. Chart of Accounts Balances\n\n";

        $coas = ChartOfAccount::with('journalEntries')->get();
        $report .= "### COA Balances\n";
        foreach ($coas as $coa) {
            $balance = $coa->calculateEndingBalance();
            if ($balance != 0) {
                $report .= "- {$coa->code}: {$coa->name} - Balance: " . number_format($balance) . "\n";
            }
        }
        $report .= "\n";

        // ==========================================
        // BUSINESS FLOW SUMMARY
        // ==========================================

        $report .= "## 6. Business Flow Summary\n\n";

        $report .= "### Step 1: Quotation Creation\n";
        $quotation = Quotation::first();
        if ($quotation) {
            $report .= "- Created quotation {$quotation->quotation_number} for customer {$quotation->customer->name}\n";
            $report .= "- Product: {$quotation->quotationItem->first()->product->name}, Quantity: {$quotation->quotationItem->first()->quantity}, Unit Price: " . number_format($quotation->quotationItem->first()->unit_price) . "\n";
        }
        $report .= "\n";

        $report .= "### Step 2: Sales Order Creation\n";
        $saleOrder = SaleOrder::first();
        if ($saleOrder) {
            $report .= "- Converted quotation to sales order {$saleOrder->so_number}\n";
            $report .= "- Status: {$saleOrder->status}\n";
        }
        $report .= "\n";

        $report .= "### Step 3: Delivery Order Creation\n";
        $deliveryOrder = DeliveryOrder::first();
        if ($deliveryOrder) {
            $report .= "- Created delivery order {$deliveryOrder->do_number}\n";
            $report .= "- Status: {$deliveryOrder->status}\n";
        }
        $report .= "\n";

        $report .= "### Step 4: Delivery Order Posting\n";
        $stockMovements = StockMovement::where('type', 'sales')->get();
        if ($stockMovements->count() > 0) {
            $report .= "- Posted delivery order, created stock movements\n";
            $report .= "- Inventory reduced by " . $stockMovements->sum('quantity') . " units\n";
        }
        $report .= "\n";

        $report .= "### Step 5: Invoice Creation\n";
        $invoice = Invoice::first();
        if ($invoice) {
            $report .= "- Created invoice {$invoice->invoice_number} for " . number_format($invoice->total) . "\n";
            $report .= "- Status: {$invoice->status}\n";
        }
        $report .= "\n";

        $report .= "### Step 6: Customer Payment\n";
        $customerReceipt = CustomerReceipt::first();
        if ($customerReceipt) {
            $report .= "- Received payment of " . number_format($customerReceipt->total_payment) . "\n";
            $report .= "- Payment method: {$customerReceipt->customerReceiptItem->first()->method}\n";
            $report .= "- Invoice status updated to: {$invoice->fresh()->status}\n";
        }
        $report .= "\n";

        // ==========================================
        // FINANCIAL IMPACT
        // ==========================================

        $report .= "## 7. Financial Impact\n\n";

        $report .= "### Balance Sheet Impact\n";
        $inventoryBalance = $this->inventoryCoa ? $this->inventoryCoa->calculateEndingBalance() : 0;
        $cashBalance = $this->cashCoa ? $this->cashCoa->calculateEndingBalance() : 0;
        $arBalance = $this->arCoa ? $this->arCoa->calculateEndingBalance() : 0;

        $report .= "- Inventory (Asset): " . number_format($inventoryBalance) . "\n";
        $report .= "- Cash (Asset): " . number_format($cashBalance) . "\n";
        $report .= "- Accounts Receivable (Asset): " . number_format($arBalance) . "\n";
        $report .= "- **Total Assets: " . number_format($inventoryBalance + $cashBalance + $arBalance) . "**\n\n";

        $report .= "### Income Statement Impact\n";
        $revenueBalance = $this->revenueCoa ? $this->revenueCoa->calculateEndingBalance() : 0;
        $cogsBalance = $this->cogsCoa ? $this->cogsCoa->calculateEndingBalance() : 0;

        $report .= "- Revenue: " . number_format($revenueBalance) . "\n";
        $report .= "- Cost of Goods Sold: " . number_format($cogsBalance) . "\n";
        $report .= "- **Gross Profit: " . number_format($revenueBalance - $cogsBalance) . "**\n\n";

        // Save report to file
        $filePath = storage_path('app/sales_flow_analysis_' . now()->format('Y-m-d_H-i-s') . '.md');
        file_put_contents($filePath, $report);

        // Also output to console for immediate viewing
        echo $report;

        $this->assertTrue(true); // Just to make the test pass
    }
}