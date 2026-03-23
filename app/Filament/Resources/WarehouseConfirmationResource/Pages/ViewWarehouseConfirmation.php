<?php

namespace App\Filament\Resources\WarehouseConfirmationResource\Pages;

use App\Filament\Resources\WarehouseConfirmationResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\Facades\Auth;

class ViewWarehouseConfirmation extends ViewRecord
{
    protected static string $resource = WarehouseConfirmationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->icon('heroicon-o-pencil')->label('Edit Confirmation'),
            Actions\Action::make('approve_wc')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve Konfirmasi Gudang')
                ->modalDescription('Approve konfirmasi ini dan update status Delivery Order.')
                ->action(function () {
                    $this->record->update([
                        'status'       => 'confirmed',
                        'rejection_reason' => null,
                        'confirmed_by' => Auth::id(),
                        'confirmed_at' => now(),
                    ]);
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn () => strtolower($this->record->status) === 'request'),

            Actions\Action::make('reject_wc')
                ->label('Tolak')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->form([
                    Textarea::make('rejection_reason')
                        ->label('Alasan Penolakan')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update([
                        'status'           => 'rejected',
                        'rejection_reason' => $data['rejection_reason'],
                        'confirmed_by'     => Auth::id(),
                        'confirmed_at'     => now(),
                    ]);
                    $this->redirect($this->getResource()::getUrl('view', ['record' => $this->record]));
                })
                ->visible(fn () => strtolower($this->record->status) === 'request'),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Warehouse Confirmation Details')
                    ->schema([
                        TextEntry::make('confirmation_type')
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
                            ->color(function($state){
                                return match (strtolower($state)) {
                                    'confirmed' => 'success',
                                    'partial_confirmed' => 'warning',
                                    'rejected' => 'danger',
                                    'request' => 'info',
                                    default => 'gray',
                                };
                            }),
                           

                        Infolists\Components\TextEntry::make('user.name')
                            ->label('Confirmed By'),

                        Infolists\Components\TextEntry::make('confirmed_at')
                            ->label('Confirmed At')
                            ->dateTime(),

                        Infolists\Components\TextEntry::make('note')
                            ->label('Notes')
                            ->formatStateUsing(function ($state) {
                                return $state;
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                // I2: DO information section (visible when WC is linked to a DO)
                Infolists\Components\Section::make('Informasi Delivery Order')
                    ->schema([
                        Infolists\Components\TextEntry::make('deliveryOrder.do_number')
                            ->label('Nomor DO'),
                        Infolists\Components\TextEntry::make('deliveryOrder.delivery_date')
                            ->label('Tanggal Pengiriman')
                            ->date(),
                        Infolists\Components\TextEntry::make('delivery_order_customer')
                            ->label('Customer')
                            ->getStateUsing(function ($record) {
                                return $record->deliveryOrder?->salesOrders?->first()?->customer?->name
                                    ?? $record->saleOrder?->customer?->name
                                    ?? '-';
                            }),
                        Infolists\Components\TextEntry::make('delivery_order_total_items')
                            ->label('Total Item')
                            ->getStateUsing(function ($record) {
                                $count = $record->warehouseConfirmationItems->count();
                                $qty = (float) $record->warehouseConfirmationItems->sum('requested_qty');
                                return "{$count} baris / qty {$qty}";
                            }),
                        Infolists\Components\TextEntry::make('deliveryOrder.status')
                            ->label('Status DO')
                            ->badge()
                            ->color(fn ($state) => match (strtolower((string) $state)) {
                                'approved'      => 'success',
                                'rejected', 'reject' => 'danger',
                                'request_stock' => 'warning',
                                'draft'         => 'gray',
                                default         => 'info',
                            }),
                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->label('Alasan Penolakan')
                            ->placeholder('-')
                            ->visible(fn ($record) => strtolower($record->status) === 'rejected'),
                    ])
                    ->columns(2)
                    ->visible(fn ($record) => ! empty($record->delivery_order_id)),

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

                Infolists\Components\Section::make('Warehouse Confirmations')
                    ->schema([
                        // Show warehouse confirmation items
                        Infolists\Components\RepeatableEntry::make('warehouseConfirmationItems')
                            ->label('Item Konfirmasi Gudang')
                            ->schema([
                                Infolists\Components\TextEntry::make('saleOrderItem.product')
                                    ->label('Product')
                                    ->formatStateUsing(function ($state) {
                                        return "(" . $state['sku'] . ") " . $state['name'];
                                    })
                                    ->columnSpan(2),

                                Infolists\Components\TextEntry::make('requested_qty')
                                    ->label('Requested Qty')
                                    ->numeric(),

                                Infolists\Components\TextEntry::make('warehouse')
                                    ->formatStateUsing(function ($state) {
                                        return "(" . $state['kode'] . ") " . $state['name'];
                                    })
                                    ->label('Gudang')
                                    ->columnSpan(2),

                                Infolists\Components\TextEntry::make('rak.name')
                                    ->label('Rak'),

                                Infolists\Components\TextEntry::make('status')
                                    ->badge()
                                    ->color(fn(string $state): string => match (strtolower($state)) {
                                        'confirmed' => 'success',
                                        'partial_confirmed' => 'warning',
                                        'rejected' => 'danger',
                                        'request' => 'info',
                                        default => 'gray',
                                    }),
                            ])
                            ->columns(6)
                            ->visible(fn($record) => $record->warehouseConfirmationItems->count() > 0),
                    ])
                    ->columns(1),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'deliveryOrder',
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
