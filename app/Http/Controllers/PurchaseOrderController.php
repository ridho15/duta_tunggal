<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Pembelian\PurchaseOrderController as BasePurchaseOrderController;
use App\Models\PurchaseOrder;

class PurchaseOrderController extends BasePurchaseOrderController
{
    /**
     * Legacy cetakPdf method kept for backward compatibility.
     *
     * @param int|string $id
     * @return mixed
     */
    public function cetakPdf($id)
    {
        $purchaseOrder = PurchaseOrder::find($id);
        if (!$purchaseOrder) {
            return redirect()->back()->with('fail', 'Data purchase order tidak ditemukan !');
        }

        return HelperController::cetakPO($purchaseOrder);
    }
}
