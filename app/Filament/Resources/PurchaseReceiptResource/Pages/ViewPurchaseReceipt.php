<?php

namespace App\Filament\Resources\PurchaseReceiptResource\Pages;

use App\Filament\Resources\PurchaseReceiptResource;
use Filament\Actions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseReceipt extends ViewRecord
{
    protected static string $resource = PurchaseReceiptResource::class;
    protected static string $view = 'filament.resources.purchase-receipt-resource.pages.view-purchase-receipt';

    public function mount($record): void
    {
        parent::mount($record);

        $this->record->loadMissing([
            'purchaseOrder.supplier',
            'receivedBy',
            'currency',
            'purchaseReceiptItem.product',
            'purchaseReceiptItem.purchaseOrderItem.product',
            'purchaseReceiptItem.warehouse',
            'purchaseReceiptItem.rak',
            'purchaseReceiptItem.qualityControl.inspectedBy',
            'purchaseReceiptItem.qualityControl.product',
            'purchaseReceiptItem.qualityControl.warehouse',
            'purchaseReceiptItem.qualityControl.rak',
            'purchaseReceiptBiaya.coa',
        ]);
    }

    protected function getActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square'),
            DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }
}
