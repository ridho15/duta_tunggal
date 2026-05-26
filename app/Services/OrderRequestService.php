<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\OrderRequestItem;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Services\ProductSupplierSyncService;
use App\Support\CurrencyConversionResolver;
use App\Support\OrderRequestQuantityLock;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrderRequestService
{
    public function __construct(
        private readonly ProductSupplierSyncService $productSupplierSyncService
    ) {
    }

    /**
     * Build the list of OrderRequestItems to convert to PO items.
     * When $data['selected_items'] is present, use only items with include=true
     * and respect the user-edited quantity/price. Otherwise fall back to all items.
     *
     * Returns a Collection of arrays:
     *  ['order_request_item' => OrderRequestItem, 'quantity' => float, 'unit_price' => float]
     */
    private function resolveSelectedItems($orderRequest, array $data, Supplier $supplier): \Illuminate\Support\Collection
    {
        $selectedItems = $data['selected_items'] ?? null;

        if (!empty($selectedItems)) {
            return collect($selectedItems)
                ->filter(fn($row) => !empty($row['include']))
                ->filter(fn($row) => ($row['quantity'] ?? 0) > 0)
                ->map(function ($row) use ($orderRequest, $supplier) {
                    $orderRequestItem = OrderRequestItem::find($row['item_id']);
                    if (!$orderRequestItem || $orderRequestItem->order_request_id !== $orderRequest->id) {
                        return null;
                    }

                    $qty = (float) ($row['quantity'] ?? $orderRequestItem->quantity);

                    // Validate quantity does not exceed remaining unfulfilled AND un-locked quantity.
                    // fulfilled_quantity = received via PurchaseReceipt; locked = in approved (non-draft) POs.
                    $fulfilledQty = (float) ($orderRequestItem->fulfilled_quantity ?? 0);
                    $lockedQty    = (float) \App\Models\PurchaseOrderItem::query()
                        ->where('refer_item_model_type', \App\Models\OrderRequestItem::class)
                        ->where('refer_item_model_id', $orderRequestItem->id)
                        ->whereHas('purchaseOrder', fn ($q) => $q->whereNotIn('status', ['draft', 'closed', 'cancelled', 'rejected']))
                        ->sum('quantity');
                    $maxQty = max(0, $orderRequestItem->quantity - max($fulfilledQty, $lockedQty));
                    if ($qty > $maxQty) {
                        Log::warning('OrderRequest qty clamped to remaining quantity.', [
                            'order_request_id' => $orderRequest->id,
                            'order_request_item_id' => $orderRequestItem->id,
                            'requested_qty' => $qty,
                            'remaining_qty' => $maxQty,
                        ]);
                        $qty = $maxQty;
                    }
                    if ($qty <= 0) {
                        return null;
                    }

                    // Use form-provided price if given; otherwise fall back to OR item price → supplier pivot → cost_price
                    $formPrice = isset($row['unit_price']) && $row['unit_price'] !== '' ? \App\Helpers\MoneyHelper::safeParse($row['unit_price']) : null;
                    if ($formPrice !== null && $formPrice >= 0) {
                        $unitPrice = $formPrice;
                    } elseif (($orderRequestItem->unit_price ?? 0) > 0) {
                        $unitPrice = (float) $orderRequestItem->unit_price;
                    } else {
                        $product = $orderRequestItem->product;
                        $sp = $product ? $product->suppliers()->where('suppliers.id', $supplier->id)->first() : null;
                        $unitPrice = $sp ? (float) $sp->pivot->supplier_price : (float) ($product->cost_price ?? 0);
                    }

                    return [
                        'order_request_item' => $orderRequestItem,
                        'quantity'           => $qty,
                        'unit_price'         => $unitPrice,
                        'discount'           => $orderRequestItem->discount ?? 0,
                        'tax'                => $orderRequestItem->tax ?? 0,
                    ];
                })
                ->filter();
        }

        // No selection provided — use all items with remaining quantity
        return $orderRequest->orderRequestItem
            ->map(function ($orderRequestItem) use ($supplier) {
            $remainingQty = OrderRequestQuantityLock::orderRequestItemLimit((int) $orderRequestItem->id)['remaining_for_po'];
            if ($remainingQty <= 0) {
                return null;
            }

            if (($orderRequestItem->unit_price ?? 0) > 0) {
                $unitPrice = (float) $orderRequestItem->unit_price;
            } else {
                $product = $orderRequestItem->product;
                $sp = $product ? $product->suppliers()->where('suppliers.id', $supplier->id)->first() : null;
                $unitPrice = $sp ? (float) $sp->pivot->supplier_price : (float) ($product->cost_price ?? 0);
            }

            return [
                'order_request_item' => $orderRequestItem,
                'quantity'           => $remainingQty,
                'unit_price'         => $unitPrice,
                'discount'           => $orderRequestItem->discount ?? 0,
                'tax'                => $orderRequestItem->tax ?? 0,
            ];
        })
            ->filter();
    }

    private function resolvePurchaseOrderCabangId($orderRequest, array $data, \Illuminate\Support\Collection $resolvedItems): ?int
    {
        if (! empty($data['cabang_id'])) {
            return (int) $data['cabang_id'];
        }

        $firstItem = $resolvedItems->first();
        if ($firstItem) {
            $itemCabangId = $firstItem['order_request_item']->cabang_id ?? null;

            if (! empty($itemCabangId)) {
                return (int) $itemCabangId;
            }
        }

        return ! empty($orderRequest->cabang_id) ? (int) $orderRequest->cabang_id : null;
    }

    public function approve($orderRequest, $data)
    {
        $createPurchaseOrder = $data['create_purchase_order'] ?? true;

        if ($createPurchaseOrder) {
            $supplier = Supplier::findOrFail($data['supplier_id']);

            $defaultCurrency = Currency::query()->first();

            if (! $defaultCurrency) {
                throw new \RuntimeException('Data mata uang wajib tersedia sebelum order request dapat disetujui.');
            }

            $orderRequest->load('orderRequestItem');
            $resolvedItems = $this->resolveSelectedItems($orderRequest, $data, $supplier);
            $cabangId = $this->resolvePurchaseOrderCabangId($orderRequest, $data, $resolvedItems);

            $purchaseOrder = $orderRequest->purchaseOrders()->create([
                'po_number'    => $data['po_number'],
                'supplier_id'  => $supplier->id,
                'order_date'   => $data['order_date'],
                'expected_date'=> $data['expected_date'] ?? null,
                'note'         => $data['note'] ?? null,
                'status'       => 'draft', // PO dimulai dari draft; fulfilled_quantity diupdate saat PO diapprove
                'tempo_hutang' => $supplier->tempo_hutang ?? 0,
                'created_by'   => Auth::id() ?? $orderRequest->created_by,
                'cabang_id'    => $cabangId,
            ]);

            $itemsForPivotSync = [];

            foreach ($resolvedItems as $row) {
                /** @var OrderRequestItem $orderRequestItem */
                $orderRequestItem = $row['order_request_item'];

                $itemCurrencyId = $orderRequestItem->currency_id ?? $defaultCurrency->id;

                $orderRequestItem->purchaseOrderItem()->create([
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id'        => $orderRequestItem->product_id,
                    'quantity'          => $row['quantity'],
                    'unit_price'        => $row['unit_price'],
                    'discount'          => $row['discount'],
                    'tax'               => $row['tax'],
                    'tipe_pajak'        => $this->resolveTipePajak($orderRequestItem->tipe_pajak ?? null, $row['tax']),
                    'currency_id'       => $itemCurrencyId,
                ]);

                $rate = CurrencyConversionResolver::resolveRate((int)$itemCurrencyId);
                $idrPrice = $row['unit_price'] * $rate;

                $itemsForPivotSync[] = [
                    'product_id' => (int) $orderRequestItem->product_id,
                    'supplier_id' => (int) ($orderRequestItem->supplier_id ?: $supplier->id),
                    'unit_price' => $idrPrice,
                ];
                // fulfilled_quantity akan diupdate saat PO diapprove, bukan saat PO dibuat
            }

            // Create PurchaseOrderCurrency entries for any currencies used by items
            $usedCurrencyIds = $purchaseOrder->purchaseOrderItem()->pluck('currency_id')->filter()->unique()->values()->all();
            foreach ($usedCurrencyIds as $cid) {
                $currency = Currency::find($cid);
                $nominal = $currency ? ($currency->to_rupiah ?? 1) : 1;
                $purchaseOrder->purchaseOrderCurrency()->create([
                    'currency_id' => $cid,
                    'nominal' => $nominal,
                ]);
            }

            // Ensure OR is approved first, then sync supplier-product pivot.
            $orderRequest->update(['status' => 'approved']);
            foreach ($itemsForPivotSync as $syncRow) {
                $this->productSupplierSyncService->syncSupplierProductPrice(
                    $syncRow['product_id'],
                    $syncRow['supplier_id'],
                    $syncRow['unit_price']
                );
            }

            // Auto-approve PO when created from Order Request approval flow.
            app(PurchaseOrderService::class)->approvePo($purchaseOrder, Auth::id());
        } else {
            foreach($orderRequest->orderRequestItem as $item) {
                $productId = $item->product_id;
                $supplierId = $item->supplier_id;

                if ($productId && $supplierId) {
                    $itemCurrencyId = $item->currency_id;
                    $rate = CurrencyConversionResolver::resolveRate($itemCurrencyId ? (int)$itemCurrencyId : null);
                    $idrPrice = $item->unit_price * $rate;

                    $this->productSupplierSyncService->syncSupplierProductPrice(
                        $productId,
                        $supplierId,
                        $idrPrice
                    );
                }
            }
            $orderRequest->update(['status' => 'approved']);
        }

        return $orderRequest->fresh(['purchaseOrder.purchaseOrderItem']);
    }

    public function createPurchaseOrder($orderRequest, $data)
    {

        $supplier = Supplier::findOrFail($data['supplier_id']);
        $defaultCurrency = Currency::query()->first();

        if (! $defaultCurrency) {
            throw new \RuntimeException('Data mata uang wajib tersedia sebelum purchase order dibuat.');
        }

        $orderRequest->load('orderRequestItem');
        $resolvedItems = $this->resolveSelectedItems($orderRequest, $data, $supplier);
        $cabangId = $this->resolvePurchaseOrderCabangId($orderRequest, $data, $resolvedItems);

        $purchaseOrder = $orderRequest->purchaseOrders()->create([
            'po_number'    => $data['po_number'],
            'supplier_id'  => $supplier->id,
            'order_date'   => $data['order_date'],
            'expected_date'=> $data['expected_date'] ?? null,
            'note'         => $data['note'] ?? null,
            'status'       => 'draft', // PO dimulai dari draft; fulfilled_quantity diupdate saat PO diapprove
            'tempo_hutang' => $supplier->tempo_hutang ?? 0,
            'created_by'   => Auth::id() ?? $orderRequest->created_by,
            'cabang_id'    => $cabangId,
        ]);

        foreach ($resolvedItems as $row) {
            /** @var OrderRequestItem $orderRequestItem */
            $orderRequestItem = $row['order_request_item'];

            $itemCurrencyId = $orderRequestItem->currency_id ?? $defaultCurrency->id;

            $orderRequestItem->purchaseOrderItem()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id'        => $orderRequestItem->product_id,
                'quantity'          => $row['quantity'],
                'unit_price'        => $row['unit_price'],
                'discount'          => $row['discount'],
                'tax'               => $row['tax'],
                'tipe_pajak'        => $this->resolveTipePajak($orderRequestItem->tipe_pajak ?? null, $row['tax']),
                'currency_id'       => $itemCurrencyId,
            ]);
            // fulfilled_quantity akan diupdate saat PO diapprove, bukan saat PO dibuat
        }

        // Ensure PurchaseOrderCurrency entries are created for item currencies
        $usedCurrencyIds = collect($purchaseOrder->purchaseOrderItem)->pluck('currency_id')->filter()->unique()->values()->all();
        foreach ($usedCurrencyIds as $cid) {
            $currency = Currency::find($cid);
            $nominal = $currency ? ($currency->to_rupiah ?? 1) : 1;
            $purchaseOrder->purchaseOrderCurrency()->create([
                'currency_id' => $cid,
                'nominal' => $nominal,
            ]);
        }

        $purchaseOrder = app(PurchaseOrderService::class)->approvePo($purchaseOrder, Auth::id());

        return $purchaseOrder->fresh(['purchaseOrderItem']);
    }

    /**
     * Derive the PurchaseOrderItem tipe_pajak from the Order Request tax_type and item tax rate.
     *  - tax = 0 → 'none'
     *  - tax_type = 'inklusif' → 'inklusif'
     *  - tax_type = 'eklusif' (default) → 'eklusif'
     */
    private function resolveTipePajak(?string $itemTaxType, float $tax): string
    {
        if ((float) $tax <= 0) {
            return 'none';
        }

        $normalized = strtolower(trim((string) $itemTaxType));
        if (in_array($normalized, ['inklusif', 'ppn included', 'included'], true)) {
            return 'inklusif';
        }

        return 'eklusif';
    }

    public function reject($orderRequest)
    {
        $orderRequest->update(['status' => 'rejected']);
    }

    public function submitForApproval($orderRequest)
    {
        $orderRequest->update(['status' => 'pending']);
    }
}
