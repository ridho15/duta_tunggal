<?php

namespace App\Observers;

use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;

class PurchaseReturnObserver
{
    protected $purchaseReturnService;

    public function __construct(PurchaseReturnService $purchaseReturnService)
    {
        $this->purchaseReturnService = $purchaseReturnService;
    }

    /**
     * Handle the PurchaseReturn "updated" event.
     */
    public function updated(PurchaseReturn $purchaseReturn): void
    {
        // Auto-generate nota_retur if not set
        if (!$purchaseReturn->nota_retur && $purchaseReturn->return_date) {
            $purchaseReturn->update([
                'nota_retur' => $this->purchaseReturnService->generateNotaRetur()
            ]);
        }
    }
}