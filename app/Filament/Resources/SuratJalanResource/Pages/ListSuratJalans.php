<?php

namespace App\Filament\Resources\SuratJalanResource\Pages;

use App\Filament\Resources\SuratJalanResource;
use Filament\Actions;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSuratJalans extends ListRecords
{
    protected static string $resource = SuratJalanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('cetak_rekap_fleksibel')
                ->label('Cetak Rekap Fleksibel')
                ->icon('heroicon-o-printer')
                ->modalHeading('Cetak Rekap Pengiriman Fleksibel')
                ->form([
                    \Filament\Forms\Components\DatePicker::make('tanggal_mulai')
                        ->label('Tanggal Mulai')
                        ->required(),
                    \Filament\Forms\Components\DatePicker::make('tanggal_selesai')
                        ->label('Tanggal Selesai')
                        ->required(),
                    \Filament\Forms\Components\Select::make('status_pengiriman')
                        ->label('Status Pengiriman')
                        ->options([
                            'all' => 'Semua',
                            'pending' => 'Pending',
                            'sent' => 'Sent',
                            'delivered' => 'Delivered',
                        ])
                        ->default('all')
                        ->required(),
                ])
                ->action(fn () => null),
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
