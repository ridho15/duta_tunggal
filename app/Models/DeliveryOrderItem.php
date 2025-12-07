<?php

namespace App\Models;

use App\Traits\LogsGlobalActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class DeliveryOrderItem extends Model
{
    use SoftDeletes, HasFactory,LogsGlobalActivity;
    protected $table = 'delivery_order_items';
    protected $fillable = [
        'delivery_order_id',
        'purchase_receipt_item_id',
        'sale_order_item_id',
        'product_id',
        'quantity',
        'reason'
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function purchaseReceiptItem()
    {
        return $this->belongsTo(PurchaseReceiptItem::class, 'purchase_receipt_item_id')->withDefault();
    }

    public function saleOrderItem()
    {
        return $this->belongsTo(SaleOrderItem::class, 'sale_order_item_id')->withDefault();
    }

    public function stockMovement()
    {
        return $this->morphOne(StockMovement::class, 'from_model')->withDefault();
    }

    protected static function booted()
    {
        static::updated(function ($deliveryOrderItem) {
            // Sync journal entries, stock movements, and delivered quantities when quantity changes
            if ($deliveryOrderItem->isDirty('quantity')) {
                $deliveryOrder = $deliveryOrderItem->deliveryOrder;
                if ($deliveryOrder && in_array($deliveryOrder->status, ['sent', 'received', 'completed'])) {
                    // Sync journal entries if they exist
                    self::syncJournalEntries($deliveryOrder);
                    
                    // Sync stock movements if they exist
                    self::syncStockMovements($deliveryOrder);
                    
                    // Update delivered_quantity for sale order items
                    self::syncDeliveredQuantities($deliveryOrder);
                }
            }
        });

        static::created(function ($deliveryOrderItem) {
            // Update delivered_quantity when delivery order item is created
            if ($deliveryOrderItem->sale_order_item_id) {
                $saleOrderItem = $deliveryOrderItem->saleOrderItem;
                if ($saleOrderItem) {
                    // Only update if delivery order is in final status
                    $deliveryOrder = $deliveryOrderItem->deliveryOrder;
                    if ($deliveryOrder && in_array($deliveryOrder->status, ['sent', 'received', 'completed'])) {
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
        });

        static::saving(function ($deliveryOrderItem) {
            // Validate quantity doesn't exceed remaining quantity from sales order
            if ($deliveryOrderItem->sale_order_item_id) {
                $saleOrderItem = $deliveryOrderItem->saleOrderItem;
                $currentDeliveredQty = $saleOrderItem->deliveryOrderItems()
                    ->where('id', '!=', $deliveryOrderItem->id) // Exclude current item if updating
                    ->whereHas('deliveryOrder', function ($query) {
                        $query->whereIn('status', ['sent', 'received', 'completed']); // Only count actually delivered orders
                    })
                    ->sum('quantity');

                $remainingQty = $saleOrderItem->quantity - $currentDeliveredQty;

                if ($deliveryOrderItem->quantity > $remainingQty) {
                    throw new \Exception("Quantity ({$deliveryOrderItem->quantity}) melebihi sisa quantity yang tersedia ({$remainingQty}) untuk sales order item ini.");
                }
            }
        });
    }

    /**
     * Sync journal entries when delivery order item quantity changes
     */
    protected static function syncJournalEntries(DeliveryOrder $deliveryOrder): void
    {
        // Delete existing journal entries
        $deliveryOrder->journalEntries()->delete();
        
        // Recreate journal entries based on current quantities
        $deliveryOrder->load('deliveryOrderItem.product.inventoryCoa', 'deliveryOrderItem.product.goodsDeliveryCoa');
        
        $date = $deliveryOrder->delivery_date ?? now()->toDateString();
        
        // Get default COAs
        $defaultInventoryCoa = \App\Models\ChartOfAccount::whereIn('code', ['1140.10', '1140.01'])->first();
        $defaultGoodsDeliveryCoa = \App\Models\ChartOfAccount::whereIn('code', ['1140.20', '1180.10'])->first();
        
        $debitTotals = [];
        $creditTotals = [];
        
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $qtyDelivered = max(0, $item->quantity ?? 0);
            if ($qtyDelivered <= 0) continue;
            
            $product = $item->product;
            $costPerUnit = $product?->cost_price ?? 0;
            if ($costPerUnit <= 0) continue;
            
            $lineAmount = round($qtyDelivered * $costPerUnit, 2);
            if ($lineAmount <= 0) continue;
            
            $inventoryCoa = $product?->inventoryCoa ?: $defaultInventoryCoa;
            $goodsDeliveryCoa = $product?->goodsDeliveryCoa ?: $defaultGoodsDeliveryCoa;
            
            if (!$inventoryCoa || !$goodsDeliveryCoa) continue;
            
            $debitTotals[$goodsDeliveryCoa->id]['coa'] = $goodsDeliveryCoa;
            $debitTotals[$goodsDeliveryCoa->id]['amount'] = ($debitTotals[$goodsDeliveryCoa->id]['amount'] ?? 0) + $lineAmount;
            
            $creditTotals[$inventoryCoa->id]['coa'] = $inventoryCoa;
            $creditTotals[$inventoryCoa->id]['amount'] = ($creditTotals[$inventoryCoa->id]['amount'] ?? 0) + $lineAmount;
        }
        
        // Create new journal entries
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

    /**
     * Sync stock movements when delivery order item quantity changes
     */
    protected static function syncStockMovements(DeliveryOrder $deliveryOrder): void
    {
        // Delete existing stock movements for all items in this delivery order
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $item->stockMovement()->delete();
        }
        
        // Recreate stock movements based on current quantities
        $productService = app(\App\Services\ProductService::class);
        $date = $deliveryOrder->delivery_date ?? now()->toDateString();
        
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $quantity = max(0, $item->quantity ?? 0);
            if ($quantity <= 0) continue;
            
            $product = $item->product;
            if (!$product || !$deliveryOrder->warehouse_id) continue;
            
            $productService->createStockMovement(
                product_id: $product->id,
                warehouse_id: $deliveryOrder->warehouse_id,
                quantity: $quantity,
                type: 'sales',
                date: $date,
                notes: "Sales delivery for DO {$deliveryOrder->do_number}",
                rak_id: $item->rak_id,
                fromModel: $item,
                value: $product->cost_price * $quantity
            );
        }
    }

    /**
     * Sync delivered quantities for sale order items
     */
    protected static function syncDeliveredQuantities(DeliveryOrder $deliveryOrder): void
    {
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            if ($item->sale_order_item_id) {
                $saleOrderItem = $item->saleOrderItem;
                if ($saleOrderItem) {
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
