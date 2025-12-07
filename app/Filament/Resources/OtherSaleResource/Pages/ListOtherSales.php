<?php

namespace App\Filament\Resources\OtherSaleResource\Pages;

use App\Filament\Resources\OtherSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOtherSales extends ListRecords
{
    protected static string $resource = OtherSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->icon('heroicon-o-plus'),
        ];
    }
}
