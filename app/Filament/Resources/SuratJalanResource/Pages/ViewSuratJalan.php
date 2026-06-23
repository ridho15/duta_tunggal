<?php

namespace App\Filament\Resources\SuratJalanResource\Pages;

use App\Filament\Resources\SuratJalanResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Illuminate\Support\Str;

class ViewSuratJalan extends ViewRecord
{
    protected static string $resource = SuratJalanResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Surat Jalan')
                    ->schema([
                        TextEntry::make('sj_number')
                            ->label('No. Surat Jalan'),
                        TextEntry::make('issued_at')
                            ->label('Tanggal Terbit')
                            ->date('d M Y'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'draft' => 'gray',
                                'issued' => 'info',
                                'delivered' => 'success',
                                default => 'gray',
                            }),
                        TextEntry::make('shipping_method_display')
                            ->label('Metode Pengiriman'),
                        TextEntry::make('sender_display')
                            ->label('Pengirim/Penanggung Jawab'),
                    ])
                    ->columns(2),
                Section::make('Lainnya')
                    ->schema([
                        TextEntry::make('signedBy.name')
                            ->label('Ditandatangani Oleh'),
                        TextEntry::make('createdBy.name')
                            ->label('Dibuat Oleh'),
                        TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime('d M Y H:i'),
                    ])
                    ->columns(2),
                Section::make('Delivery Orders')
                    ->schema([
                        // List delivery orders related to this Surat Jalan
                        TextEntry::make('deliveryOrder_count')
                            ->label('Jumlah DO')
                            ->badge(),
                    ]),
            ]);
    }
}
