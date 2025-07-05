<?php

namespace App\Services;

use App\Http\Controllers\HelperController;
use App\Models\Currency;
use App\Models\SaleOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class SalesOrderService
{
    public function updateTotalAmount($salesOrder)
    {
        $total_amount = 0;
        foreach ($salesOrder->saleOrderItem as $item) {
            $total_amount += HelperController::hitungSubtotal($item->quantity, $item->unit_price, $item->discount, $item->tax);
        }

        return $salesOrder->update([
            'total_amount' => $total_amount
        ]);
    }

    public function confirm($salesOrder)
    {
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
}
