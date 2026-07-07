<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\OrderRequestItem;
use App\Models\Supplier;
use App\Services\PurchaseOrderService;
use App\Services\ProductSupplierSyncService;
use App\Support\CurrencyConversionResolver;
use App\Support\OrderRequestQuantityLock;
use App\Support\TaxTypeHelper;
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
     * Only approved Order Request items may be converted into Purchase Order
     * items. When $data['selected_items'] is present, use only approved items
     * with include=true and respect the user-edited quantity/price. Otherwise
     * fall back to all approved items with remaining quantity.
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

                    if (OrderRequestItem::normalizeApprovalStatus($orderRequestItem->status ?? null) !== OrderRequestItem::STATUS_APPROVED) {
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

        // No selection provided — use all approved items with remaining quantity.
        return $orderRequest->orderRequestItem
            ->filter(fn ($orderRequestItem) => OrderRequestItem::normalizeApprovalStatus($orderRequestItem->status ?? null) === OrderRequestItem::STATUS_APPROVED)
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

    private function applyItemApprovalDecisions($orderRequest, array $data): void
    {
        $orderRequest->loadMissing('orderRequestItem');
        $selectedItems = collect($data['selected_items'] ?? []);

        if ($selectedItems->isEmpty()) {
            throw new \InvalidArgumentException('Keputusan item wajib diisi sebelum Order Request dapat di-approve.');
        }

        $selectedByItemId = $selectedItems
            ->filter(fn ($row) => ! empty($row['item_id']))
            ->keyBy(fn ($row) => (int) $row['item_id']);

        foreach ($orderRequest->orderRequestItem as $item) {
            $row = $selectedByItemId->get((int) $item->id);

            if (! $row) {
                if (OrderRequestItem::normalizeApprovalStatus($item->status ?? null) === OrderRequestItem::STATUS_DRAFT) {
                    throw new \InvalidArgumentException('Masih ada item berstatus Draft. Ambil keputusan Approve atau Reject untuk semua item sebelum menyetujui Order Request.');
                }

                continue;
            }

            if (! array_key_exists('approval_status', $row)) {
                throw new \InvalidArgumentException('Keputusan item wajib diisi sebelum Order Request dapat di-approve.');
            }

            $decision = OrderRequestItem::normalizeApprovalStatus($row['approval_status'] ?? null);
            if ($decision === OrderRequestItem::STATUS_DRAFT) {
                throw new \InvalidArgumentException('Masih ada item berstatus Draft. Ambil keputusan Approve atau Reject untuk semua item sebelum menyetujui Order Request.');
            }

            if ($decision === OrderRequestItem::STATUS_REJECTED && trim((string) ($row['rejection_note'] ?? '')) === '') {
                throw new \InvalidArgumentException('Alasan reject wajib diisi untuk item yang ditolak.');
            }
        }

        foreach ($selectedItems as $row) {
            $itemId = $row['item_id'] ?? null;
            if (! $itemId) {
                continue;
            }

            $item = $orderRequest->orderRequestItem->firstWhere('id', (int) $itemId);
            if (! $item) {
                continue;
            }

            $decision = OrderRequestItem::normalizeApprovalStatus($row['approval_status'] ?? null);
            if (! isset($row['approval_status']) || $decision === OrderRequestItem::STATUS_DRAFT) {
                $item->update([
                    'status' => OrderRequestItem::STATUS_DRAFT,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_note' => null,
                ]);
                continue;
            }

            if ($decision === OrderRequestItem::STATUS_APPROVED) {
                $item->update([
                    'status' => OrderRequestItem::STATUS_APPROVED,
                    'approved_by' => Auth::id(),
                    'approved_at' => now(),
                    'rejected_by' => null,
                    'rejected_at' => null,
                    'rejection_note' => null,
                ]);
                continue;
            }

            if ($decision === OrderRequestItem::STATUS_REJECTED) {
                $note = trim((string) ($row['rejection_note'] ?? ''));
                if ($note === '') {
                    throw new \InvalidArgumentException('Alasan reject wajib diisi untuk item yang ditolak.');
                }

                $item->update([
                    'status' => OrderRequestItem::STATUS_REJECTED,
                    'approved_by' => null,
                    'approved_at' => null,
                    'rejected_by' => Auth::id(),
                    'rejected_at' => now(),
                    'rejection_note' => $note,
                ]);
                continue;
            }

            $item->update([
                'status' => OrderRequestItem::STATUS_DRAFT,
                'approved_by' => null,
                'approved_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_note' => null,
            ]);
        }

        $orderRequest->syncItemApprovalStatus();
    }

    private function ensureAllItemsHaveApprovalDecision($orderRequest): void
    {
        $orderRequest->load('orderRequestItem');

        $hasDraftItem = $orderRequest->orderRequestItem
            ->contains(fn (OrderRequestItem $item): bool => OrderRequestItem::normalizeApprovalStatus($item->status ?? null) === OrderRequestItem::STATUS_DRAFT);

        if ($hasDraftItem) {
            throw new \InvalidArgumentException('Masih ada item berstatus Draft. Ambil keputusan Approve atau Reject untuk semua item sebelum menyetujui Order Request.');
        }
    }

    public function approve($orderRequest, $data)
    {
        $createPurchaseOrder = $data['create_purchase_order'] ?? true;

        $this->applyItemApprovalDecisions($orderRequest, $data);
        $orderRequest->refresh();
        $this->ensureAllItemsHaveApprovalDecision($orderRequest);

        if ($createPurchaseOrder) {
            $hasIncludedApprovedItem = ! empty($data['selected_items'])
                && collect($data['selected_items'])
                    ->filter(fn ($row) => ! empty($row['include']))
                    ->contains(fn ($row) => OrderRequestItem::normalizeApprovalStatus($row['approval_status'] ?? null) === OrderRequestItem::STATUS_APPROVED);

            if (! $hasIncludedApprovedItem) {
                return $orderRequest->fresh(['purchaseOrder.purchaseOrderItem']);
            }

            $supplier = Supplier::findOrFail($data['supplier_id']);

            $defaultCurrency = Currency::query()->first();

            if (! $defaultCurrency) {
                throw new \RuntimeException('Data mata uang wajib tersedia sebelum order request dapat disetujui.');
            }

            $orderRequest->load('orderRequestItem');
            $resolvedItems = $this->resolveSelectedItems($orderRequest, $data, $supplier);
            if ($resolvedItems->isEmpty()) {
                throw new \RuntimeException('Tidak ada item Approved dengan sisa qty yang bisa dibuatkan Purchase Order.');
            }

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
            $orderRequest->syncItemApprovalStatus();
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
            $orderRequest->syncItemApprovalStatus();
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
        if ($resolvedItems->isEmpty()) {
            throw new \RuntimeException('Tidak ada item Approved dengan sisa qty yang bisa dibuatkan Purchase Order.');
        }

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
     * Preserve the normalized item-level tax type when an OR creates a PO.
     */
    private function resolveTipePajak(?string $itemTaxType, float $tax): string
    {
        if ((float) $tax <= 0) {
            return TaxTypeHelper::NONE;
        }

        return TaxTypeHelper::normalize($itemTaxType);
    }

    public function reject($orderRequest)
    {
        $orderRequest->orderRequestItem()->update([
            'status' => OrderRequestItem::STATUS_REJECTED,
            'approved_by' => null,
            'approved_at' => null,
            'rejected_by' => Auth::id(),
            'rejected_at' => now(),
            'rejection_note' => 'Order Request ditolak pada level header.',
        ]);

        $orderRequest->update(['status' => 'rejected']);
    }

    public function submitForApproval($orderRequest)
    {
        $orderRequest->update(['status' => 'request_approve']);
    }
}

