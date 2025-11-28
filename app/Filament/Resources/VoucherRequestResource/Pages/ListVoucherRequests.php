<?php

namespace App\Filament\Resources\VoucherRequestResource\Pages;

use App\Filament\Resources\VoucherRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVoucherRequests extends ListRecords
{
    protected static string $resource = VoucherRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
            ->icon('heroicon-o-plus'),
        ];
    }
}
