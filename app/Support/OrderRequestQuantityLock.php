<?php

namespace App\Support;

use App\Models\OrderRequestItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceiptItem;

class OrderRequestQuantityLock
{
    public const ACTIVE_PO_EXCLUDED_STATUSES = ['draft', 'closed', 'cancelled', 'canceled', 'rejected'];

    public static function activePurchaseOrderItemQuantity(int $orderRequestItemId, ?int $excludePurchaseOrderItemId = null): float
    {
        return (float) PurchaseOrderItem::query()
            ->where('refer_item_model_type', OrderRequestItem::class)
            ->where('refer_item_model_id', $orderRequestItemId)
            ->when($excludePurchaseOrderItemId, fn ($query) => $query->whereKeyNot($excludePurchaseOrderItemId))
            ->whereHas('purchaseOrder', fn ($query) => $query->whereNotIn('status', self::ACTIVE_PO_EXCLUDED_STATUSES))
            ->sum('quantity');
    }

    public static function orderRequestItemLimit(int $orderRequestItemId, ?int $excludePurchaseOrderItemId = null): array
    {
        $orderRequestItem = OrderRequestItem::withoutGlobalScopes()->find($orderRequestItemId);

        if (! $orderRequestItem) {
            return [
                'or_quantity' => 0.0,
                'active_po_quantity' => 0.0,
                'accepted_receipt_quantity' => 0.0,
                'remaining_for_po' => 0.0,
                'remaining_for_receipt' => 0.0,
            ];
        }

        $orQuantity = (float) ($orderRequestItem->quantity ?? 0);
        $activePoQuantity = self::activePurchaseOrderItemQuantity($orderRequestItemId, $excludePurchaseOrderItemId);
        $acceptedReceiptQuantity = self::receiptQuantityForOrderRequestItem($orderRequestItemId, 'qty_accepted');
        $fulfilledQuantity = (float) ($orderRequestItem->fulfilled_quantity ?? 0);
        $accountedForPo = max($activePoQuantity, $acceptedReceiptQuantity, $fulfilledQuantity);

        return [
            'or_quantity' => $orQuantity,
            'active_po_quantity' => $activePoQuantity,
            'accepted_receipt_quantity' => max($acceptedReceiptQuantity, $fulfilledQuantity),
            'remaining_for_po' => max(0, $orQuantity - $accountedForPo),
            'remaining_for_receipt' => max(0, $orQuantity - max($acceptedReceiptQuantity, $fulfilledQuantity)),
        ];
    }

    public static function purchaseOrderItemReceiptLimit(int $purchaseOrderItemId, ?int $excludeReceiptItemId = null): array
    {
        $purchaseOrderItem = PurchaseOrderItem::withoutGlobalScopes()
            ->with('referItemModel')
            ->find($purchaseOrderItemId);

        if (! $purchaseOrderItem) {
            return [
                'po_quantity' => 0.0,
                'received_quantity' => 0.0,
                'accepted_quantity' => 0.0,
                'remaining_received' => 0.0,
                'remaining_accepted' => 0.0,
                'or_quantity' => null,
                'or_received_quantity' => null,
                'or_accepted_quantity' => null,
                'or_remaining_received' => null,
                'or_remaining_accepted' => null,
            ];
        }

        $poQuantity = (float) ($purchaseOrderItem->quantity ?? 0);
        $receivedQuantity = self::receiptQuantityForPurchaseOrderItem($purchaseOrderItemId, 'qty_received', $excludeReceiptItemId);
        $acceptedQuantity = self::receiptQuantityForPurchaseOrderItem($purchaseOrderItemId, 'qty_accepted', $excludeReceiptItemId);
        $remainingReceived = max(0, $poQuantity - $receivedQuantity);
        $remainingAccepted = max(0, $poQuantity - $acceptedQuantity);

        $orQuantity = null;
        $orReceivedQuantity = null;
        $orAcceptedQuantity = null;
        $orRemainingReceived = null;
        $orRemainingAccepted = null;

        if (
            $purchaseOrderItem->refer_item_model_type === OrderRequestItem::class
            && $purchaseOrderItem->refer_item_model_id
        ) {
            $orderRequestItem = OrderRequestItem::withoutGlobalScopes()->find($purchaseOrderItem->refer_item_model_id);
            if ($orderRequestItem) {
                $orQuantity = (float) ($orderRequestItem->quantity ?? 0);
                $orReceivedQuantity = self::receiptQuantityForOrderRequestItem((int) $orderRequestItem->id, 'qty_received', $excludeReceiptItemId);
                $orAcceptedQuantity = self::receiptQuantityForOrderRequestItem((int) $orderRequestItem->id, 'qty_accepted', $excludeReceiptItemId);
                $fulfilledQuantity = (float) ($orderRequestItem->fulfilled_quantity ?? 0);
                $orRemainingReceived = max(0, $orQuantity - $orReceivedQuantity);
                $orRemainingAccepted = max(0, $orQuantity - max($orAcceptedQuantity, $fulfilledQuantity));
                $remainingReceived = min($remainingReceived, $orRemainingReceived);
                $remainingAccepted = min($remainingAccepted, $orRemainingAccepted);
            }
        }

        return [
            'po_quantity' => $poQuantity,
            'received_quantity' => $receivedQuantity,
            'accepted_quantity' => $acceptedQuantity,
            'remaining_received' => $remainingReceived,
            'remaining_accepted' => $remainingAccepted,
            'or_quantity' => $orQuantity,
            'or_received_quantity' => $orReceivedQuantity,
            'or_accepted_quantity' => $orAcceptedQuantity,
            'or_remaining_received' => $orRemainingReceived,
            'or_remaining_accepted' => $orRemainingAccepted,
        ];
    }

    public static function validatePurchaseOrderItem(PurchaseOrderItem $purchaseOrderItem): void
    {
        if (
            $purchaseOrderItem->refer_item_model_type !== OrderRequestItem::class
            || ! $purchaseOrderItem->refer_item_model_id
        ) {
            return;
        }

        $purchaseOrder = $purchaseOrderItem->purchaseOrder ?: PurchaseOrder::withoutGlobalScopes()->find($purchaseOrderItem->purchase_order_id);
        if (! $purchaseOrder || in_array($purchaseOrder->status, self::ACTIVE_PO_EXCLUDED_STATUSES, true)) {
            return;
        }

        $limit = self::orderRequestItemLimit(
            (int) $purchaseOrderItem->refer_item_model_id,
            $purchaseOrderItem->exists ? (int) $purchaseOrderItem->id : null
        );

        if ((float) ($purchaseOrderItem->quantity ?? 0) > $limit['remaining_for_po']) {
            throw new \InvalidArgumentException("Qty Purchase Order tidak boleh melebihi sisa Order Request ({$limit['remaining_for_po']}).");
        }
    }

    public static function validatePurchaseOrderApproval(PurchaseOrder $purchaseOrder): void
    {
        $purchaseOrder->loadMissing('purchaseOrderItem');

        foreach ($purchaseOrder->purchaseOrderItem as $item) {
            if (
                $item->refer_item_model_type !== OrderRequestItem::class
                || ! $item->refer_item_model_id
            ) {
                continue;
            }

            $limit = self::orderRequestItemLimit((int) $item->refer_item_model_id, (int) $item->id);
            if ((float) ($item->quantity ?? 0) > $limit['remaining_for_po']) {
                throw new \InvalidArgumentException("Qty Purchase Order untuk item {$item->product?->name} tidak boleh melebihi sisa Order Request ({$limit['remaining_for_po']}).");
            }
        }
    }

    public static function validatePurchaseReceiptItem(PurchaseReceiptItem $receiptItem): void
    {
        if (! $receiptItem->purchase_order_item_id) {
            return;
        }

        $qtyReceived = (float) ($receiptItem->qty_received ?? 0);
        $qtyAccepted = (float) ($receiptItem->qty_accepted ?? 0);
        $qtyRejected = (float) ($receiptItem->qty_rejected ?? 0);

        if ($qtyAccepted > $qtyReceived) {
            throw new \InvalidArgumentException("Quantity Accepted tidak boleh melebihi Quantity Received ({$qtyReceived}).");
        }

        if (($qtyAccepted + $qtyRejected) > $qtyReceived) {
            throw new \InvalidArgumentException("Total Accepted + Rejected tidak boleh melebihi Quantity Received ({$qtyReceived}).");
        }

        $purchaseOrderItem = PurchaseOrderItem::withoutGlobalScopes()->find($receiptItem->purchase_order_item_id);
        if (
            ! $purchaseOrderItem
            || $purchaseOrderItem->refer_item_model_type !== OrderRequestItem::class
            || ! $purchaseOrderItem->refer_item_model_id
        ) {
            return;
        }

        $limit = self::purchaseOrderItemReceiptLimit(
            (int) $receiptItem->purchase_order_item_id,
            $receiptItem->exists ? (int) $receiptItem->id : null
        );

        if ($qtyReceived > $limit['remaining_received']) {
            throw new \InvalidArgumentException("Quantity Received tidak boleh melebihi sisa PO/Order Request ({$limit['remaining_received']}).");
        }

        if ($qtyAccepted > $limit['remaining_accepted']) {
            throw new \InvalidArgumentException("Quantity Accepted tidak boleh melebihi sisa PO/Order Request ({$limit['remaining_accepted']}).");
        }
    }

    protected static function receiptQuantityForPurchaseOrderItem(int $purchaseOrderItemId, string $column, ?int $excludeReceiptItemId = null): float
    {
        return (float) PurchaseReceiptItem::withoutGlobalScopes()
            ->where('purchase_order_item_id', $purchaseOrderItemId)
            ->when($excludeReceiptItemId, fn ($query) => $query->whereKeyNot($excludeReceiptItemId))
            ->sum($column);
    }

    protected static function receiptQuantityForOrderRequestItem(int $orderRequestItemId, string $column, ?int $excludeReceiptItemId = null): float
    {
        $poItemIds = PurchaseOrderItem::withoutGlobalScopes()
            ->where('refer_item_model_type', OrderRequestItem::class)
            ->where('refer_item_model_id', $orderRequestItemId)
            ->pluck('id');

        if ($poItemIds->isEmpty()) {
            return 0.0;
        }

        return (float) PurchaseReceiptItem::withoutGlobalScopes()
            ->whereIn('purchase_order_item_id', $poItemIds)
            ->when($excludeReceiptItemId, fn ($query) => $query->whereKeyNot($excludeReceiptItemId))
            ->sum($column);
    }
}
