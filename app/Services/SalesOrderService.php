<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\SaleOrder;
use App\Models\StockReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SalesOrderService
{
    public function updateTotalAmount($salesOrder)
    {
        $total_amount = 0;
        foreach ($salesOrder->saleOrderItem as $item) {
            $total_amount += HelperController::hitungSubtotal($item->quantity, $item->unit_price, $item->discount, $item->tax, 'Inklusif');
        }

        return $salesOrder->update([
            'total_amount' => $total_amount
        ]);
    }

    public function confirm($salesOrder)
    {
        // Validate stock availability before reserving
        foreach ($salesOrder->saleOrderItem as $item) {
            $inventoryStock = InventoryStock::where('product_id', $item->product_id)
                ->where('warehouse_id', $item->warehouse_id)
                ->first();

            if (!$inventoryStock) {
                throw new \Exception("No inventory stock found for product {$item->product_id} in warehouse {$item->warehouse_id}");
            }

            $availableForReservation = $inventoryStock->qty_available - $inventoryStock->qty_reserved;
            if ($availableForReservation < $item->quantity) {
                $productName = $item->product ? $item->product->name : $item->product_id;
                throw new \Exception("Insufficient stock for product {$productName}. Available: {$availableForReservation}, Requested: {$item->quantity}");
            }
        }

        // Reserve stock for each item
        foreach ($salesOrder->saleOrderItem as $item) {
            StockReservation::create([
                'sale_order_id' => $salesOrder->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'warehouse_id' => $item->warehouse_id,
                'rak_id' => $item->rak_id,
            ]);
        }

        return $salesOrder->update([
            'status' => 'confirmed'
        ]);
    }

    public function requestApprove($saleOrder)
    {
        return $saleOrder->update([
            'status' => 'request_approve',
            'request_approve_by' => Auth::user()->id,
            'request_approve_at' => Carbon::now()
        ]);
    }

    public function requestClose($saleOrder)
    {
        return $saleOrder->update([
            'status' => 'request_close',
            'request_close_by' => Auth::user()->id,
            'request_close_at' => Carbon::now()
        ]);
    }

    public function approve($saleOrder)
    {
        return $saleOrder->update([
            'status' => 'approved',
            'approve_by' => Auth::user()->id,
            'approve_at' => Carbon::now()
        ]);
    }

    public function close($saleOrder)
    {
        return $saleOrder->update([
            'status' => 'closed',
            'close_by' => Auth::user()->id,
            'close_at' => Carbon::now()
        ]);
    }

    public function reject($saleOrder)
    {
        return $saleOrder->update([
            'status' => 'reject',
            'reject_by' => Auth::user()->id,
            'reject_at' => Carbon::now()
        ]);
    }

    public function completed($saleOrder)
    {
        return $saleOrder->update([
            'status' => 'completed',
            'completed_at' => Carbon::now()
        ]);
    }

    public function createPurchaseOrder($saleOrder, $data)
    {
        // Create Purchase order
        $purchaseOrder = $saleOrder->purchaseOrder()->create([
            'po_number' => $data['po_number'],
            'supplier_id' => $data['supplier_id'],
            'order_date' => $data['order_date'],
            'note' => $data['note'],
            'warehouse_id' => $data['warehouse_id'],
            'delivery_date' => $data['delivery_date'],
            'expected_date' => $data['expected_date'],
            'tempo_hutang' => $data['tempo_hutang'],
        ]);

        foreach ($saleOrder->saleOrderItem as $saleOrderItem) {
            $saleOrderItem->purchaseOrderItem()->create([
                'purchase_order_id' => $purchaseOrder->id,
                'product_id' => $saleOrderItem->product_id,
                'quantity' => $saleOrderItem->quantity,
                'currency_id' => Currency::where('name', 'Rupiah')->first()->id,
                'unit_price' => $saleOrderItem->product->sell_price,
                'discount' => 0,
                'tax' => 0,
            ]);
        }
        return $saleOrder;
    }

    public function generateSoNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa PO pada hari ini
        $lastSaleOrder = SaleOrder::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($lastSaleOrder) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($lastSaleOrder->so_number, -4));
            $number = $lastNumber + 1;
        }

        return 'RN-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    public function titipSaldo($saleOrder, $data)
    {
        if ($saleOrder->customer->deposit->id == null) {
            $deposit = $saleOrder->customer->deposit()->create([
                'amount' => $data['titip_saldo'],
                'used_amount' => 0,
                'remaining_amount' => $data['titip_saldo'],
                'coa_id' => $data['coa_id'],
                'created_by' => Auth::user()->id,
            ]);
        } else {
            $deposit = $saleOrder->customer->deposit()->update([
                'amount' => $saleOrder->customer->deposit->amount + $data['titip_saldo'],
                'remaining_amount' => $saleOrder->customer->deposit->amount + $data['titip_saldo']
            ]);

            $saleOrder->customer->deposit->depositLog()->create([
                'deposit_id' => $deposit->id,
                'type' => 'add',
                'amount' => $data['titip_saldo'],
                'note' => $data['note'],
                'created_by' => Auth::user()->id,
            ]);

            $saleOrder->depositLog()->create([
                'deposit_id' => $deposit->id,
                'type' => 'add',
                'amount' => $data['titip_saldo'],
                'note' => $data['note'],
                'created_by' => Auth::user()->id,
            ]);
        }
    }

    public function confirmWarehouse($saleOrder, $confirmationData)
    {
        // Validate that SO is approved
        if ($saleOrder->status !== 'approved') {
            throw new \Exception('Sales Order must be approved before warehouse confirmation');
        }

        // Create warehouse confirmation record
        $confirmation = $saleOrder->warehouseConfirmation()->create([
            'status' => $confirmationData['status'] ?? 'confirmed',
            'notes' => $confirmationData['notes'] ?? null,
            'confirmed_by' => Auth::user()->id,
            'confirmed_at' => Carbon::now()
        ]);

        // Process each item
        foreach ($confirmationData['items'] as $itemData) {
            $confirmation->warehouseConfirmationItems()->create([
                'sale_order_item_id' => $itemData['sale_order_item_id'],
                'confirmed_qty' => $itemData['confirmed_qty'],
                'warehouse_id' => $itemData['warehouse_id'],
                'rak_id' => $itemData['rak_id'],
                'status' => $itemData['status']
            ]);
        }

        // Update SO status based on confirmation
        $overallStatus = $this->determineOverallStatus($confirmationData['items']);
        $saleOrder->update([
            'status' => $overallStatus,
            'warehouse_confirmed_at' => Carbon::now()
        ]);

        // Update warehouse confirmation status based on overall status
        $confirmationStatus = match($overallStatus) {
            'confirmed' => 'confirmed',
            'partial_confirmed' => 'partial_confirmed',
            'reject' => 'rejected',
            default => 'confirmed'
        };
        $confirmation->update(['status' => $confirmationStatus]);

        return true;
    }

    private function determineOverallStatus($items)
    {
        $allConfirmed = true;
        $allRejected = true;
        $hasPartial = false;

        foreach ($items as $item) {
            if ($item['status'] === 'confirmed') {
                $allRejected = false;
            } elseif ($item['status'] === 'partial_confirmed') {
                $allConfirmed = false;
                $allRejected = false;
                $hasPartial = true;
            } elseif ($item['status'] === 'rejected') {
                $allConfirmed = false;
            }
        }

        if ($allConfirmed) {
            return 'confirmed';
        } elseif ($allRejected) {
            return 'reject';
        } elseif ($hasPartial) {
            return 'partial_confirmed';
        }

        return 'confirmed'; // default
    }

    public function createDeliveryOrder($saleOrder, $deliveryData)
    {
        // Validate that SO is confirmed
        if (!in_array($saleOrder->status, ['confirmed', 'partial_confirmed'])) {
            throw new \Exception('Sales Order must be warehouse confirmed before creating delivery order');
        }

        // Create delivery order
        $warehouseId = $deliveryData['warehouse_id'] ?? $saleOrder->warehouseConfirmation->warehouseConfirmationItems->first()->warehouse_id ?? null;
        if (!$warehouseId) {
            throw new \Exception('Warehouse ID is required to create delivery order');
        }
        $deliveryOrder = $saleOrder->deliveryOrder()->create([
            'do_number' => $this->generateDoNumber(),
            'delivery_date' => $deliveryData['delivery_date'],
            'warehouse_id' => $warehouseId,
            'driver_id' => 1, // Default driver for testing
            'vehicle_id' => 1, // Default vehicle for testing
            'status' => 'draft',
            'notes' => $deliveryData['notes'] ?? null,
            'created_by' => Auth::user()->id
        ]);

        // Copy confirmed items to delivery order
        foreach ($saleOrder->warehouseConfirmation->warehouseConfirmationItems as $confirmedItem) {
            if ($confirmedItem->status === 'confirmed' || $confirmedItem->status === 'partial_confirmed') {
                $deliveryOrder->deliveryOrderItem()->create([
                    'sale_order_item_id' => $confirmedItem->sale_order_item_id,
                    'product_id' => $confirmedItem->saleOrderItem->product_id,
                    'qty' => $confirmedItem->confirmed_qty,
                    'warehouse_id' => $confirmedItem->warehouse_id,
                    'rak_id' => $confirmedItem->rak_id
                ]);
            }
        }

        return true;
    }

    public function generateDoNumber()
    {
        $date = now()->format('Ymd');

        // Hitung berapa DO pada hari ini
        $lastDeliveryOrder = \App\Models\DeliveryOrder::whereDate('created_at', now()->toDateString())
            ->orderBy('id', 'desc')
            ->first();

        $number = 1;

        if ($lastDeliveryOrder) {
            // Ambil nomor urut terakhir
            $lastNumber = intval(substr($lastDeliveryOrder->do_number, -4));
            $number = $lastNumber + 1;
        }

        return 'DO-' . $date . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
