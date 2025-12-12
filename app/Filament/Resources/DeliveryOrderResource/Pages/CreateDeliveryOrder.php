<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Services\DeliveryOrderItemService;
use App\Services\DeliveryOrderService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateDeliveryOrder extends CreateRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    // Store salesOrderIds for use in afterCreate
    protected array $processedSalesOrderIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Normalize and validate that salesOrders is not empty
        // Filament/Livewire may send values as string, nested arrays or single values.
        $salesOrderIds = $data['salesOrders'] ?? $data['sales_orders'] ?? $data['salesOrder'] ?? [];

        // Alternative: extract from deliveryOrderItem if salesOrders is not present
        if (empty($salesOrderIds) && !empty($data['deliveryOrderItem'])) {
            $salesOrderIds = collect($data['deliveryOrderItem'])
                ->pluck('sale_order_item_id')
                ->map(function ($itemId) {
                    $saleOrderItem = \App\Models\SaleOrderItem::find($itemId);
                    return $saleOrderItem ? $saleOrderItem->sale_order_id : null;
                })
                ->filter()
                ->unique()
                ->values()
                ->toArray();
        }

        if (is_string($salesOrderIds)) {
            $decoded = json_decode($salesOrderIds, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $salesOrderIds = $decoded;
            }
        }

        // Ensure we have a flat array of integer ids
        $salesOrderIds = \Illuminate\Support\Arr::wrap($salesOrderIds);
        $salesOrderIds = collect($salesOrderIds)->flatten()->filter(function ($v) {
            return $v !== null && $v !== '';
        })->map(function ($v) {
            return is_numeric($v) ? (int) $v : $v; // Convert to integer if numeric
        })->values()->toArray();

        // Store for use in afterCreate
        $this->processedSalesOrderIds = $salesOrderIds;
        if (empty($salesOrderIds)) {
            \Filament\Notifications\Notification::make()
                ->title('Validation Error')
                ->body('Minimal 1 Sales Order harus dipilih untuk membuat Delivery Order.')
                ->danger()
                ->send();

            // Prevent form submission by throwing validation exception
            $validator = Validator::make([], []);
            $validator->errors()->add('salesOrders', 'Minimal 1 Sales Order harus dipilih untuk membuat Delivery Order.');
            throw new ValidationException($validator);
        }

        // Validate warehouse confirmation for all selected sales orders
        if (!empty($salesOrderIds)) {
            foreach ($salesOrderIds as $salesOrderId) {
                $salesOrder = \App\Models\SaleOrder::find($salesOrderId);
                if (!$salesOrder) {
                    \Filament\Notifications\Notification::make()
                        ->title('Validation Error')
                        ->body("Sales Order dengan ID {$salesOrderId} tidak ditemukan.")
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('salesOrders', "Sales Order dengan ID {$salesOrderId} tidak ditemukan.");
                    throw new ValidationException($validator);
                }

                if ($salesOrder->status !== 'confirmed') {
                    \Filament\Notifications\Notification::make()
                        ->title('Validation Error')
                        ->body("Sales Order {$salesOrder->so_number} belum dikonfirmasi warehouse (status: {$salesOrder->status}).")
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('salesOrders', "Sales Order {$salesOrder->so_number} belum dikonfirmasi warehouse (status: {$salesOrder->status}).");
                    throw new ValidationException($validator);
                }

                if (!$salesOrder->warehouse_confirmed_at) {
                    \Filament\Notifications\Notification::make()
                        ->title('Validation Error')
                        ->body("Sales Order {$salesOrder->so_number} belum memiliki tanggal konfirmasi warehouse.")
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('salesOrders', "Sales Order {$salesOrder->so_number} belum memiliki tanggal konfirmasi warehouse.");
                    throw new ValidationException($validator);
                }
            }
            
            // Set warehouse_id from the first sales order item (assuming all items from same warehouse)
            $firstSalesOrder = \App\Models\SaleOrder::find($salesOrderIds[0]);
            if ($firstSalesOrder) {
                $firstItem = $firstSalesOrder->saleOrderItem()->first();
                if ($firstItem && !isset($data['warehouse_id'])) {
                    $data['warehouse_id'] = $firstItem->warehouse_id;
                }
            }
        }

        // Validate delivery order items against all selected sales orders
        $deliveryItems = $data['deliveryOrderItem'] ?? [];

        // If deliveryOrderItem is empty but salesOrders are selected, auto-populate from sales orders
        if (empty($deliveryItems) && !empty($salesOrderIds)) {
            $listSaleOrder = \App\Models\SaleOrder::whereIn('id', $salesOrderIds)->get();
            $deliveryItems = [];

            foreach ($listSaleOrder as $saleOrder) {
                foreach ($saleOrder->saleOrderItem as $saleOrderItem) {
                    $remainingQty = $saleOrderItem->remaining_quantity;
                    // Only add items that still have remaining quantity
                    if ($remainingQty > 0) {
                        $deliveryItems[] = [
                            'options_from' => 2,
                            'sale_order_item_id' => $saleOrderItem->id,
                            'product_id' => $saleOrderItem->product_id,
                            'quantity' => $remainingQty,
                            'reason' => '',
                        ];
                    }
                }
            }
            
            $data['deliveryOrderItem'] = $deliveryItems;
        }

        // Validate delivery order items against all selected sales orders
        $validDeliveryItems = collect($deliveryItems)->filter(function ($item) {
            return !empty($item['quantity']) && $item['quantity'] > 0;
        });

        \Illuminate\Support\Facades\Log::info('Delivery Order Create - validDeliveryItems count: ' . $validDeliveryItems->count());

        if ($validDeliveryItems->isEmpty()) {
            \Filament\Notifications\Notification::make()
                ->title('Validation Error')
                ->body('Delivery Order harus memiliki minimal 1 item dengan quantity lebih dari 0 untuk dibuat.')
                ->danger()
                ->send();

            $validator = Validator::make([], []);
            $validator->errors()->add('deliveryOrderItem', 'Delivery Order harus memiliki minimal 1 item dengan quantity lebih dari 0 untuk dibuat.');
            throw new ValidationException($validator);
        }

        if ($validDeliveryItems->isNotEmpty()) {
            // Get all sale order items from selected sales orders
            $saleOrderItems = \App\Models\SaleOrderItem::whereIn('sale_order_id', $salesOrderIds)
                ->with('product')
                ->get()
                ->keyBy('id');

            // Validate each delivery item
            foreach ($validDeliveryItems as $index => $item) {
                $saleOrderItemId = $item['sale_order_item_id'] ?? null;
                $quantity = (float) ($item['quantity'] ?? 0);

                if (!$saleOrderItemId) {
                    \Filament\Notifications\Notification::make()
                        ->title('Validation Error')
                        ->body("Item delivery order #{$index}: Sale order item wajib dipilih.")
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('deliveryOrderItem', "Item delivery order #{$index}: Sale order item wajib dipilih.");
                    throw new ValidationException($validator);
                }

                $saleOrderItem = $saleOrderItems->get($saleOrderItemId);
                if (!$saleOrderItem) {
                    \Filament\Notifications\Notification::make()
                        ->title('Validation Error')
                        ->body("Item delivery order #{$index}: Sale order item tidak ditemukan atau tidak terkait dengan sales orders yang dipilih.")
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('deliveryOrderItem', "Item delivery order #{$index}: Sale order item tidak ditemukan atau tidak terkait dengan sales orders yang dipilih.");
                    throw new ValidationException($validator);
                }

                if ($quantity <= 0) {
                    \Filament\Notifications\Notification::make()
                        ->title('Validation Error')
                        ->body("Item delivery order #{$index}: Quantity harus lebih besar dari 0.")
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('deliveryOrderItem', "Item delivery order #{$index}: Quantity harus lebih besar dari 0.");
                    throw new ValidationException($validator);
                }

                if ($quantity > $saleOrderItem->quantity) {
                    $productName = $saleOrderItem->product->name ?? 'produk';
                    \Filament\Notifications\Notification::make()
                        ->title('Validation Error')
                        ->body("Item delivery order #{$index}: Quantity untuk {$productName} ({$quantity}) melebihi quantity pada sales order ({$saleOrderItem->quantity}).")
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('deliveryOrderItem', "Item delivery order #{$index}: Quantity untuk {$productName} ({$quantity}) melebihi quantity pada sales order ({$saleOrderItem->quantity}).");
                    throw new ValidationException($validator);
                }

                // Additional validation: Check against remaining quantity
                if ($quantity > $saleOrderItem->remaining_quantity) {
                    $productName = $saleOrderItem->product->name ?? 'produk';
                    \Filament\Notifications\Notification::make()
                        ->title('Validation Error')
                        ->body("Item delivery order #{$index}: Quantity untuk {$productName} ({$quantity}) melebihi sisa quantity yang tersedia ({$saleOrderItem->remaining_quantity}).")
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('deliveryOrderItem', "Item delivery order #{$index}: Quantity untuk {$productName} ({$quantity}) melebihi sisa quantity yang tersedia ({$saleOrderItem->remaining_quantity}).");
                    throw new ValidationException($validator);
                }
            }

            // Check for duplicate sale order items
            $saleOrderItemIds = collect($deliveryItems)->pluck('sale_order_item_id')->filter();
            $duplicates = $saleOrderItemIds->duplicates();
            if ($duplicates->isNotEmpty()) {
                \Filament\Notifications\Notification::make()
                    ->title('Validation Error')
                    ->body("Tidak boleh ada item sales order yang duplikat dalam satu delivery order.")
                    ->danger()
                    ->send();

                $validator = Validator::make([], []);
                $validator->errors()->add('deliveryOrderItem', "Tidak boleh ada item sales order yang duplikat dalam satu delivery order.");
                throw new ValidationException($validator);
            }
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $deliveryOrder = $this->record;

        // Save salesOrders relationship using processed salesOrderIds
        if (!empty($this->processedSalesOrderIds)) {
            $deliveryOrder->salesOrders()->sync($this->processedSalesOrderIds);
        }

        // Note: Delivery order items are now saved automatically by Filament relationship repeater
        // This allows for approval/review before committing to inventory reduction
    }
}
