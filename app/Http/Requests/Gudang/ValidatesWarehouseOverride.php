<?php

namespace App\Http\Requests\Gudang;

use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;

/** Gudang terima GRN yang berbeda dari gudang tujuan PO wajib disertai alasan (tercatat di GRN). */
trait ValidatesWarehouseOverride
{
    protected function validateWarehouseOverride($validator, ?int $purchaseOrderId): void
    {
        $poWarehouseId = $purchaseOrderId ? PurchaseOrder::whereKey($purchaseOrderId)->value('warehouse_id') : null;
        $warehouseIds = collect($this->input('items', []))->pluck('warehouse_id')->all();

        if (PurchaseReceipt::differsFromPoWarehouse($poWarehouseId, $warehouseIds) && blank(trim((string) $this->input('warehouse_override_reason')))) {
            $validator->errors()->add(
                'warehouse_override_reason',
                'Gudang terima berbeda dari gudang tujuan PO. Isi alasan perubahan gudang.'
            );
        }
    }
}
