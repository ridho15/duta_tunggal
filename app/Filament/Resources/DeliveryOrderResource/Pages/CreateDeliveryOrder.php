<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Services\DeliveryOrderItemService;
use App\Services\DeliveryOrderService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDeliveryOrder extends CreateRecord
{
    protected static string $resource = DeliveryOrderResource::class;
    
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Validate warehouse confirmation for all selected sales orders
        $salesOrderIds = $data['salesOrders'] ?? [];
        if (!empty($salesOrderIds)) {
            foreach ($salesOrderIds as $salesOrderId) {
                $salesOrder = \App\Models\SaleOrder::find($salesOrderId);
                if (!$salesOrder) {
                    throw new \Exception("Sales Order dengan ID {$salesOrderId} tidak ditemukan.");
                }
                
                if ($salesOrder->status !== 'confirmed') {
                    throw new \Exception("Sales Order {$salesOrder->so_number} belum dikonfirmasi warehouse (status: {$salesOrder->status}).");
                }
                
                if (!$salesOrder->warehouse_confirmed_at) {
                    throw new \Exception("Sales Order {$salesOrder->so_number} belum memiliki tanggal konfirmasi warehouse.");
                }
            }
            
            // Set warehouse_id from the first sales order (assuming all sales orders from same warehouse)
            $firstSalesOrder = \App\Models\SaleOrder::find($salesOrderIds[0]);
            if ($firstSalesOrder && !$data['warehouse_id']) {
                $data['warehouse_id'] = $firstSalesOrder->warehouse_id;
            }
        }

        // Additional validation before creating
        app(DeliveryOrderItemService::class)->validateItemsForSalesOrder(
            (int) ($data['salesOrders'] ?? 0),
            $data['deliveryOrderItem'] ?? []
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $deliveryOrder = $this->record;
        
        // Note: Delivery order posting to ledger happens on completion, not creation
        // This allows for approval/review before committing to inventory reduction
    }
}
