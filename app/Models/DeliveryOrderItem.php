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

    /** Status level item DO (lihat DeliveryOrderService::updateStatus & WarehouseConfirmationItem). */
    public const STATUS_LABELS = [
        'pending' => 'Menunggu',
        'requested' => 'Menunggu Konfirmasi Gudang',
        'confirmed' => 'Terkonfirmasi',
        'partial' => 'Sebagian',
        'rejected' => 'Ditolak',
        'sent' => 'Sedang Dikirim',
        'received' => 'Diterima',
    ];

    public const STATUS_COLORS = [
        'pending' => 'gray',
        'requested' => 'warning',
        'confirmed' => 'success',
        'partial' => 'info',
        'rejected' => 'danger',
        'sent' => 'primary',
        'received' => 'success',
    ];

    public static function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? ($status ? ucfirst(str_replace('_', ' ', $status)) : '-');
    }

    public static function statusColor(?string $status): string
    {
        return self::STATUS_COLORS[$status ?? ''] ?? 'gray';
    }
    protected $table = 'delivery_order_items';
    protected $fillable = [
        'delivery_order_id',
        'sale_order_item_id',
        'product_id',
        'quantity',
        'reason',
        'status',  // G-09: tracks item-level warehouse/delivery state
    ];

    public function deliveryOrder()
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id')->withDefault();
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id')->withDefault();
    }

    public function saleOrderItem()
    {
        return $this->belongsTo(SaleOrderItem::class, 'sale_order_item_id')->withDefault();
    }

    public function stockMovement()
    {
        return $this->morphOne(StockMovement::class, 'from_model')->withDefault();
    }

    public function warehouseSources()
    {
        return $this->hasMany(DeliveryOrderItemWarehouseSource::class, 'delivery_order_item_id');
    }

    protected static function booted()
    {
        static::updated(function ($deliveryOrderItem) {
            // Sync journal entries, stock movements, and delivered quantities when quantity changes
            if ($deliveryOrderItem->isDirty('quantity')) {
                $deliveryOrder = $deliveryOrderItem->deliveryOrder;

                // T2.1 (flag stock.ledger): edit kuantitas (mis. checker) saat DO masih Siap Kirim → reservasi ikut turun/naik.
                if ($deliveryOrder && $deliveryOrder->status === 'approved' && \App\Services\StockReservationLedger::enabled()) {
                    app(\App\Services\DeliveryOrderReservations::class)->sync($deliveryOrder, "Kuantitas item DO {$deliveryOrder->do_number} diubah");
                    app(\App\Services\SaleOrderReservationSynchronizer::class)->syncForDeliveryOrder($deliveryOrder, "Kuantitas item DO {$deliveryOrder->do_number} diubah");
                }

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
            // Cache delivered_quantity & status SO hanya berubah bila DO sudah berstatus terkirim.
            if ($deliveryOrderItem->sale_order_item_id) {
                $deliveryOrder = $deliveryOrderItem->deliveryOrder;
                if ($deliveryOrder && in_array($deliveryOrder->status, DeliveryOrder::DELIVERED_STATUSES, true)) {
                    app(\App\Services\SaleOrderDeliveryProgress::class)
                        ->syncForSaleOrderItems([$deliveryOrderItem->sale_order_item_id]);
                }
            }
        });

        static::deleted(function ($deliveryOrderItem) {
            // Item dilepas dari DO (mis. dihapus di halaman edit) -> hitung ulang progres SO.
            if ($deliveryOrderItem->sale_order_item_id) {
                app(\App\Services\SaleOrderDeliveryProgress::class)
                    ->syncForSaleOrderItems([$deliveryOrderItem->sale_order_item_id]);
            }
        });

        static::deleting(function ($deliveryOrderItem) {
            if ($deliveryOrderItem->isForceDeleting()) {
                $deliveryOrderItem->warehouseSources()->forceDelete();
            } else {
                $deliveryOrderItem->warehouseSources()->delete();
            }
        });

        static::restoring(function ($deliveryOrderItem) {
            $deliveryOrderItem->warehouseSources()->withTrashed()->restore();
        });

        static::saving(function ($deliveryOrderItem) {
            // Fallback linking: jika sale_order_item_id kosong tapi item DO terkait ke DO yang punya SO, kaitkan otomatis berdasarkan product_id
            if (! $deliveryOrderItem->sale_order_item_id && $deliveryOrderItem->delivery_order_id && $deliveryOrderItem->product_id) {
                $do = $deliveryOrderItem->deliveryOrder;
                if ($do) {
                    $soIds = $do->salesOrders()->pluck('sale_orders.id')->filter()->all();
                    if (!empty($soIds)) {
                        $matched = SaleOrderItem::whereIn('sale_order_id', $soIds)
                            ->where('product_id', $deliveryOrderItem->product_id)
                            ->first();
                        if ($matched) {
                            $deliveryOrderItem->sale_order_item_id = $matched->id;
                        }
                    }
                }
            }

            // Guard: kuantitas item DO tidak boleh melebihi sisa SO yang BELUM terikat DO lain
            // (terkirim ATAU masih diproses). Baris ini sendiri dikecualikan agar edit tidak menghitung dua kali.
            if (! $deliveryOrderItem->sale_order_item_id) {
                return;
            }

            $saleOrderItem = $deliveryOrderItem->saleOrderItem;
            if (! $saleOrderItem || ! $saleOrderItem->exists) {
                return;
            }

            $progress = app(\App\Services\SaleOrderDeliveryProgress::class)->forItems(
                [$saleOrderItem->id],
                null,
                $deliveryOrderItem->exists ? $deliveryOrderItem->id : null
            )[$saleOrderItem->id] ?? null;

            if (! $progress) {
                return;
            }

            $remainingQty = max(0.0, $progress['ordered'] - $progress['delivered'] - $progress['in_process']);

            if ((float) $deliveryOrderItem->quantity > $remainingQty + 0.0001) {
                throw new \Exception("Quantity ({$deliveryOrderItem->quantity}) melebihi sisa quantity yang tersedia ({$remainingQty}) untuk sales order item ini.");
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
            
            $inventoryCoa = $product?->resolveInventoryCoaOrDefault() ?: $defaultInventoryCoa;
            $goodsDeliveryCoa = $product?->resolveGoodsDeliveryCoaOrDefault() ?: $defaultGoodsDeliveryCoa;
            
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
        // Delete existing stock movements for all items in this delivery order (fire model events)
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $movements = StockMovement::where('from_model_type', DeliveryOrderItem::class)
                ->where('from_model_id', $item->id)
                ->get();
            foreach ($movements as $movement) {
                $movement->delete();
            }
        }
        
        // Recreate stock movements based on current quantities
        $productService = app(\App\Services\ProductService::class);
        $date = $deliveryOrder->delivery_date ?? now()->toDateString();
        
        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $quantity = max(0, $item->quantity ?? 0);
            if ($quantity <= 0) continue;
            
            $product = $item->product;
            if (!$product) continue;

            $sources = $item->warehouseSources;
            if ($sources->isNotEmpty()) {
                foreach ($sources as $source) {
                    $sourceQty = max(0, (float) ($source->quantity ?? 0));
                    if ($sourceQty <= 0 || !$source->warehouse_id) {
                        continue;
                    }

                    $productService->createStockMovement(
                        product_id: $product->id,
                        warehouse_id: $source->warehouse_id,
                        quantity: $sourceQty,
                        type: 'sales',
                        date: $date,
                        notes: "Sales delivery for DO {$deliveryOrder->do_number}",
                        rak_id: $source->rak_id,
                        fromModel: $item,
                        value: $product->cost_price * $sourceQty
                    );
                }

                continue;
            }

            if (!$deliveryOrder->warehouse_id) continue;

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
     * Sync delivered quantities & status SO — didelegasikan ke SaleOrderDeliveryProgress
     * (satu-satunya penulis cache delivered_quantity).
     */
    protected static function syncDeliveredQuantities(DeliveryOrder $deliveryOrder): void
    {
        app(\App\Services\SaleOrderDeliveryProgress::class)->syncForDeliveryOrder($deliveryOrder);
    }
}
