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

                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $this->record]));
                }),
            Actions\DeleteAction::make()->icon('heroicon-o-trash'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;

        // Load warehouse confirmation items for form display
        $confirmationItems = [];
        foreach ($record->warehouseConfirmationItems as $item) {
            $confirmationItems[] = [
                'sale_order_item_id' => $item->sale_order_item_id,
                'product_name' => $item->product_name,
                'requested_qty' => $item->requested_qty,
                'confirmed_qty' => $item->confirmed_qty,
                'warehouse_id' => $item->warehouse_id,
                'rak_id' => $item->rak_id,
                'status' => $item->status,
            ];
        }

        // Update data with simplified structure
        $data['confirmation_type'] = $record->sale_order_id ? 'sales_order' : 'manufacturing_order';
        $data['sale_order_id'] = $record->sale_order_id;
        $data['manufacturing_order_id'] = $record->manufacturing_order_id;
        $data['confirmation_items'] = $confirmationItems;

        return $data;
    }
}
