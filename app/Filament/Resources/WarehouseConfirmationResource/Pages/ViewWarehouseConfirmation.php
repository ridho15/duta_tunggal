<?php

namespace App\Filament\Resources\WarehouseConfirmationResource\Pages;

use App\Filament\Resources\WarehouseConfirmationResource;
use App\Models\DeliveryOrder;
use App\Models\MaterialIssue;
use App\Models\ManufacturingOrder;
use App\Models\SaleOrder;
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
                    $this->record->getLinkedDeliveryOrder()?->updateStatusFromWarehouseConfirmations();
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
                    $this->record->getLinkedDeliveryOrder()?->updateStatusFromWarehouseConfirmations();
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
                        TextEntry::make('source_label')
                            ->label('Dokumen Sumber')
                            ->getStateUsing(fn ($record) => $record->source_label),

                        TextEntry::make('primary_item_source_label')
                            ->label('Source Item')
                            ->getStateUsing(fn ($record) => $record->primary_item_source_label),

                        TextEntry::make('primary_item_product_label')
                            ->label('Produk Request')
                            ->getStateUsing(fn ($record) => $record->primary_item_product_label),

                        TextEntry::make('primary_item_warehouse_label')
                            ->label('Gudang Request')
                            ->getStateUsing(fn ($record) => $record->primary_item_warehouse_label),

                        TextEntry::make('request_qty_summary')
                            ->label('Qty Request')
                            ->getStateUsing(fn ($record) => $record->request_qty_summary),

                        TextEntry::make('confirmation_type')
                            ->label('Confirmation Type')
                            ->formatStateUsing(function ($state) {
                                return match ($state) {
                                    'sales_order' => 'Sales Order Confirmation',
                                    'manufacturing_order' => 'Manufacturing Order Confirmation',
                                    'material_issue' => 'Material Issue Confirmation',
                                    default => ucfirst($state),
                                };
                            })
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'sales_order' => 'success',
                                'manufacturing_order' => 'info',
                                'material_issue' => 'danger',
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
                        Infolists\Components\TextEntry::make('do_number_display')
                            ->label('Nomor DO')
                            ->getStateUsing(fn ($record) => $record->getLinkedDeliveryOrder()?->do_number ?? '-'),
                        Infolists\Components\TextEntry::make('do_delivery_date_display')
                            ->label('Tanggal Pengiriman')
                            ->getStateUsing(fn ($record) => $record->getLinkedDeliveryOrder()?->delivery_date
                                ? \Carbon\Carbon::parse($record->getLinkedDeliveryOrder()->delivery_date)->format('d/m/Y')
                                : '-'),
                        Infolists\Components\TextEntry::make('delivery_order_customer')
                            ->label('Customer')
                            ->getStateUsing(function ($record) {
                                $do = $record->getLinkedDeliveryOrder();
                                return $do?->salesOrders?->first()?->customer?->name
                                    ?? $record->getLinkedSaleOrder()?->customer?->name
                                    ?? '-';
                            }),
                        Infolists\Components\TextEntry::make('delivery_order_total_items')
                            ->label('Ringkasan WC Ini')
                            ->getStateUsing(function ($record) {
                                $count = $record->warehouseConfirmationItems->count();
                                $qty = (float) $record->warehouseConfirmationItems->sum('requested_qty');
                                return "{$count} baris / qty " . (string) $qty;
                            }),
                        Infolists\Components\TextEntry::make('do_status_display')
                            ->label('Status DO')
                            ->getStateUsing(fn ($record) => $record->getLinkedDeliveryOrder()?->status ?? '-')
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
                    ->visible(fn ($record) => $record->confirmable_type === DeliveryOrder::class),

                Infolists\Components\Section::make('Manufacturing Order Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('mo_number_display')
                            ->label('MO Number')
                            ->getStateUsing(fn ($record) => $record->confirmable?->mo_number ?? '-'),

                        Infolists\Components\TextEntry::make('mo_status_display')
                            ->label('MO Status')
                            ->getStateUsing(fn ($record) => $record->confirmable?->status ?? '-')
                            ->badge(),

                        Infolists\Components\TextEntry::make('mo_created_at_display')
                            ->label('Created Date')
                            ->getStateUsing(fn ($record) => $record->confirmable?->created_at
                                ? \Carbon\Carbon::parse($record->confirmable->created_at)->format('d/m/Y')
                                : '-'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record->confirmable_type === ManufacturingOrder::class),

                Infolists\Components\Section::make('Informasi Material Issue')
                    ->schema([
                        Infolists\Components\TextEntry::make('mi_number_display')
                            ->label('Nomor Material Issue')
                            ->getStateUsing(fn ($record) => $record->confirmable?->issue_number ?? '-'),
                        Infolists\Components\TextEntry::make('mi_status_display')
                            ->label('Status Material Issue')
                            ->getStateUsing(fn ($record) => $record->confirmable?->status ?? '-')
                            ->badge(),
                        Infolists\Components\TextEntry::make('mi_total_items_display')
                            ->label('Ringkasan WC Ini')
                            ->getStateUsing(function ($record) {
                                $count = $record->warehouseConfirmationItems->count();
                                $qty = (float) $record->warehouseConfirmationItems->sum('requested_qty');
                                return "{$count} baris / qty " . (string) $qty;
                            }),
                        Infolists\Components\TextEntry::make('mi_material_items_display')
                            ->label('Rincian Bahan')
                            ->getStateUsing(function ($record) {
                                if ($record->warehouseConfirmationItems->isEmpty()) {
                                    return '-';
                                }

                                return $record->warehouseConfirmationItems
                                    ->map(fn ($item, $index) => sprintf(
                                        '%d. %s | Request %s | Confirm %s | Status %s',
                                        $index + 1,
                                        $item->product_display,
                                        (string) $item->requested_qty,
                                        (string) $item->confirmed_qty,
                                        ucfirst((string) $item->status)
                                    ))
                                    ->implode("\n");
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record->confirmable_type === MaterialIssue::class),

                Infolists\Components\Section::make('Rincian Item Konfirmasi')
                    ->schema([
                        // Show warehouse confirmation items
                        Infolists\Components\RepeatableEntry::make('warehouseConfirmationItems')
                            ->label('Item Konfirmasi')
                            ->schema([
                                Infolists\Components\TextEntry::make('product_display')
                                    ->label('Product')
                                    ->getStateUsing(fn ($record) => $record->product_display)
                                    ->columnSpan(2),

                                Infolists\Components\TextEntry::make('source_item_display')
                                    ->label('Source Item')
                                    ->getStateUsing(fn ($record) => $record->source_item_display),

                                Infolists\Components\TextEntry::make('requested_qty')
                                    ->label('Requested Qty')
                                    ->numeric(),

                                Infolists\Components\TextEntry::make('confirmed_qty')
                                    ->label('Confirmed Qty')
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
                                    ->columns(7)
                                    ->visible(fn($record) => $record->warehouseConfirmationItems->count() > 0),
                    ])
                    ->columns(1),
            ]);
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'confirmable',
                'warehouseConfirmationItems.saleOrderItem.product',
                'warehouseConfirmationItems.materialIssueItem.product',
                'warehouseConfirmationItems.warehouse',
                'warehouseConfirmationItems.rak',
                'user'
            ]);
    }
}
