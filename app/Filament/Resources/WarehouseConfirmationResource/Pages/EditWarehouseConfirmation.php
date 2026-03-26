<?php

namespace App\Filament\Resources\WarehouseConfirmationResource\Pages;

use App\Filament\Resources\WarehouseConfirmationResource;
use Filament\Actions;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditWarehouseConfirmation extends EditRecord
{
    protected static string $resource = WarehouseConfirmationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->label('View')->color('primary'),
            Actions\Action::make('approve_wc')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => strtolower((string) $this->record->status) === 'request')
                ->action(function () {
                    $this->record->update([
                        'status' => 'confirmed',
                        'rejection_reason' => null,
                        'confirmed_by' => Auth::id(),
                        'confirmed_at' => now(),
                    ]);
                    // Trigger DO status update when this WC is DO-linked
                    $this->record->getLinkedDeliveryOrder()?->updateStatusFromWarehouseConfirmations();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),
            Actions\Action::make('reject_wc')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('rejection_reason')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->rows(3),
                ])
                ->visible(fn () => strtolower((string) $this->record->status) === 'request')
                ->action(function (array $data) {
                    $this->record->update([
                        'status' => 'rejected',
                        'rejection_reason' => $data['rejection_reason'],
                        'confirmed_by' => Auth::id(),
                        'confirmed_at' => now(),
                    ]);
                    // Trigger DO status update when this WC is DO-linked
                    $this->record->getLinkedDeliveryOrder()?->updateStatusFromWarehouseConfirmations();

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Strip virtual fields before saving to model
        unset($data['confirmation_items'], $data['source_number_display'], $data['so_id_virtual'], $data['mo_id_virtual'], $data['do_id_virtual']);
        return $data;
    }

    protected function afterSave(): void
    {
        // G-04: persist confirmation_items changes (confirmed_qty, warehouse, rak, status) back to DB
        $record = $this->record;
        $formState = $this->form->getState();
        $items = $formState['confirmation_items'] ?? [];

        foreach ($items as $itemData) {
            // Match by sale_order_item_id to find the existing WC item
            $wcItem = $record->warehouseConfirmationItems()
                ->where('sale_order_item_id', $itemData['sale_order_item_id'] ?? null)
                ->first();

            if ($wcItem) {
                $wcItem->update([
                    'confirmed_qty' => $itemData['confirmed_qty'] ?? $wcItem->confirmed_qty,
                    'warehouse_id'  => $itemData['warehouse_id'] ?? $wcItem->warehouse_id,
                    'rak_id'        => $itemData['rak_id'] ?? $wcItem->rak_id,
                    'status'        => $itemData['status'] ?? $wcItem->status,
                ]);
            }
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;

        // Load warehouse confirmation items for form display
        $confirmationItems = [];
        foreach ($record->warehouseConfirmationItems as $item) {
            $confirmationItems[] = [
                'sale_order_item_id' => $item->sale_order_item_id,
                'product_name' => $item->product_name ?? $item->saleOrderItem?->product?->name ?? '-',
                'requested_qty' => $item->requested_qty,
                'confirmed_qty' => $item->confirmed_qty,
                'warehouse_id' => $item->warehouse_id,
                'rak_id' => $item->rak_id,
                'status' => $item->status,
            ];
        }

        // Detect confirmation_type from confirmable_type
        $data['confirmation_type'] = match ($record->confirmable_type) {
            \App\Models\ManufacturingOrder::class => 'manufacturing_order',
            \App\Models\DeliveryOrder::class      => 'delivery_order',
            default                               => 'sales_order',
        };

        // Populate virtual source picker fields
        if ($record->confirmable_type === \App\Models\SaleOrder::class) {
            $data['so_id_virtual'] = $record->confirmable_id;
        } elseif ($record->confirmable_type === \App\Models\ManufacturingOrder::class) {
            $data['mo_id_virtual'] = $record->confirmable_id;
        } elseif ($record->confirmable_type === \App\Models\DeliveryOrder::class) {
            $data['do_id_virtual'] = $record->confirmable_id;
        }

        $data['source_number_display'] = $record->source_label;
        $data['confirmation_items'] = $confirmationItems;

        return $data;
    }
}
