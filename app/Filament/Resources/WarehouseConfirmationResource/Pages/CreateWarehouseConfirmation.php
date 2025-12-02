<?php

namespace App\Filament\Resources\WarehouseConfirmationResource\Pages;

use App\Filament\Resources\WarehouseConfirmationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateWarehouseConfirmation extends CreateRecord
{
    protected static string $resource = WarehouseConfirmationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['confirmation_type'] === 'sales_order') {
            // Handle Sales Order confirmation with simplified structure
            $saleOrder = \App\Models\SaleOrder::find($data['sale_order_id']);
            if (!$saleOrder) {
                throw new \Exception('Sales Order not found');
            }

            // Store confirmation items data for later processing
            $data['confirmation_items_data'] = $data['confirmation_items'] ?? [];

            // Return basic data for warehouse confirmation record
            return [
                'confirmation_type' => $data['confirmation_type'],
                'sale_order_id' => $data['sale_order_id'],
                'notes' => $data['notes'] ?? null,
                'status' => 'request', // Start with request status
            ];
        } else {
            // Handle Manufacturing Order confirmation (existing logic)
            return [
                'confirmation_type' => $data['confirmation_type'],
                'manufacturing_order_id' => $data['manufacturing_order_id'],
                'notes' => $data['notes'] ?? null,
                'status' => 'confirmed',
                'confirmed_by' => \Illuminate\Support\Facades\Auth::id(),
                'confirmed_at' => now(),
            ];
        }
    }

    protected function afterCreate(): void
    {
        $record = $this->record;
        $data = $this->form->getState();

        if (isset($data['confirmation_items_data'])) {
            foreach ($data['confirmation_items_data'] as $itemData) {
                // Create confirmation item directly
                $record->warehouseConfirmationItems()->create([
                    'sale_order_item_id' => $itemData['sale_order_item_id'],
                    'product_name' => $itemData['product_name'],
                    'requested_qty' => $itemData['requested_qty'],
                    'confirmed_qty' => $itemData['confirmed_qty'],
                    'warehouse_id' => $itemData['warehouse_id'],
                    'rak_id' => $itemData['rak_id'] ?? null,
                    'status' => $itemData['status'] ?? 'request',
                ]);
            }
        }
    }
}
