<?php

namespace App\Filament\Resources\DeliveryOrderResource\Pages;

use App\Filament\Resources\DeliveryOrderResource;
use App\Models\DeliveryOrder;
use App\Models\InventoryStock;
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

        // Aturan sumber DO tunggal (sama dengan form & Edit): status SO, sisa kuantitas yang belum terikat DO
        // lain, customer sama, dan alamat kirim sama. Pesan kesalahan dikirim ke field salesOrders.
        app(\App\Services\DeliveryOrderSourceValidator::class)->assertValid($salesOrderIds);

        // Validate warehouse confirmation for all selected sales orders
        if (!empty($salesOrderIds)) {
            $sourceCabangIds = [];
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

                if (!empty($salesOrder->cabang_id)) {
                    $sourceCabangIds[] = (int) $salesOrder->cabang_id;
                }
            }

            $sourceCabangIds = array_values(array_unique($sourceCabangIds));
            if (count($sourceCabangIds) > 1) {
                $validator = Validator::make([], []);
                $validator->errors()->add('salesOrders', 'Semua Sales Order yang dipilih harus berasal dari cabang yang sama.');
                throw new ValidationException($validator);
            }

            if (!empty($sourceCabangIds)) {
                // Enforce branch inheritance from source Sales Order(s)
                $data['cabang_id'] = $sourceCabangIds[0];
            }
            
            // Set warehouse_id from the first sales order item (assuming all items from same warehouse)
            $firstSalesOrder = \App\Models\SaleOrder::find($salesOrderIds[0]);
            if ($firstSalesOrder) {
                $firstItem = $firstSalesOrder->saleOrderItem()->first();
                if ($firstItem && !isset($data['warehouse_id'])) {
                    $fallbackWarehouseId = $firstItem->warehouse_id;
                    if (empty($fallbackWarehouseId)) {
                        $fallbackWarehouseId = $firstItem->warehouseAllocations()->value('warehouse_id');
                    }
                    $data['warehouse_id'] = $fallbackWarehouseId;
                }
            }

            // E.1: nomor DO belum warehouse-aware (tombol "Generate" diklik sebelum gudang diketahui) — buat ulang
            // sekarang gudang sudah pasti. Hanya untuk nomor pola lama DO-YYYYMMDD-SEQ4 (auto-generate), bukan yang diubah manual.
            if (!empty($data['warehouse_id']) && !empty($data['do_number']) && preg_match('/^DO-\d{8}-\d{4}$/', $data['do_number'])) {
                $data['do_number'] = \App\Services\DeliveryOrderService::generateStaticDoNumber(null, (int) $data['warehouse_id']);
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
                    $remainingQty = $saleOrderItem->availableQuantityForDelivery();
                    // Only add items that still have remaining quantity
                    if ($remainingQty > 0) {
                        $warehouseSources = $saleOrderItem->warehouseAllocations
                            ->map(function ($allocation) {
                                return [
                                    'warehouse_id' => $allocation->warehouse_id,
                                    'quantity' => (float) $allocation->quantity,
                                    'rak_id' => null,
                                ];
                            })
                            ->values()
                            ->toArray();

                        if (empty($warehouseSources)) {
                            $fallbackWarehouseId = $saleOrderItem->warehouse_id;
                            if (!$fallbackWarehouseId) {
                                $cabangId = $saleOrder->cabang_id ?? Auth::user()?->cabang_id;
                                // Pilih gudang dengan stok bebas terbanyak untuk produk ini, bukan gudang aktif pertama secara acak.
                                $fallbackWarehouseId = InventoryStock::query()
                                    ->join('warehouses', 'warehouses.id', '=', 'inventory_stocks.warehouse_id')
                                    ->where('inventory_stocks.product_id', $saleOrderItem->product_id)
                                    ->where('warehouses.status', 1)
                                    ->when($cabangId, fn ($q) => $q->where('warehouses.cabang_id', $cabangId))
                                    ->orderByRaw('(inventory_stocks.qty_available - inventory_stocks.qty_reserved) desc')
                                    ->value('inventory_stocks.warehouse_id');
                                if (!$fallbackWarehouseId) {
                                    $fallbackWarehouseId = \App\Models\Warehouse::where('status', 1)
                                        ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
                                        ->value('id');
                                }
                            }
                            if ($fallbackWarehouseId) {
                                $warehouseSources = [
                                    [
                                        'warehouse_id' => $fallbackWarehouseId,
                                        'quantity' => (float) $remainingQty,
                                        'rak_id' => $saleOrderItem->rak_id,
                                    ]
                                ];
                            }
                        }

                        $deliveryItems[] = [
                            'options_from' => 2,
                            'sale_order_item_id' => $saleOrderItem->id,
                            'product_id' => $saleOrderItem->product_id,
                            'quantity' => $remainingQty,
                            'warehouseSources' => $warehouseSources,
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
                $availableQty = $saleOrderItem->availableQuantityForDelivery();
                if ($quantity > $availableQty) {
                    $productName = $saleOrderItem->product->name ?? 'produk';
                    \Filament\Notifications\Notification::make()
                        ->title('Validation Error')
                        ->body("Item delivery order #{$index}: Quantity untuk {$productName} ({$quantity}) melebihi sisa quantity yang tersedia ({$availableQty}).")
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('deliveryOrderItem', "Item delivery order #{$index}: Quantity untuk {$productName} ({$quantity}) melebihi sisa quantity yang tersedia ({$availableQty}).");
                    throw new ValidationException($validator);
                }

                $warehouseSources = collect($item['warehouseSources'] ?? []);
                if ($warehouseSources->isEmpty()) {
                    $message = "Item delivery order #" . ($index + 1) . ": Pilih minimal satu gudang sumber pengiriman.";
                    Notification::make()
                        ->title('Validation Error')
                        ->body($message)
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('deliveryOrderItem', $message);
                    throw new ValidationException($validator);
                }

                $sourceQty = (float) $warehouseSources->sum(function ($source) {
                    return (float) ($source['quantity'] ?? 0);
                });

                if (abs($sourceQty - $quantity) > 0.0001) {
                    $message = "Item delivery order #" . ($index + 1) . ": Total kuantitas sumber gudang ({$sourceQty}) harus sama dengan kuantitas item delivery order ({$quantity}).";
                    Notification::make()
                        ->title('Validation Error')
                        ->body($message)
                        ->danger()
                        ->send();

                    $validator = Validator::make([], []);
                    $validator->errors()->add('deliveryOrderItem', $message);
                    throw new ValidationException($validator);
                }

                foreach ($warehouseSources as $sourceIndex => $source) {
                    $sourceWarehouseId = $source['warehouse_id'] ?? null;
                    $sourceQtyItem = (float) ($source['quantity'] ?? 0);

                    if (!$sourceWarehouseId || $sourceQtyItem <= 0) {
                        $message = "Item delivery order #" . ($index + 1) . ", sumber #" . ($sourceIndex + 1) . ": Gudang dan kuantitas (> 0) wajib diisi.";
                        Notification::make()
                            ->title('Validation Error')
                            ->body($message)
                            ->danger()
                            ->send();

                        $validator = Validator::make([], []);
                        $validator->errors()->add('deliveryOrderItem', $message);
                        throw new ValidationException($validator);
                    }

                    $availableStock = app(\App\Services\StockAvailability::class)->freeForSaleOrderItem((int) ($item['product_id'] ?? 0), (int) $sourceWarehouseId, $saleOrderItemId ? (int) $saleOrderItemId : null);   // stok bebas + reservasi milik SO ini

                    if ((float) $availableStock < $sourceQtyItem) {
                        $productName = $saleOrderItem->product->name ?? 'produk';
                        $whName = \App\Models\Warehouse::find($sourceWarehouseId)?->name ?? "Gudang #{$sourceWarehouseId}";
                        $message = "Stok {$productName} tidak mencukupi di {$whName} (tersedia: {$availableStock}, diminta: {$sourceQtyItem}).";
                        Notification::make()
                            ->title('Validation Error')
                            ->body($message)
                            ->danger()
                            ->send();

                        $validator = Validator::make([], []);
                        $validator->errors()->add('deliveryOrderItem', $message);
                        throw new ValidationException($validator);
                    }
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

        // Tahap 3 (Brief Ko Handy): Stok terpotong begitu Dibuat DO (bukan saat SO & bukan saat TERKIRIM)
        app(\App\Observers\DeliveryOrderObserver::class)->createStockMovementsForDeliveryOrder($deliveryOrder);

        $warehouseConfirmations = app(\App\Services\DeliveryOrderService::class)
            ->createWarehouseConfirmationsForDeliveryOrder($deliveryOrder);

        if (! empty($warehouseConfirmations)) {
            $warehouseCount = collect($warehouseConfirmations)
                ->map(fn ($warehouseConfirmation) => $warehouseConfirmation->warehouseConfirmationItems->first()?->warehouse_id)
                ->filter()
                ->unique()
                ->count();

            // Advance DO status to request_stock (waiting for warehouse confirmations)
            app(\App\Services\DeliveryOrderService::class)->updateStatus(
                deliveryOrder: $deliveryOrder,
                status: 'request_stock',
                comments: 'WC otomatis dibuat per item request untuk ' . count($warehouseConfirmations) . ' request dari ' . $warehouseCount . ' gudang.',
            );

            \Filament\Notifications\Notification::make()
                ->title('Delivery Order Dibuat')
                ->success()
                ->body('WC otomatis dibuat per item request untuk ' . count($warehouseConfirmations) . ' request dari ' . $warehouseCount . ' gudang. Status: Request Stock.')
                ->send();
        }
    }
}
