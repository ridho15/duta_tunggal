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
        // Validate that delivery order cannot be approved without surat jalan
        if ($status === 'approved' && !$deliveryOrder->suratJalan()->exists()) {
            throw new \Exception('Delivery Order tidak dapat di-approve karena belum ada Surat Jalan yang terkait.');
        }

        $deliveryOrder->update([
            'status' => $status
        ]);

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

    public function generateDoNumber()
    {
        $date = now()->format('Ymd');

        // Gunakan database transaction untuk menghindari race condition
        return DB::transaction(function () use ($date) {
            // Lock table untuk mencegah race condition
            $last = DB::table('delivery_orders')
                ->whereDate('created_at', now()->toDateString())
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $number = 1;

            if ($last) {
                // Ambil nomor urut terakhir
                $lastNumber = intval(substr($last->do_number, -4));
                $number = $lastNumber + 1;
            }

            return 'DO-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
        });
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
