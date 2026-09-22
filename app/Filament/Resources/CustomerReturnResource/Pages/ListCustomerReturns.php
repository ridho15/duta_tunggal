<?php

namespace App\Filament\Resources\CustomerReturnResource\Pages;

use App\Filament\Resources\CustomerReturnResource;
use App\Models\CustomerReturn;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomerReturns extends ListRecords
{
    protected static string $resource = CustomerReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
