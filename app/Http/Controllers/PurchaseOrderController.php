<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function cetakPdf($id)
    {
        $purchaseOrder = PurchaseOrder::find($id);
        if (!$purchaseOrder) {
            return redirect()->with('fail', 'Data purchase order tidak ditemukan !');
        }

        return HelperController::cetakPO($purchaseOrder);
    }
}
