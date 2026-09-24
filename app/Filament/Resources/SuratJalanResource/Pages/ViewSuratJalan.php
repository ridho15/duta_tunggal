<?php

namespace App\Filament\Resources\SuratJalanResource\Pages;

use App\Filament\Resources\SuratJalanResource;
use App\Models\DeliveryOrder;
use App\Models\SuratJalan;
use App\Services\SuratJalanDocumentBuilder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSuratJalan extends ViewRecord
{
    protected static string $resource = SuratJalanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Edit/Hapus otomatis tersembunyi bila Policy menolak (hanya Draft yang boleh).
            EditAction::make()->icon('heroicon-o-pencil-square'),
            DeleteAction::make()->icon('heroicon-o-trash'),
            SuratJalanResource::issueAction(page: true),
            Action::make('pdf_surat_jalan')
                ->label('Preview / Download PDF')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->url(fn (SuratJalan $record) => route('pdf-stream', ['type' => 'surat-jalan', 'id' => $record->id]))
                ->openUrlInNewTab(),
            SuratJalanResource::uploadDocumentAction(page: true),
            SuratJalanResource::cancelAction(page: true),
            SuratJalanResource::reissueAction(page: true),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Surat Jalan')
                    ->schema([
                        TextEntry::make('sj_number')
                            ->label('No. Surat Jalan'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => SuratJalan::statusLabel($state))
                            ->color(fn ($state) => SuratJalan::statusColor($state)),
                        TextEntry::make('issued_at')
                            ->label('Tanggal Terbit')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('cabang.nama')
                            ->label('Cabang')
                            ->placeholder('-'),
                        TextEntry::make('createdBy.name')
                            ->label('Dibuat Oleh')
                            ->placeholder('-'),
                        TextEntry::make('signedBy.name')
                            ->label('Ditandatangani Oleh')
                            ->placeholder('-'),
                        TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('document_path')
                            ->label('Dokumen Bertanda Tangan')
                            ->getStateUsing(fn (SuratJalan $record): string => $record->document_path ? 'Sudah diunggah' : 'Belum diunggah')
                            ->badge()
                            ->color(fn (SuratJalan $record): string => $record->document_path ? 'success' : 'warning'),
                    ])
                    ->columns(2),
                Section::make('Pengiriman')
                    ->description('Driver dan kendaraan ditentukan oleh Jadwal Pengiriman atau Delivery Order.')
                    ->schema([
                        TextEntry::make('shipping_method')
                            ->label('Metode Pengiriman')
                            ->getStateUsing(fn (SuratJalan $record): string => $record->primaryDeliverySchedule()?->delivery_method_label ?? ($record->deliveryOrder->first()?->shipping_method ?? SuratJalanDocumentBuilder::NOT_SCHEDULED_LABEL)),
                        TextEntry::make('schedule_number')
                            ->label('No. Jadwal')
                            ->getStateUsing(fn (SuratJalan $record): string => $record->primaryDeliverySchedule()?->schedule_number ?? SuratJalanDocumentBuilder::NOT_SCHEDULED_LABEL),
                        TextEntry::make('sender')
                            ->label('Driver / Ekspedisi')
                            ->getStateUsing(function (SuratJalan $record): string {
                                $schedule = $record->primaryDeliverySchedule();
                                if ($schedule) {
                                    return (string) ($schedule->senderName() ?: SuratJalanDocumentBuilder::NOT_SCHEDULED_LABEL);
                                }
                                $firstDo = $record->deliveryOrder->first(fn ($d) => $d->driver_id);
                                if ($firstDo?->driver?->name) {
                                    return (string) $firstDo->driver->name;
                                }

                                return SuratJalanDocumentBuilder::NOT_SCHEDULED_LABEL;
                            }),
                        TextEntry::make('vehicle')
                            ->label('Kendaraan')
                            ->getStateUsing(function (SuratJalan $record): string {
                                $schedule = $record->primaryDeliverySchedule();
                                if ($schedule) {
                                    return (string) ($schedule->vehicleLabel() ?: SuratJalanDocumentBuilder::NOT_SCHEDULED_LABEL);
                                }
                                $firstDo = $record->deliveryOrder->first(fn ($d) => $d->vehicle_id);
                                if ($firstDo?->vehicle) {
                                    $label = trim(($firstDo->vehicle->plate ?? '') . ($firstDo->vehicle->type ? ' (' . $firstDo->vehicle->type . ')' : ''));
                                    if ($label !== '') {
                                        return $label;
                                    }
                                }

                                return SuratJalanDocumentBuilder::NOT_SCHEDULED_LABEL;
                            }),
                        TextEntry::make('tracking_number')
                            ->label('No. Resi')
                            ->getStateUsing(fn (SuratJalan $record): ?string => $record->primaryDeliverySchedule()?->tracking_number)
                            ->placeholder('-'),
                    ])
                    ->columns(2),
                Section::make('Delivery Order')
                    ->schema([
                        TextEntry::make('delivery_order_count')
                            ->label('Jumlah DO')
                            ->getStateUsing(fn (SuratJalan $record): int => $record->deliveryOrder()->count())
                            ->badge(),
                        RepeatableEntry::make('deliveryOrder')
                            ->label('')
                            ->schema([
                                TextEntry::make('do_number')
                                    ->label('No. DO'),
                                TextEntry::make('status')
                                    ->label('Status DO')
                                    ->badge()
                                    ->formatStateUsing(fn ($state) => DeliveryOrder::statusLabel($state))
                                    ->color(fn ($state) => DeliveryOrder::statusColor($state)),
                                TextEntry::make('sales_orders')
                                    ->label('Sales Order')
                                    ->getStateUsing(fn (DeliveryOrder $record): string => $record->salesOrders->pluck('so_number')->implode(', ') ?: '-'),
                                TextEntry::make('customers')
                                    ->label('Customer')
                                    ->getStateUsing(fn (DeliveryOrder $record): string => $record->salesOrders->map(fn ($so) => $so->customer?->name)->filter()->unique()->implode(', ') ?: '-'),
                            ])
                            ->columns(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Pembatalan')
                    ->visible(fn (SuratJalan $record): bool => $record->isCancelled())
                    ->schema([
                        TextEntry::make('cancelled_at')
                            ->label('Dibatalkan Pada')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('cancelledBy.name')
                            ->label('Dibatalkan Oleh')
                            ->placeholder('-'),
                        TextEntry::make('cancel_reason')
                            ->label('Alasan')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
