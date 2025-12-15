<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use App\Models\QualityControl;
use App\Models\SaleOrder;
use App\Models\SaleOrderItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Services\PurchaseReceiptService;
use App\Services\ProductService;
use App\Services\QualityControlService;
use App\Services\DeliveryOrderService;
use App\Models\ChartOfAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class StockMovementComprehensiveTest extends TestCase
{
    use RefreshDatabase;

    protected $product;
    protected $productService;
    protected $purchaseReceiptService;
    protected $qualityControlService;
    protected $deliveryOrderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productService = app(ProductService::class);
        $this->purchaseReceiptService = app(PurchaseReceiptService::class);
        $this->qualityControlService = app(QualityControlService::class);
        $this->deliveryOrderService = app(DeliveryOrderService::class);

        // Create required COA accounts for testing
        ChartOfAccount::factory()->create([
            'code' => '1140.10',
            'name' => 'Inventory Account',
            'type' => 'asset',
            'is_active' => true,
        ]);

        ChartOfAccount::factory()->create([
            'code' => '1140.01',
            'name' => 'Alternative Inventory Account',
            'type' => 'asset',
            'is_active' => true,
        ]);

        ChartOfAccount::factory()->create([
            'code' => '1180.01',
            'name' => 'Temporary Procurement',
            'type' => 'asset',
            'is_active' => true,
        ]);

        ChartOfAccount::factory()->create([
            'code' => '1400.01',
            'name' => 'Alternative Temporary Procurement',
            'type' => 'asset',
            'is_active' => true,
        ]);

        ChartOfAccount::factory()->create([
            'code' => '2100.10',
            'name' => 'Unbilled Purchase',
            'type' => 'liability',
            'is_active' => true,
        ]);

        ChartOfAccount::factory()->create([
            'code' => '2190.10',
            'name' => 'Alternative Unbilled Purchase',
            'type' => 'liability',
            'is_active' => true,
        ]);

        // Create test product
        $this->product = Product::factory()->create([
            'name' => 'Comprehensive Stock Test Product',
            'sku' => 'SKU-CSTP-' . time(),
            'cost_price' => 100.00,
            'sell_price' => 150.00,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_creates_comprehensive_stock_movement_test_data()
    {
        echo "\n=== CREATING COMPREHENSIVE STOCK MOVEMENT TEST DATA ===\n";

        // 1. STOCK IN FROM PURCHASE - Complete flow
        echo "\n--- STOCK IN FROM PURCHASE ---\n";

        $po = PurchaseOrder::factory()->create([
            'supplier_id' => 1,
            'po_number' => 'PO-STOCKIN-' . time(),
            'status' => 'approved',
            'warehouse_id' => 1,
            'tempo_hutang' => 30,
            'expected_date' => now()->addDays(7),
            'total_amount' => 5000.00,
            'note' => 'Test PO for stock in',
            'created_by' => 1,
        ]);
        echo "✓ Created PO: {$po->po_number}\n";

        $poItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $this->product->id,
            'quantity' => 50,
            'unit_price' => 100.00,
        ]);
        echo "✓ Created PO Item\n";

        $pr = PurchaseReceipt::factory()->create([
            'receipt_number' => 'PR-STOCKIN-' . time(),
            'purchase_order_id' => $po->id,
            'status' => 'completed',
        ]);
        echo "✓ Created PR: {$pr->receipt_number}\n";

        $prItem = PurchaseReceiptItem::factory()->create([
            'purchase_receipt_id' => $pr->id,
            'purchase_order_item_id' => $poItem->id,
            'product_id' => $this->product->id,
            'qty_received' => 50,
            'qty_accepted' => 50,
            'qty_rejected' => 0,
        ]);
        echo "✓ Created PR Item\n";

        $qc = QualityControl::factory()->create([
            'qc_number' => 'QC-STOCKIN-' . time(),
            'from_model_type' => PurchaseOrderItem::class,
            'from_model_id' => $poItem->id,
            'passed_quantity' => 30,
            'rejected_quantity' => 0,
            'status' => 0,
            'warehouse_id' => 1,
            'product_id' => $this->product->id,
            'rak_id' => 1,
        ]);
        echo "✓ Created QC: {$qc->qc_number}\n";

        // Complete QC to trigger stock movement creation
        $this->qualityControlService->completeQualityControl($qc, [
            'warehouse_id' => 1,
            'rak_id' => 1,
        ]);
        echo "✓ Completed QC and created stock movement\n";

        // 2. STOCK OUT FROM SALES - Complete flow
        echo "\n--- STOCK OUT FROM SALES ---\n";

        $so = SaleOrder::factory()->create([
            'so_number' => 'SO-STOCKOUT-' . time(),
            'customer_id' => 1,
            'status' => 'approved',
        ]);
        echo "✓ Created SO: {$so->so_number}\n";

        $soItem = SaleOrderItem::factory()->create([
            'sale_order_id' => $so->id,
            'product_id' => $this->product->id,
            'quantity' => 25,
            'unit_price' => 150.00,
            'warehouse_id' => 1,
            'rak_id' => 1,
        ]);
        echo "✓ Created SO Item\n";

        $do = DeliveryOrder::factory()->create([
            'do_number' => 'DO-STOCKOUT-' . time(),
            'warehouse_id' => 1,
            'status' => 'sent',
        ]);
        echo "✓ Created DO: {$do->do_number}\n";

        // Link DO to SO
        DB::table('delivery_sales_orders')->insert([
            'delivery_order_id' => $do->id,
            'sales_order_id' => $so->id,
        ]);

        $doItem = DeliveryOrderItem::factory()->create([
            'delivery_order_id' => $do->id,
            'sale_order_item_id' => $soItem->id,
            'product_id' => $this->product->id,
            'quantity' => 25,
        ]);
        echo "✓ Created DO Item\n";

        // Post delivery order to create stock movements
        $this->deliveryOrderService->postDeliveryOrder($do);
        echo "✓ Posted DO and created sales stock movement\n";
        echo "\n--- STOCK TRANSFER ---\n";

        $transfer = StockTransfer::factory()->create([
            'transfer_number' => 'ST-TEST-' . time(),
            'from_warehouse_id' => 1,
            'to_warehouse_id' => 2,
            'status' => 'completed',
        ]);
        echo "✓ Created Stock Transfer: {$transfer->transfer_number}\n";

        $transferItem = StockTransferItem::factory()->create([
            'stock_transfer_id' => $transfer->id,
            'product_id' => $this->product->id,
            'quantity' => 10,
            'from_rak_id' => 1,
            'to_rak_id' => 2,
        ]);
        echo "✓ Created Stock Transfer Item\n";

        // Create stock movements for transfer
        $this->productService->createStockMovement(
            $this->product->id, 1, 10, 'transfer_out', now(),
            'Stock transfer out from warehouse 1', 1, $transferItem, null,
            ['transfer_id' => $transfer->id]
        );
        $this->productService->createStockMovement(
            $this->product->id, 2, 10, 'transfer_in', now(),
            'Stock transfer in to warehouse 2', 2, $transferItem, null,
            ['transfer_id' => $transfer->id]
        );
        echo "✓ Created transfer stock movements\n";

        // 4. STOCK ADJUSTMENT - Manual creation
        echo "\n--- STOCK ADJUSTMENT ---\n";

        $adjustment = StockAdjustment::factory()->create([
            'adjustment_number' => 'SA-TEST-' . time(),
            'warehouse_id' => 1,
            'reason' => 'Test adjustment',
            'status' => 'approved',
        ]);
        echo "✓ Created Stock Adjustment: {$adjustment->adjustment_number}\n";

        $adjustmentItem = StockAdjustmentItem::factory()->create([
            'stock_adjustment_id' => $adjustment->id,
            'product_id' => $this->product->id,
            'rak_id' => 1,
        ]);
        echo "✓ Created Stock Adjustment Item\n";

        // Create stock movement for adjustment
        StockMovement::create([
            'product_id' => $this->product->id,
            'warehouse_id' => 1,
            'quantity' => 5,
            'value' => 0,
            'type' => 'adjustment_in',
            'date' => now(),
            'notes' => 'Stock adjustment increase',
            'rak_id' => 1,
            'from_model_type' => StockAdjustment::class,
            'from_model_id' => $adjustment->id,
            'meta' => ['adjustment_id' => $adjustment->id]
        ]);
        echo "✓ Created adjustment stock movement\n";

        echo "\n=== TEST DATA CREATION COMPLETED ===\n";
        echo "Product ID: {$this->product->id}\n";
        echo "Expected final stock: 30 (purchase) - 25 (sales) + 5 (adjustment) = 10\n";

        // Verify stock movements were created
        $stockMovements = StockMovement::where('product_id', $this->product->id)->get();
        echo "\n=== STOCK MOVEMENTS SUMMARY ===\n";
        foreach ($stockMovements as $movement) {
            echo "ID: {$movement->id}, Type: {$movement->type}, Qty: {$movement->quantity}, Warehouse: {$movement->warehouse_id}\n";
            echo "  Source: {$movement->from_model_type} - {$movement->from_model_id}\n";
        }

        // Verify final stock calculation
        $stockMovements = StockMovement::where('product_id', $this->product->id)->get();
        $finalStock = 0;
        foreach ($stockMovements as $movement) {
            if (in_array($movement->type, ['purchase_in', 'transfer_in', 'manufacture_in', 'adjustment_in'])) {
                $finalStock += $movement->quantity;
            } elseif (in_array($movement->type, ['sales', 'transfer_out', 'manufacture_out', 'adjustment_out'])) {
                $finalStock -= $movement->quantity;
            }
        }
        echo "\nFinal calculated stock: {$finalStock}\n";
        $this->assertEquals(10, $finalStock);

        // Test source information display
        echo "\n=== TESTING SOURCE INFORMATION ===\n";
        foreach ($stockMovements as $movement) {
            $sourceInfo = $this->getSourceInfo($movement);
            echo "Movement ID {$movement->id}: {$sourceInfo['type']} - {$sourceInfo['number']} (Link: {$sourceInfo['link']})\n";
        }
    }

    private function getSourceInfo($movement)
    {
        $sourceType = 'Unknown';
        $sourceNumber = 'N/A';
        $sourceLink = 'N/A';

        if ($movement->fromModel) {
            $model = $movement->fromModel;

            switch (get_class($model)) {
                case PurchaseReceiptItem::class:
                    $sourceType = 'Purchase Receipt';
                    $sourceNumber = $model->purchaseReceipt->receipt_number ?? 'N/A';
                    $sourceLink = route('filament.admin.resources.purchase-receipts.view', $model->purchaseReceipt->id);
                    break;
                case DeliveryOrderItem::class:
                    $sourceType = 'Delivery Order';
                    $sourceNumber = $model->deliveryOrder->do_number ?? 'N/A';
                    $sourceLink = route('filament.admin.resources.delivery-orders.view', $model->deliveryOrder->id);
                    break;
                case StockTransferItem::class:
                    $sourceType = 'Stock Transfer';
                    $sourceNumber = $model->stockTransfer->transfer_number ?? 'N/A';
                    $sourceLink = route('filament.admin.resources.stock-transfers.view', $model->stockTransfer->id);
                    break;
                case StockAdjustmentItem::class:
                    $sourceType = 'Stock Adjustment';
                    $sourceNumber = $model->stockAdjustment->adjustment_number ?? 'N/A';
                    $sourceLink = route('filament.admin.resources.stock-adjustments.view', $model->stockAdjustment->id);
                    break;
                default:
                    $sourceType = get_class($model);
                    $sourceNumber = $model->id ?? 'N/A';
                    $sourceLink = 'N/A';
            }
        }

        return [
            'type' => $sourceType,
            'number' => $sourceNumber,
            'link' => $sourceLink,
        ];
    }
}