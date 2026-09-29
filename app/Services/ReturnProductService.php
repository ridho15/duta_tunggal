<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\ReturnProduct;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnProductService
{
    public function updateQuantityFromModel($returnProduct)
    {
        return DB::transaction(function () use ($returnProduct) {
            $returnProduct->loadMissing([
                'returnProductItem.product.inventoryCoa',
                'returnProductItem.product.goodsDeliveryCoa',
                'returnProductItem.product.unbilledPurchaseCoa',
                'returnProductItem.fromItemModel',
                'warehouse',
                'fromModel'
            ]);

            $isSalesReturn = ($returnProduct->from_model_type === \App\Models\DeliveryOrder::class);

            foreach ($returnProduct->returnProductItem as $returnProductItem) {
                $fromItemModel = $returnProductItem->fromItemModel;
                if ($fromItemModel) {
                    $defaultQuantity = (float) $fromItemModel->quantity;
                    $newQuantity = max(0.0, $defaultQuantity - (float) $returnProductItem->quantity);

                    // Update without triggering DeliveryOrderItem::updated (which rewrites original DO sales movements)
                    if ($fromItemModel instanceof \Illuminate\Database\Eloquent\Model) {
                        $fromItemModel::withoutEvents(function () use ($fromItemModel, $newQuantity) {
                            $fromItemModel->update([
                                'quantity' => $newQuantity,
                            ]);
                        });

                        if ($fromItemModel instanceof \App\Models\DeliveryOrderItem && $fromItemModel->sale_order_item_id) {
                            app(\App\Services\SaleOrderDeliveryProgress::class)
                                ->syncForSaleOrderItems([$fromItemModel->sale_order_item_id]);
                        }
                    } else {
                        $fromItemModel->update([
                            'quantity' => $newQuantity,
                        ]);
                    }
                }

                // Create stock movement if not already created
                $existingMovement = StockMovement::where('from_model_type', ReturnProduct::class)
                    ->where('from_model_id', $returnProduct->id)
                    ->where('meta->return_product_item_id', $returnProductItem->id)
                    ->first();

                if (! $existingMovement && (float) $returnProductItem->quantity > 0) {
                    $warehouseId = $returnProductItem->fromItemModel?->warehouse_id ?? $returnProduct->warehouse_id;
                    $rakId = $returnProductItem->rak_id ?? $returnProductItem->fromItemModel?->rak_id;
                    $costPrice = (float) ($returnProductItem->product?->cost_price ?? 0);
                    $type = $isSalesReturn ? 'customer_return' : 'purchase_return';
                    $notes = ($isSalesReturn ? 'Retur Penjualan (Customer Return): ' : 'Retur Pembelian (Vendor Return): ') . $returnProduct->return_number;

                    StockMovement::create([
                        'product_id' => $returnProductItem->product_id,
                        'warehouse_id' => $warehouseId,
                        'rak_id' => $rakId,
                        'quantity' => (float) $returnProductItem->quantity,
                        'value' => round((float) $returnProductItem->quantity * $costPrice, 2),
                        'type' => $type,
                        'reference_id' => $returnProduct->id,
                        'date' => now()->toDateString(),
                        'notes' => $notes,
                        'from_model_type' => ReturnProduct::class,
                        'from_model_id' => $returnProduct->id,
                        'meta' => [
                            'return_product_id' => $returnProduct->id,
                            'return_product_item_id' => $returnProductItem->id,
                            'from_model_type' => $returnProduct->from_model_type,
                            'from_model_id' => $returnProduct->from_model_id,
                        ],
                    ]);
                }
            }

            // Create reversing journal entries if not already created
            $this->createReversingJournalEntries($returnProduct);

            $returnProduct->update([
                'status' => 'approved'
            ]);

            // Check if all quantities are returned and close SO/DO partial if needed
            $this->handleReturnAction($returnProduct);

            return $returnProduct;
        });
    }

    /**
     * Create reversing journal entries for approved ReturnProduct
     */
    protected function createReversingJournalEntries(ReturnProduct $returnProduct): void
    {
        $hasJournals = JournalEntry::where('source_type', ReturnProduct::class)
            ->where('source_id', $returnProduct->id)
            ->exists();

        if ($hasJournals) {
            return;
        }

        $isSalesReturn = ($returnProduct->from_model_type === \App\Models\DeliveryOrder::class);
        $isPurchaseReturn = ($returnProduct->from_model_type === \App\Models\PurchaseReceipt::class);

        if (! $isSalesReturn && ! $isPurchaseReturn) {
            return;
        }

        $date = now()->toDateString();
        $cabangId = $returnProduct->warehouse?->cabang_id ?? $returnProduct->fromModel?->cabang_id;
        $defaultInventoryCoa = app(\App\Services\AccountingSettings::class)->anyOf('inventory');
        $defaultGoodsDeliveryCoa = app(\App\Services\AccountingSettings::class)->anyOf('goods_in_transit')
            ?? app(\App\Services\AccountingSettings::class)->anyOf('cogs');

        if ($isSalesReturn) {
            $debitTotals = [];
            $creditTotals = [];

            foreach ($returnProduct->returnProductItem as $item) {
                $qty = max(0, (float) ($item->quantity ?? 0));
                $product = $item->product;
                $cost = (float) ($product?->cost_price ?? 0);
                $lineAmount = round($qty * $cost, 2);

                if ($lineAmount <= 0) {
                    continue;
                }

                $inventoryCoa = $product?->resolveInventoryCoaOrDefault() ?? $defaultInventoryCoa;
                $goodsDeliveryCoa = $product?->resolveGoodsDeliveryCoaOrDefault() ?? $defaultGoodsDeliveryCoa;

                if ($inventoryCoa && $goodsDeliveryCoa) {
                    $debitTotals[$inventoryCoa->id] = [
                        'coa' => $inventoryCoa,
                        'amount' => ($debitTotals[$inventoryCoa->id]['amount'] ?? 0) + $lineAmount,
                    ];
                    $creditTotals[$goodsDeliveryCoa->id] = [
                        'coa' => $goodsDeliveryCoa,
                        'amount' => ($creditTotals[$goodsDeliveryCoa->id]['amount'] ?? 0) + $lineAmount,
                    ];
                }
            }

            foreach ($debitTotals as $data) {
                JournalEntry::create([
                    'coa_id' => $data['coa']->id,
                    'date' => $date,
                    'reference' => $returnProduct->return_number,
                    'description' => 'Product Return - Inventory Restoration for ' . $returnProduct->return_number,
                    'debit' => round($data['amount'], 2),
                    'credit' => 0,
                    'journal_type' => 'sales',
                    'source_type' => ReturnProduct::class,
                    'source_id' => $returnProduct->id,
                    'cabang_id' => $cabangId,
                ]);
            }

            foreach ($creditTotals as $data) {
                JournalEntry::create([
                    'coa_id' => $data['coa']->id,
                    'date' => $date,
                    'reference' => $returnProduct->return_number,
                    'description' => 'Product Return - Delivery / COGS Reversal for ' . $returnProduct->return_number,
                    'debit' => 0,
                    'credit' => round($data['amount'], 2),
                    'journal_type' => 'sales',
                    'source_type' => ReturnProduct::class,
                    'source_id' => $returnProduct->id,
                    'cabang_id' => $cabangId,
                ]);
            }
        } elseif ($isPurchaseReturn) {
            $debitTotals = [];
            $creditTotals = [];

            foreach ($returnProduct->returnProductItem as $item) {
                $qty = max(0, (float) ($item->quantity ?? 0));
                $product = $item->product;
                $cost = (float) ($product?->cost_price ?? 0);
                $lineAmount = round($qty * $cost, 2);

                if ($lineAmount <= 0) {
                    continue;
                }

                $inventoryCoa = $product?->resolveInventoryCoaOrDefault() ?? $defaultInventoryCoa;
                $unbilledCoa = $product?->resolveUnbilledPurchaseCoaOrDefault();

                if ($inventoryCoa && $unbilledCoa) {
                    $debitTotals[$unbilledCoa->id] = [
                        'coa' => $unbilledCoa,
                        'amount' => ($debitTotals[$unbilledCoa->id]['amount'] ?? 0) + $lineAmount,
                    ];
                    $creditTotals[$inventoryCoa->id] = [
                        'coa' => $inventoryCoa,
                        'amount' => ($creditTotals[$inventoryCoa->id]['amount'] ?? 0) + $lineAmount,
                    ];
                }
            }

            foreach ($debitTotals as $data) {
                JournalEntry::create([
                    'coa_id' => $data['coa']->id,
                    'date' => $date,
                    'reference' => $returnProduct->return_number,
                    'description' => 'Product Return - Unbilled Purchase Reversal for ' . $returnProduct->return_number,
                    'debit' => round($data['amount'], 2),
                    'credit' => 0,
                    'journal_type' => 'purchase',
                    'source_type' => ReturnProduct::class,
                    'source_id' => $returnProduct->id,
                    'cabang_id' => $cabangId,
                ]);
            }

            foreach ($creditTotals as $data) {
                JournalEntry::create([
                    'coa_id' => $data['coa']->id,
                    'date' => $date,
                    'reference' => $returnProduct->return_number,
                    'description' => 'Product Return - Inventory Reduction for ' . $returnProduct->return_number,
                    'debit' => 0,
                    'credit' => round($data['amount'], 2),
                    'journal_type' => 'purchase',
                    'source_type' => ReturnProduct::class,
                    'source_id' => $returnProduct->id,
                    'cabang_id' => $cabangId,
                ]);
            }
        }
    }

    public function createReturnProduct($fromModel, $data)
    {
        return $fromModel->returnProduct()->create($data);
    }

    public function generateReturnNumber()
    {
        $date = now()->format('Ymd');
        $prefix = 'RN-' . $date . '-';

        do {
            $random = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $candidate = $prefix . $random;
            $exists = ReturnProduct::where('return_number', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }

    /**
     * Handle different return actions based on user selection
     */
    private function handleReturnAction($returnProduct)
    {
        $action = $returnProduct->return_action ?? 'reduce_quantity_only';

        switch ($action) {
            case 'reduce_quantity_only':
                // Only reduce quantity, don't close anything automatically
                break;

            case 'close_do_partial':
                // Force close DO regardless of remaining quantity
                $this->forceCloseDeliveryOrder($returnProduct);
                break;

            case 'close_so_complete':
                // Force close both DO and SO
                $this->forceCloseDeliveryOrder($returnProduct);
                $this->forceCloseRelatedSalesOrders($returnProduct);
                break;
        }
    }

    /**
     * Force close delivery order regardless of remaining quantity
     */
    private function forceCloseDeliveryOrder($returnProduct)
    {
        $fromModel = $returnProduct->fromModel;

        if ($fromModel instanceof \App\Models\DeliveryOrder) {
            $fromModel->update([
                'status' => 'completed',
                'notes' => ($fromModel->notes ? $fromModel->notes . ' | ' : '') . 'Force closed due to return action (RN: ' . $returnProduct->return_number . ')'
            ]);
        }
    }

    /**
     * Force close related sales orders
     */
    private function forceCloseRelatedSalesOrders($returnProduct)
    {
        $fromModel = $returnProduct->fromModel;

        if ($fromModel instanceof \App\Models\DeliveryOrder) {
            foreach ($fromModel->salesOrders as $saleOrder) {
                if (in_array($saleOrder->status, ['confirmed', 'approved'])) {
                    // Instead of direct 'completed', use 'request_close' for approval workflow
                    $saleOrder->update([
                        'status' => 'request_close',
                        'request_close_by' => $returnProduct->created_by,
                        'request_close_at' => now(),
                        'reason_close' => 'Force close requested due to return action (RN: ' . $returnProduct->return_number . ')'
                    ]);

                    // Log the request close action
                    Log::info('SO close requested due to return action', [
                        'so_number' => $saleOrder->so_number,
                        'return_number' => $returnProduct->return_number,
                        'requested_by' => $returnProduct->created_by,
                        'reason' => 'Return action: close_so_complete'
                    ]);
                }
            }
        }
    }

    /**
     * Check if all quantities are returned and close SO/DO partial if needed (legacy method)
     */
    public function checkAndClosePartialOrders($returnProduct)
    {
        $fromModel = $returnProduct->fromModel;

        if ($fromModel instanceof \App\Models\DeliveryOrder) {
            $this->checkAndCloseDeliveryOrderPartial($returnProduct, $fromModel);
        } elseif ($fromModel instanceof \App\Models\PurchaseReceipt) {
            // Handle purchase receipt if needed
            $this->checkAndClosePurchaseReceiptPartial($returnProduct, $fromModel);
        }
    }

    /**
     * Check and close Delivery Order partial if all items are fully returned
     */
    private function checkAndCloseDeliveryOrderPartial($returnProduct, $deliveryOrder)
    {
        $allItemsReturned = true;

        foreach ($deliveryOrder->deliveryOrderItem as $doItem) {
            if ($doItem->quantity > 0) {
                $allItemsReturned = false;
                break;
            }
        }

        if ($allItemsReturned) {
            // Close delivery order as completed due to full return
            $deliveryOrder->update([
                'status' => 'completed',
                'notes' => ($deliveryOrder->notes ? $deliveryOrder->notes . ' | ' : '') . 'Closed due to full return (RN: ' . $returnProduct->return_number . ')'
            ]);

            // Also close related sales orders if all delivery orders are completed
            $this->checkAndCloseRelatedSalesOrders($deliveryOrder);
        }
    }

    /**
     * Check and close related Sales Orders if all their delivery orders are completed
     */
    private function checkAndCloseRelatedSalesOrders($deliveryOrder)
    {
        foreach ($deliveryOrder->salesOrders as $saleOrder) {
            $allDOCompleted = true;

            foreach ($saleOrder->deliveryOrder as $do) {
                if ($do->status !== 'completed') {
                    $allDOCompleted = false;
                    break;
                }
            }

            if ($allDOCompleted && in_array($saleOrder->status, ['confirmed', 'approved'])) {
                $saleOrder->update([
                    'status' => 'completed',
                    'reason_close' => 'All delivery orders completed due to returns'
                ]);
            }
        }
    }

    /**
     * Check and close Purchase Receipt partial if all items are fully returned
     */
    private function checkAndClosePurchaseReceiptPartial($returnProduct, $purchaseReceipt)
    {
        // Implement logic for purchase receipt if needed
        // Similar to delivery order logic but for purchase receipts
    }
}
