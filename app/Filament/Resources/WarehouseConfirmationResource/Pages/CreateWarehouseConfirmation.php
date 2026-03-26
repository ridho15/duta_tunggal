<?php

namespace App\Filament\Resources\WarehouseConfirmationResource\Pages;

use App\Filament\Resources\WarehouseConfirmationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateWarehouseConfirmation extends CreateRecord
{
    protected static string $resource = WarehouseConfirmationResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $type = $data['confirmation_type'] ?? 'sales_order';

        if ($type === 'sales_order') {
            $soId = $data['so_id_virtual'] ?? null;
            $saleOrder = \App\Models\SaleOrder::find($soId);
            if (!$saleOrder) {
                Log::warning('CreateWarehouseConfirmation failed: sales order not found', [
                    'so_id_virtual' => $soId,
                    'confirmation_type' => $type,
                    'user_id' => Auth::id(),
                    'payload_keys' => array_keys($data),
                ]);
                \Filament\Notifications\Notification::make()
                    ->title('Sales Order Tidak Ditemukan')
                    ->body('Sales Order yang dipilih tidak ditemukan. Pastikan data SO sudah benar sebelum membuat konfirmasi gudang.')
                    ->danger()
                    ->send();
                $this->halt();
            }
            $data['confirmation_items_data'] = $data['confirmation_items'] ?? [];
            return [
                'confirmable_type'  => \App\Models\SaleOrder::class,
                'confirmable_id'    => $soId,
                'confirmation_type' => 'sales_order',
                'note'              => $data['note'] ?? null,
                'status'            => 'request',
            ];
        } elseif ($type === 'manufacturing_order') {
            return [
                'confirmable_type'  => \App\Models\ManufacturingOrder::class,
                'confirmable_id'    => $data['mo_id_virtual'] ?? null,
                'confirmation_type' => 'manufacturing_order',
                'note'              => $data['note'] ?? null,
                'status'            => 'confirmed',
                'confirmed_by'      => \Illuminate\Support\Facades\Auth::id(),
                'confirmed_at'      => now(),
            ];
        } else {
            // delivery_order
            return [
                'confirmable_type'  => \App\Models\DeliveryOrder::class,
                'confirmable_id'    => $data['do_id_virtual'] ?? null,
                'confirmation_type' => 'delivery_order',
                'note'              => $data['note'] ?? null,
                'status'            => 'request',
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
