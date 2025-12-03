<?php

namespace App\Observers;

use App\Models\DeliveryOrder;
use App\Models\StockReservation;
use App\Models\SaleOrder;
use App\Services\ProductService;
use Illuminate\Support\Facades\Log;

class DeliveryOrderObserver
{
    protected ProductService $productService;

    public function __construct()
    {
        $this->productService = app(ProductService::class);
    }

    /**
     * Handle the DeliveryOrder "updated" event.
     */
    public function updated(DeliveryOrder $deliveryOrder): void
    {
        $originalStatus = $deliveryOrder->getOriginal('status');
        $newStatus = $deliveryOrder->status;

        // Jika status berubah ke 'approved', buat stock reservations
        if ($originalStatus !== 'approved' && $newStatus === 'approved') {
            $this->handleApprovedStatus($deliveryOrder);
        }

        // Jika status berubah ke 'sent', hapus stock reservations
        if ($originalStatus !== 'sent' && $newStatus === 'sent') {
            $this->handleSentStatus($deliveryOrder);
        }

        // Jika status berubah ke 'completed', update related sales orders to completed
        if ($originalStatus !== 'completed' && $newStatus === 'completed') {
            $this->handleCompletedStatus($deliveryOrder);
        }
    }

    /**
     * Handle when Delivery Order status becomes 'approved'
     * Move qty_available to qty_reserved by creating stock reservations
     */
    protected function handleApprovedStatus(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling approved status', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $quantity = max(0, $item->quantity ?? 0);
            if ($quantity <= 0) {
                continue;
            }

            // Buat stock reservation untuk memindahkan available ke reserved
            $warehouseId = $deliveryOrder->warehouse_id ?? $item->warehouse_id;
            if (!$warehouseId) {
                Log::error('DeliveryOrderObserver: warehouse_id is null for delivery order item', [
                    'delivery_order_id' => $deliveryOrder->id,
                    'item_id' => $item->id,
                    'product_id' => $item->product_id,
                ]);
                throw new \Exception('Warehouse ID is required for stock reservation');
            }
            StockReservation::create([
                'sale_order_id' => $item->saleOrderItem->sale_order_id ?? null,
                'product_id' => $item->product_id,
                'warehouse_id' => $warehouseId,
                'rak_id' => $item->rak_id,
                'quantity' => $quantity,
                'delivery_order_id' => $deliveryOrder->id,
            ]);

            // Note: Stock movement 'sales' will be created when status becomes 'completed'
        }
    }

    /**
     * Handle when Delivery Order status becomes 'sent'
     * Release qty_reserved by deleting stock reservations and create journal entries for goods delivery
     */
    protected function handleSentStatus(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling sent status', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // Load delivery order items with related data
        $deliveryOrder->load('deliveryOrderItem.product.inventoryCoa', 'deliveryOrderItem.product.goodsDeliveryCoa', 'salesOrders');

        $date = $deliveryOrder->delivery_date ?? now()->toDateString();

        // Debug: Check if COA exist in database
        Log::info('COA Count Check', [
            'total_coa_count' => \App\Models\ChartOfAccount::count(),
            'coa_1140_10_exists' => \App\Models\ChartOfAccount::where('code', '1140.10')->exists(),
            'coa_1140_20_exists' => \App\Models\ChartOfAccount::where('code', '1140.20')->exists(),
            'coa_1180_10_exists' => \App\Models\ChartOfAccount::where('code', '1180.10')->exists(),
        ]);

        // Build journal entries for cost-of-goods-sold (goods delivery) and inventory credit
        $defaultInventoryCoa = \App\Models\ChartOfAccount::whereIn('code', ['1140.10', '1140.01'])->first();
        $defaultGoodsDeliveryCoa = \App\Models\ChartOfAccount::whereIn('code', ['1140.20', '1180.10'])->first();

        Log::info('COA Query Results', [
            'defaultInventoryCoa' => $defaultInventoryCoa ? $defaultInventoryCoa->code : 'NULL',
            'defaultGoodsDeliveryCoa' => $defaultGoodsDeliveryCoa ? $defaultGoodsDeliveryCoa->code : 'NULL',
        ]);

        $debitTotals = [];
        $creditTotals = [];

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $qtyDelivered = max(0, $item->quantity ?? 0);
            if ($qtyDelivered <= 0) {
                continue;
            }

            $product = $item->product;
            $costPerUnit = $product?->cost_price ?? 0;
            if ($costPerUnit <= 0) {
                continue;
            }

            $lineAmount = round($qtyDelivered * $costPerUnit, 2);
            if ($lineAmount <= 0) {
                continue;
            }

            $inventoryCoa = $product?->inventoryCoa?->id ? $product->inventoryCoa : $defaultInventoryCoa;
            $goodsDeliveryCoa = $product?->goodsDeliveryCoa?->id ? $product->goodsDeliveryCoa : $defaultGoodsDeliveryCoa;

            // Debug right before assignment
            Log::info('Before COA Assignment', [
                'product_inventory_coa_exists' => !is_null($product?->inventoryCoa),
                'product_goods_delivery_coa_exists' => !is_null($product?->goodsDeliveryCoa),
                'default_inventory_coa_exists' => !is_null($defaultInventoryCoa),
                'default_goods_delivery_coa_exists' => !is_null($defaultGoodsDeliveryCoa),
                'product_inventory_coa_id' => $product?->inventory_coa_id,
                'product_goods_delivery_coa_id' => $product?->goods_delivery_coa_id,
            ]);

            // Debug COA resolution
            Log::info('COA Resolution Debug', [
                'product_id' => $product?->id,
                'product_inventory_coa' => $product?->inventoryCoa?->code,
                'product_goods_delivery_coa' => $product?->goodsDeliveryCoa?->code,
                'default_inventory_coa' => $defaultInventoryCoa?->code,
                'default_goods_delivery_coa' => $defaultGoodsDeliveryCoa?->code,
                'final_inventory_coa' => $inventoryCoa?->code,
                'final_goods_delivery_coa' => $goodsDeliveryCoa?->code,
            ]);

            if (!$inventoryCoa || !$goodsDeliveryCoa) {
                Log::warning('Skipping journal entry due to missing COA', [
                    'inventory_coa_null' => is_null($inventoryCoa),
                    'goods_delivery_coa_null' => is_null($goodsDeliveryCoa),
                ]);
                continue;
            }

            $debitTotals[$goodsDeliveryCoa->id]['coa'] = $goodsDeliveryCoa;
            $debitTotals[$goodsDeliveryCoa->id]['amount'] = ($debitTotals[$goodsDeliveryCoa->id]['amount'] ?? 0) + $lineAmount;

            $creditTotals[$inventoryCoa->id]['coa'] = $inventoryCoa;
            $creditTotals[$inventoryCoa->id]['amount'] = ($creditTotals[$inventoryCoa->id]['amount'] ?? 0) + $lineAmount;
        }

        // Create journal entries
        if (!empty($debitTotals) && !empty($creditTotals)) {
            foreach ($debitTotals as $debitData) {
                \App\Models\JournalEntry::create([
                    'coa_id' => $debitData['coa']->id,
                    'date' => $date,
                    'reference' => $deliveryOrder->do_number,
                    'description' => 'Goods Delivery - Cost of Goods Sold for ' . $deliveryOrder->do_number,
                    'debit' => round($debitData['amount'], 2),
                    'credit' => 0,
                    'journal_type' => 'sales',
                    'source_type' => \App\Models\DeliveryOrder::class,
                    'source_id' => $deliveryOrder->id,
                ]);
            }

            foreach ($creditTotals as $creditData) {
                \App\Models\JournalEntry::create([
                    'coa_id' => $creditData['coa']->id,
                    'date' => $date,
                    'reference' => $deliveryOrder->do_number,
                    'description' => 'Goods Delivery - Inventory Reduction for ' . $deliveryOrder->do_number,
                    'debit' => 0,
                    'credit' => round($creditData['amount'], 2),
                    'journal_type' => 'sales',
                    'source_type' => \App\Models\DeliveryOrder::class,
                    'source_id' => $deliveryOrder->id,
                ]);
            }
        }

        // Hapus stock reservations yang terkait dengan delivery order ini
        $reservations = StockReservation::where('delivery_order_id', $deliveryOrder->id)->get();

        foreach ($reservations as $reservation) {
            // Hapus reservation, yang akan trigger observer untuk mengembalikan qty_available
            $reservation->delete();
        }

        // Update delivered_quantity untuk semua sale order items yang terkait
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            if ($item->sale_order_item_id) {
                $saleOrderItem = $item->saleOrderItem;
                if ($saleOrderItem) {
                    // Hitung total delivered quantity dari semua delivery orders yang sudah sent/completed
                    $totalDelivered = $saleOrderItem->deliveryOrderItems()
                        ->whereHas('deliveryOrder', function ($query) {
                            $query->whereIn('status', ['sent', 'received', 'completed']);
                        })
                        ->sum('quantity');

                    $saleOrderItem->update([
                        'delivered_quantity' => $totalDelivered
                    ]);
                }
            }
        }
    }

    /**
     * Handle when Delivery Order status becomes 'completed'
     * Update all related sales orders to completed status and create stock movements
     */
    protected function handleCompletedStatus(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling completed status', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // Load delivery order items with related data for stock movements
        $deliveryOrder->load('deliveryOrderItem.product');

        $date = $deliveryOrder->delivery_date ?? now()->toDateString();

        // Create stock movements for physical inventory reduction
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $qtyDelivered = max(0, $item->quantity ?? 0);
            if ($qtyDelivered <= 0) {
                continue;
            }

            $product = $item->product;
            if (!$product) {
                continue;
            }

            // Skip if warehouse_id is null
            if (!$deliveryOrder->warehouse_id) {
                continue;
            }

            // Create sales stock movement to reduce physical inventory
            $productService = app(\App\Services\ProductService::class);
            $productService->createStockMovement(
                product_id: $product->id,
                warehouse_id: $deliveryOrder->warehouse_id,
                quantity: $qtyDelivered,
                type: 'sales',
                date: $date,
                notes: "Sales delivery for DO {$deliveryOrder->do_number}",
                rak_id: $item->rak_id,
                fromModel: $item,
                value: $product->cost_price * $qtyDelivered
            );
        }

        // Get all sales orders related to this delivery order
        $salesOrders = $deliveryOrder->salesOrders;

        foreach ($salesOrders as $saleOrder) {
            // Only update if not already completed
            if ($saleOrder->status !== 'completed') {
                Log::info('DeliveryOrderObserver: Updating sale order to completed', [
                    'sale_order_id' => $saleOrder->id,
                    'so_number' => $saleOrder->so_number,
                    'delivery_order_id' => $deliveryOrder->id,
                ]);

                // Update sale order status to completed
                $saleOrder->update([
                    'status' => 'completed',
                    'completed_at' => now()
                ]);
            }
        }

        // Update delivered_quantity untuk semua sale order items yang terkait
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            if ($item->sale_order_item_id) {
                $saleOrderItem = $item->saleOrderItem;
                if ($saleOrderItem) {
                    // Hitung total delivered quantity dari semua delivery orders yang sudah sent/completed
                    $totalDelivered = $saleOrderItem->deliveryOrderItems()
                        ->whereHas('deliveryOrder', function ($query) {
                            $query->whereIn('status', ['sent', 'received', 'completed']);
                        })
                        ->sum('quantity');

                    $saleOrderItem->update([
                        'delivered_quantity' => $totalDelivered
                    ]);
                }
            }
        }
    }
}