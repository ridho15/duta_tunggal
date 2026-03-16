<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\HelperController;
use App\Models\Currency;
use App\Models\InventoryStock;
use App\Models\SaleOrder;
use App\Models\StockReservation;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SalesOrderService
{
    public function updateTotalAmount($salesOrder)
    {
        $total_amount = 0;
        foreach ($salesOrder->saleOrderItem as $item) {
            $total_amount += HelperController::hitungSubtotal(
                $item->quantity,
                $item->unit_price,
                $item->discount,
                $item->tax,
                $item->tipe_pajak ?? 'Inklusif'
            );
        }

        return $salesOrder->update([
            'total_amount' => $total_amount
        ]);
    }

    public function confirm($salesOrder)
    {
        try {
            DB::transaction(function () use ($salesOrder) {
                // Validate and reserve stock with pessimistic locking to prevent concurrent negative stock
                foreach ($salesOrder->saleOrderItem as $item) {
                    $inventoryStock = InventoryStock::where('product_id', $item->product_id)
                        ->where('warehouse_id', $item->warehouse_id)
                        ->lockForUpdate()
                        ->first();

                    if (!$inventoryStock) {
                        throw new InsufficientStockException("No inventory stock found for product {$item->product_id} in warehouse {$item->warehouse_id}");
                    }

                    $availableForReservation = $inventoryStock->qty_available - $inventoryStock->qty_reserved;
                    if ($availableForReservation < $item->quantity) {
                        $productName = $item->product ? $item->product->name : $item->product_id;
                        throw new InsufficientStockException("Insufficient stock for product {$productName}. Available: {$availableForReservation}, Requested: {$item->quantity}");
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

                $salesOrder->update(['status' => 'confirmed']);
            });

            return true;
        } catch (InsufficientStockException $e) {
            Notification::make()
                ->title('Stok Tidak Cukup')
                ->body($e->getMessage())
                ->danger()
                ->send();

            throw $e;
        }
    }

    /**
     * Cancel a sale order and release any stock reservations.
     */
    public function cancel($salesOrder)
    {
        DB::transaction(function () use ($salesOrder) {
            // Release all stock reservations for this SO
            StockReservation::where('sale_order_id', $salesOrder->id)->each(function ($reservation) {
                $reservation->delete(); // triggers StockReservationObserver::deleted → restores qty_available
            });

            $salesOrder->update(['status' => 'canceled']);
        });

        return true;
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
        $prefix = 'SO-';

        // Find the highest existing sequence number globally (ignoring branch scopes)
        $max = SaleOrder::withoutGlobalScopes()
            ->where('so_number', 'like', $prefix . '%')
            ->max('so_number');

        $next = 1;
        if ($max !== null) {
            $suffix = substr((string) $max, strlen($prefix));
            if (is_numeric($suffix)) {
                $next = (int) $suffix + 1;
            }
        }

        // Guard against concurrent inserts
        do {
            $candidate = $prefix . str_pad($next, 5, '0', STR_PAD_LEFT);
            $exists = SaleOrder::withoutGlobalScopes()
                ->where('so_number', $candidate)
                ->exists();
            if ($exists) {
                $next++;
            }
        } while ($exists);

        return $candidate;
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
        $confirmationStatus = match ($overallStatus) {
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

        // Resolve a real driver and vehicle to satisfy NOT NULL FK constraints
        $driverId  = $deliveryData['driver_id']  ?? \App\Models\Driver::first()?->id;
        $vehicleId = $deliveryData['vehicle_id'] ?? \App\Models\Vehicle::first()?->id;

        if (!$driverId || !$vehicleId) {
            \Filament\Notifications\Notification::make()
                ->title('Gagal Membuat Delivery Order')
                ->danger()
                ->body('Tidak ditemukan driver atau kendaraan di database. Silakan pastikan data master sudah terisi untuk auto-creation Delivery Order.')
                ->send();
            throw new \Exception('A Driver and a Vehicle must exist before a Delivery Order can be created.');
        }

        $deliveryOrder = $saleOrder->deliveryOrder()->create([
            'do_number'     => $this->generateDoNumber(),
            'delivery_date' => $deliveryData['delivery_date'],
            'warehouse_id'  => $warehouseId,
            'driver_id'     => $driverId,
            'vehicle_id'    => $vehicleId,
            'status'        => 'draft',
            'notes'         => $deliveryData['notes'] ?? null,
            'created_by'    => Auth::user()->id,
        ]);

        // Copy confirmed items to delivery order
        foreach ($saleOrder->warehouseConfirmation->warehouseConfirmationItems as $confirmedItem) {
            if ($confirmedItem->status === 'confirmed' || $confirmedItem->status === 'partial_confirmed') {
                $deliveryOrder->deliveryOrderItem()->create([
                    'sale_order_item_id' => $confirmedItem->sale_order_item_id,
                    'product_id'         => $confirmedItem->saleOrderItem->product_id,
                    'quantity'           => $confirmedItem->confirmed_qty,
                    'warehouse_id'       => $confirmedItem->warehouse_id,
                    'rak_id'             => $confirmedItem->rak_id,
                ]);
            }
        }

        return true;
    }

    public function generateDoNumber()
    {
        return \App\Services\DeliveryOrderService::generateStaticDoNumber();
    }
}
