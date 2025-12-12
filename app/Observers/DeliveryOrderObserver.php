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

        // Jika status sudah 'sent' dan ada perubahan quantity, update journal entries
        if ($newStatus === 'sent' && $this->hasQuantityChanges($deliveryOrder)) {
            $this->handleQuantityUpdateAfterSent($deliveryOrder);
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

        // Create journal entries for goods delivery
        $this->createJournalEntriesForDelivery($deliveryOrder);

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

    /**
     * Handle the DeliveryOrder "deleted" event.
     * Delete related journal entries when delivery order is soft deleted
     */
    public function deleted(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling deleted event', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // Delete all journal entries related to this delivery order
        $journalEntries = \App\Models\JournalEntry::where('source_type', \App\Models\DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->get();

        foreach ($journalEntries as $entry) {
            Log::info('DeliveryOrderObserver: Deleting journal entry', [
                'journal_entry_id' => $entry->id,
                'coa_code' => $entry->coa->code ?? 'unknown',
                'amount' => $entry->debit > 0 ? $entry->debit : $entry->credit,
            ]);
            $entry->delete();
        }

        // Delete related stock reservations
        $reservations = StockReservation::where('delivery_order_id', $deliveryOrder->id)->get();
        foreach ($reservations as $reservation) {
            $reservation->delete();
        }

        // Update delivered_quantity for related sale order items (set to 0 since DO is deleted)
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            if ($item->sale_order_item_id) {
                $saleOrderItem = $item->saleOrderItem;
                if ($saleOrderItem) {
                    // Recalculate total delivered quantity excluding this deleted delivery order
                    $totalDelivered = $saleOrderItem->deliveryOrderItems()
                        ->whereHas('deliveryOrder', function ($query) {
                            $query->whereIn('status', ['sent', 'received', 'completed']);
                        })
                        ->whereHas('deliveryOrder', function ($query) use ($deliveryOrder) {
                            $query->where('id', '!=', $deliveryOrder->id); // Exclude this deleted DO
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
     * Check if delivery order items have quantity changes
     */
    protected function hasQuantityChanges(DeliveryOrder $deliveryOrder): bool
    {
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $originalQuantity = $item->getOriginal('quantity');
            $currentQuantity = $item->quantity;

            if ($originalQuantity != $currentQuantity) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle quantity updates after delivery order status is 'sent'
     * Update journal entries to reflect new quantities
     */
    public function handleQuantityUpdateAfterSent(DeliveryOrder $deliveryOrder): void
    {
        Log::info('DeliveryOrderObserver: Handling quantity update after sent', [
            'delivery_order_id' => $deliveryOrder->id,
            'do_number' => $deliveryOrder->do_number,
        ]);

        // Delete existing journal entries for this delivery order
        $existingEntries = \App\Models\JournalEntry::where('source_type', \App\Models\DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->get();

        foreach ($existingEntries as $entry) {
            Log::info('DeliveryOrderObserver: Deleting old journal entry for quantity update', [
                'journal_entry_id' => $entry->id,
                'coa_code' => $entry->coa->code ?? 'unknown',
            ]);
            $entry->delete();
        }

        // Recreate journal entries with updated quantities
        $this->createJournalEntriesForDelivery($deliveryOrder);

        // Update delivered_quantity for related sale order items
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            if ($item->sale_order_item_id) {
                $saleOrderItem = $item->saleOrderItem;
                if ($saleOrderItem) {
                    // Recalculate total delivered quantity from all delivery orders that are sent/completed
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
     * Create journal entries for delivery order (extracted from handleSentStatus)
     */
    protected function createJournalEntriesForDelivery(DeliveryOrder $deliveryOrder): void
    {
        // Load delivery order items with related data
        $deliveryOrder->load('deliveryOrderItem.product.inventoryCoa', 'deliveryOrderItem.product.goodsDeliveryCoa');

        $date = $deliveryOrder->delivery_date ?? now()->toDateString();

        // Build journal entries for cost-of-goods-sold (goods delivery) and inventory credit
        $defaultInventoryCoa = \App\Models\ChartOfAccount::whereIn('code', ['1140.10', '1140.01'])->first();
        $defaultGoodsDeliveryCoa = \App\Models\ChartOfAccount::whereIn('code', ['1140.20', '1180.10'])->first();

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
    }
}