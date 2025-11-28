<?php

namespace Tests\Unit;

use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\InventoryStock;
use App\Services\BalanceSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InventoryFinanceImpactTest extends TestCase
{
    use RefreshDatabase;

    private function seedInventoryPrerequisites(array $additionalSeeders = []): void
    {
        $baseSeeders = [
            \Database\Seeders\ChartOfAccountSeeder::class,
            \Database\Seeders\ProductSeeder::class,
            \Database\Seeders\InventoryStockSeeder::class,
        ];

        $this->seed(array_merge($baseSeeders, $additionalSeeders));

        // Some seeders may leave journal entries populated; clear them so tests start from a predictable ledger state.
        JournalEntry::withTrashed()->forceDelete();
    }

    private function findAccountInBalanceSheet(array $balanceSheet, string $accountCode): ?object
    {
        $sections = [
            $balanceSheet['current_assets']['accounts'] ?? collect(),
            $balanceSheet['fixed_assets']['accounts'] ?? collect(),
            $balanceSheet['contra_assets']['accounts'] ?? collect(),
            $balanceSheet['current_liabilities']['accounts'] ?? collect(),
            $balanceSheet['long_term_liabilities']['accounts'] ?? collect(),
            $balanceSheet['equity']['accounts'] ?? collect(),
        ];

        foreach ($sections as $accounts) {
            $collection = $accounts instanceof \Illuminate\Support\Collection ? $accounts : collect($accounts);
            $account = $collection->firstWhere('kode', $accountCode);

            if ($account) {
                return $account;
            }
        }

        return null;
    }

    private function createOpeningInventoryBalance(): array
    {
        $inventoryCoa = ChartOfAccount::where('code', '1140')->first();
        $cashCoa = ChartOfAccount::where('code', '1111')->first();

        $this->assertNotNull($inventoryCoa, 'Inventory COA should exist');
        $this->assertNotNull($cashCoa, 'Cash COA should exist');

        $totalInventoryValue = Product::sum('cost_price') * 10; // Assume 10 units each

        JournalEntry::create([
            'coa_id' => $inventoryCoa->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'INV-OPEN-001',
            'description' => 'Opening Inventory Balance',
            'debit' => $totalInventoryValue,
            'credit' => 0,
            'journal_type' => 'opening_balance',
            'source_type' => 'manual',
            'source_id' => 1,
        ]);

        JournalEntry::create([
            'coa_id' => $cashCoa->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'INV-OPEN-001',
            'description' => 'Opening Inventory Balance - Cash Reduction',
            'debit' => 0,
            'credit' => $totalInventoryValue,
            'journal_type' => 'opening_balance',
            'source_type' => 'manual',
            'source_id' => 1,
        ]);

        return compact('inventoryCoa', 'cashCoa', 'totalInventoryValue');
    }

    #[Test]
    public function inventory_stock_does_not_directly_affect_balance_sheet_without_journal_entries()
    {
        $this->seedInventoryPrerequisites();

        // Get balance sheet before any transactions
        $balanceSheetService = app(BalanceSheetService::class);
        $balanceSheet = $balanceSheetService->generate();

        // Assert balance sheet is zero (no transactions)
        $this->assertEquals(0, $balanceSheet['total_assets']);
        $this->assertEquals(0, $balanceSheet['total_liabilities']);
        $this->assertEquals(0, $balanceSheet['total_equity']);
        $this->assertTrue($balanceSheet['is_balanced']);

        // Verify inventory stocks exist but don't affect balance sheet
        $inventoryCount = InventoryStock::count();
        $this->assertGreaterThan(0, $inventoryCount, 'Inventory stocks should exist');

        $productCount = Product::count();
        $this->assertGreaterThan(0, $productCount, 'Products should exist');
    }

    #[Test]
    public function inventory_opening_balance_affects_balance_sheet_when_journal_entries_created()
    {
        $this->seedInventoryPrerequisites();

        ['inventoryCoa' => $inventoryCoa, 'cashCoa' => $cashCoa, 'totalInventoryValue' => $totalInventoryValue] = $this->createOpeningInventoryBalance();

        $this->assertEquals(2, JournalEntry::count(), 'Should have 2 journal entries');

        // Get balance sheet after journal entries
        $balanceSheetService = app(BalanceSheetService::class);
        $balanceSheet = $balanceSheetService->generate();

    // Inventory should appear with the correct balance while total assets remain unchanged (cash decreased by the same amount)
    $inventoryAccount = $this->findAccountInBalanceSheet($balanceSheet, $inventoryCoa->code);
    $this->assertNotNull($inventoryAccount, 'Inventory should appear in current assets');
    $this->assertEqualsWithDelta($totalInventoryValue, (float) $inventoryAccount->balance, 0.01, 'Inventory balance should match journal entry');

    $cashAccount = $this->findAccountInBalanceSheet($balanceSheet, $cashCoa->code);
    $this->assertNotNull($cashAccount, 'Cash should appear in current assets');
    $this->assertEqualsWithDelta(-$totalInventoryValue, (float) $cashAccount->balance, 0.01, 'Cash should decrease by the same amount');

    $this->assertEquals(0, $balanceSheet['total_assets'], 'Total assets remain unchanged when moving between asset accounts');
    $this->assertEquals(0, $balanceSheet['total_liabilities'], 'No liabilities created');
    $this->assertEquals(0, $balanceSheet['total_equity'], 'No equity change from asset reclassification');
    $this->assertTrue($balanceSheet['is_balanced'], 'Balance sheet should still be balanced');
    }

    #[Test]
    public function purchase_transaction_affects_inventory_and_balance_sheet()
    {
        $this->seedInventoryPrerequisites();

        // Find relevant COAs
        $inventoryCoa = ChartOfAccount::where('code', '1140')->first();
        $cashCoa = ChartOfAccount::where('code', '1111')->first();
        $accountsPayableCoa = ChartOfAccount::where('code', '2110')->first();

        $this->assertNotNull($inventoryCoa);
        $this->assertNotNull($cashCoa);
        $this->assertNotNull($accountsPayableCoa);

        $purchaseAmount = 5000000;

        // Create purchase journal entries (inventory increase, accounts payable increase)
        JournalEntry::create([
            'coa_id' => $inventoryCoa->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'PO-001',
            'description' => 'Purchase Order - Inventory Increase',
            'debit' => $purchaseAmount,
            'credit' => 0,
            'journal_type' => 'purchase',
            'source_type' => 'purchase_order',
            'source_id' => 1,
        ]);

        JournalEntry::create([
            'coa_id' => $accountsPayableCoa->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'PO-001',
            'description' => 'Purchase Order - Accounts Payable Increase',
            'debit' => 0,
            'credit' => $purchaseAmount,
            'journal_type' => 'purchase',
            'source_type' => 'purchase_order',
            'source_id' => 1,
        ]);

        // Get balance sheet
        $balanceSheetService = app(BalanceSheetService::class);
        $balanceSheet = $balanceSheetService->generate();

        // Assert purchase affects balance sheet
        $this->assertEquals($purchaseAmount, $balanceSheet['total_assets'], 'Assets should equal purchase amount');
        $this->assertEquals($purchaseAmount, $balanceSheet['total_liabilities'], 'Liabilities should equal purchase amount');
        $this->assertEquals(0, $balanceSheet['total_equity'], 'Equity unchanged');
        $this->assertTrue($balanceSheet['is_balanced']);

        // Verify inventory and accounts payable in balance sheet
        $inventoryAccount = $this->findAccountInBalanceSheet($balanceSheet, $inventoryCoa->code);
        $this->assertNotNull($inventoryAccount);
        $this->assertEquals($purchaseAmount, (float) $inventoryAccount->balance);

        $payableAccount = $this->findAccountInBalanceSheet($balanceSheet, $accountsPayableCoa->code);
        $this->assertNotNull($payableAccount);
        $this->assertEquals($purchaseAmount, (float) $payableAccount->balance);
    }

    #[Test]
    public function sales_transaction_reduces_inventory_and_affects_balance_sheet()
    {
    $this->seedInventoryPrerequisites();

    ['inventoryCoa' => $inventoryCoa, 'cashCoa' => $cashCoa, 'totalInventoryValue' => $totalInventoryValue] = $this->createOpeningInventoryBalance();

    $salesRevenueCoa = ChartOfAccount::where('code', '4100.10')->first();
    $cogsCoa = ChartOfAccount::where('code', '5100.10')->first();

    $this->assertNotNull($salesRevenueCoa, 'Sales revenue COA should exist');
    $this->assertNotNull($cogsCoa, 'COGS COA should exist');

        $salesAmount = 1000000;
        $cogsAmount = 800000; // Cost of goods sold

        // Create sales journal entries
        // 1. Cash increase
        JournalEntry::create([
            'coa_id' => $cashCoa->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'INV-001',
            'description' => 'Sales Revenue',
            'debit' => $salesAmount,
            'credit' => 0,
            'journal_type' => 'sales',
            'source_type' => 'invoice',
            'source_id' => 1,
        ]);

        // 2. Sales revenue increase
        JournalEntry::create([
            'coa_id' => $salesRevenueCoa->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'INV-001',
            'description' => 'Sales Revenue',
            'debit' => 0,
            'credit' => $salesAmount,
            'journal_type' => 'sales',
            'source_type' => 'invoice',
            'source_id' => 1,
        ]);

        // 3. Cost of goods sold expense
        JournalEntry::create([
            'coa_id' => $cogsCoa->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'INV-001',
            'description' => 'Cost of Goods Sold Expense',
            'debit' => $cogsAmount,
            'credit' => 0,
            'journal_type' => 'sales',
            'source_type' => 'invoice',
            'source_id' => 1,
        ]);

        // 4. Inventory reduction (COGS)
        JournalEntry::create([
            'coa_id' => $inventoryCoa->id,
            'date' => now()->format('Y-m-d'),
            'reference' => 'INV-001',
            'description' => 'Cost of Goods Sold',
            'debit' => 0,
            'credit' => $cogsAmount,
            'journal_type' => 'sales',
            'source_type' => 'invoice',
            'source_id' => 1,
        ]);

        // Get balance sheet after sales
        $balanceSheetService = app(BalanceSheetService::class);
        $balanceSheet = $balanceSheetService->generate();

        // Assert sales affects balance sheet (profit reflected as retained earnings)
        $expectedProfit = $salesAmount - $cogsAmount;

    $this->assertEqualsWithDelta($expectedProfit, $balanceSheet['total_assets'], 0.01);
        $this->assertEquals(0, $balanceSheet['total_liabilities'], 'No liabilities');
    $this->assertEqualsWithDelta($expectedProfit, $balanceSheet['total_equity'], 0.01, 'Equity should increase from profit');
    $this->assertEqualsWithDelta($expectedProfit, $balanceSheet['retained_earnings'], 0.01);
        $this->assertTrue($balanceSheet['is_balanced']);

        // Verify inventory reduced by cogs and cash increased by sales receipt
        $inventoryAccount = $this->findAccountInBalanceSheet($balanceSheet, $inventoryCoa->code);
        $this->assertNotNull($inventoryAccount);
    $this->assertEqualsWithDelta($totalInventoryValue - $cogsAmount, (float) $inventoryAccount->balance, 0.01);

        $cashAccount = $this->findAccountInBalanceSheet($balanceSheet, $cashCoa->code);
        $this->assertNotNull($cashAccount);
    $this->assertEqualsWithDelta(-$totalInventoryValue + $salesAmount, (float) $cashAccount->balance, 0.01);
    }

    #[Test]
    public function purchase_receipt_item_sent_to_qc_creates_temporary_procurement_journal_entries()
    {
        $this->seedInventoryPrerequisites([
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\WarehouseSeeder::class,
            \Database\Seeders\CurrencySeeder::class,
        ]);

        // Create test data
        $user = \App\Models\User::first();
        $product = Product::first();
        $warehouse = \App\Models\Warehouse::first();
        $currency = \App\Models\Currency::first();

        // Create purchase order
        $purchaseOrder = \App\Models\PurchaseOrder::factory()->create([
            'po_number' => 'PO-QC-TEST-001',
            'supplier_id' => \App\Models\Supplier::factory()->create()->id,
            'order_date' => now(),
            'status' => 'approved',
        ]);

        $poItem = \App\Models\PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100000,
            'discount' => 0,
            'tax' => 0,
        ]);

        // Create purchase receipt
        $purchaseReceipt = \App\Models\PurchaseReceipt::factory()->create([
            'receipt_number' => 'RN-QC-TEST-001',
            'purchase_order_id' => $purchaseOrder->id,
            'receipt_date' => now(),
            'received_by' => $user->id,
            'status' => 'draft',
            'currency_id' => $currency->id,
            'other_cost' => 0,
        ]);

        $receiptItem = \App\Models\PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $product->id,
            'qty_received' => 10,
            'qty_accepted' => 10,
            'qty_rejected' => 0,
            'warehouse_id' => $warehouse->id,
            'is_sent' => false, // Initially not sent to QC
        ]);

        // Verify no journal entries exist for this receipt item initially
        $this->assertDatabaseMissing('journal_entries', [
            'source_type' => \App\Models\PurchaseReceiptItem::class,
            'source_id' => $receiptItem->id,
        ]);

        // Ensure product has temporary procurement COA configured
        $product->update([
            'temporary_procurement_coa_id' => ChartOfAccount::where('code', '1400.01')->first()->id
        ]);

        // Send item to QC using the service
        $purchaseReceiptService = app(\App\Services\PurchaseReceiptService::class);
        $journalResult = $purchaseReceiptService->createTemporaryProcurementEntriesForReceiptItem($receiptItem);

        // Verify journal entries were created
        $this->assertEquals('posted', $journalResult['status']);
        $this->assertCount(2, $journalResult['entries']); // Debit temporary procurement + Credit unbilled purchase

        // Update receipt item to mark as sent to QC (this is done in the UI)
        $receiptItem->update(['is_sent' => true]);

        // Verify journal entries for this receipt item
        $this->assertEquals(2, JournalEntry::where('source_type', \App\Models\PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->count());

        // Find debit entry (temporary procurement expense)
        $debitEntry = JournalEntry::where('description', 'like', '%Temporary Procurement%')
            ->where('debit', '>', 0)
            ->where('source_type', \App\Models\PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->first();

        $this->assertNotNull($debitEntry, 'Should have a debit entry for temporary procurement');
        $this->assertEquals(1000000.0, (float) $debitEntry->debit); // 10 * 100000
        $this->assertEquals(0.0, (float) $debitEntry->credit);
        $this->assertEquals('1400.01', $debitEntry->coa->code); // Temporary Procurement COA
        $this->assertEquals(\App\Models\PurchaseReceiptItem::class, $debitEntry->source_type);
        $this->assertEquals($receiptItem->id, $debitEntry->source_id);
        $this->assertStringStartsWith('Temporary Procurement - Item sent to QC', $debitEntry->description);

        // Find credit entry (unbilled purchase liability)
        $creditEntry = JournalEntry::where('description', 'like', '%Temporary Procurement%')
            ->where('credit', '>', 0)
            ->where('source_type', \App\Models\PurchaseReceiptItem::class)
            ->where('source_id', $receiptItem->id)
            ->first();

        $this->assertNotNull($creditEntry, 'Should have a credit entry for unbilled purchase');
        $this->assertEquals(0.0, (float) $creditEntry->debit);
        $this->assertEquals(1000000.0, (float) $creditEntry->credit); // 10 * 100000
        $this->assertEquals('2100.10', $creditEntry->coa->code); // Unbilled Purchase COA (liability)
        $this->assertEquals(\App\Models\PurchaseReceiptItem::class, $creditEntry->source_type);
        $this->assertEquals($receiptItem->id, $creditEntry->source_id);

        // Verify both entries reference the same transaction
    $this->assertEquals($debitEntry->transaction_id, $creditEntry->transaction_id);
    $transactionId = $debitEntry->transaction_id;

        // Verify journal entries are balanced
    $totalDebit = JournalEntry::where('transaction_id', $transactionId)->sum('debit');
    $totalCredit = JournalEntry::where('transaction_id', $transactionId)->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit, 'Journal entries should be balanced');

        // Verify receipt item is marked as sent to QC
        $receiptItem->refresh();
        $this->assertEquals(1, $receiptItem->is_sent, 'Receipt item should be marked as sent to QC');
    }
}