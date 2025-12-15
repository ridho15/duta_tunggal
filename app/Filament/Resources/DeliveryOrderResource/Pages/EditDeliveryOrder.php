<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Services\DeliveryOrderItemService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EditDeliveryOrder extends EditRecord
{
    protected static string $resource = DeliveryOrderResource::class;

    public function resolveRecord($key): \Illuminate\Database\Eloquent\Model
    {
        return DeliveryOrder::with([
            'salesOrders',
            'deliveryOrderItem.saleOrderItem.product',
            'deliveryOrderItem.product'
        ])->findOrFail($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->color('primary')->icon('heroicon-o-eye')->label('Lihat'),
            DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure salesOrders field is populated with related sales order IDs
        if (!isset($data['salesOrders']) || empty($data['salesOrders'])) {
            $data['salesOrders'] = $this->record->salesOrders->pluck('id')->toArray();
        }
        
        return $data;
    }
    
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Validate that salesOrders is not empty
        $salesOrderIds = $data['salesOrders'] ?? [];
        if (empty($salesOrderIds)) {
            \Filament\Notifications\Notification::make()
                ->title('Validation Error')
                ->body('Minimal 1 Sales Order harus dipilih untuk Delivery Order.')
                ->danger()
                ->send();

            // Prevent form submission by throwing validation exception
            $validator = Validator::make([], []);
            $validator->errors()->add('salesOrders', 'Minimal 1 Sales Order harus dipilih untuk Delivery Order.');
            throw new ValidationException($validator);
        }

        // Additional validation before updating
        // Validate delivery order items against all selected sales orders
        $deliveryItems = $data['deliveryOrderItem'] ?? [];
        if (!empty($deliveryItems)) {
            // Get all sale order items from selected sales orders
            $saleOrderItems = \App\Models\SaleOrderItem::whereIn('sale_order_id', $salesOrderIds)
                ->with('product')
                ->get()
                ->keyBy('id');

            // Validate each delivery item
            foreach ($deliveryItems as $index => $item) {
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
                // For edit, we need to add back the current delivery order item's quantity to remaining_quantity
                $currentDeliveryOrderItem = $this->record->deliveryOrderItem->where('sale_order_item_id', $saleOrderItemId)->first();
                $adjustedRemainingQty = $saleOrderItem->remaining_quantity;
                if ($currentDeliveryOrderItem) {
                    $adjustedRemainingQty += $currentDeliveryOrderItem->quantity;
                }

                if ($quantity > $adjustedRemainingQty) {
                    $productName = $saleOrderItem->product->name ?? 'produk';
                    \Filament\Notifications\Notification::make()
                        ->title('Validation Error')
                        ->body("Item delivery order #{$index}: Quantity untuk {$productName} ({$quantity}) melebihi sisa quantity yang tersedia ({$adjustedRemainingQty}).")
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('deliveryOrderItem', "Item delivery order #{$index}: Quantity untuk {$productName} ({$quantity}) melebihi sisa quantity yang tersedia ({$adjustedRemainingQty}).");
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
}
