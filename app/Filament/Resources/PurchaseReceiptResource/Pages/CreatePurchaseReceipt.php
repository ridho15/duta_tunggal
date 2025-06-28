<?php

namespace App\Filament\Resources\PurchaseReceiptResource\Pages;

use App\Filament\Resources\PurchaseReceiptResource;
use App\Services\QualityControlService;
use Filament\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseReceipt extends CreateRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected function afterCreate()
    {
        $qualityControlService = app(QualityControlService::class);
        $purchaseReceipt = $this->getRecord();
        $data['inspected_by'] = $purchaseReceipt->received_by;
        foreach ($purchaseReceipt->purchaseReceiptItem as $purchaseReceiptItem) {
            if ($purchaseReceiptItem->purchaseOrderItem && $purchaseReceiptItem->purchaseOrderItem->quantity == $purchaseReceiptItem->qty_accepted) {
                $qualityControlService->createQCFromPurchaseReceiptItem($purchaseReceiptItem, $data);
                $purchaseReceiptItem->update([
                    'is_sent' => 1
                ]);
            }
        }
    }
}
