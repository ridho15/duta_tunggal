<?php

namespace App\Filament\Resources\WarehouseConfirmationResource\Pages;

use App\Filament\Resources\WarehouseConfirmationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;

class ListWarehouseConfirmations extends ListRecords
{
    protected static string $resource = WarehouseConfirmationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
            // Tahap 3: layar checker HP (cek barang lalu KIRIM)
            \Filament\Actions\Action::make('kirim_hp')
                ->label('Siapkan & Kirim di HP')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('gray')
                ->url(fn () => \App\Filament\Pages\Hp\KirimanPage::getUrl())
                ->visible(fn () => \App\Filament\Pages\Hp\KirimanPage::canAccess()),
        ];
    }
}
