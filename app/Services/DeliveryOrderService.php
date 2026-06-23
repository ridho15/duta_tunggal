<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLog;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\StockReservation;
use App\Models\InventoryStock;
use App\Models\WarehouseConfirmation;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class DeliveryOrderService
{
    /**
     * Lightweight cache so repeated COA lookups by code stay fast.
     *
     * @var array<string, ?ChartOfAccount>
     */
    protected static array $coaCache = [];

    protected ProductService $productService;

    public function __construct()
    {
        $this->productService = app(ProductService::class);
    }

    public function updateStatus($deliveryOrder, $status, $comments = null, $action = null)
    {
        $deliveryOrder->update([
            'status' => $status
        ]);

        // G-09: sync DO item statuses whenever DO status changes
        $itemStatus = match ($status) {
            'request_stock'           => 'requested',
            'approved'                => 'confirmed',
            'reject', 'rejected'      => 'rejected',
            'partial'                 => 'partial',
            'sent'                    => 'sent',
            'received', 'completed'   => 'received',
            default                   => null,
        };
        if ($itemStatus !== null) {
            $deliveryOrder->deliveryOrderItem()->update(['status' => $itemStatus]);
        }

        $this->createLog(delivery_order_id: $deliveryOrder->id, status: $status, comments: $comments, action: $action);
    }

    public function createLog($delivery_order_id, $status, $comments = null, $action = null)
    {
        DeliveryOrderLog::create([
            'delivery_order_id' => $delivery_order_id,
            'status' => $status,
            'confirmed_by' => Auth::user()?->id ?? 13, // Fallback to user ID 13 if not authenticated
        ]);
    }

    public function updateQuantity() {}

    public function createWarehouseConfirmationsForDeliveryOrder(DeliveryOrder $deliveryOrder): array
    {
        $deliveryOrder->loadMissing('deliveryOrderItem.warehouseSources', 'deliveryOrderItem.product');

        $confirmations = [];

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $sources = $item->warehouseSources;

            if ($sources->isNotEmpty()) {
                foreach ($sources as $source) {
                    $sourceQty = max(0, (float) ($source->quantity ?? 0));

                    if (! $source->warehouse_id || $sourceQty <= 0) {
                        continue;
                    }

                    $confirmations[] = $this->createSingleWarehouseConfirmationForDeliveryOrder(
                        deliveryOrder: $deliveryOrder,
                        item: $item,
                        warehouseId: (int) $source->warehouse_id,
                        quantity: $sourceQty,
                        rakId: $source->rak_id,
                    );
                }

                continue;
            }

            $quantity = max(0, (float) ($item->quantity ?? 0));
            if (! $deliveryOrder->warehouse_id || $quantity <= 0) {
                continue;
            }

            $confirmations[] = $this->createSingleWarehouseConfirmationForDeliveryOrder(
                deliveryOrder: $deliveryOrder,
                item: $item,
                warehouseId: (int) $deliveryOrder->warehouse_id,
                quantity: $quantity,
                rakId: $item->rak_id,
            );
        }

        return array_values(array_filter($confirmations));
    }

    protected function createSingleWarehouseConfirmationForDeliveryOrder(
        DeliveryOrder $deliveryOrder,
        $item,
        int $warehouseId,
        float $quantity,
        $rakId = null,
    ): WarehouseConfirmation {
        $warehouseConfirmation = WarehouseConfirmation::create([
            'confirmable_type' => DeliveryOrder::class,
            'confirmable_id' => $deliveryOrder->id,
            'confirmation_type' => 'delivery_order',
            'status' => 'request',
            'note' => sprintf(
                'Auto-created dari DO %s | SO Item #%s | Gudang #%s',
                $deliveryOrder->do_number,
                $item->sale_order_item_id ?? '-',
                $warehouseId,
            ),
        ]);

        $warehouseConfirmation->warehouseConfirmationItems()->create([
            'sale_order_item_id' => $item->sale_order_item_id,
            'product_name' => $item->product->name ?? '-',
            'requested_qty' => $quantity,
            'confirmed_qty' => $quantity,
            'warehouse_id' => $warehouseId,
            'rak_id' => $rakId,
            'status' => 'request',
        ]);

        return $warehouseConfirmation;
    }

    public function generateDoNumber()
    {
        return static::generateStaticDoNumber();
    }

    /**
     * Static version so models / observers can call it without injecting the service.
     */
    public static function generateStaticDoNumber(): string
    {
        $date   = now()->format('Ymd');
        $prefix = 'DO-' . $date . '-';

        // Sequential approach: no infinite-loop risk, works across branches
        $max = DeliveryOrder::withoutGlobalScopes()
            ->where('do_number', 'like', $prefix . '%')
            ->max('do_number');

        $next = 1;
        if ($max !== null) {
            $suffix = substr((string) $max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validate that sufficient stock is available for delivery order items.
     * Checks if qty_available - qty_reserved >= requested quantity.
     */
    public function validateStockAvailability(DeliveryOrder $deliveryOrder): array
    {
        $errors = [];

        $deliveryOrder->loadMissing('deliveryOrderItem.product', 'deliveryOrderItem.warehouseSources');

        foreach ($deliveryOrder->deliveryOrderItem as $item) {
            $qtyRequested = max(0, $item->quantity ?? 0);
            if ($qtyRequested <= 0) {
                continue;
            }

            $product = $item->product;
            if (!$product) {
                $errors[] = "Product not found for delivery item";
                continue;
            }

            $sources = $item->warehouseSources;
            if ($sources->isNotEmpty()) {
                $totalSourceQty = (float) $sources->sum(function ($source) {
                    return (float) ($source->quantity ?? 0);
                });

                if (abs($totalSourceQty - $qtyRequested) > 0.0001) {
                    $errors[] = "Source quantity mismatch for product '{$product->name}'. Source total: {$totalSourceQty}, Item qty: {$qtyRequested}";
                    continue;
                }

                foreach ($sources as $source) {
                    $sourceWarehouseId = $source->warehouse_id;
                    $sourceQty = max(0, (float) ($source->quantity ?? 0));

                    if (!$sourceWarehouseId || $sourceQty <= 0) {
                        $errors[] = "Invalid warehouse source configuration for product '{$product->name}'";
                        continue;
                    }

                    $inventoryQuery = InventoryStock::where('product_id', $product->id)
                        ->where('warehouse_id', $sourceWarehouseId);

                    if (!empty($source->rak_id)) {
                        $inventoryQuery->where('rak_id', $source->rak_id);
                    }

                    $inventoryStock = $inventoryQuery->first();

                    if (!$inventoryStock) {
                        $errors[] = "No inventory stock found for product '{$product->name}' in selected source warehouse";
                        continue;
                    }

                    $availableForDelivery = $inventoryStock->qty_available - $inventoryStock->qty_reserved;

                    if ($availableForDelivery < $sourceQty) {
                        $errors[] = "Insufficient stock for product '{$product->name}' in source warehouse. " .
                            "Available: {$availableForDelivery}, Requested: {$sourceQty}";
                    }
                }

                continue;
            }

            // Skip validation if warehouse_id is null
            if (!$deliveryOrder->warehouse_id) {
                continue;
            }

            $inventoryStock = InventoryStock::where('product_id', $product->id)
                ->where('warehouse_id', $deliveryOrder->warehouse_id)
                ->first();

            if (!$inventoryStock) {
                $errors[] = "No inventory stock found for product '{$product->name}' in selected warehouse";
                continue;
            }

            $availableForDelivery = $inventoryStock->qty_available - $inventoryStock->qty_reserved;

            if ($availableForDelivery < $qtyRequested) {
                $errors[] = "Insufficient stock for product '{$product->name}'. " .
                    "Available: {$availableForDelivery}, Requested: {$qtyRequested}";
            }
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'errors' => $errors
            ];
        }

        return ['valid' => true];
    }

    /**
     * Post delivery order to general ledger. Creates JournalEntry rows linked to the delivery order.
     */
    public function postDeliveryOrder(DeliveryOrder $deliveryOrder): array
    {
        $deliveryOrder->loadMissing('deliveryOrderItem.product', 'deliveryOrderItem.warehouseSources');

        // Check if stock movements already exist for this delivery order
        $deliveryOrderItemIds = $deliveryOrder->deliveryOrderItem()->pluck('id');
        $existingStockMovements = \App\Models\StockMovement::where('from_model_type', \App\Models\DeliveryOrderItem::class)
            ->whereIn('from_model_id', $deliveryOrderItemIds)
            ->where('type', 'sales')
            ->exists();

        if ($existingStockMovements) {
            return ['status' => 'skipped', 'message' => 'Delivery order stock movements already created'];
        }

        // Release stock reservations first before validation
        $this->releaseStockReservations($deliveryOrder);

        // Validate stock availability before posting
        $stockValidation = $this->validateStockAvailability($deliveryOrder);
        if (!$stockValidation['valid']) {
            return [
                'status' => 'error',
                'message' => 'Stock validation failed',
                'errors' => $stockValidation['errors']
            ];
        }

        $date = $deliveryOrder->delivery_date ?? Carbon::now()->toDateString();

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

            $sources = $item->warehouseSources;
            if ($sources->isNotEmpty()) {
                foreach ($sources as $source) {
                    $sourceQty = max(0, (float) ($source->quantity ?? 0));
                    if ($sourceQty <= 0 || !$source->warehouse_id) {
                        continue;
                    }

                    $this->productService->createStockMovement(
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

            // Skip if warehouse_id is null
            if (!$deliveryOrder->warehouse_id) {
                continue;
            }

            // Create sales stock movement to reduce physical inventory
            $this->productService->createStockMovement(
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

        return ['status' => 'posted'];
    }

    protected function createJournalEntriesForDelivery(DeliveryOrder $deliveryOrder): void
    {
        $existingEntries = JournalEntry::where('source_type', DeliveryOrder::class)
            ->where('source_id', $deliveryOrder->id)
            ->exists();

        if ($existingEntries) {
            return;
        }

        $deliveryOrder->loadMissing('deliveryOrderItem.product.inventoryCoa', 'deliveryOrderItem.product.goodsDeliveryCoa');

        $date = $deliveryOrder->delivery_date ?? now()->toDateString();

        $defaultInventoryCoa = ChartOfAccount::whereIn('code', ['1140.10', '1140.01'])->first();
        $defaultGoodsDeliveryCoa = ChartOfAccount::whereIn('code', ['1140.20', '1180.10'])->first();

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

            $inventoryCoa = $product?->resolveInventoryCoaOrDefault() ?? $defaultInventoryCoa;
            $goodsDeliveryCoa = $product?->resolveGoodsDeliveryCoaOrDefault() ?? $defaultGoodsDeliveryCoa;

            if (!$inventoryCoa || !$goodsDeliveryCoa) {
                throw new \RuntimeException(
                    'COA persediaan atau barang terkirim tidak ditemukan untuk delivery order ' . $deliveryOrder->do_number
                );
            }

            $debitTotals[$goodsDeliveryCoa->id]['coa'] = $goodsDeliveryCoa;
            $debitTotals[$goodsDeliveryCoa->id]['amount'] = ($debitTotals[$goodsDeliveryCoa->id]['amount'] ?? 0) + $lineAmount;

            $creditTotals[$inventoryCoa->id]['coa'] = $inventoryCoa;
            $creditTotals[$inventoryCoa->id]['amount'] = ($creditTotals[$inventoryCoa->id]['amount'] ?? 0) + $lineAmount;
        }

        foreach ($debitTotals as $debitData) {
            JournalEntry::create([
                'coa_id' => $debitData['coa']->id,
                'date' => $date,
                'reference' => $deliveryOrder->do_number,
                'description' => 'Goods Delivery - Cost of Goods Sold for ' . $deliveryOrder->do_number,
                'debit' => round($debitData['amount'], 2),
                'credit' => 0,
                'journal_type' => 'sales',
                'source_type' => DeliveryOrder::class,
                'source_id' => $deliveryOrder->id,
                'cabang_id' => $deliveryOrder->cabang_id,
            ]);
        }

        foreach ($creditTotals as $creditData) {
            JournalEntry::create([
                'coa_id' => $creditData['coa']->id,
                'date' => $date,
                'reference' => $deliveryOrder->do_number,
                'description' => 'Goods Delivery - Inventory Reduction for ' . $deliveryOrder->do_number,
                'debit' => 0,
                'credit' => round($creditData['amount'], 2),
                'journal_type' => 'sales',
                'source_type' => DeliveryOrder::class,
                'source_id' => $deliveryOrder->id,
                'cabang_id' => $deliveryOrder->cabang_id,
            ]);
        }
    }

    /**
     * Validate that journal entries are balanced (debit = credit)
     */
    public function validateJournalBalance(array $entries): bool
    {
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $entry) {
            if ($entry instanceof JournalEntry) {
                $totalDebit += $entry->debit ?? 0;
                $totalCredit += $entry->credit ?? 0;
            } else {
                $totalDebit += $entry['debit'] ?? 0;
                $totalCredit += $entry['credit'] ?? 0;
            }
        }

        return abs($totalDebit - $totalCredit) < 0.01; // allow small floating point differences
    }

    /**
     * Release stock reservations for delivered items.
     * This should be called after delivery order is posted and stock movements are created.
     */
    public function releaseStockReservations(DeliveryOrder $deliveryOrder): void
    {
        $deliveryOrder->loadMissing('salesOrders', 'deliveryOrderItem.warehouseSources');

        // Get all sale orders linked to this delivery order
        $saleOrderIds = $deliveryOrder->salesOrders->pluck('id')->toArray();

        if (empty($saleOrderIds)) {
            return;
        }

        foreach ($deliveryOrder->deliveryOrderItem as $deliveryItem) {
            $qtyDelivered = max(0, $deliveryItem->quantity ?? 0);
            if ($qtyDelivered <= 0) {
                continue;
            }

            $sources = $deliveryItem->warehouseSources;
            if ($sources->isNotEmpty()) {
                foreach ($sources as $source) {
                    $sourceQty = max(0, (float) ($source->quantity ?? 0));
                    if ($sourceQty <= 0 || !$source->warehouse_id) {
                        continue;
                    }

                    $reservations = StockReservation::whereIn('sale_order_id', $saleOrderIds)
                        ->where('product_id', $deliveryItem->product_id)
                        ->where('warehouse_id', $source->warehouse_id)
                        ->when(!empty($source->rak_id), function ($query) use ($source) {
                            $query->where('rak_id', $source->rak_id);
                        })
                        ->get();

                    $remainingToRelease = $sourceQty;

                    foreach ($reservations as $reservation) {
                        if ($remainingToRelease <= 0) {
                            break;
                        }

                        $releaseQty = min($remainingToRelease, $reservation->quantity);
                        $remainingToRelease -= $releaseQty;

                        if ($releaseQty >= $reservation->quantity) {
                            $reservation->delete();
                        } else {
                            $reservation->quantity -= $releaseQty;
                            $reservation->save();

                            $inventoryStock = InventoryStock::where('product_id', $reservation->product_id)
                                ->where('warehouse_id', $reservation->warehouse_id)
                                ->first();
                            if ($inventoryStock) {
                                $inventoryStock->decrement('qty_reserved', $releaseQty);
                            }
                        }
                    }
                }

                continue;
            }

            // Find stock reservations for this product across all linked sale orders
            $reservations = StockReservation::whereIn('sale_order_id', $saleOrderIds)
                ->where('product_id', $deliveryItem->product_id)
                ->where('warehouse_id', $deliveryOrder->warehouse_id)
                ->get();

            $remainingToRelease = $qtyDelivered;

            foreach ($reservations as $reservation) {
                if ($remainingToRelease <= 0) {
                    break;
                }

                $releaseQty = min($remainingToRelease, $reservation->quantity);
                $remainingToRelease -= $releaseQty;

                if ($releaseQty >= $reservation->quantity) {
                    // Delete the reservation (observer will decrement qty_reserved)
                    $reservation->delete();
                } else {
                    // Partially release: update reservation quantity
                    $reservation->quantity -= $releaseQty;
                    $reservation->save();
                    // Update inventory qty_reserved manually since observer only handles full delete
                    $inventoryStock = InventoryStock::where('product_id', $reservation->product_id)
                        ->where('warehouse_id', $reservation->warehouse_id)
                        ->first();
                    if ($inventoryStock) {
                        $inventoryStock->decrement('qty_reserved', $releaseQty);
                    }
                }
            }
        }
    }

    protected function resolveCoaByCodes(array $codes): ?ChartOfAccount
    {
        foreach ($codes as $code) {
            if (! $code) {
                continue;
            }

            if (! array_key_exists($code, self::$coaCache)) {
                self::$coaCache[$code] = ChartOfAccount::where('code', $code)->first();
            }

            if (self::$coaCache[$code]) {
                return self::$coaCache[$code];
            }
        }

        return null;
    }
}
