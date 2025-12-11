<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\JournalEntry;
use App\Services\QualityControlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Database\Seeders\CabangSeeder;
use Database\Seeders\ChartOfAccountSeeder;

class QCTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Supplier $supplier;
    protected Warehouse $warehouse;
    protected Product $product;
    protected Currency $currency;

    protected function setUp(): void
    {
        parent::setUp();

        // Only seed what's needed
        $this->seed(\Database\Seeders\CabangSeeder::class);
        $this->seed(\Database\Seeders\ChartOfAccountSeeder::class);

        $this->user = User::factory()->create();
        $this->supplier = Supplier::factory()->create();
        $this->warehouse = Warehouse::factory()->create();
        $this->product = Product::factory()->create();
        $this->currency = Currency::factory()->create();

        // Set up product COA relationships
        $inventoryCoa = \App\Models\ChartOfAccount::where('code', '1140.01')->first();
        $unbilledPurchaseCoa = \App\Models\ChartOfAccount::where('code', '2100.10')->first();
        $temporaryProcurementCoa = \App\Models\ChartOfAccount::where('code', '1400.01')->first();

        if ($inventoryCoa) {
            $this->product->inventory_coa_id = $inventoryCoa->id;
        }
        if ($unbilledPurchaseCoa) {
            $this->product->unbilled_purchase_coa_id = $unbilledPurchaseCoa->id;
        }
        if ($temporaryProcurementCoa) {
            $this->product->temporary_procurement_coa_id = $temporaryProcurementCoa->id;
        }
        $this->product->save();

        $this->actingAs($this->user);
    }

    /** @test */
    public function test_qc_purchase_complete_creates_data()
    {
        // Seed required data
        $this->seed(CabangSeeder::class);
        $this->seed(ChartOfAccountSeeder::class);
        // Create purchase order and receipt item
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $this->supplier->id,
            'status' => 'approved',
            'warehouse_id' => $this->warehouse->id,
        ]);

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'product_id' => $this->product->id,
            'quantity' => 5,
            'unit_price' => 15000,
        ]);

        $purchaseReceipt = PurchaseReceipt::factory()->create([
            'purchase_order_id' => $purchaseOrder->id,
            'status' => 'completed',
            'currency_id' => $this->currency->id,
        ]);

        $receiptItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $purchaseReceipt->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 5,
            'qty_accepted' => 5,
            'qty_rejected' => 0,
            'warehouse_id' => $this->warehouse->id,
            'is_sent' => true,
        ]);

        // Create and complete QC
        $qualityControlService = app(QualityControlService::class);
        $qualityControl = $qualityControlService->createQCFromPurchaseReceiptItem($receiptItem, [
            'inspected_by' => $this->user->id,
        ]);

        $qualityControl->update(['notes' => 'All goods accepted', 'passed_quantity' => 5]);
        $qualityControlService->completeQualityControl($qualityControl->fresh(), [
            'item_condition' => 'good',
        ]);

        // Check stock movement
        $stockMovement = StockMovement::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        echo "=== STOCK MOVEMENT ===\n";
        if ($stockMovement) {
            echo "ID: {$stockMovement->id}\n";
            echo "Type: {$stockMovement->type}\n";
            echo "Quantity: {$stockMovement->quantity}\n";
            echo "Product ID: {$stockMovement->product_id}\n";
            echo "Warehouse ID: {$stockMovement->warehouse_id}\n";
            echo "From Model: {$stockMovement->from_model_type}::{$stockMovement->from_model_id}\n";
        } else {
            echo "No stock movement found!\n";
        }

        // Check inventory stock
        $inventoryStock = InventoryStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        echo "\n=== INVENTORY STOCK ===\n";
        if ($inventoryStock) {
            echo "ID: {$inventoryStock->id}\n";
            echo "Product ID: {$inventoryStock->product_id}\n";
            echo "Warehouse ID: {$inventoryStock->warehouse_id}\n";
            echo "Qty Available: {$inventoryStock->qty_available}\n";
            echo "Qty Reserved: {$inventoryStock->qty_reserved}\n";
        } else {
            echo "No inventory stock found!\n";
        }

        // Check journal entries
        $journalEntries = JournalEntry::where('source_type', 'App\\Models\\QualityControl')
            ->orWhere('source_type', 'App\\Models\\StockMovement')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        echo "\n=== JOURNAL ENTRIES ===\n";
        if ($journalEntries->count() > 0) {
            foreach ($journalEntries as $entry) {
                echo "ID: {$entry->id} | Date: {$entry->date} | Description: {$entry->description} | Debit: {$entry->debit} | Credit: {$entry->credit} | COA: {$entry->coa_id}\n";
            }
        } else {
            echo "No journal entries found!\n";
        }

        // Assertions
        $this->assertNotNull($stockMovement);
        $this->assertEquals('purchase_in', $stockMovement->type);
        $this->assertEquals(5.0, (float) $stockMovement->quantity);

        $this->assertNotNull($inventoryStock);
        $this->assertEquals(5.0, (float) $inventoryStock->qty_available);
    }
}