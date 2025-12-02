<?php

namespace App\Services;

use App\Models\ReturnProduct;

class ReturnProductService
{
    public function updateQuantityFromModel($returnProduct)
    {
        foreach ($returnProduct->returnProductItem as $returnProductItem) {
            $defaultQuantity = $returnProductItem->fromItemModel->quantity;
            $returnProductItem->fromItemModel()->update([
                'quantity' => $defaultQuantity - $returnProductItem->quantity
            ]);
        }

        $returnProduct->update([
            'status' => 'approved'
        ]);

        // Check if all quantities are returned and close SO/DO partial if needed
        $this->handleReturnAction($returnProduct);

        return $returnProduct;
    }

    public function createReturnProduct($fromModel, $data)
    {
        return $fromModel->returnProduct()->create($data);
    }

    public function generateReturnNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $last = ReturnProduct::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($last) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($last->return_number, -4));
            $number = $lastNumber + 1;
        }

        return 'RN-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
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
                    $saleOrder->update([
                        'status' => 'completed',
                        'reason_close' => 'Force closed due to return action (RN: ' . $returnProduct->return_number . ')'
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
