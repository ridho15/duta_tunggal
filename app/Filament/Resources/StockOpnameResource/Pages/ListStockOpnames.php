<?php

namespace App\Filament\Resources\StockOpnameResource\Pages;

use App\Filament\Resources\StockOpnameResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStockOpnames extends ListRecords
{
    protected static string $resource = StockOpnameResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->icon('heroicon-o-plus'),
            // Tahap 3: hitung fisik di HP (blind count)
            Actions\Action::make('hitung_hp')
                ->label('Hitung di HP')
                ->icon('heroicon-o-device-phone-mobile')
                ->color('gray')
                ->url(fn () => \App\Filament\Pages\Hp\OpnameHpPage::getUrl())
                ->visible(fn () => \App\Filament\Pages\Hp\OpnameHpPage::canAccess()),
        ];
    }
}
