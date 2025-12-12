<?php

use App\Models\Cabang;
use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\JournalEntry;
use App\Models\SaleOrder;
use App\Models\DeliveryOrderItem;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOrderJournalIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed cabang data first
        $this->seed(\Database\Seeders\CabangSeeder::class);

        // Create required COAs for journal entries
        ChartOfAccount::create([
            'code' => '1140.10',
            'name' => 'Inventory',
            'type' => 'asset',
            'level' => 3,
            'is_active' => true,
        ]);

        ChartOfAccount::create([
            'code' => '1140.20',
            'name' => 'Cost of Goods Sold',
            'type' => 'expense',
            'level' => 3,
            'is_active' => true,
        ]);

        // Create a warehouse
        Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH001',
            'kode' => 'WH001',
            'cabang_id' => 1, // Now cabang_id 1 should exist
            'location' => 'Test Location',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function journal_entries_are_created_when_delivery_order_status_becomes_sent()
    {
        // Create required data
        $cabang = Cabang::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);

        // Create a product with COAs
        $inventoryCoa = ChartOfAccount::where('code', '1140.10')->first();
        $cogsCoa = ChartOfAccount::where('code', '1140.20')->first();

        $product = Product::factory()->create([
            'inventory_coa_id' => $inventoryCoa->id,
            'goods_delivery_coa_id' => $cogsCoa->id,
        ]);

        // Create a sale order with items
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'confirmed',
        ]);

        // Create sale order item
        $saleOrderItem = \App\Models\SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_price' => 100,
        ]);

        // Create delivery order from sale order
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'approved',
            'warehouse_id' => $warehouse->id,
        ]);

        // Create delivery order item
        DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $deliveryOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'sale_order_item_id' => $saleOrderItem->id,
        ]);

        // Initially no journal entries
        $this->assertEquals(0, JournalEntry::count());

        // Change status to 'sent' - this should create journal entries
        $deliveryOrder->update(['status' => 'sent']);

        // Journal entries should be created
        $this->assertGreaterThan(0, JournalEntry::count());

        // Check that journal entries are linked to the delivery order
        $journalEntries = JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->get();

        $this->assertGreaterThan(0, $journalEntries->count());

        // Check journal entry details
        foreach ($journalEntries as $entry) {
            $this->assertEquals('sales', $entry->journal_type);
            $this->assertTrue(strpos($entry->description, 'Goods Delivery') !== false);
            $this->assertEquals($deliveryOrder->do_number, $entry->reference);
        }
    }

    /** @test */
    public function journal_entries_are_updated_when_delivery_order_quantity_is_changed_after_sent()
    {
        // Create required data
        $cabang = Cabang::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);

        // Create a product with COAs
        $inventoryCoa = ChartOfAccount::where('code', '1140.10')->first();
        $cogsCoa = ChartOfAccount::where('code', '1140.20')->first();

        $product = Product::factory()->create([
            'inventory_coa_id' => $inventoryCoa->id,
            'goods_delivery_coa_id' => $cogsCoa->id,
        ]);

        // Create a sale order first
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'confirmed',
        ]);

        // Create sale order item
        $saleOrderItem = \App\Models\SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10000,
        ]);

        // Create delivery order
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'approved',
            'warehouse_id' => $warehouse->id,
        ]);

        // Create delivery order item
        $deliveryOrderItem = DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $deliveryOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'sale_order_item_id' => $saleOrderItem->id,
        ]);

        // Change status to 'sent' to create initial journal entries
        $deliveryOrder->update(['status' => 'sent']);

        $initialJournalCount = JournalEntry::count();
        $this->assertGreaterThan(0, $initialJournalCount);

        // Get initial journal entry amounts
        $initialDebitAmount = JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->where('debit', '>', 0)
            ->sum('debit');

        $initialCreditAmount = JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->where('credit', '>', 0)
            ->sum('credit');

        // Update quantity - this should update journal entries
        $deliveryOrderItem->update(['quantity' => 3]);

        // Manually trigger the observer since quantity changes on items don't automatically trigger delivery order observer
        $deliveryOrderObserver = app(\App\Observers\DeliveryOrderObserver::class);
        $deliveryOrderObserver->handleQuantityUpdateAfterSent($deliveryOrder);

        // Journal entries count should remain the same (entries are updated, not added)
        $this->assertEquals($initialJournalCount, JournalEntry::count());

        // Check that journal entry amounts have been updated
        $updatedDebitAmount = JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->where('debit', '>', 0)
            ->sum('debit');

        $updatedCreditAmount = JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->where('credit', '>', 0)
            ->sum('credit');

        // Amounts should be reduced (from 5 items to 3 items)
        $this->assertLessThan($initialDebitAmount, $updatedDebitAmount);
        $this->assertLessThan($initialCreditAmount, $updatedCreditAmount);
    }

    /** @test */
    public function journal_entries_are_deleted_when_delivery_order_is_soft_deleted()
    {
        // Create required data
        $cabang = Cabang::factory()->create();
        $customer = Customer::factory()->create(['cabang_id' => $cabang->id]);
        $warehouse = Warehouse::factory()->create(['cabang_id' => $cabang->id]);

        // Create a product with COAs
        $inventoryCoa = ChartOfAccount::where('code', '1140.10')->first();
        $cogsCoa = ChartOfAccount::where('code', '1140.20')->first();

        $product = Product::factory()->create([
            'inventory_coa_id' => $inventoryCoa->id,
            'goods_delivery_coa_id' => $cogsCoa->id,
        ]);

        // Create a sale order first
        $saleOrder = SaleOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'confirmed',
        ]);

        // Create sale order item
        $saleOrderItem = \App\Models\SaleOrderItem::factory()->create([
            'sale_order_id' => $saleOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 10000,
        ]);

        // Create delivery order
        $deliveryOrder = DeliveryOrder::factory()->create([
            'status' => 'approved',
            'warehouse_id' => $warehouse->id,
        ]);

        // Create delivery order item
        DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $deliveryOrder->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'sale_order_item_id' => $saleOrderItem->id,
        ]);

        // Change status to 'sent' to create journal entries
        $deliveryOrder->update(['status' => 'sent']);

        $initialJournalCount = JournalEntry::count();
        $this->assertGreaterThan(0, $initialJournalCount);

        // Soft delete the delivery order
        $deliveryOrder->delete();

        // Journal entries should be deleted
        $this->assertEquals(0, JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->count());

        // Total journal entries should be reduced
        $this->assertLessThan($initialJournalCount, JournalEntry::count());
    }
}