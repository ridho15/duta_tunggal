<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderLog;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\StockReservation;
use App\Models\InventoryStock;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
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

    public function updateStatus($deliveryOrder, $status)
    {
        $deliveryOrder->update([
            'status' => $status
        ]);

        $this->createLog(delivery_order_id: $deliveryOrder->id, status: $status);
    }

    public function createLog($delivery_order_id, $status)
    {
        DeliveryOrderLog::create([
            'delivery_order_id' => $delivery_order_id,
            'status' => $status,
            'confirmed_by' => Auth::user()->id,
        ]);
    }

    public function updateQuantity() {}

    public function generateDoNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = DeliveryOrder::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->do_number, -4));
            $number = $lastNumber + 1;
        }

        return 'DO-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validate that sufficient stock is available for delivery order items.
     * Checks if qty_available - qty_reserved >= requested quantity.
     */
    public function validateStockAvailability(DeliveryOrder $deliveryOrder): array
    {
        $errors = [];

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
        // prevent duplicate posting
        if (JournalEntry::where('source_type', DeliveryOrder::class)->where('source_id', $deliveryOrder->id)->exists()) {
            return ['status' => 'skipped', 'message' => 'Delivery order already posted to ledger'];
        }

        // Validate stock availability before posting
        $stockValidation = $this->validateStockAvailability($deliveryOrder);
        if (!$stockValidation['valid']) {
            return [
                'status' => 'error',
                'message' => 'Stock validation failed',
                'errors' => $stockValidation['errors']
            ];
        }

        $deliveryOrder->loadMissing([
            'deliveryOrderItem.product.inventoryCoa',
            'deliveryOrderItem.product.goodsDeliveryCoa',
            'salesOrders',
        ]);

        $date = $deliveryOrder->delivery_date ?? Carbon::now()->toDateString();

        // Build journal entries for cost-of-goods-sold (goods delivery) and inventory credit
        $defaultInventoryCoa = $this->resolveCoaByCodes(['1140.10', '1140.01']);
        $defaultGoodsDeliveryCoa = $this->resolveCoaByCodes(['1140.20', '1180.10']);

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

            $inventoryCoa = $product?->inventoryCoa?->exists ? $product->inventoryCoa : $defaultInventoryCoa;
            $goodsDeliveryCoa = $product?->goodsDeliveryCoa?->exists ? $product->goodsDeliveryCoa : $defaultGoodsDeliveryCoa;

            if (! $inventoryCoa || ! $goodsDeliveryCoa) {
                continue;
            }

            $debitTotals[$goodsDeliveryCoa->id]['coa'] = $goodsDeliveryCoa;
            $debitTotals[$goodsDeliveryCoa->id]['amount'] = ($debitTotals[$goodsDeliveryCoa->id]['amount'] ?? 0) + $lineAmount;

            $creditTotals[$inventoryCoa->id]['coa'] = $inventoryCoa;
            $creditTotals[$inventoryCoa->id]['amount'] = ($creditTotals[$inventoryCoa->id]['amount'] ?? 0) + $lineAmount;
        }

        if (empty($debitTotals) || empty($creditTotals)) {
            // nothing to post, continue with stock movements only
            $entries = [];
        } else {
            $plannedEntries = [];

            foreach ($debitTotals as $debitData) {
                $plannedEntries[] = [
                    'coa_id' => $debitData['coa']->id,
                    'date' => $date,
                    'reference' => $deliveryOrder->do_number,
                    'description' => 'Delivery Order - Goods in transit for ' . $deliveryOrder->do_number,
                    'debit' => round($debitData['amount'], 2),
                    'credit' => 0,
                    'journal_type' => 'sales',
                    'source_type' => DeliveryOrder::class,
                    'source_id' => $deliveryOrder->id,
                ];
            }

            foreach ($creditTotals as $creditData) {
                $plannedEntries[] = [
                    'coa_id' => $creditData['coa']->id,
                    'date' => $date,
                    'reference' => $deliveryOrder->do_number,
                    'description' => 'Delivery Order - Inventory reduction for ' . $deliveryOrder->do_number,
                    'debit' => 0,
                    'credit' => round($creditData['amount'], 2),
                    'journal_type' => 'sales',
                    'source_type' => DeliveryOrder::class,
                    'source_id' => $deliveryOrder->id,
                ];
            }

            if (! $this->validateJournalBalance($plannedEntries)) {
                return ['status' => 'error', 'message' => 'Journal entries are not balanced'];
            }

            $entries = [];
            foreach ($plannedEntries as $data) {
                $entries[] = JournalEntry::create($data);
            }
        }

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

        // Release stock reservations for delivered items
        $this->releaseStockReservations($deliveryOrder);

        return ['status' => 'posted', 'entries' => []]; // Return empty entries array
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
