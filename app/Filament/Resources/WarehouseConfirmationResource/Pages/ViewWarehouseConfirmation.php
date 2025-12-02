<?php

namespace App\Filament\Resources\WarehouseConfirmationResource\Pages;

use App\Filament\Resources\WarehouseConfirmationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Support\Facades\Auth;

class ViewWarehouseConfirmation extends ViewRecord
{
    protected static string $resource = WarehouseConfirmationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil')->label('Edit Confirmation'),
            Actions\Action::make('confirm')
                ->label('Confirm Warehouse')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm Warehouse')
                ->modalDescription('This will confirm the warehouse confirmation and update the sales order status.')
                ->action(function () {
                    $record = $this->record;
                    // Update warehouse confirmation status
                    $record->update([
                        'status' => 'confirmed',
                        'confirmed_by' => Auth::id(),
                        'confirmed_at' => now(),
                    ]);

                    // Update sales order status if needed
                    if ($record->saleOrder) {
                        $record->saleOrder->update([
                            'status' => 'confirmed',
                            'warehouse_confirmed_at' => now(),
                        ]);
                    }

                    $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
                })
                ->visible(fn() => strtolower($this->record->status) === 'request'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Warehouse Confirmation Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('confirmation_type')
                            ->label('Confirmation Type')
                            ->formatStateUsing(function ($state) {
                                return match ($state) {
                                    'sales_order' => 'Sales Order Confirmation',
                                    'manufacturing_order' => 'Manufacturing Order Confirmation',
                                    default => ucfirst($state),
                                };
                            })
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'sales_order' => 'success',
                                'manufacturing_order' => 'info',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'confirmed' => 'success',
                                'partial_confirmed' => 'warning',
                                'rejected' => 'danger',
                                'request' => 'info',
                                default => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Confirmed By'),

                        Infolists\Components\TextEntry::make('confirmed_at')
                            ->label('Confirmed At')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('notes')
                            ->label('Notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('Sales Order Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('saleOrder.so_number')
                            ->label('SO Number'),

                        Infolists\Components\TextEntry::make('saleOrder.customer.name')
                            ->label('Customer'),

                        Infolists\Components\TextEntry::make('saleOrder.order_date')
                            ->label('Order Date')
                            ->date(),

                        Infolists\Components\TextEntry::make('saleOrder.delivery_date')
                            ->label('Delivery Date')
                            ->date()
                            ->placeholder('Not set')
                            ->visible(fn($record) => $record->sale_order_id !== null),

                        Infolists\Components\TextEntry::make('saleOrder.total_amount')
                            ->label('Total Amount')
                            ->money('IDR'),

                        Infolists\Components\TextEntry::make('saleOrder.status')
                            ->label('SO Status')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'confirmed' => 'success',
                                'partial_confirmed' => 'warning',
                                'rejected' => 'danger',
                                'request' => 'info',
                                'approved' => 'success',
                                'draft' => 'gray',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record->sale_order_id !== null),

                Infolists\Components\Section::make('Manufacturing Order Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('manufacturingOrder.mo_number')
                            ->label('MO Number'),

                        Infolists\Components\TextEntry::make('manufacturingOrder.status')
                            ->label('MO Status')
                            ->badge(),

                        Infolists\Components\TextEntry::make('manufacturingOrder.created_at')
                            ->label('Created Date')
                            ->date(),
                    ])
                    ->columns(3)
                    ->visible(fn($record) => $record->manufacturing_order_id !== null),

                Infolists\Components\Section::make('Sales Order Items')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('saleOrder.saleOrderItem')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('product.name')
                                    ->label('Product')
                                    ->columnSpan(2),

                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Quantity')
                                    ->numeric(),

                                Infolists\Components\TextEntry::make('unit_price')
                                    ->label('Price')
                                    ->money('IDR'),

                                Infolists\Components\TextEntry::make('total_amount')
                                    ->label('Total')
                                    ->money('IDR')
                                    ->state(function ($record) {
                                        return $record->unit_price * $record->quantity;
                                    }),

                                Infolists\Components\TextEntry::make('warehouse.name')
                                    ->label('Warehouse')
                                    ->columnSpan(2),

                                Infolists\Components\TextEntry::make('rak.name')
                                    ->label('Rak'),
                            ])
                            ->columns(8),
                    ]),

                Infolists\Components\Section::make('Warehouse Confirmations')
                    ->schema([
                        // Show warehouse confirmation items
                        Infolists\Components\RepeatableEntry::make('warehouseConfirmationItems')
                            ->label('Warehouse Confirmation Items')
                            ->schema([
                                Infolists\Components\TextEntry::make('saleOrderItem.product.name')
                                    ->label('Product')
                                    ->columnSpan(2),

                                Infolists\Components\TextEntry::make('requested_qty')
                                    ->label('Requested Qty')
                                    ->numeric(),

                                Infolists\Components\TextEntry::make('confirmed_qty')
                                    ->label('Confirmed Qty')
                                    ->numeric(),

                                Infolists\Components\TextEntry::make('warehouse.name')
                                    ->label('Warehouse')
                                    ->columnSpan(2),

                                Infolists\Components\TextEntry::make('rak.name')
                                    ->label('Rak'),

                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'confirmed' => 'success',
                                        'partial_confirmed' => 'warning',
                                        'rejected' => 'danger',
                                        'request' => 'info',
                                        default => 'gray',
                                    }),

                                Infolists\Components\Actions::make([
                                    Infolists\Components\Actions\Action::make('confirm_item')
                                        ->label('Confirm')
                                        ->icon('heroicon-o-check-badge')
                                        ->color('success')
                                        ->requiresConfirmation()
                                        ->modalHeading('Confirm Item')
                                        ->modalDescription('Are you sure you want to confirm this warehouse confirmation item?')
                                        ->action(function ($record) {
                                            $record->update([
                                                'status' => 'confirmed',
                                                'confirmed_qty' => $record->requested_qty,
                                                'confirmed_by' => Auth::id(),
                                                'confirmed_at' => now(),
                                            ]);

                                            $warehouseConfirmation = $record->warehouseConfirmation;

                                            // Check if all items are confirmed to update main status
                                            $allConfirmed = $warehouseConfirmation->warehouseConfirmationItems()
                                                ->where('status', '!=', 'confirmed')
                                                ->count() === 0;

                                            if ($allConfirmed) {
                                                $warehouseConfirmation->update([
                                                    'status' => 'confirmed',
                                                    'confirmed_by' => Auth::id(),
                                                    'confirmed_at' => now(),
                                                ]);
                                            }

                                            $this->redirect($this->getResource()::getUrl('view', ['record' => $warehouseConfirmation]));
                                        })
                                        ->visible(fn($record) => strtolower($record->status) === 'request'),
                                ]),
                            ])
                            ->columns(9)
                            ->visible(fn($record) => $record->warehouseConfirmationItems->count() > 0),
                    ])
                    ->columns(1),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'saleOrder.customer',
                'saleOrder.saleOrderItem.product',
                'saleOrder.saleOrderItem.warehouse',
                'saleOrder.saleOrderItem.rak',
                'manufacturingOrder',
                'warehouseConfirmationItems.saleOrderItem.product',
                'warehouseConfirmationItems.warehouse',
                'warehouseConfirmationItems.rak',
                'user'
            ]);
    }
}
